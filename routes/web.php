<?php

use App\Http\Controllers\API\DriverConfigController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\FileTreeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
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

    Route::post('/project', [ProjectController::class, 'store']);
    Route::patch('/project/{project}', [ProjectController::class, 'update']);
    Route::delete('/project/{project}', [ProjectController::class, 'delete']);

    Route::patch('/driver-config/{config}', [DriverConfigController::class, 'update']);
    Route::get('/{project}', [DiscussionController::class, 'home']);
    Route::get('/{project}/{discussion}', [DiscussionController::class, 'discussion']);
});
