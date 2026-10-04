<?php

namespace App\ValueObjects;

final readonly class StringList
{
    /** @var list<string> */
    public array $values;

    public function __construct(mixed $values)
    {
        $normalized = [];

        foreach (is_array($values) ? $values : [] as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        $this->values = $normalized;
    }
}
