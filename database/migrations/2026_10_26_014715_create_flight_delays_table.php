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
        Schema::create('flight_delays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('flight_plan_key');
            $table->longText('flight_context');
            $table->unsignedInteger('revision')->default(0);
            $table->dateTime('logbook_received_at')->nullable();
            $table->dateTime('flight_following_brief_at')->nullable();
            $table->dateTime('fueling_completed_at')->nullable();
            $table->dateTime('loading_completed_at')->nullable();
            $table->dateTime('load_master_briefing_at')->nullable();
            $table->dateTime('new_payload_received_at')->nullable();
            $table->dateTime('new_recall_flight_plan_at')->nullable();
            $table->dateTime('new_tlr_at')->nullable();
            $table->dateTime('other_at')->nullable();
            $table->dateTime('delay_started_at')->nullable();
            $table->dateTime('delay_ended_at')->nullable();
            $table->longText('other_label')->nullable();
            $table->longText('other_details')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('delay_cause')->nullable();
            $table->longText('delay_timeline')->nullable();
            $table->longText('actions_taken')->nullable();
            $table->longText('additional_facts')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'flight_plan_key']);
            $table->index(['user_id', 'updated_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_delays');
    }
};
