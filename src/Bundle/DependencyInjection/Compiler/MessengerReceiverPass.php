<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MessengerBundle\DependencyInjection\Compiler;

use Symfony\Bundle\MessengerBundle\ServiceLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MessengerReceiverPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if (class_exists(ServiceLocatorTagPass::class)) {
            return;
        }

        $container->getDefinition('messenger.receiver_locator')
            ->setClass(ServiceLocator::class);
    }
}
