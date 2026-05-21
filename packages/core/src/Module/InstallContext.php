<?php

declare(strict_types=1);

namespace Opora\Core\Module;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Контекст, передаваемый в install() и update().
 *
 * Содержит всё, что нужно модулю для выполнения lifecycle-хуков.
 *
 * @api
 */
final readonly class InstallContext
{
    public function __construct(
        public ContainerInterface $container,
        public LoggerInterface $logger,
        public InputInterface $input,
    ) {
    }
}
