<?php

use App\Http\Controllers\API\DriverConfigController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Chat\DatabaseSchemaController;
use App\Http\Controllers\Chat\MailFormController;
use App\Http\Controllers\Chat\TableRecordController;
use App\Http\Controllers\Chat\ToolArtifactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\Database\DatabaseExportController;
use App\Http\Controllers\Database\DatabaseImportController;
use App\Http\Controllers\Database\QueryConsoleController;
use App\Http\Controllers\FileTreeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailRecipientController;
use App\Http\Controllers\MailSettingController;
use App\Http\Controllers\Project\ProjectSettingsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SqlEditor\QueryHistoryController;
use App\Http\Controllers\SqlEditor\SqlEditorController;
use App\Http\Controllers\SqlEditor\SqlEditorQueryController;
use App\Http\Controllers\SqlEditor\SqlFileController;
use App\Http\Controllers\Tables\TableAssistantController;
use App\Http\Controllers\Tables\TableBrowserController;
use App\Http\Controllers\Tables\TableChangesController;
use App\Http\Controllers\Tables\TableDesignController;
use App\Http\Controllers\Tables\TableImportController;
use App\Http\Controllers\Tables\TableRecordsController;
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

    Route::controller(SettingsController::class)->prefix('/settings')->group(function () {
        Route::put('/agent', 'updateAgent');
        Route::put('/export', 'updateExport');
        Route::put('/account', 'updateAccount');
        Route::get('/about', 'about');
        Route::delete('/cache', 'clearCaches');
    });

    Route::prefix('/projects')->group(function () {
        Route::post('/store-infos', [ProjectController::class, 'storeInfos']);

        Route::prefix('/{project}')->middleware('can:update,project')->group(function () {
            Route::patch('/', [ProjectController::class, 'update']);
            Route::delete('/', [ProjectController::class, 'delete']);
            Route::post('/store-driver-config', [ProjectController::class, 'storeDriverConfig']);
            Route::post('/save-table-infos', [ProjectController::class, 'saveWithDisabledTables']);
            Route::post('/save-with-cloning', [ProjectController::class, 'saveWithCloning']);
            Route::get('/cloning/logs', [ProjectController::class, 'cloningLogs']);
            Route::post('/open-folder', [ProjectController::class, 'openFolder']);

            Route::prefix('/settings')->controller(ProjectSettingsController::class)->group(function () {
                Route::put('/info', 'updateInfo');
                Route::put('/database', 'updateDatabase');
                Route::get('/agent-tables', 'agentTables');
                Route::put('/agent-tables', 'updateAgentTables');
                Route::get('/memory', 'memory');
                Route::put('/memory', 'updateMemory');
                Route::delete('/', 'destroy');
            });
        });
    });

    Route::prefix('/driver-config/{config}')->middleware('can:update,config')->group(function () {
        Route::patch('/', [DriverConfigController::class, 'update']);
        Route::get('/cloning/client-payload', [DriverConfigController::class, 'cloningClientPayload']);
    });

    Route::prefix('/{project}')->middleware(ExcludeDraftProjectMiddleware::class)->group(function () {
        Route::get('/', [ConversationController::class, 'home']);
        Route::get('/conversations/search', [ConversationController::class, 'search']);
        Route::resource('/conversations', ConversationController::class)->only(['index', 'store']);
        Route::get('/chat/{conversation}', [ConversationController::class, 'conversation'])->name('conversation.chat');

        Route::get('/schema/database', [DatabaseSchemaController::class, 'database']);
        Route::get('/schema/table/{table}', [DatabaseSchemaController::class, 'table']);
        Route::get('/tables/{table}/record-form', [TableRecordController::class, 'form']);

        Route::get('/files', ProjectFileController::class);

        Route::prefix('/database')->middleware('can:view,project')->group(function () {
            Route::post('/export', DatabaseExportController::class);
            Route::post('/import', DatabaseImportController::class);
            Route::post('/query', QueryConsoleController::class);
            Route::post('/query/write', [TableAssistantController::class, 'writeQuery']);
        });

        Route::prefix('/sql-editor')->middleware('can:view,project')->group(function () {
            Route::get('/', [SqlEditorController::class, 'index'])->name('sql-editor.index');
            Route::post('/run', [SqlEditorQueryController::class, 'run']);
            Route::post('/export', [SqlEditorQueryController::class, 'export']);
            Route::post('/explain', [SqlEditorQueryController::class, 'explain']);
            Route::post('/review', [TableAssistantController::class, 'reviewQuery']);

            Route::controller(QueryHistoryController::class)->prefix('/history')->group(function () {
                Route::get('/', 'index');
                Route::delete('/', 'destroy');
            });

            Route::controller(SqlFileController::class)->prefix('/files')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{file}', 'show');
                Route::put('/{file}', 'update');
                Route::post('/{file}/rename', 'rename');
                Route::delete('/{file}', 'destroy');
            });
        });

        Route::prefix('/tables')->middleware('can:view,project')->group(function () {
            Route::controller(TableDesignController::class)->group(function () {
                Route::post('/', 'store');
                Route::post('/preview', 'previewCreate');
                Route::get('/{table}/design', 'show');
                Route::post('/{table}/design/preview', 'previewUpdate');
                Route::put('/{table}/design', 'update');
                Route::post('/{table}/rename', 'rename');
                Route::post('/{table}/duplicate', 'duplicate');
                Route::post('/{table}/truncate', 'truncate');
                Route::delete('/{table}', 'destroy');
            });

            Route::post('/{table}/import', TableImportController::class);

            Route::controller(TableBrowserController::class)->group(function () {
                Route::get('/{table?}', 'index')->name('tables.index');
                Route::get('/{table}/rows', 'rows');
                Route::get('/{table}/count', 'count');
                Route::get('/{table}/structure', 'structure');
                Route::get('/{table}/definition', 'definition');
                Route::get('/{table}/record', 'record');
                Route::get('/{table}/outline', 'outline');
                Route::get('/{table}/export', 'export');
            });

            Route::post('/{table}/records', [TableRecordsController::class, 'store']);

            Route::controller(TableChangesController::class)->group(function () {
                Route::post('/{table}/changes/preview', 'preview');
                Route::post('/{table}/changes', 'store');
            });

            Route::controller(TableAssistantController::class)->group(function () {
                Route::get('/{table}/profile', 'profile');
                Route::post('/{table}/ai/filter', 'filter')->name('tables.ai.filter');
                Route::post('/{table}/ai/insights', 'insights');
                Route::post('/{table}/ai/indexes', 'indexes');
                Route::post('/{table}/ai/chat', 'chat');
            });
        });
    });

    Route::prefix('/conversations')->group(function () {
        Route::get('/{conversation}', [ConversationController::class, 'show']);
        Route::post('/{conversation}', [ConversationController::class, 'update']);
        Route::patch('/{conversation}/archive', [ConversationController::class, 'archive']);
        Route::delete('/{conversation}', [ConversationController::class, 'destroy']);
        Route::post('/{conversation}/chat', [ConversationController::class, 'chat']);
        Route::post('/{conversation}/tool-calls/{toolCallId}/record', [TableRecordController::class, 'store']);
        Route::post('/{conversation}/tool-calls/{toolCallId}/mail', [MailFormController::class, 'store']);
        Route::get('/{conversation}/tool-calls/{toolCallId}/mail/attachments', [MailFormController::class, 'attachments']);

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
