<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MessengerBundle\Command;

use Symfony\Component\Messenger\Command\DebugCommand as OriginalDebugCommand;

class DebugCommand extends OriginalDebugCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName(self::$defaultName);
    }
}
