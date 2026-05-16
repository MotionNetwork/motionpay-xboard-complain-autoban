<?php

use Illuminate\Support\Facades\Route;
use Plugin\MotionPayWebhook\Controllers\WebhookController;

Route::post('/api/v1/motionpay/webhook', [WebhookController::class, 'handle']);
