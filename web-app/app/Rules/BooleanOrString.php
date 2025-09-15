<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BooleanOrString implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Allow null values (handled by nullable rule)
        if ($value === null) {
            return;
        }

        // Allow actual boolean values
        if (is_bool($value)) {
            return;
        }

        // Allow string representations of booleans
        if (is_string($value)) {
            $validStrings = ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'];
            if (in_array(strtolower($value), $validStrings, true)) {
                return;
            }
        }

        // Allow numeric representations
        if (is_numeric($value) && in_array((string) $value, ['1', '0'], true)) {
            return;
        }

        $fail('The :attribute field must be a valid boolean value.');
    }
}
