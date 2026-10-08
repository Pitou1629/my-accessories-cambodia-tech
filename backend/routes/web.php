<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $index = public_path('app/index.html');

    return is_file($index)
        ? response()->file($index)
        : response()->json(['message' => 'Frontend bundle not built yet.'], 404);
})->where('path', '^(?!api(?:/|$)).*');
