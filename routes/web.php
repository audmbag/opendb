<?php

use App\Http\Controllers\API\DriverConfigController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Chat\DatabaseSchemaController;
use App\Http\Controllers\Chat\TableRecordController;
use App\Http\Controllers\Chat\ToolArtifactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FileTreeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailRecipientController;
use App\Http\Controllers\MailSettingController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProviderController;
use App\Http\Middleware\ExcludeDraftProjectMiddleware;
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

    Route::resource('/providers', ProviderController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::prefix('/providers')->group(function () {
        Route::patch('/{provider}', [ProviderController::class, 'connect']);
        Route::delete('/{provider}', [ProviderController::class, 'disconnect']);
        Route::post('/toggle-model/{provider}', [ProviderController::class, 'toggleModel']);
    });

    Route::controller(MailSettingController::class)->prefix('/mail-settings')->group(function () {
        Route::get('/', 'show');
        Route::put('/', 'update');
        Route::post('/test', 'test');
    });

    Route::resource('/mail-recipients', MailRecipientController::class)->only(['index', 'store', 'destroy']);

    Route::prefix('/projects')->group(function () {
        Route::patch('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'delete']);
        Route::post('/store-infos', [ProjectController::class, 'storeInfos']);
        Route::post('/{project}/store-driver-config', [ProjectController::class, 'storeDriverConfig']);
        Route::post('/{project}/save-table-infos', [ProjectController::class, 'saveWithDisabledTables']);
        Route::post('/{project}/save-with-cloning', [ProjectController::class, 'saveWithCloning']);
        Route::get('/{project}/cloning/logs', [ProjectController::class, 'cloningLogs']);
    });

    Route::patch('/driver-config/{config}', [DriverConfigController::class, 'update']);
    Route::get('/driver-config/{config}/cloning/client-payload', [DriverConfigController::class, 'cloningClientPayload']);

    Route::prefix('/{project}')->middleware(ExcludeDraftProjectMiddleware::class)->group(function () {
        Route::get('/', [ConversationController::class, 'home']);
        Route::resource('/conversations', ConversationController::class)->only(['index', 'store']);
        Route::get('/chat/{conversation}', [ConversationController::class, 'conversation'])->name('conversation.chat');

        Route::get('/schema/database', [DatabaseSchemaController::class, 'database']);
        Route::get('/schema/table/{table}', [DatabaseSchemaController::class, 'table']);
        Route::get('/tables/{table}/record-form', [TableRecordController::class, 'form']);

        Route::get('/files', ProjectFileController::class);
    });

    Route::prefix('/conversations')->group(function () {
        Route::post('/{conversation}', [ConversationController::class, 'update']);
        Route::patch('/{conversation}/archive', [ConversationController::class, 'archive']);
        Route::delete('/{conversation}', [ConversationController::class, 'destroy']);
        Route::post('/{conversation}/chat', [ConversationController::class, 'chat']);
        Route::post('/{conversation}/tool-calls/{toolCallId}/record', [TableRecordController::class, 'store']);

        Route::controller(ToolArtifactController::class)->prefix('/{conversation}/tool-calls/{toolCallId}/artifact')->group(function () {
            Route::get('/results', 'results');
            Route::get('/kpis', 'kpis');
            Route::get('/chart', 'chart');
            Route::get('/profile', 'profile');
            Route::get('/record', 'record');
            Route::get('/explain', 'explain');
            Route::get('/write-preview', 'writePreview');
            Route::get('/download', 'download');
        });
    });

});
