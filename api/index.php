<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$app = require __DIR__ . '/../bootstrap/app.php';

$app->usePublicPath(__DIR__ . '/../public');

$kernel = $app->handleRequest(Request::capture());

$kernel->send();
