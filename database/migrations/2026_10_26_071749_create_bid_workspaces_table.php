<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bid_package_id')->constrained()->cascadeOnDelete();
            $table->string('seat', 2);
            $table->text('preferences');
            $table->text('selections');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'bid_package_id', 'seat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_workspaces');
    }
};
