<?php
$container->loadFromExtension('framework', ['serializer' => false]);

$container->loadFromExtension(
    'messenger',
    [
        'serializer' => [
            'enabled' => true,
        ],
        'transports' => [
            'default' => 'amqp://localhost/%2f/messages',
        ],
    ]
);
