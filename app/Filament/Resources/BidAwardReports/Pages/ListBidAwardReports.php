<?php

namespace App\Filament\Resources\BidAwardReports\Pages;

use App\Filament\Resources\BidAwardReports\BidAwardReportResource;
use Filament\Resources\Pages\ListRecords;

class ListBidAwardReports extends ListRecords
{
    protected static string $resource = BidAwardReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BidAwardReportResource::importAction(),
        ];
    }
}
