<?php

namespace App\Http\Requests;

use App\Validation\ParserValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ParseRosterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ParserValidationRules::rosterRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ParserValidationRules::rosterMessages();
    }
}
