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
        // id
        // tailnnumber
        // flight_number
        // origin
        // destination
        // departure_time
        // arrival_time
        // aircraft_type
        // status
        Schema::create('flight_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->string('timezone')->nullable();
            $table->json('metadata')->nullable();
            $table->string('type_label')->nullable();
            $table->string('type_description')->nullable();
            $table->string('type_icon')->nullable();
            $table->string('schedule_label')->nullable();
            $table->string('duration_label')->nullable();
            $table->string('tail_number')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->boolean('is_deadhead')->default(false);
            $table->string('badge_color')->nullable();
            $table->string('download_url')->nullable();
            $table->string('download_id')->nullable();
            $table->string('trip_id')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('status')->nullable();
            
            $table->foreignId('aircraft_id')
                ->nullable()
                ->constrained('aircraft')
                ->nullOnDelete();
                
            $table->timestamps();

            // Indexes
            $table->index(['tail_number', 'start', 'end']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_events');
    }
};
