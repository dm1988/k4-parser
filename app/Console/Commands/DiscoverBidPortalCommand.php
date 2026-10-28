<?php

namespace App\Console\Commands;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\DiscoverBidPortal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('crew-bids:discover-portal {--lines= : Optional comma-separated local line IDs for an offline eligibility check}')]
#[Description('Log in and inspect portal structure without submitting or changing any bid')]
class DiscoverBidPortalCommand extends Command
{
    public function handle(DiscoverBidPortal $discovery): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run interactively; credentials are accepted only through hidden prompts.');

            return self::FAILURE;
        }
        $this->info('Read-only discovery: no bid will be submitted. Page values and credentials are omitted from output.');
        try {
            $employee = $this->secret('Employee number', false);
            $password = $this->secret('Portal password', false);
            if (! is_string($employee) || ! is_string($password)) {
                throw new BidPortalException('Employee number and password are required.');
            }
            $lines = $this->option('lines');
            $selections = is_string($lines) && trim($lines) !== '' ? array_map(trim(...), explode(',', $lines)) : [];
            $report = $discovery->handle($employee, $password, $selections);
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->warn('Discovery is not submission preflight. Account/context, deadline, submit action, and receipt verification remain unverified.');

            return self::SUCCESS;
        } catch (BidPortalException $exception) {
            $this->error($exception->getMessage());
        } catch (Throwable) {
            $this->error('Portal discovery failed. No bid was sent.');
        } finally {
            unset($employee, $password);
        }

        return self::FAILURE;
    }
}
