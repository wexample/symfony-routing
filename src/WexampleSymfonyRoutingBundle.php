<?php

namespace Wexample\SymfonyRouting;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyRouting\DependencyInjection\Compiler\TemplateBasedRoutesTagCompilerPass;

class WexampleSymfonyRoutingBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(
            new TemplateBasedRoutesTagCompilerPass()
        );
    }
}
