<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\View;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
Env::set('APP_ENV', 'testing');
date_default_timezone_set('Asia/Makassar');
mb_internal_encoding('UTF-8');
View::setRoot($root);
