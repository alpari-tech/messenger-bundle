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


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TransportFactoryPass implements CompilerPassInterface
{
    const TAG = 'messenger.transport_factory';

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        $services = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $serviceId => $tagsAttributes) {
            $services[] = new Reference($serviceId);
        }

        $container->getDefinition('messenger.transport_factory')
            ->replaceArgument(0, $services);
    }
}
