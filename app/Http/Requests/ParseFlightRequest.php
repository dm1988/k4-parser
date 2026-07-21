<?php

namespace App\Http\Requests;

use App\Validation\ParserValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ParseFlightRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ParserValidationRules::textRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ParserValidationRules::textMessages();
    }
}
