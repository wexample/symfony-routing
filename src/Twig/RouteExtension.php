<?php

namespace Wexample\SymfonyRouting\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Helper\RouteHelper;
use Wexample\SymfonyHelpers\Twig\AbstractExtension;

class RouteExtension extends AbstractExtension
{
    protected ?string $currentPath = null;
    protected ?RequestStack $requestStack = null;

    public function __construct(
        RequestStack $requestStack,
        public UrlGeneratorInterface $urlGenerator,
        private RouterInterface $router
    ) {
        $this->requestStack = $requestStack;
        $request = $requestStack->getCurrentRequest();

        if ($request) {
            $this->currentPath = $request->getPathInfo();
        }
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'route_is_current',
                [$this, 'routeIsCurrent']
            ),
            new TwigFunction(
                'route_is_current_or_related',
                [$this, 'routeIsCurrentOrRelated']
            ),
            new TwigFunction(
                'route_current',
                [$this, 'routeCurrent']
            ),
            new TwigFunction(
                'route_get_controller_routes',
                [$this, 'getControllerRoutes']
            ),
        ];
    }

    public function routeIsCurrent(
        string $route,
        ?array $params = null,
        mixed $returnValueIfSuccess = true,
        mixed $returnValueIfFail = false,
    ): mixed {
        return $this->urlGenerator->generate(
            $route,
            $params ?: []
        ) === $this->currentPath ? $returnValueIfSuccess : $returnValueIfFail;
    }

    public function routeCurrent(): ?string
    {
        return $this->requestStack?->getCurrentRequest()?->attributes->get('_route');
    }

    public function routeIsCurrentOrRelated(
        string $route,
        ?array $params = null,
        mixed $returnValueIfSuccess = true,
        mixed $returnValueIfFail = false,
    ): mixed {
        if ($this->routeIsCurrent($route, $params, true, false) === true) {
            return $returnValueIfSuccess;
        }

        $request = $this->requestStack?->getCurrentRequest();
        if (! $request) {
            return $returnValueIfFail;
        }

        $currentRoute = $request->attributes->get('_route');
        if (is_string($currentRoute) && $this->isRouteInSameSection($route, $currentRoute)) {
            return $returnValueIfSuccess;
        }

        $stack = $request->attributes->get('_breadcrumb_stack', []);
        if (! is_array($stack)) {
            return $returnValueIfFail;
        }

        foreach ($stack as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['route'] ?? null) !== $route) {
                continue;
            }

            if ($this->routeParamsMatch($params ?? [], $item['params'] ?? [])) {
                return $returnValueIfSuccess;
            }
        }

        return $returnValueIfFail;
    }

    /**
     * Get all routes for a specific controller class.
     *
     * @param string $controllerClass Fully qualified controller class name
     * @return array Array of routes information
     */
    public function getControllerRoutes(string $controllerClass): array
    {
        return RouteHelper::filterRouteByController(
            $this->router->getRouteCollection(),
            $controllerClass
        );
    }

    private function isRouteInSameSection(
        string $parentRoute,
        string $currentRoute
    ): bool {
        if ($parentRoute === $currentRoute) {
            return true;
        }

        if (! str_ends_with($parentRoute, '_index')) {
            return false;
        }

        $prefix = substr($parentRoute, 0, -strlen('_index')) . '_';

        return str_starts_with($currentRoute, $prefix);
    }

    private function routeParamsMatch(
        array $expectedParams,
        mixed $actualParams
    ): bool {
        $actual = is_array($actualParams) ? $actualParams : [];

        foreach ($expectedParams as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                return false;
            }

            if ((string) $actual[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
