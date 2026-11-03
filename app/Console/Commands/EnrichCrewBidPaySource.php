<?php

namespace App\Console\Commands;

use App\Models\BidPackage;
use App\Services\CrewBids\CrewPayPeriod;
use App\Services\CrewBids\ParseBidDocuments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('crew-bids:enrich-pay-source {package : Published package ID} {files* : Original Lines, Trips and/or Overview files} {--included-occupied-day=* : Confirm a LINE:YYYY-MM-DD obligation is already included in published line Credit}')]
#[Description('Recover missing pay metadata from fingerprint-verified original bid files')]
class EnrichCrewBidPaySource extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ParseBidDocuments $parser): int
    {
        $package = BidPackage::query()->whereNotNull('published_at')->findOrFail((int) $this->argument('package'));
        $files = [];
        $hashes = [];
        foreach ($this->argument('files') as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                $this->error('Every source must be an existing readable file.');

                return self::FAILURE;
            }
            $files[] = new UploadedFile($path, basename($path), null, null, true);
            $hashes[] = hash_file('sha256', $path);
        }
        sort($hashes);
        $source = implode('', $hashes);
        $fingerprints = [hash('sha256', $source)];
        foreach (['v1', 'v2', 'v3', 'v4'] as $version) {
            $fingerprints[] = hash('sha256', 'crew-bids-parser:'.$version.':'.$source);
        }
        if (! in_array($package->fingerprint, $fingerprints, true)) {
            $this->error('Source files do not match this published package fingerprint. No pay metadata was changed.');

            return self::FAILURE;
        }
        Validator::make(['files' => $files], ['files' => ['required', 'array', 'min:1', 'max:3'],
            'files.*' => ['file', 'mimes:pdf,xlsx', 'extensions:pdf,xlsx', 'max:12288']])->validate();
        $parsed = $parser->parse($files);
        if ($parsed['period'] !== $package->period || $parsed['fleet'] !== $package->fleet || $parsed['source_revision'] !== $package->source_revision) {
            $this->error('Parsed source identity differs from the published package. No pay metadata was changed.');

            return self::FAILURE;
        }
        $prior = $package->pay_source;
        $confirmations = [];
        if (($prior['package_fingerprint'] ?? null) === $package->fingerprint) {
            foreach ($prior['payload']['lines'] ?? [] as $id => $line) {
                if (($line['source_credit'] ?? null) === ($parsed['payload']['lines'][$id]['source_credit'] ?? null)) {
                    foreach ($line['pay_included_occupied_dates'] ?? [] as $date) {
                        $confirmations[] = $id.':'.$date;
                    }
                }
            }
        }
        foreach (array_unique(array_merge($confirmations, $this->option('included-occupied-day'))) as $confirmation) {
            if (! preg_match('/\A(F\d+):(\d{4}-\d{2}-\d{2})\z/', $confirmation, $match)) {
                $this->error('Included occupied days must use LINE:YYYY-MM-DD, for example F49:2026-11-15.');

                return self::FAILURE;
            }
            [, $id, $date] = $match;
            $line = $parsed['payload']['lines'][$id] ?? [];
            if (! isset($line['source_credit']) || ! is_int($line['source_credit']) || $line['source_credit'] < 0
                || ($line['calendar'][$date]['status'] ?? '') !== 'occupied'
                || ! (new CrewPayPeriod($package->period))->contains($date)) {
                $this->error('An included-day confirmation requires an occupied date and published Credit for that line. No pay metadata was changed.');

                return self::FAILURE;
            }
            $parsed['payload']['lines'][$id]['pay_included_occupied_dates'][] = $date;
        }
        DB::transaction(function () use ($package, $parsed): void {
            $current = BidPackage::query()->lockForUpdate()->findOrFail($package->id);
            abort_unless($current->fingerprint === $package->fingerprint, 409);
            $current->timestamps = false;
            $current->forceFill(['pay_source' => [
                'package_fingerprint' => $current->fingerprint,
                'source_fingerprint' => $parsed['fingerprint'],
                'parser_version' => ParseBidDocuments::VERSION,
                'verified_at' => now('UTC')->toIso8601String(),
                'payload' => $parsed['payload'],
            ]])->save();
        });
        $this->info('Verified pay-source metadata saved for '.count($parsed['payload']['lines']).' lines.');

        return self::SUCCESS;
    }
}
