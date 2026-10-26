<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period', 7);
            $table->string('fleet', 3);
            $table->string('source_revision');
            $table->string('fingerprint', 64)->unique();
            $table->longText('payload');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['period', 'fleet', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_packages');
    }
};
