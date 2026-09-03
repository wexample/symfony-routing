# symfony_routing

Version: 1.0.2

`wexample/symfony-routing` is a Symfony bundle that turns Twig templates into routes: a controller carrying the `#[TemplateBasedRoutes]` attribute gets one route per template file found in its template directory, each wired to `resolveSimpleRoute`, so publishing a page means dropping a `.html.twig` file rather than declaring a route. It also ships a Twig extension exposing `route_is_current`, `route_is_current_or_related`, `route_current` and `route_get_controller_routes`, the functions a template needs to know where the visitor currently stands. It addresses Symfony applications already built on the Wexample suite: the loader leans on the controller and template conventions of `wexample/symfony-helpers` and `wexample/symfony-template`, both required.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The bundle is small — six PHP classes and two YAML files — and does two unrelated things: it generates routes from Twig template files at container-compilation/routing time, and it exposes four Twig functions about the current route. Only the first has moving parts.

### Parts

| File | Role |
| --- | --- |
| src/Attribute/TemplateBasedRoutes.php | Marker attribute, `#[Attribute(Attribute::TARGET_CLASS)]`, empty body. Carries no data — its presence is the whole signal. |
| src/WexampleSymfonyRoutingBundle.php | Extends `AbstractBundle` (symfony-helpers); its only job is `$container->addCompilerPass(new TemplateBasedRoutesTagCompilerPass())`. |
| src/DependencyInjection/Compiler/TemplateBasedRoutesTagCompilerPass.php | Translates the attribute into the service tag `has_template_routes`. |
| src/DependencyInjection/WexampleSymfonyRoutingExtension.php | Loads `services.yaml` through `AbstractWexampleSymfonyExtension::loadConfig(__DIR__, $container)`, which resolves `__DIR__.'/../Resources/config'`. |
| src/Routing/TemplateBasedRouteLoader.php | The route loader: scans template directories, builds the `RouteCollection`. |
| src/Twig/RouteExtension.php | Twig functions `route_is_current`, `route_is_current_or_related`, `route_current`, `route_get_controller_routes`. |
| src/Resources/config/services.yaml | Wires the loader (tag `routing.loader`) and autoloads `../../{Twig}`. |
| src/Resources/config/routes.yaml | The three-line import a host application needs: `resource: .` / `type: template_based_routes`. |

### Attribute → tag, at compile time

The loader never scans the filesystem for controllers. It receives them, already filtered, from the container:

```yaml
$taggedControllers: !tagged_iterator 'has_template_routes'
```

The tag comes from `TemplateBasedRoutesTagCompilerPass::process()`, which walks every non-abstract definition whose class exists and tags it when `ClassHelper::hasAttributesInHierarchy($class, TemplateBasedRoutes::class)` is true — *in hierarchy*, so a base controller can opt an entire family in. The pass is idempotent: it skips definitions that already carry the tag.

