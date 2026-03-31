<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\EmiPaynamicsConstellation\Actions\Webhooks\HandleConstellationWebhook;

Route::post('/webhooks/constellation', function () {
    $receipt = HandleConstellationWebhook::run(request()->all());

    return response()->json([
        'status' => $receipt->processing_status,
        'postback_id' => $receipt->postback_id,
    ]);
})->name('constellation.webhook');
