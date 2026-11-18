<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class BidPortalException extends Exception implements ShouldntReport {}
