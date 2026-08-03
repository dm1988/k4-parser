<?php

namespace App\Console\Commands;

use App\Mail\VersionOneAnnouncement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendVersionOneAnnouncement extends Command
{
    protected $signature = 'mail:version-one
        {--email= : Send one preview to a specific email address}
        {--all : Queue the announcement for all eligible users}
        {--force : Permit another full send after the announcement was already queued}';

    protected $description = 'Preview or send the K4 Parser Version 1 announcement';

    public function handle(): int
    {
        $email = $this->option('email');
        $sendToAll = (bool) $this->option('all');

        if ($email && $sendToAll) {
            $this->error('Choose either --email for a preview or --all for the full send.');

            return self::FAILURE;
        }

        if ($email) {
            return $this->sendPreview((string) $email);
        }

        if (! $sendToAll) {
            $this->error('Specify --email=address@example.com or --all.');

            return self::FAILURE;
        }

        return $this->sendToAllUsers();
    }

    private function sendPreview(string $email): int
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('The preview email address is invalid.');

            return self::FAILURE;
        }

        $previewUser = new User([
            'name' => 'Dave',
            'email' => $email,
        ]);

        Mail::to($email)->send(new VersionOneAnnouncement($previewUser));

        $this->info("Preview sent to {$email}.");

        return self::SUCCESS;
    }

    private function sendToAllUsers(): int
    {
        $guardKey = 'mailings:version-one-announcement:queued';

        if (Cache::has($guardKey) && ! $this->option('force')) {
            $queuedAt = Cache::get($guardKey);

            $this->error("The Version 1 announcement was already queued at {$queuedAt}.");
            $this->line('Use --force only if you intentionally want to send it again.');

            return self::FAILURE;
        }

        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email');

        $recipientCount = $query->count();

        if ($recipientCount === 0) {
            $this->warn('No eligible users were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Audience', 'Recipients'],
            [['Active users with verified email addresses', $recipientCount]],
        );

        if (! $this->confirm("Queue the announcement for {$recipientCount} users?")) {
            $this->warn('Send cancelled.');

            return self::SUCCESS;
        }

        $queued = 0;

        $query->chunkById(100, function ($users) use (&$queued): void {
            foreach ($users as $user) {
                Mail::to($user)->queue(new VersionOneAnnouncement($user));
                $queued++;
            }
        });

        Cache::forever($guardKey, now()->toIso8601String());

        $this->info("Queued {$queued} individual announcement emails.");

        return self::SUCCESS;
    }
}
