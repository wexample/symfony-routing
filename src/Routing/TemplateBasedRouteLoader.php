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
use Wexample\SymfonyRouting\Routing\Attribute\TemplateBasedRoutes;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Helper\TemplateHelper;
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
            $templatesDir = $templatesRoot . $controller::getControllerTemplateDir(bundle: $bundle);

            // Use Finder to scan template files
            $finder = new Finder();
            $finder->files()->in($templatesDir)->name('*' . TemplateHelper::TEMPLATE_FILE_EXTENSION);

            foreach ($finder as $file) {
                // Extract template name (without extension)
                $filename = $file->getBasename(TemplateHelper::TEMPLATE_FILE_EXTENSION);
                $routeName = $controller::buildRouteName($filename);
                $fullPath = $this->buildRoutePathFromController($controller, $filename);

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

    protected function getName(): string
    {
        return 'template_based_routes';
    }
}
