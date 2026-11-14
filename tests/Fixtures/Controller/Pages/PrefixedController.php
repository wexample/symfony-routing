<?php

declare(strict_types=1);

namespace Wexample\SymfonyRouting\Tests\Fixtures\Controller\Pages;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyRouting\Attribute\TemplateBasedRoutes;

/**
 * A controller naming and placing its pages itself, one of them answered by a
 * method of its own.
 */
#[TemplateBasedRoutes]
#[Route(path: '/prefixed', name: 'prefixed_')]
class PrefixedController extends AbstractController
{
    public static function getTemplateFrontDir(?string $bundle = null): string
    {
        return 'front';
    }

    // Written without its leading slash: RouteHelper::combineRoutePaths()
    // takes a path starting with one as whole, apart from the class prefix.
    #[Route(path: 'explicit', name: 'explicit')]
    public function explicit(): Response
    {
        return new Response();
    }
}
