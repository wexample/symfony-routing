<?php

namespace Wexample\SymfonyRouting\Routing;

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\FileHelper;
use Wexample\SymfonyRouting\Attribute\TemplateBasedRoutes;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyTemplate\Helper\TemplateHelper;
use Wexample\SymfonyHelpers\Routing\AbstractRouteLoader;
use Wexample\SymfonyHelpers\Routing\Traits\RoutePathBuilderTrait;
use Wexample\SymfonyHelpers\Controller\AbstractController;

class TemplateBasedRouteLoader extends AbstractRouteLoader
{
    use RoutePathBuilderTrait;

    public function __construct(
        protected RewindableGenerator $taggedControllers,
        protected ParameterBagInterface $parameterBag,
        protected \Symfony\Component\HttpKernel\KernelInterface $kernel,
        ContainerInterface $container,
        string $env = null
    )
    {
        parent::__construct($container, $env);
    }

    protected function loadOnce(
        $resource,
        string $type = null
    ): RouteCollection
    {
        $collection = new RouteCollection();

        /** @var AbstractController $controller */
        foreach ($this->taggedControllers as $controller) {
            if (!ClassHelper::hasAttributes($controller::class, TemplateBasedRoutes::class)) {
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
                $routeName = $controller::buildRouteName($filename);
                $fullPath = $this->buildRoutePathFromController($controller, $filename);

                // Skip auto-generation when an explicit Route attribute already defines this route.
                if ($this->controllerDefinesRoute($reflectionClass, $routeName)) {
                    continue;
                }

                if ($fullPath) {
                    // Create the route
                    $route = new Route($fullPath, [
                        '_controller' => $reflectionClass->getName() . '::resolveSimpleRoute',
                        'routeName' => $filename,
                    ]);

                    $collection->add($routeName, $route);
                }
            }
        }

        return $collection;
    }

    private function controllerDefinesRoute(\ReflectionClass $reflectionClass, string $routeName): bool
    {
        $classRouteAttributes = $reflectionClass->getAttributes(\Symfony\Component\Routing\Annotation\Route::class);
        $classRouteNamePrefix = $classRouteAttributes
            ? ($classRouteAttributes[0]->newInstance()->getName() ?? '')
            : '';

        foreach ($reflectionClass->getMethods() as $method) {
            foreach ($method->getAttributes(\Symfony\Component\Routing\Annotation\Route::class) as $attribute) {
                $routeAttribute = $attribute->newInstance();
                $methodRouteName = $routeAttribute->getName() ?: $method->getName();
                $computedName = $classRouteNamePrefix ? $classRouteNamePrefix . $methodRouteName : $methodRouteName;

                if ($computedName === $routeName) {
                    return true;
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
