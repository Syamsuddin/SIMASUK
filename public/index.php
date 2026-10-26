<?php

declare(strict_types=1);

use App\Core\Request;

$root = dirname(__DIR__);

// Mode pemeliharaan saat deploy (docs/08, docs/25).
if (is_file($root . '/MAINTENANCE')) {
    http_response_code(503);
    header('Retry-After: 600');
    header('Content-Type: text/html; charset=UTF-8');
    require $root . '/resources/views/errors/503.php';
    exit;
}

/** @var \App\Core\App $app */
$app = require $root . '/bootstrap/app.php';
$app->handle(Request::fromGlobals())->send();
