<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'airline_iata_code' => $this->normalizedCode('airline_iata_code'),
            'airline_icao_code' => $this->normalizedCode('airline_icao_code'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'airline_iata_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z0-9]{2}$/'],
            'airline_icao_code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    private function normalizedCode(string $key): ?string
    {
        $value = strtoupper(trim((string) $this->input($key)));

        return $value === '' ? null : $value;
    }
}
