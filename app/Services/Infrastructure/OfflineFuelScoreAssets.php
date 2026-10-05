<?php

namespace App\Services\Infrastructure;

use Illuminate\Foundation\Vite as ViteRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;

class OfflineFuelScoreAssets
{
    public function tags(): HtmlString
    {
        $vite = clone app(ViteRenderer::class);

        if (File::exists(public_path('build/manifest.json'))) {
            $vite->useHotFile(storage_path('framework/offline-fuel-score.hot'));
        }

        return $vite(['resources/css/app.css', 'resources/js/offline-fuel-score.js']);
    }

    /** @return list<string> */
    public function handle(): array
    {
        $path = public_path('build/manifest.json');

        if (! File::exists($path)) {
            return [];
        }

        /** @var array<string, array{file: string, imports?: list<string>, css?: list<string>, assets?: list<string>}> $manifest */
        $manifest = File::json($path);
        $visited = [];
        $files = [];

        foreach (['resources/css/app.css', 'resources/js/offline-fuel-score.js'] as $entry) {
            $dependencies = $this->dependencies($manifest, $entry, $visited);

            if ($dependencies === null) {
                return [];
            }

            array_push($files, ...$dependencies);
        }

        return array_values(array_map(
            fn (string $file): string => asset('build/'.$file),
            array_unique($files),
        ));
    }

    /**
     * @param  array<string, array{file: string, imports?: list<string>, css?: list<string>, assets?: list<string>}>  $manifest
     * @param  array<string, bool>  $visited
     * @return list<string>|null
     */
    private function dependencies(array $manifest, string $key, array &$visited): ?array
    {
        if (isset($visited[$key])) {
            return [];
        }

        $visited[$key] = true;
        $chunk = $manifest[$key] ?? null;

        if ($chunk === null) {
            return null;
        }

        $files = [$chunk['file'], ...($chunk['css'] ?? []), ...($chunk['assets'] ?? [])];

        foreach ($chunk['imports'] ?? [] as $import) {
            $dependencies = $this->dependencies($manifest, $import, $visited);

            if ($dependencies === null) {
                return null;
            }

            array_push($files, ...$dependencies);
        }

        return $files;
    }
}
