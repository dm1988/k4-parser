<?php

namespace Database\Seeders;

use App\Models\CrewBidSnapshot;
use Illuminate\Database\Seeder;

class CrewBidSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing') && ! CrewBidSnapshot::query()->exists()) {
            CrewBidSnapshot::factory()->create();
        }
    }
}
