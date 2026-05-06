<?php

use Illuminate\Support\Facades\Route;

$c = \Hearth\LicenseClient\Controllers\LicenseManagementController::class;

// Middleware + optional URL prefix are applied in LicenseServiceProvider::registerLicenseWebRoutes().
Route::get('licenta', [$c, 'licenta'])->name('license-client.licenta.index');
Route::post('licenta/solicita', [$c, 'solicita'])->name('license-client.licenta.solicita');
Route::post('licenta/activate', [$c, 'upload'])->name('license-client.licenta.upload');
Route::post('licenta/verify', [$c, 'verify'])->name('license-client.licenta.verify');
Route::delete('licenta', [$c, 'destroy'])->name('license-client.licenta.destroy');
