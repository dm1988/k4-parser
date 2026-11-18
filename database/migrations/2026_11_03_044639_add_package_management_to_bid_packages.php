<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bid_packages', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
        });
        Schema::table('bid_workspaces', function (Blueprint $table): void {
            $table->dropForeign(['bid_package_id']);
            $table->foreign('bid_package_id')->references('id')->on('bid_packages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bid_workspaces', function (Blueprint $table): void {
            $table->dropForeign(['bid_package_id']);
            $table->foreign('bid_package_id')->references('id')->on('bid_packages')->cascadeOnDelete();
        });
        Schema::table('bid_packages', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
