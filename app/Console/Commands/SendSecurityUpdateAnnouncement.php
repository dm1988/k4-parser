<?php

namespace App\Console\Commands;

use App\Mail\SecurityUpdateAnnouncement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendSecurityUpdateAnnouncement extends Command
{
    protected $signature = 'mail:security-update
        {--email= : Queue one review copy to a specific address}
        {--all : Queue the update for all active users with email addresses}';

    protected $description = 'Review or queue the K4 Parser security and feature update';

    public function handle(): int
    {
        $email = $this->option('email');
        $sendToAll = (bool) $this->option('all');

        if ($email && $sendToAll) {
            $this->error('Choose either --email for a review copy or --all for the full send.');

            return self::FAILURE;
        }

        if ($email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('The review email address is invalid.');

                return self::FAILURE;
            }

            Mail::to($email)->queue(new SecurityUpdateAnnouncement('Dave'));
            $this->info("Review copy queued for {$email}.");

            return self::SUCCESS;
        }

        if (! $sendToAll) {
            $this->error('Specify --email=address@example.com or --all.');

            return self::FAILURE;
        }

        $guardKey = 'mailings:security-update:queued';

        if (Cache::has($guardKey)) {
            $this->error('The security update was already queued.');

            return self::FAILURE;
        }

        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        $recipientCount = $query->count();

        if ($recipientCount === 0) {
            $this->warn('No eligible users were found.');

            return self::SUCCESS;
        }

        $this->table(['Audience', 'Recipients'], [['Active users with email addresses', $recipientCount]]);

        $recipientLabel = $recipientCount === 1 ? 'user' : 'users';

        if (! $this->confirm("Queue the security update for {$recipientCount} {$recipientLabel}?")) {
            $this->warn('Send cancelled.');

            return self::SUCCESS;
        }

        $queued = 0;

        $query->chunkById(100, function ($users) use (&$queued): void {
            foreach ($users as $user) {
                $message = (new SecurityUpdateAnnouncement((string) $user->name))
                    ->onQueue('broadcasts')
                    ->delay(now()->addSeconds($queued * 4));

                Mail::to($user)->queue($message);
                $queued++;
            }
        });

        Cache::forever($guardKey, now()->toIso8601String());

        $this->info("Queued {$queued} individual security update emails.");

        return self::SUCCESS;
    }
}
