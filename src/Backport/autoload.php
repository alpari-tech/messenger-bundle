<?php

(function () {
    $directAutoloadMap = [
        \Symfony\Component\DependencyInjection\ChildDefinition::class => \Symfony\Component\DependencyInjection\DefinitionDecorator::class,
        \Symfony\Component\Console\Exception\RuntimeException::class => \RuntimeException::class,
    ];

    spl_autoload_register(
        function ($class) use ($directAutoloadMap) {
            if (isset($directAutoloadMap[$class])) {
                class_alias($directAutoloadMap[$class], $class);
                return;
            }

            if (strpos($class, 'Symfony\\Backport\\') !== false || strpos($class, 'Symfony\\') !== 0) {
                return;
            }

            $backport = 'Symfony\\Backport\\' . substr($class, 8);
            if (class_exists($backport) || interface_exists($backport) || trait_exists($backport)) {
                class_alias($backport, $class);
            }
        },
        false,
        false
    );
})();

