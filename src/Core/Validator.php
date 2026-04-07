<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function make(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && trim((string) $value) === '') {
                    $errors[$field][] = 'This field is required.';
                }

                if ($rule === 'email' && trim((string) $value) !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'Please enter a valid email address.';
                }

                if ($rule === 'numeric' && trim((string) $value) !== '' && !is_numeric($value)) {
                    $errors[$field][] = 'Please enter a numeric value.';
                }

                if (str_starts_with($rule, 'min:') && trim((string) $value) !== '') {
                    $minimum = (float) substr($rule, 4);
                    if ((float) $value < $minimum) {
                        $errors[$field][] = 'The value must be at least ' . $minimum . '.';
                    }
                }

                if (str_starts_with($rule, 'max:') && trim((string) $value) !== '') {
                    $maximum = (int) substr($rule, 4);
                    if (mb_strlen((string) $value) > $maximum) {
                        $errors[$field][] = 'The text may not be longer than ' . $maximum . ' characters.';
                    }
                }
            }
        }

        return $errors;
    }
}
