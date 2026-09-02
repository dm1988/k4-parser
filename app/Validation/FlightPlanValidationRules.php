<?php

namespace App\Validation;

class FlightPlanValidationRules
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'flightRelease' => ['required', 'file', 'mimes:pdf', 'max:12288'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'flightRelease.required' => 'Upload a flight release PDF to extract the route.',
            'flightRelease.file' => 'The upload could not be processed as a file.',
            'flightRelease.mimes' => 'Only PDF flight release uploads are supported.',
            'flightRelease.mimetypes' => 'The uploaded file was not recognized as a PDF.',
            'flightRelease.max' => 'The PDF is too large. The maximum allowed size is 12 MB.',
        ];
    }
}
