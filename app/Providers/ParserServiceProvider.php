<?php

namespace App\Providers;

use App\Mappers\FlightMapper;
use App\Services\Calendar\IcsGenerator;
use App\Services\Schedule\Extractor\CrewListParser;
use App\Services\Schedule\Extractor\PdfTextExtractor;
use App\Services\Schedule\Extractor\PublishedRosterParser;
use App\Services\Schedule\Extractor\ScheduleFormatParser;
use App\Services\Schedule\Extractor\TripInformationParser;
use App\Services\Schedule\ScheduleInputResolver;
use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PdfTextExtractor::class);
        $this->app->singleton(CrewListParser::class);
        $this->app->singleton(TripInformationParser::class);
        $this->app->singleton(PublishedRosterParser::class);
        $this->app->singleton(ScheduleFormatParser::class);
        $this->app->singleton(IcsGenerator::class);
        $this->app->singleton(ScheduleInputResolver::class);
        $this->app->singleton(FlightMapper::class);
    }
}
