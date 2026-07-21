<?php

namespace App\Validation;

use App\Enums\ParserEventType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class ParserValidationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function rosterRules(string $eventTypesField = 'event_types'): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp', 'max:12288', 'required_without:text'],
            'text' => ['nullable', 'string', 'required_without:file'],
            $eventTypesField => ['nullable', 'array'],
            $eventTypesField.'.*' => [Rule::in(ParserEventType::filterValues())],
        ];
    }

    /** @return array<string, string> */
    public static function rosterMessages(string $eventTypesField = 'event_types'): array
    {
        return [
            'file.required_without' => 'Please provide either roster text or an uploaded file.',
            'text.required_without' => 'Please provide either roster text or an uploaded file.',
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
