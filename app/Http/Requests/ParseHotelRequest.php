<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParseHotelRequest extends FormRequest
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
            'text' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Please provide some text to parse.',
        ];
    }
}
