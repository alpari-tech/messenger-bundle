<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MessengerBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FullStack;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Messenger\MessageBusInterface;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), []);
        $this->assertEquals(
            self::getBundleDefaultConfig(),
            $config
        );
    }

    protected static function getBundleDefaultConfig()
    {
        return [
            'enabled' => !class_exists(FullStack::class) && interface_exists(MessageBusInterface::class),
            'routing' => array(),
            'transports' => array(),
            'serializer' => array(
                'enabled' => !class_exists(FullStack::class),
                'format' => 'json',
                'context' => array(),
            ),
            'encoder' => 'messenger.transport.serializer',
            'decoder' => 'messenger.transport.serializer',
            'default_bus' => null,
            'buses' => array('messenger.bus.default' => array('default_middleware' => true, 'middleware' => array())),
        ];
    }
}
