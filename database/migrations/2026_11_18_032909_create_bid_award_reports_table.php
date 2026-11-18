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
        Schema::create('bid_award_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_package_id')->constrained('bid_packages')->cascadeOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period', 7);
            $table->string('fleet', 3);
            $table->string('fingerprint', 64);
            $table->string('source', 20);
            $table->longText('awards');
            $table->timestamps();
            $table->unique(['bid_package_id', 'fingerprint']);
            $table->index(['period', 'fleet']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_award_reports');
    }
};
