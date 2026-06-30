<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_events', function (Blueprint $table) {
            // Drop old index
            $table->dropIndex(['tail_number', 'start', 'end']);

            // Add new indexes
            $table->index(['aircraft_id', 'start', 'end']);
            $table->index(['start', 'end']);
            $table->index('trip_id');
            $table->index('flight_number');
        });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        Schema::table('flight_events', function (Blueprint $table) {
            // Remove new indexes
            $table->dropIndex(['aircraft_id', 'start', 'end']);
            $table->dropIndex(['start', 'end']);
            $table->dropIndex(['trip_id']);
            $table->dropIndex(['flight_number']);

            // Restore original index
            $table->index(['tail_number', 'start', 'end']);
        });
    }
};
