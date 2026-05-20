<?php

declare(strict_types=1);

return [
    'app.name' => 'Opora',
    'app.env' => $_SERVER['APP_ENV'] ?? 'production',
    'app.debug' => (bool) ($_SERVER['APP_DEBUG'] ?? false),
];
