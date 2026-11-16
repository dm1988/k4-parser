<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_bid_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('period', 7);
            $table->string('fleet', 3);
            $table->string('seat', 2);
            $table->unsignedBigInteger('portal_package_id');
            $table->unsignedInteger('portal_revision');
            $table->timestamp('retrieved_at');
            $table->longText('bidders');
            $table->timestamps();
            $table->unique(['period', 'fleet', 'seat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_bid_snapshots');
    }
};
