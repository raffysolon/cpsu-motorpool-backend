<?php

namespace App\Helpers;

class InputSanitizer
{
    /**
     * Sanitize a single string input by removing HTML tags and extra whitespace.
     * Safe for database storage and API responses.
     *
     * @param string|null $value
     * @return string|null
     */
    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Remove all HTML and PHP tags
        $value = strip_tags($value);

        // Convert multiple spaces/newlines to single space
        $value = preg_replace('/\s+/', ' ', $value);

        // Trim leading and trailing whitespace
        $value = trim($value);

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        return $value;
    }

    /**
     * Sanitize an array of inputs.
     * Recursively cleans all string values in the array.
     *
     * @param array $data
     * @return array
     */
    public static function cleanArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::clean($value);
            } elseif (is_array($value)) {
                $data[$key] = self::cleanArray($value);
            }
        }

        return $data;
    }

    /**
     * Sanitize specific fields in an array.
     * Only cleans the specified field names.
     *
     * @param array $data
     * @param array $fields
     * @return array
     */
    public static function cleanFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = self::clean($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Sanitize email address.
     * Removes potentially dangerous characters while preserving valid email format.
     *
     * @param string|null $email
     * @return string|null
     */
    public static function cleanEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $email;
        }

        // Remove whitespace and convert to lowercase
        $email = trim(strtolower($email));

        // Remove HTML tags
        $email = strip_tags($email);

        return $email;
    }
}
