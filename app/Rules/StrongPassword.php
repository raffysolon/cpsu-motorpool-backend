<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     * 
     * Password must contain:
     * - At least 8 characters
     * - At least one uppercase letter (A-Z)
     * - At least one lowercase letter (a-z)
     * - At least one number (0-9)
     * - At least one special character (!@#$%^&*(),.?":{}|<>)
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        if (strlen($value) < 8) {
            $fail('The :attribute must be at least 8 characters long.');
            return;
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter (A-Z).');
            return;
        }

        if (!preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter (a-z).');
            return;
        }

        if (!preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number (0-9).');
            return;
        }

        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            $fail('The :attribute must contain at least one special character (!@#$%^&*(),.?":{}|<>).');
            return;
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', 'password123', '12345678', 'qwerty123', 'admin123', 
            'letmein123', 'welcome123', 'monkey123', 'dragon123', 'master123'
        ];

        if (in_array(strtolower($value), $weakPasswords)) {
            $fail('The :attribute is too common. Please choose a stronger password.');
            return;
        }
    }
}
