<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('crew_bid_seat_preference', 2)->default('FO');
        });

        DB::table('users')->select('id')->addSelect([
            'last_bid_seat' => DB::table('bid_workspaces')->select('seat')
                ->whereColumn('user_id', 'users.id')
                ->orderByDesc('updated_at')->orderByDesc('id')->limit(1),
        ])->chunkById(200, function (Collection $users): void {
            foreach ($users as $user) {
                if (in_array($user->last_bid_seat, ['FO', 'CA'], true)) {
                    DB::table('users')->where('id', $user->id)->update(['crew_bid_seat_preference' => $user->last_bid_seat]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('crew_bid_seat_preference');
        });
    }
};
