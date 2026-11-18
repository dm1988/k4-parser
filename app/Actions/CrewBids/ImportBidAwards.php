<?php

namespace App\Actions\CrewBids;

use App\Models\BidAwardReport;
use App\Models\BidPackage;
use App\Services\CrewBids\ParseBidAwards;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportBidAwards
{
    public function __construct(private ParseBidAwards $parser) {}

    public function handle(UploadedFile $file, BidPackage $package, string $source = 'upload'): BidAwardReport
    {
        Gate::authorize('manage-crew-bids');
        abort_unless($package->published_at !== null && $package->archived_at === null, 404);
        Validator::make(['awardFile' => $file], ['awardFile' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:12288']])->validate();
        $key = 'bid-award-import:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['awardFile' => 'Please wait a minute before importing awards again.']);
        }
        RateLimiter::hit($key, 60);
        $parsed = $this->parser->parse($file->getPathname());
        if ($parsed['period'] !== $package->period || $parsed['fleet'] !== $package->fleet) {
            throw ValidationException::withMessages(['awardFile' => 'The awards and selected package must have the same month and fleet.']);
        }

        return BidAwardReport::query()->firstOrCreate([
            'bid_package_id' => $package->id,
            'fingerprint' => hash('sha256', 'bid-awards:v1:'.hash_file('sha256', $file->getPathname())),
        ], $parsed + ['imported_by' => Auth::id(), 'source' => $source]);
    }
}
