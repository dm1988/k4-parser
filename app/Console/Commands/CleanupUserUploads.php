<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:cleanup-user-uploads')]
#[Description('Deletes user-uploaded files older than 5 days')]
class CleanupUserUploads extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expirationDate = now()->subDays(5);

        foreach (['user_uploads', 'user_flight_releases'] as $diskName) {
            $disk = Storage::disk($diskName);

            foreach ($disk->allFiles() as $file) {
                $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));

                if ($lastModified->lt($expirationDate)) {
                    $disk->delete($file);
                    $this->info("Deleted [{$diskName}]: {$file}");
                }
            }
        }

        return Command::SUCCESS;
    }
}
