<?php

namespace App\View\Models;

use App\View\Presenters\FlightRelease\CrewPresenter;
use App\View\Presenters\FlightRelease\EtopsPresenter;
use App\View\Presenters\FlightRelease\FlightInitPresenter;
use App\View\Presenters\FlightRelease\FuelPresenter;
use App\View\Presenters\FlightRelease\MaintenancePresenter;
use App\View\Presenters\FlightRelease\RoutePresenter;
use App\View\Presenters\FlightRelease\SchedulePresenter;
use App\View\Presenters\FlightRelease\TakeoffLandingReportPresenter;
use App\View\Presenters\FlightRelease\WeatherPresenter;
use App\View\Presenters\FlightRelease\WeightBalancePresenter;

class FlightReleasePageViewModelFactory
{
    public function make(?FlightPlanPageData $pageData): FlightReleasePageViewModel
    {
        return new FlightReleasePageViewModel(
            pageData: $pageData,
            etopsPresenter: new EtopsPresenter($pageData),
            schedulePresenter: new SchedulePresenter($pageData),
            fuelPresenter: new FuelPresenter($pageData),
            routePresenter: new RoutePresenter($pageData),
            flightInitPresenter: new FlightInitPresenter($pageData),
            maintenancePresenter: new MaintenancePresenter($pageData),
            crewPresenter: new CrewPresenter($pageData),
            weatherPresenter: new WeatherPresenter($pageData),
            takeoffLandingReportPresenter: new TakeoffLandingReportPresenter($pageData),
            weightBalancePresenter: new WeightBalancePresenter($pageData),
        );
    }
}
