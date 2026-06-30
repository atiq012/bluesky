<?php

use App\Http\Controllers\AgentUploadController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/uploads/agents/{path}', [AgentUploadController::class, 'show'])
    ->where('path', '.+');

Route::get('/clearcache', function () {
    Artisan::call('optimize:clear');

    return back();
})->name('clearcache');

Route::get('/configcache', function () {
    Artisan::call('config:cache');

    return Artisan::output();
});

Route::get('/migrate', function () {
    Artisan::call('migrate');

    return Artisan::output();
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
