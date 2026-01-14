<?php

namespace Wexample\SymfonyRouting\Routing;

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\FileHelper;
use Wexample\SymfonyHelpers\Helper\RouteHelper;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Routing\AbstractRouteLoader;
use Wexample\SymfonyHelpers\Routing\Traits\RoutePathBuilderTrait;
use Wexample\SymfonyRouting\Attribute\TemplateBasedRoutes;
use Wexample\SymfonyTemplate\Helper\TemplateHelper;

class TemplateBasedRouteLoader extends AbstractRouteLoader
{
    use RoutePathBuilderTrait;

    public function __construct(
        protected RewindableGenerator $taggedControllers,
        protected ParameterBagInterface $parameterBag,
        protected \Symfony\Component\HttpKernel\KernelInterface $kernel,
        ContainerInterface $container,
        string $env = null
    ) {
        parent::__construct($container, $env);
    }

    protected function loadOnce(
        $resource,
        string $type = null
    ): RouteCollection {
        $collection = new RouteCollection();
        /** @var AbstractController $controller */
        foreach ($this->taggedControllers as $controller) {
            if (! ClassHelper::hasAttributesInHierarchy($controller::class, TemplateBasedRoutes::class)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($controller);

            $bundle = BundleHelper::getRelatedBundle($controller);
            if ($bundle) {
                // Controller advertises a bundle: resolve its location to scan the bundle templates.
                $templatesRoot =
                    realpath(
                        dirname(
                            $this->kernel->getBundle(
                                ClassHelper::getShortName($bundle)
                            )->getPath()
                        )
                    ) . FileHelper::FOLDER_SEPARATOR;
            } else {
                // Fallback to the project templates when the controller does not target a bundle.
                $templatesRoot = $this->parameterBag->get('kernel.project_dir') . FileHelper::FOLDER_SEPARATOR;
            }

            $templatesDir = $templatesRoot
                . $controller::getControllerTemplateDir(bundle: $bundle);

            // Use Finder to scan template files
            $finder = new Finder();
            $finder->files()->in($templatesDir)->name('*' . TemplateHelper::TEMPLATE_FILE_EXTENSION);

            foreach ($finder as $file) {
                // Extract template name (without extension)
                $filename = $file->getBasename(TemplateHelper::TEMPLATE_FILE_EXTENSION);
                $fullPath = $this->buildRoutePathFromController($controller, $filename);
                if (! $fullPath) {
                    continue;
                }

                $routeName = $this->buildRouteNameFromPath($fullPath);

                // Skip auto-generation when an explicit Route attribute already defines this route.
                if ($this->controllerDefinesRoute($reflectionClass, $fullPath)) {
                    continue;
                }

                // Create the route
                $route = new Route($fullPath, [
                    '_controller' => $reflectionClass->getName() . '::resolveSimpleRoute',
                    'routeName' => $filename,
                ]);

                $collection->add($routeName, $route);
            }
        }

        return $collection;
    }

    private function controllerDefinesRoute(\ReflectionClass $reflectionClass, string $fullPath): bool
    {
        $classRouteAttributes = $reflectionClass->getAttributes(RouteAttribute::class);
        $classRoutePaths = $classRouteAttributes
            ? ($this->getRouteAttributePath($classRouteAttributes[0]->newInstance()) ?? '')
            : '';

        if ($classRoutePaths === null) {
            $classRoutePaths = [''];
        } elseif (! is_array($classRoutePaths)) {
            $classRoutePaths = [$classRoutePaths];
        }

        foreach ($reflectionClass->getMethods() as $method) {
            foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                $routeAttribute = $attribute->newInstance();
                $methodPaths = $this->getRouteAttributePath($routeAttribute);
                if ($methodPaths === null) {
                    continue;
                }

                $methodPaths = is_array($methodPaths) ? $methodPaths : [$methodPaths];
                foreach ($methodPaths as $methodPath) {
                    foreach ($classRoutePaths as $classRoutePath) {
                        $candidatePath = RouteHelper::combineRoutePaths($classRoutePath, $methodPath);
                        if (RouteHelper::normalizeRoutePath($candidatePath) === RouteHelper::normalizeRoutePath($fullPath)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function getRouteAttributePath(object $routeAttribute): array|string|null
    {
        if (! property_exists($routeAttribute, 'path')) {
            return null;
        }

        try {
            $property = new \ReflectionProperty($routeAttribute, 'path');
        } catch (\ReflectionException) {
            return null;
        }

        if (! $property->isPublic()) {
            $property->setAccessible(true);
        }

        return $property->getValue($routeAttribute);
    }


    protected function getName(): string
    {
        return 'template_based_routes';
    }
}
