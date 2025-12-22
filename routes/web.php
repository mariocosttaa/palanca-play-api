<?php

use App\Http\Middleware\TenantAuthPrivateFiles;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantFileController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');


// Files Routes
Route::prefix('file/{tenantIdHashed}')->group(function () {

    // Private Files
    Route::get('/company/{filePath}', [TenantFileController::class, 'showPrivate'])
        ->middleware(TenantAuthPrivateFiles::class)
        ->name('tenant-file-show-private')
        ->where('filePath', '.*');

    // Public Files
    Route::get('/{filePath?}', [TenantFileController::class, 'showPublic'])
        ->name('tenant-file-show-public')
        ->where('filePath', '.*');

});