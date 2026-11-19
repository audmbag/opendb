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
use App\Http\Controllers\Erd\ErdAssistantController;
use App\Http\Controllers\Erd\ErdController;
use App\Http\Controllers\FileTreeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailRecipientController;
use App\Http\Controllers\MailSettingController;
use App\Http\Controllers\McpTokenController;
use App\Http\Controllers\Project\ProjectArchivedConversationController;
use App\Http\Controllers\Project\ProjectDisconnectController;
use App\Http\Controllers\Project\ProjectSettingsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SqlEditor\QueryHistoryController;
use App\Http\Controllers\SqlEditor\SnippetController;
use App\Http\Controllers\SqlEditor\SqlEditorController;
use App\Http\Controllers\SqlEditor\SqlEditorQueryController;
use App\Http\Controllers\SqlEditor\SqlFileController;
use App\Http\Controllers\Tables\TableAssistantController;
use App\Http\Controllers\Tables\TableBrowserController;
use App\Http\Controllers\Tables\TableChangesController;
use App\Http\Controllers\Tables\TableDesignController;
use App\Http\Controllers\Tables\TableImportController;
use App\Http\Controllers\Tables\TableRecordsController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\Users\GrantController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\ExcludeDraftProjectMiddleware;
use App\Models\Provider;
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

    Route::get('/providers', [ProviderController::class, 'index']);

    Route::middleware('can:manage,'.Provider::class)->group(function () {
        Route::resource('/providers', ProviderController::class)->only(['store', 'update', 'destroy']);

        Route::prefix('/providers')->group(function () {
            Route::patch('/{provider}', [ProviderController::class, 'connect']);
            Route::delete('/{provider}', [ProviderController::class, 'disconnect']);
            Route::post('/toggle-model/{provider}', [ProviderController::class, 'toggleModel']);
        });
    });

    Route::controller(MailSettingController::class)->prefix('/mail-settings')->group(function () {
        Route::get('/', 'show');
        Route::put('/', 'update');
        Route::post('/test', 'test');
    });

    Route::resource('/mail-recipients', MailRecipientController::class)->only(['index', 'store', 'destroy']);

    Route::resource('/mcp-tokens', McpTokenController::class)->only(['index', 'store', 'destroy']);

    Route::controller(SettingsController::class)->prefix('/settings')->group(function () {
        Route::put('/agent', 'updateAgent');
        Route::put('/export', 'updateExport');
        Route::put('/account', 'updateAccount');
        Route::get('/about', 'about');
        Route::delete('/cache', 'clearCaches');
    });

    Route::get('/settings/usage', UsageController::class);

    Route::resource('/users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('/roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::controller(GrantController::class)->prefix('/grants')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::delete('/', 'destroy');
        Route::get('/catalog', 'catalog');
        Route::get('/projects/{project}', 'project')->can('share', 'project');
    });

    Route::prefix('/projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
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

        // Everyone who reaches the project keeps their own archived conversations.
        Route::prefix('/{project}/settings/archived-conversations')->middleware('can:view,project')->controller(ProjectArchivedConversationController::class)->scopeBindings()->group(function () {
            Route::get('/', 'index');
            Route::delete('/', 'destroyAll');
            Route::patch('/{conversation}/restore', 'restore')->can('update', 'conversation');
            Route::delete('/{conversation}', 'destroy')->can('update', 'conversation');
        });
    });

    Route::prefix('/driver-config/{config}')->middleware('can:update,config')->group(function () {
        Route::patch('/', [DriverConfigController::class, 'update']);
        Route::get('/cloning/client-payload', [DriverConfigController::class, 'cloningClientPayload']);
    });

    Route::prefix('/{project}')->middleware([ExcludeDraftProjectMiddleware::class, 'can:view,project'])->group(function () {
        Route::get('/', [ConversationController::class, 'home']);
        Route::post('/disconnect', ProjectDisconnectController::class);
        Route::get('/conversations/search', [ConversationController::class, 'search']);
        Route::resource('/conversations', ConversationController::class)->only(['index', 'store']);
        Route::get('/chat/{conversation}', [ConversationController::class, 'conversation'])->name('conversation.chat')->can('view', 'conversation');

        Route::get('/schema/database', [DatabaseSchemaController::class, 'database']);
        Route::get('/schema/table/{table}', [DatabaseSchemaController::class, 'table']);
        Route::get('/tables/{table}/record-form', [TableRecordController::class, 'form']);

        Route::get('/files', ProjectFileController::class);

        Route::prefix('/database')->middleware('can:view,project')->group(function () {
            Route::post('/export', DatabaseExportController::class);
            Route::post('/import', DatabaseImportController::class)->can('import', 'project');
            Route::post('/query', QueryConsoleController::class);
            Route::post('/query/write', [TableAssistantController::class, 'writeQuery']);
        });

        Route::prefix('/erd')->middleware('can:view,project')->controller(ErdController::class)->group(function () {
            Route::get('/', 'index')->name('erd.index');
            Route::post('/export', 'export');
            Route::put('/layout', 'saveLayout');
            Route::post('/assistant/chat', [ErdAssistantController::class, 'chat']);
            Route::post('/assistant/preview', [ErdAssistantController::class, 'preview']);
        });

        Route::prefix('/sql-editor')->middleware('can:view,project')->group(function () {
            Route::get('/', [SqlEditorController::class, 'index'])->name('sql-editor.index');
            Route::post('/run', [SqlEditorQueryController::class, 'run']);
            Route::post('/export', [SqlEditorQueryController::class, 'export']);
            Route::post('/explain', [SqlEditorQueryController::class, 'explain']);
            Route::post('/rows', [SqlEditorQueryController::class, 'rows']);
            Route::post('/cancel', [SqlEditorQueryController::class, 'cancel']);
            Route::put('/read-only', [SqlEditorQueryController::class, 'readOnly']);
            Route::post('/review', [TableAssistantController::class, 'reviewQuery']);

            Route::controller(QueryHistoryController::class)->prefix('/history')->group(function () {
                Route::get('/', 'index');
                Route::delete('/', 'destroy');
            });

            Route::controller(SnippetController::class)->prefix('/snippets')->group(function () {
                Route::get('/', 'index');
                Route::put('/', 'update');
            });

            // A file or a folder is named by its path in .opendb/sql, whose slashes stay in the URL.
            Route::controller(SqlFileController::class)->prefix('/files')->where(['file' => '.+'])->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{file}', 'show');
                Route::put('/{file}', 'update');
                Route::post('/{file}/rename', 'rename');
                Route::delete('/{file}', 'destroy');
            });

            Route::controller(SqlFileController::class)->prefix('/folders')->where(['folder' => '.+'])->group(function () {
                Route::post('/', 'storeFolder');
                Route::post('/{folder}/rename', 'renameFolder');
                Route::delete('/{folder}', 'destroyFolder');
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

            Route::get('/{table}/profile', [TableAssistantController::class, 'profile']);

            Route::controller(TableAssistantController::class)->group(function () {
                Route::post('/{table}/ai/filter', 'filter')->name('tables.ai.filter');
                Route::post('/{table}/ai/insights', 'insights');
                Route::post('/{table}/ai/indexes', 'indexes');
                Route::post('/{table}/ai/chat', 'chat');
            });
        });
    });

    Route::prefix('/conversations/{conversation}')->middleware('can:view,conversation')->group(function () {
        Route::get('/', [ConversationController::class, 'show']);
        Route::post('/', [ConversationController::class, 'update']);
        Route::patch('/archive', [ConversationController::class, 'archive']);
        Route::post('/cancel', [ConversationController::class, 'cancel']);
        Route::delete('/', [ConversationController::class, 'destroy']);
        Route::post('/chat', [ConversationController::class, 'chat']);
        Route::post('/tool-calls/{toolCallId}/record', [TableRecordController::class, 'store']);
        Route::post('/tool-calls/{toolCallId}/mail', [MailFormController::class, 'store']);
        Route::get('/tool-calls/{toolCallId}/mail/attachments', [MailFormController::class, 'attachments']);

        Route::controller(ToolArtifactController::class)->prefix('/tool-calls/{toolCallId}/artifact')->group(function () {
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
