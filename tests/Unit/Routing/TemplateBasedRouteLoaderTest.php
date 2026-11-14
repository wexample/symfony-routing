<?php

declare(strict_types=1);

namespace Wexample\SymfonyRouting\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Wexample\SymfonyRouting\Routing\TemplateBasedRouteLoader;
use Wexample\SymfonyRouting\Tests\Fixtures\Controller\Pages\DemoController;
use Wexample\SymfonyRouting\Tests\Fixtures\Controller\Pages\PrefixedController;

/**
 * The routes made of a project's templates, the project made on the fly: a
 * directory of templates for each fixture controller.
 */
class TemplateBasedRouteLoaderTest extends TestCase
{
    private const string TEMPLATES_DIR = 'front/tests/fixtures/controller/pages/';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/template-routes-test-'.uniqid();

        $this->addTemplates('demo', ['index', 'about_us', '_partial']);
        $this->addTemplates('prefixed', ['index', 'explicit']);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->projectDir);
    }

    public function testTemplatesBecomeRoutesNamedAfterTheControllerNamespace(): void
    {
        $routes = $this->load([new DemoController()])->all();

        ksort($routes);
        $this->assertSame(
            [
                'tests_fixtures_controller_pages_demo_about_us',
                'tests_fixtures_controller_pages_demo_index',
            ],
            array_keys($routes)
        );

        $route = $routes['tests_fixtures_controller_pages_demo_about_us'];
        $this->assertSame('/tests/fixtures/controller/pages/demo/about-us', $route->getPath());
        $this->assertSame(DemoController::class.'::resolveSimpleRoute', $route->getDefault('_controller'));
        $this->assertSame('about_us', $route->getDefault('routeName'));
    }

    public function testClassRouteGivesNamePrefixAndPathBase(): void
    {
        $routes = $this->load([new PrefixedController()])->all();

        $this->assertArrayHasKey('prefixed_index', $routes);
        $this->assertSame('/prefixed', $routes['prefixed_index']->getPath());
    }

    public function testTemplateAnsweredByAMethodRouteIsLeftToIt(): void
    {
        $routes = $this->load([new PrefixedController()])->all();

        $this->assertSame(['prefixed_index'], array_keys($routes));
    }

    public function testControllersAreReadEachWithTheirOwnClassRoute(): void
    {
        // The class route read once per controller is that controller's, not
        // the previous one's carried along.
        $routes = $this->load([new PrefixedController(), new DemoController()])->all();

        $this->assertArrayHasKey('prefixed_index', $routes);
        $this->assertArrayHasKey('tests_fixtures_controller_pages_demo_index', $routes);
        $this->assertSame(
            '/tests/fixtures/controller/pages/demo',
            $routes['tests_fixtures_controller_pages_demo_index']->getPath()
        );
    }

    private function addTemplates(string $controllerDir, array $names): void
    {
        $dir = $this->projectDir.'/'.self::TEMPLATES_DIR.$controllerDir;
        mkdir($dir, 0777, true);

        foreach ($names as $name) {
            file_put_contents($dir.'/'.$name.'.html.twig', '');
        }
    }

    private function load(array $controllers): \Symfony\Component\Routing\RouteCollection
    {
        $loader = new TemplateBasedRouteLoader(
            new RewindableGenerator(static function () use ($controllers) {
                yield from $controllers;
            }, count($controllers)),
            new ParameterBag(['kernel.project_dir' => $this->projectDir]),
            $this->createStub(KernelInterface::class),
            $this->createStub(ContainerInterface::class),
        );

        return $loader->load('.', 'template_based_routes');
    }
}
