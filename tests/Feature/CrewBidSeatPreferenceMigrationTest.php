<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrewBidSeatPreferenceMigrationTest extends TestCase
{
    public function test_migration_preserves_each_users_latest_seat_and_defaults_new_users_to_first_officer(): void
    {
        $originalConnection = config('database.default');
        Config::set('database.connections.bid_seat_migration', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        Config::set('database.default', 'bid_seat_migration');

        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('bid_workspaces', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('seat');
                $table->timestamps();
            });
            DB::table('users')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
            DB::table('bid_workspaces')->insert([
                ['user_id' => 1, 'seat' => 'FO', 'updated_at' => '2026-10-26 00:00:00'],
                ['user_id' => 1, 'seat' => 'CA', 'updated_at' => '2026-10-27 00:00:00'],
                ['user_id' => 2, 'seat' => 'CA', 'updated_at' => '2026-10-27 00:00:00'],
                ['user_id' => 2, 'seat' => 'FO', 'updated_at' => '2026-10-27 00:00:00'],
            ]);
            $migration = require database_path('migrations/2026_10_28_032621_add_crew_bid_seat_preference_to_users_table.php');

            $migration->up();

            $this->assertSame(['CA', 'FO', 'FO'], DB::table('users')->orderBy('id')->pluck('crew_bid_seat_preference')->all());
            DB::table('users')->insert(['id' => 4]);
            $this->assertSame('FO', DB::table('users')->where('id', 4)->value('crew_bid_seat_preference'));

            $migration->down();

            $this->assertFalse(Schema::hasColumn('users', 'crew_bid_seat_preference'));
            $this->assertSame(4, DB::table('bid_workspaces')->count());
            $this->assertSame(['FO', 'CA', 'CA', 'FO'], DB::table('bid_workspaces')->orderBy('id')->pluck('seat')->all());
        } finally {
            DB::purge('bid_seat_migration');
            Config::set('database.default', $originalConnection);
        }
    }
}
