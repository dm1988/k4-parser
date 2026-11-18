<?php

namespace App\Actions\CrewBids;

use App\Models\PilotSeniorityList;
use App\Models\User;
use App\Services\CrewBids\ReadPilotSeniority;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ImportPilotSeniority
{
    private const int MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private ReadPilotSeniority $reader) {}

    public function handle(string $url, User $actor): PilotSeniorityList
    {
        Gate::forUser($actor)->authorize('manage-crew-bids');
        if (! preg_match('~\Ahttps://crewbids\.kalittaair\.com/hyperlinks/([^/?#]+\.pdf)\z~i', $url, $match)
            || preg_match('~[/\\\\\x00-\x1F]~', rawurldecode($match[1])) || str_contains(rawurldecode($match[1]), '..')) {
            throw new RuntimeException('Use a direct HTTPS PDF link from crewbids.kalittaair.com/hyperlinks/.');
        }

        return Cache::lock('crew-bids:seniority-import', 120)->block(5, function () use ($url, $actor): PilotSeniorityList {
            try {
                $response = Http::connectTimeout(5)->timeout(30)->withoutRedirecting()
                    ->withOptions(['progress' => function (int $total, int $downloaded): void {
                        if ($downloaded > self::MAX_BYTES) {
                            throw new RuntimeException('Oversized seniority PDF.');
                        }
                    }])->get($url);
            } catch (Throwable) {
                throw new RuntimeException('The seniority PDF could not be downloaded. The previous list is unchanged.');
            }
            $pdf = $response->body();
            if ($response->status() !== 200 || strlen($pdf) > self::MAX_BYTES || ! str_starts_with($pdf, '%PDF-')
                || ! str_starts_with(strtolower($response->header('Content-Type')), 'application/pdf')) {
                throw new RuntimeException('The link did not return a supported seniority PDF. The previous list is unchanged.');
            }
            $data = $this->reader->read($pdf);

            return DB::transaction(function () use ($data, $pdf, $url, $actor): PilotSeniorityList {
                $owner = User::query()->findOrFail($actor->id);
                Gate::forUser($owner)->authorize('manage-crew-bids');
                $current = PilotSeniorityList::current();
                if ($current !== null && $current->effective_date->format('Y-m-d') > $data['effective_date']) {
                    throw new RuntimeException('An older seniority list cannot replace the current list.');
                }
                $fingerprint = hash('sha256', $pdf);
                if ($current !== null && $current->fingerprint === $fingerprint) {
                    return $current;
                }
                $list = $current ?? new PilotSeniorityList;
                $list->id = 1;
                $list->fill($data + ['source_url' => $url, 'fingerprint' => $fingerprint, 'imported_by' => $actor->id, 'imported_at' => now()])->save();

                return $list;
            });
        });
    }
}
