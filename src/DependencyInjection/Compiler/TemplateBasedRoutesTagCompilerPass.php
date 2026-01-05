<?php

namespace Wexample\SymfonyRouting\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyRouting\Attribute\TemplateBasedRoutes;

class TemplateBasedRoutesTagCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract()) {
                continue;
            }

            $class = $definition->getClass();

            if (! $class || ! class_exists($class)) {
                continue;
            }

            if (! ClassHelper::hasAttributes($class, TemplateBasedRoutes::class)) {
                continue;
            }

            if ($definition->hasTag('has_template_routes')) {
                continue;
            }

            // Mirror the #[TemplateBasedRoutes] attribute at DI level so the loader can rely on the tag only.
            $definition->addTag('has_template_routes');
        }
    }
}
