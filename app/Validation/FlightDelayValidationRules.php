<?php

namespace App\Validation;

use App\Services\FlightPlan\FlightDelayFields;
use Illuminate\Support\Facades\Validator;

class FlightDelayValidationRules
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        $rules = ['inputs' => ['array:'.implode(',', array_keys(FlightDelayFields::labels()))]];

        foreach (FlightDelayFields::labels() as $field => $label) {
            $rules['inputs.'.$field] = isset(FlightDelayFields::timestamps()[$field])
                ? ['sometimes', 'nullable', 'string', 'date_format:Y-m-d\TH:i']
                : ['sometimes', 'nullable', 'string', 'max:'.($field === 'other_label' ? '160' : '10000')];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['date_format' => ':attribute must be a complete date and time in UTC.'];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        $attributes = [];

        foreach (FlightDelayFields::labels() as $field => $label) {
            $attributes['inputs.'.$field] = $label;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, string|null>
     */
    public static function validate(array $inputs): array
    {
        return Validator::make(['inputs' => $inputs], self::rules(), self::messages(), self::attributes())
            ->validate()['inputs'];
    }
}
