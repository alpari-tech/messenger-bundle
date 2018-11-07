<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MessengerBundle;

use Symfony\Bundle\MessengerBundle\DependencyInjection\Compiler\MessengerPass as BackportMessengerPass;
use Symfony\Bundle\MessengerBundle\DependencyInjection\Compiler\MessengerReceiverPass;
use Symfony\Bundle\MessengerBundle\DependencyInjection\Compiler\MessengerSenderPass;
use Symfony\Bundle\MessengerBundle\DependencyInjection\Compiler\TransportFactoryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass as OriginalMessengerPass;

class MessengerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new TransportFactoryPass());

        $container->addCompilerPass(
            version_compare(Kernel::VERSION, '3.3.0', '<')
            ? new BackportMessengerPass()
            : new OriginalMessengerPass()
        );
        $container->addCompilerPass(new MessengerReceiverPass());
        $container->addCompilerPass(new MessengerSenderPass());
    }
}
