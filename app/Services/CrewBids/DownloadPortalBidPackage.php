<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DownloadPortalBidPackage
{
    private const string ORIGIN = 'https://crewbids.kalittaair.com/hyperlinks/';

    private const int MAX_BYTES = 12 * 1024 * 1024;

    /** @return ?list<UploadedFile> */
    public function download(string $period, string $fleet): ?array
    {
        if (! preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $period) || ! in_array($fleet, ['747', '777'], true)) {
            throw new RuntimeException('The requested bid package period or fleet is invalid.');
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $period, 'UTC');
        if ($month === null || $month->format('Y-m') !== $period) {
            throw new RuntimeException('The requested bid package period is invalid.');
        }

        $files = [];
        try {
            foreach (['Lines.pdf' => 'application/pdf', 'Trips.pdf' => 'application/pdf', 'Overview.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $suffix => $type) {
                $name = $fleet.' '.$month->format('F Y').' '.$suffix;
                $response = Http::connectTimeout(5)->timeout(30)->withoutRedirecting()
                    ->withOptions(['progress' => function (int $downloadTotal, int $downloadedBytes): void {
                        if ($downloadedBytes > self::MAX_BYTES) {
                            throw new RuntimeException('The portal returned an oversized bid document.');
                        }
                    }])
                    ->retry([200, 500], 0, fn (?Throwable $exception, PendingRequest $request): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())), throw: false)
                    ->get(self::ORIGIN.rawurlencode($name));

                if ($response->notFound()) {
                    $this->cleanup($files);

                    return null;
                }

                $body = $response->body();
                $contentLength = $response->header('Content-Length');
                $expectedMagic = str_ends_with($suffix, '.pdf') ? '%PDF-' : "PK\x03\x04";
                if ($response->status() !== 200 || ! str_starts_with(strtolower((string) $response->header('Content-Type')), $type)
                    || ! str_starts_with($body, $expectedMagic) || strlen($body) > self::MAX_BYTES
                    || ($contentLength !== '' && (! ctype_digit($contentLength) || (int) $contentLength !== strlen($body)))) {
                    throw new RuntimeException('The portal returned an invalid or oversized bid document.');
                }

                $path = tempnam(sys_get_temp_dir(), 'crew-bid-');
                if ($path === false || file_put_contents($path, $body) !== strlen($body)) {
                    if ($path !== false) {
                        @unlink($path);
                    }
                    throw new RuntimeException('The bid document could not be staged.');
                }
                $files[] = new UploadedFile($path, $name, $type, null, true);
            }

            return $files;
        } catch (Throwable $exception) {
            $this->cleanup($files);

            throw $exception;
        }
    }

    /** @param list<UploadedFile> $files */
    public function cleanup(array $files): void
    {
        foreach ($files as $file) {
            @unlink($file->getPathname());
        }
    }
}
