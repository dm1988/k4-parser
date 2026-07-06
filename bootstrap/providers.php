<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ParserServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ParserServiceProvider::class,
];
