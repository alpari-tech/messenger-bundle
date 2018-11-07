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

use Symfony\Bundle\FullStack;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Serializer;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $root = $builder->root('messenger');

        $this->addMessengerSection($root);

        return $builder;
    }

    private function addMessengerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->info('Messenger configuration')
            ->{!class_exists(FullStack::class) && interface_exists(MessageBusInterface::class) ? 'canBeDisabled' : 'canBeEnabled'}()
            ->fixXmlConfig('transport')
            ->fixXmlConfig('bus', 'buses')
            ->children()
                ->arrayNode('routing')
                    ->useAttributeAsKey('message_class')
                    ->beforeNormalization()
                        ->always()
                        ->then(function ($config) {
                            if (!\is_array($config)) {
                                return array();
                            }
                            $newConfig = array();
                            foreach ($config as $k => $v) {
                                if (!\is_int($k)) {
                                    $newConfig[$k] = array(
                                        'senders' => $v['senders'] ?? (\is_array($v) ? array_values($v) : array($v)),
                                        'send_and_handle' => $v['send_and_handle'] ?? false,
                                    );
                                } else {
                                    $newConfig[$v['message-class']]['senders'] = array_map(
                                        function ($a) {
                                            return \is_string($a) ? $a : $a['service'];
                                        },
                                        array_values($v['sender'])
                                    );
                                    $newConfig[$v['message-class']]['send-and-handle'] = $v['send-and-handle'] ?? false;
                                }
                            }
                            return $newConfig;
                        })
                    ->end()
                    ->prototype('array')
                        ->children()
                            ->arrayNode('senders')
                                ->requiresAtLeastOneElement()
                                ->prototype('scalar')->end()
                            ->end()
                            ->booleanNode('send_and_handle')->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('serializer')
                    ->{!class_exists(FullStack::class) && class_exists(Serializer::class) ? 'canBeDisabled' : 'canBeEnabled'}()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('format')->defaultValue('json')->end()
                        ->arrayNode('context')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->defaultValue(array())
                            ->prototype('variable')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('encoder')->defaultValue('messenger.transport.serializer')->end()
                ->scalarNode('decoder')->defaultValue('messenger.transport.serializer')->end()
                ->arrayNode('transports')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function (string $dsn) {
                                return array('dsn' => $dsn);
                            })
                        ->end()
                        ->fixXmlConfig('option')
                        ->children()
                            ->scalarNode('dsn')->end()
                            ->arrayNode('options')
                                ->normalizeKeys(false)
                                ->defaultValue(array())
                                ->prototype('variable')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_bus')->defaultValue(null)->end()
                ->arrayNode('buses')
                    ->defaultValue(array('messenger.bus.default' => array('default_middleware' => true, 'middleware' => array())))
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('default_middleware')->defaultTrue()->end()
                            ->arrayNode('middleware')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function (string $middleware) {
                                        return array($middleware);
                                    })
                                ->end()
                                ->defaultValue(array())
                                ->prototype('array')
                                    ->beforeNormalization()
                                        ->always()
                                        ->then(function ($middleware): array {
                                            if (!\is_array($middleware)) {
                                                return array('id' => $middleware);
                                            }
                                            if (isset($middleware['id'])) {
                                                return $middleware;
                                            }
                                            if (\count($middleware) > 1) {
                                                throw new \InvalidArgumentException(sprintf('There is an error at path "messenger" in one of the buses middleware definitions: expected a single entry for a middleware item config, with factory id as key and arguments as value. Got "%s".', json_encode($middleware)));
                                            }
                                            return array(
                                                'id' => key($middleware),
                                                'arguments' => current($middleware),
                                            );
                                        })
                                    ->end()
                                    ->fixXmlConfig('argument')
                                    ->children()
                                        ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                                        ->arrayNode('arguments')
                                            ->normalizeKeys(false)
                                            ->defaultValue(array())
                                            ->prototype('variable')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()

        ;
    }
}
