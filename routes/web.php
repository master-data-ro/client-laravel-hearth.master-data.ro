<?php

use Illuminate\Support\Facades\Route;

$c = \Hearth\LicenseClient\Controllers\LicenseManagementController::class;

Route::middleware('web')->group(function () use ($c) {
    Route::get('/licenta', [$c, 'licenta'])->name('license-client.licenta.index');
    Route::post('/licenta/activate', [$c, 'upload'])->name('license-client.licenta.upload');
    Route::post('/licenta/verify', [$c, 'verify'])->name('license-client.licenta.verify');
    Route::delete('/licenta', [$c, 'destroy'])->name('license-client.licenta.destroy');
});