Two consequences worth knowing before changing anything here. A controller must be a service definition to be seen at all (it is, under Symfony's default autoconfiguration of `src/Controller`). And a service can be tagged by hand in an application's own `services.yaml` without the attribute — which is why `loadOnce()` re-checks `hasAttributesInHierarchy` on each iterated controller and `continue`s if absent.

### Tag → RouteCollection, at routing time

`TemplateBasedRouteLoader` extends `AbstractRouteLoader` (symfony-helpers), which owns the `Loader` contract: `supports()` returns true when the declared type equals `getName()` — here `'template_based_routes'`, matching the `type:` key in the routes YAML — and `load()` delegates once to `loadOnce()`, throwing `RuntimeException('CustomRouteLoader already loaded.')` on a second call. Subclasses only implement `loadOnce()` and `getName()`.

For each tagged controller, `loadOnce()` runs this sequence:

1. **Find the templates root.** `BundleHelper::getRelatedBundle($controller)` returns a bundle class when the controller uses `BundleClassTrait`. If it does, the root is `dirname($this->kernel->getBundle(ClassHelper::getShortName($bundle))->getPath())`; otherwise it falls back to the `kernel.project_dir` parameter.
2. **Turn the namespace into directory parts.** `TemplateHelper::explodeControllerNamespaceSubParts()` strips the `Controller` suffix and splices off the bundle (or two) leading namespace segments. Empty result → controller skipped.
3. **Build the directory.** `$templatesRoot . TemplateHelper::joinNormalizedParts([$controller::getTemplateFrontDir(bundle: $bundle), ...$controllerNamespaceParts])` — every part snake-cased. The code comments why `getTemplateFrontDir()` and not `getControllerTemplateDir()`: the first is the filesystem prefix (`assets` for bundles, `front` for the app), the second is the Twig alias prefix. A directory that does not exist ends the iteration for that controller.
4. **Compute the route parts.** Same list, except a leading `Pages` segment is dropped — it structures the filesystem, not the URL.
5. **Scan.** `Finder` on that directory, `->depth('== 0')`, `->name('*' . TemplateHelper::TEMPLATE_FILE_EXTENSION)` (`.html.twig`). One file, one route candidate.
6. **Name and path.** With no class-level `#[Route]`, both come from `RouteHelper::buildRouteNameFromParts()` / `buildRoutePathFromParts()` over the controller parts plus the filename: snake for names with repeated underscores collapsed, kebab for paths, and a filename equal to `AbstractController::DEFAULT_ROUTE_NAME_INDEX` (`index`) contributes nothing to the path. With a class-level `#[Route]`, its `name` becomes the prefix and its `path` the base — read reflectively via `RouteHelper::getRouteAttributeName()` / `getRouteAttributePath()`, the latter possibly an array, of which the first entry is kept.
7. **Yield to explicit declarations.** `controllerDefinesRoute()` combines every class-level path with every method-level `#[Route]` path through `RouteHelper::combineRoutePaths()` and compares `normalizeRoutePath()` on both sides. A match means a hand-written action already serves that URL, and the generated route is dropped.
8. **Register.** Otherwise:

```php
$route = new Route($fullPath, [
    '_controller' => $reflectionClass->getName() . '::resolveSimpleRoute',
    'routeName' => $filename,
]);
```

`resolveSimpleRoute()` comes from `HasSimpleRoutesControllerTrait` in symfony-helpers and is a one-liner: `return $this->renderPage($routeName);`. So at request time the generated route carries nothing but the template's basename, and rendering is entirely the host controller's business — this bundle contributes no runtime code to the response.

Note when editing step 5: because the Finder is capped at `depth('== 0')`, `$file->getRelativePath()` is always empty and `$relativeParts` is always `[]`. The name/path builders already accept those nested parts; only the Finder constraint stands between the current behaviour and recursive template directories.

### The Twig extension

`RouteExtension` extends `AbstractExtension` (symfony-helpers) and is registered by the `Wexample\SymfonyRouting\: resource: '../../{Twig}'` block. It is autowired with `RequestStack`, `UrlGeneratorInterface` and `RouterInterface`, and reads `$request->getPathInfo()` **in its constructor** into `$this->currentPath` — that snapshot is what `routeIsCurrent()` compares generated URLs against.

`routeIsCurrentOrRelated()` is the only non-trivial function; it answers yes on three grounds, in order: the URL matches `currentPath`; `isRouteInSameSection()` holds, i.e. the tested route ends in `_index` and the request's `_route` starts with the same prefix; or the request attribute `_breadcrumb_stack` contains an entry whose `route` matches and whose `params` satisfy `routeParamsMatch()` (string-cast comparison, expected params only). That attribute is set by something else in the application — this bundle only reads it.

`route_get_controller_routes` is a straight delegation to `RouteHelper::filterRouteByController($this->router->getRouteCollection(), $controllerClass)`.

### Boundaries

Almost every helper used here lives outside the package: `AbstractRouteLoader`, `AbstractBundle`, `AbstractExtension`, `AbstractWexampleSymfonyExtension`, `RouteHelper`, `BundleHelper`, `RoutePathBuilderTrait` and `resolveSimpleRoute` in `wexample/symfony-helpers`; `TemplateHelper` in `wexample/symfony-template`; `ClassHelper` and `FileHelper` reached through them. The package owns the attribute, the compiler pass, the scanning loop and the Twig functions — nothing else. Changing route naming or path conventions means changing `RouteHelper` in symfony-helpers, not this repository.

Nothing here is executed by this package alone either: the routes appear only once a host application declares an import of `type: template_based_routes` in its own routing configuration, and `RoutePathBuilderTrait` is used for its typing/inheritance even though `loadOnce()` calls the `RouteHelper` static builders directly rather than the trait's `buildRoutePathFromController()`.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.2
- wexample/symfony-helpers: >=7.0.0
- wexample/symfony-template: >=0.0.25

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
