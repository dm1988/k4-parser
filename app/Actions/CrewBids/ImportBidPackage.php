<?php

namespace App\Actions\CrewBids;

use App\Models\BidPackage;
use App\Services\CrewBids\ParseBidDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportBidPackage
{
    public function __construct(private ParseBidDocuments $parser) {}

    /** @param list<UploadedFile> $files */
    public function handle(array $files, ?int $draftId = null): BidPackage
    {
        Gate::authorize('manage-crew-bids');

        try {
            Validator::make(['files' => $files], [
                'files' => ['required', 'array', 'min:1', 'max:3'],
                'files.*' => ['required', 'file', 'mimes:pdf,xlsx', 'extensions:pdf,xlsx', 'max:12288'],
            ])->validate();

            if ($draftId !== null) {
                Gate::authorize('update', BidPackage::query()->findOrFail($draftId));
            }
            $key = 'bid-import:'.Auth::id();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages(['files' => 'Please wait a minute before importing again.']);
            }
            RateLimiter::hit($key, 60);
            $data = $this->parser->parse($files);

            return DB::transaction(function () use ($data, $draftId): BidPackage {
                if ($draftId !== null) {
                    $draft = BidPackage::query()->lockForUpdate()->findOrFail($draftId);
                    Gate::authorize('update', $draft);
                    if (BidPackage::query()->whereKeyNot($draftId)->where('fingerprint', $data['fingerprint'])->exists()) {
                        throw ValidationException::withMessages(['files' => 'These files already belong to another package. Open that package instead.']);
                    }
                    $draft->update($data);

                    return $draft;
                }

                $draft = BidPackage::query()->firstOrCreate(['fingerprint' => $data['fingerprint']], $data + ['imported_by' => Auth::id()]);
                if ($draft->published_at === null) {
                    Gate::authorize('update', $draft);
                }

                return $draft;
            });
        } finally {
            foreach ($files as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    $file->delete();
                }
            }
        }
    }
}
