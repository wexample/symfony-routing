<?php

declare(strict_types=1);

namespace Wexample\SymfonyRouting\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Wexample\SymfonyRouting\Twig\RouteExtension;

class RouteExtensionTest extends TestCase
{
    private RequestStack $requestStack;

    private RouteExtension $extension;

    protected function setUp(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        // Each route lives at a path made of its name, its parameters after.
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name, array $params = []): string => '/'.$name.($params ? '/'.implode('/', $params) : '')
        );

        $this->requestStack = new RequestStack();
        $this->extension = new RouteExtension(
            $this->requestStack,
            $urlGenerator,
            $this->createStub(RouterInterface::class)
        );
    }

    public function testCurrentRouteIsRelated(): void
    {
        $this->pushRequest('/blog_show/3', 'blog_show');

        $this->assertTrue($this->extension->routeIsCurrentOrRelated('blog_show', ['id' => 3]));
    }

    public function testIndexRouteIsRelatedToTheRoutesOfItsSection(): void
    {
        $this->pushRequest('/blog_show/3', 'blog_show');

        $this->assertTrue($this->extension->routeIsCurrentOrRelated('blog_index'));
        $this->assertFalse($this->extension->routeIsCurrentOrRelated('shop_index'));
    }

    public function testRouteOutOfAnIndexIsNotASection(): void
    {
        // "blog" is no index: "blog_show" is not under it.
        $this->pushRequest('/blog_show/3', 'blog_show');

        $this->assertFalse($this->extension->routeIsCurrentOrRelated('blog'));
    }

    public function testRouteInTheBreadcrumbIsRelatedWhenItsParamsMatch(): void
    {
        $this->pushRequest('/comment_edit/9', 'comment_edit', [
            ['route' => 'post_detail', 'params' => ['id' => '3']],
        ]);

        // Parameters compared as strings, as they come from the url.
        $this->assertTrue($this->extension->routeIsCurrentOrRelated('post_detail', ['id' => 3]));
        $this->assertFalse($this->extension->routeIsCurrentOrRelated('post_detail', ['id' => 4]));
        // No parameter asked for: any entry of that route matches.
        $this->assertTrue($this->extension->routeIsCurrentOrRelated('post_detail'));
    }

    public function testMalformedBreadcrumbIsIgnored(): void
    {
        $this->pushRequest('/comment_edit/9', 'comment_edit', 'not a stack');
        $this->assertFalse($this->extension->routeIsCurrentOrRelated('post_detail'));

        $this->pushRequest('/comment_edit/9', 'comment_edit', ['not an item', ['route' => 'post_detail']]);
        $this->assertTrue($this->extension->routeIsCurrentOrRelated('post_detail'));
    }

    public function testReturnValuesAreTheOnesAskedFor(): void
    {
        $this->pushRequest('/blog_show/3', 'blog_show');

        $this->assertSame('active', $this->extension->routeIsCurrentOrRelated('blog_index', null, 'active', ''));
        $this->assertSame('', $this->extension->routeIsCurrentOrRelated('shop_index', null, 'active', ''));
    }

    public function testWithoutRequestNothingIsRelated(): void
    {
        $this->assertFalse($this->extension->routeIsCurrentOrRelated('blog_index'));
    }

    private function pushRequest(string $path, string $route, mixed $breadcrumb = null): void
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);

        if ($breadcrumb !== null) {
            $request->attributes->set('_breadcrumb_stack', $breadcrumb);
        }

        $this->requestStack->push($request);
    }
}
