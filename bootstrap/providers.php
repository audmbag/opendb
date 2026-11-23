<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\ConsoleServiceProvider;
use App\Providers\ErrorPageServiceProvider;
use App\Providers\InstallationServiceProvider;
use App\Providers\McpServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    ConsoleServiceProvider::class,
    ErrorPageServiceProvider::class,
    InstallationServiceProvider::class,
    McpServiceProvider::class,
];
