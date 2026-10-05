<?php

namespace Tests\Feature;

use App\Http\Middleware\ManageOfflineFuelScore;
use App\Models\User;
use App\Services\Infrastructure\FlightPlanResultStore;
use App\Services\Infrastructure\OfflineFuelScoreAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OfflineFuelScoreCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_and_guest_pages_signal_removal_of_private_offline_copies(): void
    {
        $this->get('/privacy-policy')->assertOk()->assertHeader('X-Offline-Fuel-Owner', '');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/privacy-policy')
            ->assertOk()->assertHeader('X-Offline-Fuel-Owner', (string) $user->getKey());

        $this->post('/logout')->assertRedirect('/')->assertHeader('X-Offline-Fuel-Owner', '');
    }

    public function test_livewire_responses_report_the_release_key_after_replacement_or_clearing(): void
    {
        $owner = User::factory()->admin()->create();
        $store = app(FlightPlanResultStore::class);
        $oldKey = $store->save($owner, ['flight_plan_data' => []]);
        $request = Request::create('/livewire/update', 'POST');
        $request->setUserResolver(fn (): User => $owner);
        $middleware = app(ManageOfflineFuelScore::class);
        $newKey = '';

        $response = $middleware->handle($request, function () use ($store, $owner, &$newKey): Response {
            $newKey = $store->save($owner, ['flight_plan_data' => []]);

            return response()->json([]);
        });

        $this->assertNotSame($oldKey, $newKey);
        $this->assertSame($newKey, $response->headers->get('X-Offline-Fuel-Key'));
        $this->assertSame((string) $owner->getKey(), $response->headers->get('X-Offline-Fuel-Owner'));

        $response = $middleware->handle($request, function () use ($store, $owner, $newKey): Response {
            $store->delete($owner, $newKey);

            return response()->json([]);
        });

        $this->assertSame('', $response->headers->get('X-Offline-Fuel-Key'));
    }

    public function test_account_changes_are_reported_without_exposing_release_payloads(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->get('/privacy-policy')
            ->assertHeader('X-Offline-Fuel-Owner', (string) $first->getKey());
        $this->actingAs($second)->get('/privacy-policy')
            ->assertHeader('X-Offline-Fuel-Owner', (string) $second->getKey())
            ->assertHeaderMissing('X-Offline-Fuel-Key');
    }

    public function test_asset_list_includes_transitive_imports_styles_and_fonts_once(): void
    {
        File::shouldReceive('exists')->with(public_path('build/manifest.json'))->once()->andReturn(true);
        File::shouldReceive('json')->with(public_path('build/manifest.json'))->once()->andReturn([
            'resources/css/app.css' => ['file' => 'assets/app.css'],
            'resources/js/offline-fuel-score.js' => ['file' => 'assets/calculator.js', 'imports' => ['shared']],
            'shared' => ['file' => 'assets/shared.js', 'imports' => ['nested'], 'css' => ['assets/app.css']],
            'nested' => ['file' => 'assets/nested.js', 'imports' => ['shared'], 'assets' => ['assets/font.woff2']],
        ]);

        $this->assertEqualsCanonicalizing([
            asset('build/assets/app.css'), asset('build/assets/calculator.js'),
            asset('build/assets/shared.js'), asset('build/assets/nested.js'), asset('build/assets/font.woff2'),
        ], app(OfflineFuelScoreAssets::class)->handle());
    }

    public function test_calculator_uses_built_assets_while_other_pages_keep_using_the_development_server(): void
    {
        $publicPath = storage_path('framework/testing/offline-fuel-assets-'.Str::uuid());
        File::ensureDirectoryExists($publicPath.'/build');
        File::put($publicPath.'/hot', 'http://localhost:5173');
        File::put($publicPath.'/build/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/app-test.css', 'src' => 'resources/css/app.css'],
            'resources/js/offline-fuel-score.js' => ['file' => 'assets/calculator-test.js', 'src' => 'resources/js/offline-fuel-score.js', 'imports' => ['shared']],
            'shared' => ['file' => 'assets/shared-test.js'],
        ], JSON_THROW_ON_ERROR));
        $this->app->usePublicPath($publicPath);
        $vite = new Vite;
        $this->app->instance(Vite::class, $vite);

        try {
            $owner = User::factory()->admin()->create();
            $key = app(FlightPlanResultStore::class)->save($owner, ['flight_plan_data' => [
                'identity' => ['flightNumber' => 'CKS241'],
                'schedule' => [],
                'route' => ['departure' => 'PANC', 'destination' => 'KMIA'],
                'waypoints' => [],
            ]]);
            $assets = [
                asset('build/assets/app-test.css'), asset('build/assets/calculator-test.js'), asset('build/assets/shared-test.js'),
            ];

            $this->actingAs($owner)->get(route('flight-release.fuel-score', ['flightPlanKey' => $key]))
                ->assertOk()
                ->assertSeeHtml('href="'.asset('build/assets/app-test.css').'"')
                ->assertSeeHtml('src="'.asset('build/assets/calculator-test.js').'"')
                ->assertDontSee('localhost:5173', escape: false)
                ->assertHeader('X-Offline-Fuel-Assets', json_encode($assets, JSON_THROW_ON_ERROR))
                ->assertViewHas('offlineRecovery', fn (array $config): bool => $config['assets'] === $assets);

            $this->assertTrue($vite->isRunningHot());
            $this->assertStringContainsString('localhost:5173', (string) $vite(['resources/js/app.js']));
            $this->assertSame('http://localhost:5173', File::get($publicPath.'/hot'));
        } finally {
            File::deleteDirectory($publicPath);
        }
    }

    public function test_missing_build_keeps_the_development_calculator_usable_without_offline_assets(): void
    {
        $publicPath = storage_path('framework/testing/offline-fuel-assets-'.Str::uuid());
        File::ensureDirectoryExists($publicPath);
        File::put($publicPath.'/hot', 'http://localhost:5173');
        $this->app->usePublicPath($publicPath);
        $this->app->instance(Vite::class, new Vite);

        try {
            $resolver = app(OfflineFuelScoreAssets::class);
            $this->assertSame([], $resolver->handle());
            $this->assertStringContainsString('localhost:5173', (string) $resolver->tags());
        } finally {
            File::deleteDirectory($publicPath);
        }
    }

    public function test_missing_manifest_or_import_leaves_offline_recovery_unavailable(): void
    {
        File::shouldReceive('exists')->with(public_path('build/manifest.json'))->twice()->andReturn(false, true);
        File::shouldReceive('json')->with(public_path('build/manifest.json'))->once()->andReturn([
            'resources/js/offline-fuel-score.js' => ['file' => 'assets/calculator.js', 'imports' => ['missing']],
        ]);

        $resolver = app(OfflineFuelScoreAssets::class);
        $this->assertSame([], $resolver->handle());
        $this->assertSame([], $resolver->handle());
    }
}
