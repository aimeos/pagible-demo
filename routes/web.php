<?php

use Aimeos\Cms\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response('OK', 200);
});

Route::group(config('cms.multidomain') ? ['domain' => '{domain}'] : [], function() {
    Route::get('{path?}', [Controllers\PageController::class, 'index'])
        ->middleware(['web'])
        ->name('cms.page');
});
