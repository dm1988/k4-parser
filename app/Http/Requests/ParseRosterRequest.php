<?php

namespace App\Http\Requests;

use App\Enums\ParserEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParseRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('features.schedule_parser.enabled', true)) {
            abort(404);
        }

        return (bool) $this->user()?->canUseScheduleParser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,bmp,tif,tiff,webp', 'max:12288', 'required_without:text'],
            'text' => ['nullable', 'string', 'required_without:file'],
            'event_types' => ['nullable', 'array'],
            'event_types.*' => [Rule::in(ParserEventType::filterValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Please provide either roster text or an uploaded file.',
            'text.required_without' => 'Please provide either roster text or an uploaded file.',
            'event_types.*.in' => 'The selected event type is invalid.',
        ];
    }
}
