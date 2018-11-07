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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MessengerBundle\MessengerBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\TransportFactory;

abstract class MessengerExtensionTest extends TestCase
{
    private static $containerCache = array();

    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testMessenger()
    {
        $container = $this->createContainerFromFile('messenger');
        $this->assertTrue($container->hasAlias('message_bus'));
        $this->assertTrue($container->getAlias('message_bus')->isPublic());
        $this->assertFalse($container->hasDefinition('messenger.transport.amqp.factory'));
        $this->assertTrue($container->hasDefinition('messenger.transport_factory'));
        $this->assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
    }

    public function testMessengerTransports()
    {
        $container = $this->createContainerFromFile('messenger_transports');
        $this->assertTrue($container->hasDefinition('messenger.transport.default'));
        $this->assertTrue($container->getDefinition('messenger.transport.default')->hasTag('messenger.receiver'));
        $this->assertTrue($container->getDefinition('messenger.transport.default')->hasTag('messenger.sender'));
        $this->assertEquals(array(array('alias' => 'default')), $container->getDefinition('messenger.transport.default')->getTag('messenger.receiver'));
        $this->assertEquals(array(array('alias' => 'default')), $container->getDefinition('messenger.transport.default')->getTag('messenger.sender'));
        $this->assertTrue($container->hasDefinition('messenger.transport.customised'));
        $transportFactory = $container->getDefinition('messenger.transport.customised')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.customised')->getArguments();
        $this->assertEquals(array(new Reference('messenger.transport_factory'), 'createTransport'), $transportFactory);
        $this->assertCount(2, $transportArguments);
        $this->assertSame('amqp://localhost/%2f/messages?exchange_name=exchange_name', $transportArguments[0]);
        $this->assertSame(array('queue' => array('name' => 'Queue')), $transportArguments[1]);
        $this->assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));
    }

    public function testMessengerRouting()
    {
        $container = $this->createContainerFromFile('messenger_routing');
        $senderLocatorDefinition = $container->getDefinition('messenger.asynchronous.routing.sender_locator');
        $sendMessageMiddlewareDefinition = $container->getDefinition('messenger.middleware.route_messages');
        $messageToSenderIdsMapping = array(
            DummyMessage::class => '.messenger.chain_sender.'.DummyMessage::class,
            SecondMessage::class => '.messenger.chain_sender.'.SecondMessage::class,
            '*' => 'amqp',
        );
        $messageToSendAndHandleMapping = array(
            DummyMessage::class => false,
            SecondMessage::class => true,
            '*' => false,
        );
        $this->assertSame($messageToSenderIdsMapping, $senderLocatorDefinition->getArgument(1));
        $this->assertSame($messageToSendAndHandleMapping, $sendMessageMiddlewareDefinition->getArgument(1));
        $this->assertEquals(array(new Reference('messenger.transport.amqp'), new Reference('audit')), $container->getDefinition('.messenger.chain_sender.'.DummyMessage::class)->getArgument(0));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The default Messenger serializer cannot be enabled as the Serializer support is not available. Try enable it or install it by running "composer require symfony/serializer-pack".
     */
    public function testMessengerTransportConfigurationWithoutSerializer()
    {
        $this->createContainerFromFile('messenger_transport_no_serializer');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The default AMQP transport is not available. Make sure you have installed and enabled the Serializer component. Try enable it or install it by running "composer require symfony/serializer-pack".
     */
    public function testMessengerAMQPTransportConfigurationWithoutSerializer()
    {
        $this->createContainerFromFile('messenger_amqp_transport_no_serializer');
    }

    public function testMessengerTransportConfiguration()
    {
        $container = $this->createContainerFromFile('messenger_transport');
        $this->assertSame('messenger.transport.serializer', (string) $container->getAlias('messenger.transport.encoder'));
        $this->assertSame('messenger.transport.serializer', (string) $container->getAlias('messenger.transport.decoder'));
        $serializerTransportDefinition = $container->getDefinition('messenger.transport.serializer');
        $this->assertSame('csv', $serializerTransportDefinition->getArgument(1));
        $this->assertSame(array('enable_max_depth' => true), $serializerTransportDefinition->getArgument(2));
    }

    public function testMessengerWithMultipleBuses()
    {
        $container = $this->createContainerFromFile('messenger_multiple_buses');
        $this->assertTrue($container->has('messenger.bus.commands'));
        $this->assertSame(array(), $container->getDefinition('messenger.bus.commands')->getArgument(0));
        $this->assertEquals(array(
                                array('id' => 'logging'),
                                array('id' => 'route_messages'),
                                array('id' => 'call_message_handler'),
                            ), $container->getParameter('messenger.bus.commands.middleware'));
        $this->assertTrue($container->has('messenger.bus.events'));
        $this->assertSame(array(), $container->getDefinition('messenger.bus.events')->getArgument(0));
        $this->assertEquals(array(
                                array('id' => 'logging'),
                                array('id' => 'with_factory', 'arguments' => array('foo', true, array('bar' => 'baz'))),
                                array('id' => 'allow_no_handler', 'arguments' => array()),
                                array('id' => 'route_messages'),
                                array('id' => 'call_message_handler'),
                            ), $container->getParameter('messenger.bus.events.middleware'));
        $this->assertTrue($container->has('messenger.bus.queries'));
        $this->assertSame(array(), $container->getDefinition('messenger.bus.queries')->getArgument(0));
        $this->assertEquals(array(
                                array('id' => 'route_messages', 'arguments' => array()),
                                array('id' => 'allow_no_handler', 'arguments' => array()),
                                array('id' => 'call_message_handler', 'arguments' => array()),
                            ), $container->getParameter('messenger.bus.queries.middleware'));
        $this->assertTrue($container->hasAlias('message_bus'));
        $this->assertSame('messenger.bus.commands', (string) $container->getAlias('message_bus'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage There is an error at path "messenger" in one of the buses middleware definitions: expected a single entry for a middleware item config, with factory id as key and arguments as value. Got "{"foo":["qux"],"bar":["baz"]}"
     */
    public function testMessengerMiddlewareFactoryErroneousFormat()
    {
        $this->createContainerFromFile('messenger_middleware_factory_erroneous_format');
    }

    protected function createContainerFromFile($file, $data = array(), $resetCompilerPasses = true, $compile = true)
    {
        $cacheKey = md5(\get_class($this).$file.serialize($data));
        if ($compile && isset(self::$containerCache[$cacheKey])) {
            return self::$containerCache[$cacheKey];
        }
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new MessengerExtension());
        $this->loadFromFile($container, $file);
        if ($resetCompilerPasses) {
            $container->getCompilerPassConfig()->setOptimizationPasses(array());
            $container->getCompilerPassConfig()->setRemovingPasses(array());
        }

        if (!$compile) {
            return $container;
        }
        $container->compile();
        return self::$containerCache[$cacheKey] = $container;
    }

    protected function createContainer(array $data = [])
    {
        return new ContainerBuilder(
            new ParameterBag(
                array_merge(
                    [
                        'kernel.bundles_metadata' => [
                            'MessengerBundle' => [
                                'namespace' => 'Symfony\\Bundle\\MessengerBundle',
                                'path'      => __DIR__ . '/../..',
                            ],
                            'FrameworkBundle' => [
                                'namespace' => 'Symfony\\Bundle\\FrameworkBundle',
                                'path'      => __DIR__ . '/../../vendor/symfony/framework-bundle',
                            ],
                        ],
                        'kernel.bundles' => [
                            'MessengerBundle' => MessengerBundle::class,
                            'FrameworkBundle' => FrameworkBundle::class
                        ],
                        'kernel.MessengerBundle' => [
                            'MessengerBundle' => [
                                'namespace' => 'Symfony\\Bundle\\MessengerBundle',
                                'path'      => __DIR__ . '/../..',
                            ],
                        ],
                        'kernel.FrameworkBundle' => [
                            'FrameworkBundle' => [
                                'namespace' => 'Symfony\\Bundle\\FrameworkBundle',
                                'path'      => __DIR__ . '/../../vendor/symfony/framework-bundle',
                            ],
                        ],
                        'kernel.cache_dir' => __DIR__,
                        'kernel.project_dir' => __DIR__,
                        'kernel.debug' => false,
                        'kernel.environment' => 'test',
                        'kernel.name' => 'kernel',
                        'kernel.root_dir' => __DIR__,
                        'kernel.container_class' => 'testContainer',
                        'container.build_hash' => 'Abc1234',
                        'container.build_id' => hash('crc32', 'Abc123423456789'),
                        'container.build_time' => 23456789,
                    ],
                    $data
                )
            )
        );
    }
}
