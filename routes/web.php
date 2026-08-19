<?php

use App\Http\Controllers\API\DriverConfigController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FileTreeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProviderController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'loginForm'])->name('login');
Route::post('/login', [LoginController::class, 'processLogin']);

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('/', HomeController::class);

    Route::prefix('/helpers')->group(function () {
        Route::get('database-ms', [DriverConfigController::class, 'index']);
        Route::post('test-connection', [DriverConfigController::class, 'testConnection']);
        Route::get('file-tree', FileTreeController::class);
    });

    Route::resource('providers', ProviderController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('providers/{provider}', [ProviderController::class, 'connect']);
    Route::delete('providers/{provider}', [ProviderController::class, 'disconnect']);
    Route::post('toggle-model/{provider}', [ProviderController::class, 'toggleModel']);

    Route::post('/project', [ProjectController::class, 'store']);
    Route::patch('/project/{project}', [ProjectController::class, 'update']);
    Route::delete('/project/{project}', [ProjectController::class, 'delete']);

    Route::patch('/driver-config/{config}', [DriverConfigController::class, 'update']);

    Route::prefix('/{project}')->group(function () {
        Route::get('/', [ConversationController::class, 'home']);
        Route::resource('conversations', ConversationController::class)->only(['index', 'store']);
        Route::get('/{discussion}', [ConversationController::class, 'discussion']);
    });
});
