<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submitted_bid_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bid_package_id')->constrained()->cascadeOnDelete();
            $table->string('seat', 2);
            $table->text('evidence');
            $table->timestamp('started_at', 6);
            $table->timestamp('checked_at', 6);
            $table->timestamps();
            $table->index(['user_id', 'bid_package_id', 'seat', 'started_at'], 'submitted_bid_snapshot_context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submitted_bid_snapshots');
    }
};
