<?php

namespace App\Validation;

use App\Enums\ScheduleEventType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class ExtractValidationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function rosterRules(string $eventTypesField = 'event_types'): array
    {
        return [
            'files' => [
                'nullable',
                'array',
                'max:5',
                'required_without:text',
                'prohibits:text',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $pdfCount = count(array_filter(
                        $value,
                        static fn (mixed $file): bool => $file instanceof UploadedFile
                            && $file->getMimeType() === 'application/pdf',
                    ));

                    if ($pdfCount > 0 && count($value) > 1) {
                        $fail('A PDF must be uploaded by itself.');
                    }
                },
            ],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp', 'max:12288'],
            'text' => [
                'nullable',
                'string',
                'required_without:files',
                'prohibits:files',
            ],
            $eventTypesField => ['nullable', 'array'],
            $eventTypesField.'.*' => [Rule::in(ScheduleEventType::filterValues())],
        ];
    }

    /** @return array<string, string> */
    public static function rosterMessages(string $eventTypesField = 'event_types'): array
    {
        return [
            'files.required_without' => 'Please provide either roster text or one or more uploaded files.',
            'files.max' => 'You may upload up to five images.',
            'files.prohibits' => 'Uploaded files cannot be combined with roster text.',
            'text.required_without' => 'Please provide either roster text or an uploaded file.',
            'text.prohibits' => 'Roster text cannot be combined with uploaded files.',
            $eventTypesField.'.*.in' => 'The selected event type is invalid.',
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function textRules(): array
    {
        return [
            'text' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public static function textMessages(): array
    {
        return [
            'text.required' => 'Please provide some text to parse.',
        ];
    }
}
