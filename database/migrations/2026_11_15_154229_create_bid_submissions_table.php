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
        Schema::create('bid_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bid_workspace_id')->constrained()->cascadeOnDelete();
            $table->text('review');
            $table->string('status', 24)->default('reviewed');
            $table->timestamp('expires_at');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->index(['user_id', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_submissions');
    }
};
