<?php

namespace App\Providers;

use App\Services\IcsCalendarService;
use App\Services\PdfScheduleParser;
use App\Services\PublishedRosterParser;
use App\Services\RosterDocumentParser;
use App\Services\RosterParser;
use App\Services\RosterSourceResolver;
use App\Mappers\FlightMapper;
use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PdfScheduleParser::class);
        $this->app->singleton(RosterParser::class);
        $this->app->singleton(PublishedRosterParser::class);
        $this->app->singleton(RosterDocumentParser::class);
        $this->app->singleton(IcsCalendarService::class);
        $this->app->singleton(RosterSourceResolver::class);
        $this->app->singleton(FlightMapper::class);
    }
}
