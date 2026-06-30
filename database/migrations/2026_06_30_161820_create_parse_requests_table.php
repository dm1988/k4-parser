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
        Schema::create('parse_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->uuid('request_uuid')->unique();
            $table->string('source_type');
            $table->string('parser_type');
            $table->string('status');
            $table->string('error_code')->nullable();
            $table->unsignedInteger('parse_duration_ms');
            $table->char('file_hash', 64)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('detected_event_count')->default(0);
            $table->unsignedInteger('detected_flight_count')->default(0);
            $table->unsignedInteger('detected_hotel_count')->default(0);
            $table->string('app_version')->nullable();
            $table->string('parser_version')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('mime_type')->nullable();
            $table->string('storage_path')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parse_requests');
    }
};
