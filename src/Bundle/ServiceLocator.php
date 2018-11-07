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

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class ServiceLocator implements PsrContainerInterface
{
    private $services;
    /**
     * @param callable[] $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        return isset($this->services[$id]);
    }
    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        if (!isset($this->services[$id])) {
            throw new ServiceNotFoundException($id, null, null, array_keys($this->services));
        }

        return $this->services[$id];
    }

    public function __invoke($id)
    {
        return isset($this->services[$id]) ? $this->get($id) : null;
    }
}
