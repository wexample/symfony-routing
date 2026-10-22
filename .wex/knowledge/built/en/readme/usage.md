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
