<?php

namespace App\Filament\Resources\BidPackages\Pages;

use App\Filament\Resources\BidPackages\BidPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListBidPackages extends ListRecords
{
    protected static string $resource = BidPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [BidPackageResource::importAction()];
    }
}
