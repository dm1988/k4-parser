<?php

namespace App\Providers;

use App\Mappers\FlightMapper;
use App\Services\CrewListParser;
use App\Services\IcsCalendarService;
use App\Services\PublishedRosterParser;
use App\Services\ScheduleFormatParser;
use App\Services\ScheduleInputResolver;
use App\Services\SchedulePdfExtractor;
use App\Services\TripInformationParser;
use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchedulePdfExtractor::class);
        $this->app->singleton(CrewListParser::class);
        $this->app->singleton(TripInformationParser::class);
        $this->app->singleton(PublishedRosterParser::class);
        $this->app->singleton(ScheduleFormatParser::class);
        $this->app->singleton(IcsCalendarService::class);
        $this->app->singleton(ScheduleInputResolver::class);
        $this->app->singleton(FlightMapper::class);
    }
}
