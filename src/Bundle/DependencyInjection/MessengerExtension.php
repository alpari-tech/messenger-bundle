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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration as FrameworkBundleConfiguration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\ChainSender;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var array
     */
    private $frameworkConfiguration;

    public function prepend(ContainerBuilder $container)
    {
        if (!$container->hasExtension('framework')) {
            throw new \LogicException(
                'The MessengerBundle requires symfony/framework-bundle installed'
            );
        }

        $this->frameworkConfiguration = $container->getExtensionConfig('framework');
    }


    public function load(array $config, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->getConfiguration($config, $container), $config);
        $frameworkConfig = $this->processConfiguration(
            new FrameworkBundleConfiguration($container->getParameter('kernel.debug')),
            $this->frameworkConfiguration
        );

        $loader = new XmlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('console.xml');

        if ($this->isConfigEnabled($container, $config)) {
            $loader->load('messenger_debug.xml');
            $this->registerMessengerConfiguration(
                $config,
                $container,
                $loader,
                $frameworkConfig['serializer'],
                $frameworkConfig['validation']
            );

            if (method_exists($container, 'registerForAutoconfiguration')) {
                $container->registerForAutoconfiguration(MessageHandlerInterface::class)
                          ->addTag('messenger.message_handler');
                $container->registerForAutoconfiguration(TransportFactoryInterface::class)
                          ->addTag('messenger.transport_factory');
            }
        } else {
            $container->removeDefinition('console.command.messenger_consume_messages');
            $container->removeDefinition('console.command.messenger_debug');
        }
    }

    private function registerMessengerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader, array $serializerConfig, array $validationConfig)
    {
        if (!interface_exists(MessageBusInterface::class)) {
            throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed.');
        }
        $loader->load('messenger.xml');
        if ($this->isConfigEnabled($container, $config['serializer'])) {
            if (!$this->isConfigEnabled($container, $serializerConfig)) {
                throw new LogicException('The default Messenger serializer cannot be enabled as the Serializer support is not available. Try enable it or install it by running "composer require symfony/serializer-pack".');
            }
            $container->getDefinition('messenger.transport.serializer')
                ->replaceArgument(1, $config['serializer']['format'])
                ->replaceArgument(2, $config['serializer']['context']);
        } else {
            $container->removeDefinition('messenger.transport.serializer');
            if ('messenger.transport.serializer' === $config['encoder'] || 'messenger.transport.serializer' === $config['decoder']) {
                $container->removeDefinition('messenger.transport.amqp.factory');
            }
        }
        $container->setAlias('messenger.transport.encoder', $config['encoder']);
        $container->setAlias('messenger.transport.decoder', $config['decoder']);
        if (null === $config['default_bus']) {
            if (\count($config['buses']) > 1) {
                throw new LogicException(sprintf('You need to define a default bus with the "default_bus" configuration. Possible values: %s', implode(', ', array_keys($config['buses']))));
            }
            $config['default_bus'] = key($config['buses']);
        }
        $defaultMiddleware = array(
            'before' => array(array('id' => 'logging')),
            'after' => array(array('id' => 'route_messages'), array('id' => 'call_message_handler')),
        );
        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['default_middleware'] ? array_merge($defaultMiddleware['before'], $bus['middleware'], $defaultMiddleware['after']) : $bus['middleware'];
            foreach ($middleware as $middlewareItem) {
                if (!$validationConfig['enabled'] && 'messenger.middleware.validation' === $middlewareItem['id']) {
                    throw new LogicException('The Validation middleware is only available when the Validator component is installed and enabled. Try running "composer require symfony/validator".');
                }
            }
            $container->setParameter($busId.'.middleware', $middleware);
            $container->register($busId, MessageBus::class)->addArgument(array())->addTag('messenger.bus');
            if ($busId === $config['default_bus']) {
                $alias = $container->setAlias(new Alias('message_bus', true), $busId);
                if (method_exists($alias, 'setPrivate')) {
                    $alias->setPrivate(false);
                }

                $container->setAlias(MessageBusInterface::class, $busId);
            }
        }
        if (!$container->hasAlias('message_bus')) {
            throw new LogicException(sprintf('The default bus named "%s" is not defined. Define it or change the default bus name.', $config['default_bus']));
        }
        $senderAliases = array();
        foreach ($config['transports'] as $name => $transport) {
            if (0 === strpos($transport['dsn'], 'amqp://') && !$container->hasDefinition('messenger.transport.amqp.factory')) {
                throw new LogicException('The default AMQP transport is not available. Make sure you have installed and enabled the Serializer component. Try enable it or install it by running "composer require symfony/serializer-pack".');
            }
            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory(array(new Reference('messenger.transport_factory'), 'createTransport'))
                ->setArguments(array($transport['dsn'], $transport['options']))
                ->addTag('messenger.receiver', array('alias' => $name))
                ->addTag('messenger.sender', array('alias' => $name))
            ;
            $container->setDefinition($transportId = 'messenger.transport.'.$name, $transportDefinition);
            $senderAliases[$name] = $transportId;
        }
        $messageToSenderIdMapping = array();
        $messageToSendAndHandleMapping = array();
        foreach ($config['routing'] as $message => $messageConfiguration) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false)) {
                throw new LogicException(sprintf('Messenger routing configuration contains a mistake: message "%s" does not exist. It needs to match an existing class or interface.', $message));
            }
            if (1 < \count($messageConfiguration['senders'])) {
                $senders = array_map(function ($sender) use ($senderAliases) {
                    return new Reference($senderAliases[$sender] ?? $sender);
                }, $messageConfiguration['senders']);
                $chainSenderDefinition = new Definition(ChainSender::class, array($senders));
                $chainSenderDefinition->addTag('messenger.sender');
                $chainSenderId = '.messenger.chain_sender.'.$message;
                $container->setDefinition($chainSenderId, $chainSenderDefinition);
                $messageToSenderIdMapping[$message] = $chainSenderId;
            } else {
                $messageToSenderIdMapping[$message] = $messageConfiguration['senders'][0];
            }
            $messageToSendAndHandleMapping[$message] = $messageConfiguration['send_and_handle'];
        }
        $container->getDefinition('messenger.asynchronous.routing.sender_locator')->replaceArgument(1, $messageToSenderIdMapping);
        $container->getDefinition('messenger.middleware.route_messages')->replaceArgument(1, $messageToSendAndHandleMapping);
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return \dirname(__DIR__).'/Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/messenger';
    }
}
