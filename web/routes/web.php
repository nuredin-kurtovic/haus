<?php

use Illuminate\Support\Facades\Route;

// SPA: sve neAPI rute servira Vue router.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|storage|horizon).*$');
