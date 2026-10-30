<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ValidateTemporaryUpload
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->hasValidRelativeSignature(), 401);

        $files = $request->files->get('files');
        $invalidCollection = Validator::make(['files' => $files], [
            'files' => ['required', 'array', 'list'],
        ])->fails();

        if ($invalidCollection) {
            $message = 'The upload could not be completed. Please select the file and try again.';
            $response = response()->json([
                'message' => $message,
                'errors' => ['files.0' => [$message]],
            ], 422);
        } else {
            $response = $next($request);
        }

        $requestId = (string) Str::uuid();
        $response->headers->set('X-Upload-Request-Id', $requestId);

        if ($invalidCollection || config('livewire.upload_diagnostics')) {
            $paths = $response instanceof JsonResponse ? ($response->getData(true)['paths'] ?? null) : null;

            Log::log($invalidCollection ? 'warning' : 'info', 'Temporary upload request.', [
                'request_id' => $requestId,
                'status' => $response->getStatusCode(),
                'invalid_file_collection' => $invalidCollection,
                'file_count' => is_array($files) ? count($files) : 0,
                'returned_path_count' => is_array($paths) ? count($paths) : 0,
                'reference_hash' => is_array($paths) && count($paths) === 1 && is_string($paths[0] ?? null)
                    ? hash_hmac('sha256', $paths[0], (string) config('app.key')) : null,
                'content_length' => $request->server->getInt('CONTENT_LENGTH'),
                'is_multipart' => str_starts_with((string) $request->header('Content-Type'), 'multipart/form-data'),
                'php_upload_errors' => array_map(
                    static fn (mixed $file): ?int => $file instanceof UploadedFile ? $file->getError() : null,
                    array_slice(is_array($files) ? array_values($files) : [], 0, 20),
                ),
                'php_sapi' => PHP_SAPI,
                'file_uploads' => ini_get('file_uploads'),
                'enable_post_data_reading' => ini_get('enable_post_data_reading'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'max_multipart_body_parts' => ini_get('max_multipart_body_parts'),
            ]);
        }

        return $response;
    }
}
