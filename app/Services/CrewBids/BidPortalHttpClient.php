<?php

namespace App\Services\CrewBids;

use Illuminate\Http\Client\Factory;

/**
 * Keep portal credentials/state out of application HTTP events, profilers, and global middleware.
 */
class BidPortalHttpClient extends Factory
{
    public function __construct()
    {
        parent::__construct();
    }
}
