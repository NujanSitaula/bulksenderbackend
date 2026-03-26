<?php

use Illuminate\Support\Facades\Route;
use App\Models\EmailRecipient;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/track/open/{openToken}', function (string $openToken) {
    $recipient = EmailRecipient::query()
        ->where('open_token', $openToken)
        ->first();

    if ($recipient && $recipient->opened_at === null) {
        $recipient->opened_at = now();
        $recipient->save();
    }

    // 1x1 transparent PNG.
    $pixelPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X4JkAAAAASUVORK5CYII=');

    return response($pixelPng, 200)
        ->header('Content-Type', 'image/png')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->where('openToken', '[A-Za-z0-9]+');

Route::get('/unsubscribe/{unsubscribeToken}', function (string $unsubscribeToken) {
    $recipient = EmailRecipient::query()
        ->where('unsubscribe_token', $unsubscribeToken)
        ->first();

    if ($recipient && $recipient->unsubscribed_at === null) {
        $recipient->unsubscribed_at = now();
        $recipient->save();
    }

    return response(
        '<!doctype html><html><body style="font-family:Arial,sans-serif;">'
        .'Your email has been unsubscribed successfully.'
        .'</body></html>',
        200
    )->header('Content-Type', 'text/html; charset=utf-8');
})->where('unsubscribeToken', '[A-Za-z0-9]+');
