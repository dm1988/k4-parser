<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UploadedFileCountMigrationTest extends TestCase
{
    public function test_migration_preserves_legacy_rows_with_unknown_counts_and_can_be_reversed(): void
    {
        $originalConnection = config('database.default');
        Config::set('database.connections.upload_count_migration', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('database.default', 'upload_count_migration');

        try {
            Schema::create('extract_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('source_type');
            });
            DB::table('extract_requests')->insert(['source_type' => 'image']);
            $migration = require database_path('migrations/2026_10_04_175737_add_uploaded_file_count_to_extract_requests_table.php');

            $migration->up();

            $this->assertNull(DB::table('extract_requests')->sole()->uploaded_file_count);
            DB::table('extract_requests')->insert([
                'source_type' => 'pasted_text',
                'uploaded_file_count' => 0,
            ]);
            $this->assertSame(0, DB::table('extract_requests')->where('source_type', 'pasted_text')->sole()->uploaded_file_count);

            $migration->down();

            $this->assertFalse(Schema::hasColumn('extract_requests', 'uploaded_file_count'));
            $this->assertSame(2, DB::table('extract_requests')->count());
            $this->assertSame('image', DB::table('extract_requests')->orderBy('id')->first()->source_type);
        } finally {
            DB::purge('upload_count_migration');
            Config::set('database.default', $originalConnection);
        }
    }
}
