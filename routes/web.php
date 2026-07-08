<?php

use Aimeos\Cms\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

Route::pattern('domain', '[A-Za-z0-9.-]+');

Route::domain('{domain}')->group(function () {
    Route::get('{path?}', function (Request $request) {
        $path = (string) $request->route('path', '');
        $domain = (string) $request->getHost();

        URL::defaults(['domain' => $domain]);

        return app(Controllers\PageController::class)->index($request, $path, $domain);
    })
        ->where('path', '.*')
        ->name('cms.page');
    });
