<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('crew_pay_settings')->nullable();
        });

        DB::table('crew_bid_pay_profiles')->orderBy('id')->chunkById(100, function (Collection $profiles): void {
            foreach ($profiles as $record) {
                DB::transaction(function () use ($record): void {
                    $profile = DB::table('crew_bid_pay_profiles')->where('id', $record->id)->lockForUpdate()->first();
                    $settings = json_decode(Crypt::decryptString($profile->settings), true, flags: JSON_THROW_ON_ERROR);
                    $scenarios = json_decode(Crypt::decryptString($profile->scenarios), true, flags: JSON_THROW_ON_ERROR);
                    $choices = [];
                    foreach ($scenarios as $key => &$scenario) {
                        if (array_key_exists('check_airman', $scenario)) {
                            $choices[$key] = (bool) $scenario['check_airman'];
                            unset($scenario['check_airman']);
                        }
                    }
                    unset($scenario);
                    $distinct = array_unique(array_values($choices), SORT_REGULAR);
                    $personal = ['longevity_date' => $settings['longevity_date'] ?? null,
                        'check_airman' => count($distinct) > 1 ? null : ($distinct === [] ? (bool) ($settings['check_airman'] ?? false) : (bool) reset($distinct))];
                    if (count($distinct) > 1) {
                        $personal['previous_check_airman_choices'] = $choices;
                    }
                    unset($settings['longevity_date'], $settings['check_airman']);
                    DB::table('users')->where('id', $profile->user_id)->whereNull('crew_pay_settings')
                        ->update(['crew_pay_settings' => Crypt::encryptString(json_encode($personal, JSON_THROW_ON_ERROR))]);
                    DB::table('crew_bid_pay_profiles')->where('id', $profile->id)->update([
                        'settings' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)),
                        'scenarios' => Crypt::encryptString(json_encode($scenarios, JSON_THROW_ON_ERROR)),
                        'revision' => $profile->revision + 1,
                    ]);
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->whereNotNull('crew_pay_settings')->orderBy('id')->chunkById(100, function (Collection $users): void {
            foreach ($users as $user) {
                DB::transaction(function () use ($user): void {
                    $personal = json_decode(Crypt::decryptString($user->crew_pay_settings), true, flags: JSON_THROW_ON_ERROR);
                    $profile = DB::table('crew_bid_pay_profiles')->where('user_id', $user->id)->lockForUpdate()->first();
                    $settings = $profile === null ? [] : json_decode(Crypt::decryptString($profile->settings), true, flags: JSON_THROW_ON_ERROR);
                    $scenarios = $profile === null ? [] : json_decode(Crypt::decryptString($profile->scenarios), true, flags: JSON_THROW_ON_ERROR);
                    $settings['longevity_date'] = $personal['longevity_date'] ?? null;
                    $settings['check_airman'] = $personal['check_airman'] ?? false;
                    foreach ($scenarios as $key => &$scenario) {
                        $scenario['check_airman'] = $personal['previous_check_airman_choices'][$key] ?? ($personal['check_airman'] ?? false);
                    }
                    unset($scenario);
                    DB::table('crew_bid_pay_profiles')->updateOrInsert(['user_id' => $user->id], [
                        'settings' => Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR)),
                        'scenarios' => Crypt::encryptString(json_encode($scenarios, JSON_THROW_ON_ERROR)),
                        'revision' => ($profile->revision ?? 0) + 1,
                        'created_at' => $profile->created_at ?? now(), 'updated_at' => now(),
                    ]);
                });
            }
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('crew_pay_settings');
        });
    }
};
