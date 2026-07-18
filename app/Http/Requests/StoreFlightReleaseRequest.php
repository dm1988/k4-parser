<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlightReleaseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'flight_release' => ['required', 'file', 'mimes:pdf', 'max:12288'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'flight_release.required' => 'Upload a flight release PDF to extract the route.',
            'flight_release.file' => 'The upload could not be processed as a file.',
            'flight_release.mimes' => 'Only PDF flight release uploads are supported.',
            'flight_release.mimetypes' => 'The uploaded file was not recognized as a PDF.',
            'flight_release.max' => 'The PDF is too large. The maximum allowed size is 12 MB.',
        ];
    }
}
