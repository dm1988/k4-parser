<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flight_events', function (Blueprint $table) {
            $table->index('start');
            $table->index('aircraft_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_events', function (Blueprint $table) {
            $table->dropIndex(['start']);
            $table->dropIndex(['aircraft_id']);
        });
    }
};
