<?php

use Symfony\Bundle\MessengerBundle\Fixtures\BarMessage;
use Symfony\Bundle\MessengerBundle\Fixtures\FooMessage;

$container->loadFromExtension(
    'messenger',
    [
        'serializer' => false,
        'routing'    => [
            FooMessage::class => ['sender.bar', 'sender.biz'],
            BarMessage::class => 'sender.foo',
        ],
    ]
);
