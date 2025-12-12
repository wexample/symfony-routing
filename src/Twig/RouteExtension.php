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

    public function __construct(
        RequestStack $requestStack,
        public UrlGeneratorInterface $urlGenerator,
        private RouterInterface $router
    ) {
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
}
