<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Database;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = require $root . '/config/app.php';
date_default_timezone_set($config['timezone']);
mb_internal_encoding('UTF-8');

$env = (string) Env::get('APP_ENV', 'production');
$logger = new Logger((string) Env::get('LOG_PATH', $root . '/logs'), $env === 'local' ? 'debug' : 'info');
$errorHandler = new ErrorHandler($logger);
$errorHandler->register();
View::setRoot($root);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name($config['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => Env::get('SESSION_SECURE') === '1',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$session = new Session($_SESSION, true);

$app = new App(new Router(require $root . '/config/routes.php'));
$app->instance(Logger::class, $logger);
$app->instance(ErrorHandler::class, $errorHandler);
$app->instance(Session::class, $session);
$app->set(Database::class, static fn (): Database => Database::fromEnv());

return $app;
