<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasLegacyIndex = Schema::hasIndex('flight_events', ['tail_number', 'start', 'end']);
        $missingAircraftWindowIndex = ! Schema::hasIndex('flight_events', ['aircraft_id', 'start', 'end']);
        $missingWindowIndex = ! Schema::hasIndex('flight_events', ['start', 'end']);
        $missingTripIndex = ! Schema::hasIndex('flight_events', ['trip_id']);
        $missingFlightNumberIndex = ! Schema::hasIndex('flight_events', ['flight_number']);

        Schema::table('flight_events', function (Blueprint $table) use (
            $hasLegacyIndex,
            $missingAircraftWindowIndex,
            $missingWindowIndex,
            $missingTripIndex,
            $missingFlightNumberIndex,
        ) {
            if ($hasLegacyIndex) {
                $table->dropIndex(['tail_number', 'start', 'end']);
            }

            if ($missingAircraftWindowIndex) {
                $table->index(['aircraft_id', 'start', 'end']);
            }

            if ($missingWindowIndex) {
                $table->index(['start', 'end']);
            }

            if ($missingTripIndex) {
                $table->index('trip_id');
            }

            if ($missingFlightNumberIndex) {
                $table->index('flight_number');
            }
        });
    }

    public function down(): void
    {
        $hasAircraftWindowIndex = Schema::hasIndex('flight_events', ['aircraft_id', 'start', 'end']);
        $hasWindowIndex = Schema::hasIndex('flight_events', ['start', 'end']);
        $hasTripIndex = Schema::hasIndex('flight_events', ['trip_id']);
        $hasFlightNumberIndex = Schema::hasIndex('flight_events', ['flight_number']);
        $missingLegacyIndex = ! Schema::hasIndex('flight_events', ['tail_number', 'start', 'end']);

        Schema::table('flight_events', function (Blueprint $table) use (
            $hasAircraftWindowIndex,
            $hasWindowIndex,
            $hasTripIndex,
            $hasFlightNumberIndex,
            $missingLegacyIndex,
        ) {
            if ($hasAircraftWindowIndex) {
                $table->dropIndex(['aircraft_id', 'start', 'end']);
            }

            if ($hasWindowIndex) {
                $table->dropIndex(['start', 'end']);
            }

            if ($hasTripIndex) {
                $table->dropIndex(['trip_id']);
            }

            if ($hasFlightNumberIndex) {
                $table->dropIndex(['flight_number']);
            }

            if ($missingLegacyIndex) {
                $table->index(['tail_number', 'start', 'end']);
            }
        });
    }
};
