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

            $controllerNamespaceParts = TemplateHelper::explodeControllerNamespaceSubParts(
                controllerName: $controller::class,
                bundleClassPath: $bundle
            );
            if (empty($controllerNamespaceParts) || $controllerNamespaceParts[0] !== 'Pages') {
                continue;
            }

            $controllerRouteParts = array_values(array_slice($controllerNamespaceParts, 1));

            // Use Finder to scan template files
            $finder = new Finder();
            $finder
                ->files()
                ->in($templatesDir)
                ->depth('== 0')
                ->name('*' . TemplateHelper::TEMPLATE_FILE_EXTENSION);

            foreach ($finder as $file) {
                // Extract template name (without extension)
                $filename = $file->getBasename(TemplateHelper::TEMPLATE_FILE_EXTENSION);
                $relativePath = str_replace('\\', '/', $file->getRelativePath());
                $relativeParts = $relativePath === '' ? [] : explode('/', $relativePath);
                $fullRouteParts = [
                    ...$controllerRouteParts,
                    ...$relativeParts,
                ];

                $defaultRouteName = RouteHelper::buildRouteNameFromParts($fullRouteParts, $filename);
                $defaultRoutePath = RouteHelper::buildRoutePathFromParts($fullRouteParts, $filename);

                $classRouteAttributes = $reflectionClass->getAttributes(RouteAttribute::class);
                $classRouteAttribute = $classRouteAttributes
                    ? $classRouteAttributes[0]->newInstance()
                    : null;

                $classRouteNamePrefix = $classRouteAttribute
                    ? RouteHelper::getRouteAttributeName($classRouteAttribute)
                    : null;
                $classRoutePathBase = $classRouteAttribute
                    ? RouteHelper::getRouteAttributePath($classRouteAttribute)
                    : null;

                if (is_array($classRoutePathBase)) {
                    $classRoutePathBase = $classRoutePathBase ? $classRoutePathBase[0] : null;
                }

                $routeName = ($classRouteNamePrefix !== null && $classRouteNamePrefix !== '')
                    ? $classRouteNamePrefix . RouteHelper::buildRouteNameFromParts($relativeParts, $filename)
                    : $defaultRouteName;
                $fullPath = ($classRoutePathBase !== null && $classRoutePathBase !== '')
                    ? RouteHelper::buildRoutePathFromParts($relativeParts, $filename, $classRoutePathBase)
                    : $defaultRoutePath;

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
            ? (RouteHelper::getRouteAttributePath($classRouteAttributes[0]->newInstance()) ?? '')
            : '';

        if ($classRoutePaths === null) {
            $classRoutePaths = [''];
        } elseif (! is_array($classRoutePaths)) {
            $classRoutePaths = [$classRoutePaths];
        }

        foreach ($reflectionClass->getMethods() as $method) {
            foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                $routeAttribute = $attribute->newInstance();
                $methodPaths = RouteHelper::getRouteAttributePath($routeAttribute);
                if ($methodPaths === null) {
                    $methodPaths = [''];
                } elseif (! is_array($methodPaths)) {
                    $methodPaths = [$methodPaths];
                }
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

    protected function getName(): string
    {
        return 'template_based_routes';
    }
}
