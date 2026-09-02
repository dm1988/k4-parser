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
        Schema::table('aircraft', function (Blueprint $table): void {
            $table->unsignedInteger('max_ramp_weight')->nullable();
            $table->unsignedInteger('max_zero_fuel_weight')->nullable();
            $table->unsignedInteger('max_takeoff_weight')->nullable();
            $table->unsignedInteger('max_landing_weight')->nullable();
            $table->unsignedInteger('max_autoland_weight')->nullable();
            $table->unsignedInteger('minimum_flight_weight')->nullable();
            $table->string('engines')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aircraft', function (Blueprint $table): void {
            $table->dropColumn([
                'max_ramp_weight',
                'max_zero_fuel_weight',
                'max_takeoff_weight',
                'max_landing_weight',
                'max_autoland_weight',
                'minimum_flight_weight',
                'engines',
            ]);
        });
    }
};
