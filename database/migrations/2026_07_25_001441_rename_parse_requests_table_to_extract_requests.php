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
        Schema::rename('parse_requests', 'extract_requests');

        Schema::table('extract_requests', function (Blueprint $table) {
            $table->renameColumn('parse_duration_ms', 'extraction_duration_ms');
            $table->renameColumn('parser_version', 'extractor_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extract_requests', function (Blueprint $table) {
            $table->renameColumn('extraction_duration_ms', 'parse_duration_ms');
            $table->renameColumn('extractor_version', 'parser_version');
        });

        Schema::rename('extract_requests', 'parse_requests');
    }
};
