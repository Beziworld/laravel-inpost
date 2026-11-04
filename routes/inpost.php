<?php

declare(strict_types=1);

use Beziworld\InPost\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

$middleware = Config::get('inpost.webhooks.middleware', []);

Route::match(['GET', 'POST'], (string) Config::get('inpost.webhooks.path', 'inpost/webhook'), WebhookController::class)
    ->middleware(is_array($middleware) ? $middleware : [])
    ->name((string) Config::get('inpost.webhooks.name', 'inpost.webhook'));
