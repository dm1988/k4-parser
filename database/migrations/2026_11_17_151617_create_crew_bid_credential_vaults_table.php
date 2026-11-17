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
        Schema::create('crew_bid_credential_vaults', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('envelope');
            $table->string('account_hint', 24);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('crew_bid_credential_epoch', 32)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crew_bid_credential_vaults');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('crew_bid_credential_epoch');
        });
    }
};
