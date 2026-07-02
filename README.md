# symfony_routing

Version: 0.1.4

Various helpers for Symfony routing

## Table of Contents

- [Suite Integration](#suite-integration)
- [Dependencies](#dependencies)
- [Versioning](#versioning)
- [License](#license)
- [Suite Integration](#suite-integration)
- [Suite Signature](#suite-signature)
- [Introduction](#introduction)
- [Usage](#usage)
- [Migration Notes](#migration-notes)

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.2
- wexample/symfony-helpers: *
- wexample/symfony-template: *

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

# About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

# wexample/symfony-routing

Version: 0.0.21

Various helpers for Symfony routing

## Table of Contents

# Template-based route loader

The bundle ships a Symfony route loader (`Wexample\SymfonyDesignSystem\Routing\TemplateBasedRouteLoader`) that scans
controllers flagged with the `#[TemplateBasedRoutes]` attribute and auto-registers routes for each template file
found in the controller directory.

## How it works

1. Controllers opt-in with the `#[TemplateBasedRoutes]` attribute. They usually extend `AbstractDesignSystemShowcaseController`
   (which inherits from `AbstractPagesController`) and may reuse `SymfonyDesignSystemBundleClassTrait` to declare the bundle that
   hosts their templates.
2. During the container compilation, `TemplateBasedRoutesTagCompilerPass` mirrors the attribute into a service tag (`has_template_routes`).
   The template loader receives a tagged iterator so it only processes relevant controllers and stays decoupled from the discovery logic.
3. When the loader runs, it figures out where to look for templates:
    - If the controller exposes a bundle via `getDefaultPageBundleClass()` (the trait does that), the loader resolves the bundle path and scans `Resources/views/...` for the controller.
    - Otherwise it falls back to the project root and scans the controller directory under `templates/`.
4. For each Twig template (`*.html.twig`) it finds, the loader builds a route name/path using the helpers from `AbstractPagesController`
   and wires the route to the controller method `resolveSimpleRoute`.

This means that adding a new page is as simple as dropping a Twig file in the controller directory:
no manual route declaration is needed, the loader picks it up on the next cache warmup.

## Typical setup

```php
#[Route(path: DemoController::CONTROLLER_BASE_ROUTE.'"'"'/demo/'"'"', name: DemoController::CONTROLLER_BASE_ROUTE.'"'"'_demo_'"'"')]
#[TemplateBasedRoutes]
final class DemoController extends AbstractDesignSystemShowcaseController
{
    use SymfonyDesignSystemBundleClassTrait; // exposes the bundle for template lookup
}
```

```yaml
# config/routes/design_system.yaml
template_routes:
    resource: .
    type: template_based_routes
```

The YAML snippet simply tells Symfony to call our custom loader; it does not affect discovery by itself.

## Reusability considerations

The feature currently relies on the abstractions provided by this bundle (`AbstractPagesController`, bundle traits, etc.). If you need
similar behavior elsewhere, factor those helpers out into a shared library before reusing the loader as-is.
EOF'
# Template-based route loader

The bundle ships a Symfony route loader (`Wexample\SymfonyDesignSystem\Routing\TemplateBasedRouteLoader`) that scans
controllers flagged with the `#[TemplateBasedRoutes]` attribute and auto-registers routes for each template file
found in the controller directory.

## How it works

1. Controllers opt-in with the `#[TemplateBasedRoutes]` attribute. They usually extend `AbstractDesignSystemShowcaseController`
   (which inherits from `AbstractPagesController`) and may reuse `SymfonyDesignSystemBundleClassTrait` to declare the bundle that
   hosts their templates.
2. During the container compilation, `TemplateBasedRoutesTagCompilerPass` mirrors the attribute into a service tag (`has_template_routes`).
   The template loader receives a tagged iterator so it only processes relevant controllers and stays decoupled from the discovery logic.
3. When the loader runs, it figures out where to look for templates:
    - If the controller exposes a bundle via `getDefaultPageBundleClass()` (the trait does that), the loader resolves the bundle path and scans `Resources/views/...` for the controller.
    - Otherwise it falls back to the project root and scans the controller directory under `templates/`.
4. For each Twig template (`*.html.twig`) it finds, the loader builds a route name/path using the helpers from `AbstractPagesController`
   and wires the route to the controller method `resolveSimpleRoute`.

This means that adding a new page is as simple as dropping a Twig file in the controller directory:
no manual route declaration is needed, the loader picks it up on the next cache warmup.

## Typical setup

```php
#[Route(path: DemoController::CONTROLLER_BASE_ROUTE.'/demo/', name: DemoController::CONTROLLER_BASE_ROUTE.'_demo_')]
#[TemplateBasedRoutes]
final class DemoController extends AbstractDesignSystemShowcaseController
{
    use SymfonyDesignSystemBundleClassTrait; // exposes the bundle for template lookup
}
```

```yaml
# config/routes/design_system.yaml
template_routes:
    resource: .
    type: template_based_routes
```

The YAML snippet simply tells Symfony to call our custom loader; it does not affect discovery by itself.

## Reusability considerations

The feature currently relies on the abstractions provided by this bundle (`AbstractPagesController`, bundle traits, etc.). If you need
similar behavior elsewhere, factor those helpers out into a shared library before reusing the loader as-is.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
