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
        Schema::table('extract_requests', function (Blueprint $table) {
            $table->unsignedInteger('uploaded_file_count')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extract_requests', function (Blueprint $table) {
            $table->dropColumn('uploaded_file_count');
        });
    }
};
