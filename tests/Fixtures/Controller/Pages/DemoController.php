<?php

declare(strict_types=1);

namespace Wexample\SymfonyRouting\Tests\Fixtures\Controller\Pages;

use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyRouting\Attribute\TemplateBasedRoutes;

/**
 * A controller whose pages are its templates alone, with nothing of its own
 * to say of their names or paths.
 */
#[TemplateBasedRoutes]
class DemoController extends AbstractController
{
    // What symfony-loader's controllers answer; a bare controller has it not.
    public static function getTemplateFrontDir(?string $bundle = null): string
    {
        return 'front';
    }
}
