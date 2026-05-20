<?php

declare(strict_types=1);

use Opora\Core\Config\AppConfig;

return [
    AppConfig::class => AppConfig::fromEnv(),
];
