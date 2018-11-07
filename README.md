Messenger-bundle backport for Symfony 2.7+
==========================================

[![Build Status](https://travis-ci.org/alpari-tech/messenger-bundle.svg?branch=master)](https://travis-ci.org/alpari-tech/messenger-bundle)

Bundle for [Messenger](https://github.com/symfony/messenger) component backported for Symfony 2.7+ and <4.1 from Symfony 4.1's FrameworkBundle.
The main difference with original bundle is that 
configuration must be set under `messenger` section instead of `framework` section in Symfony 4.1+.

This version of the Bundle uses 4.1+ version of the [symfony/messenger](https://github.com/symfony/messenger) component.

Usage
=====
Install the bundle using composer:
```
composer require alpari/messenger-bundle
```

Register the bundle in your AppKernel class:
```php
public function registerBundles()
{
    $bundles = array(
        // ...
        new \Symfony\Bundle\MessengerBundle\MessengerBundle(),
    );
}
```

Original bundle features are well-described in [symfony documentation](https://symfony.com/doc/4.1/messenger.html).
