<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper for Input Validation and Sanitization.
 *
 * Provides central static methods for verifying user-provided data and 
 * cleaning strings to prevent common injection and formatting issues.
 *
 * @package App\Helpers
 */
final class Validator
{
    /**
     * Checks if all required fields are present and not empty in a payload.
     *
     * @param array $payload The data to check.
     * @param array<int, string> $fields List of field names.
     * @return string|null Error message describing the first missing field, or null if all pass.
     */
    public static function requireFields(array $payload, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
                return sprintf('"%s" is required', $field);
            }
        }

        return null;
    }

    /**
     * Cleans a single-line string by stripping series_tags and trimming whitespace.
     */
    public static function sanitizeText(string $text): string
    {
        return trim(strip_tags($text));
    }

    /**
     * Cleans a multi-line string and normalizes line endings to \n.
     */
    public static function sanitizeMultilineText(string $text): string
    {
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return trim($text);
    }

    /**
     * Validates an email address format.
     */
    public static function validEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates a username (3-30 chars, alphanumeric/underscore).
     */
    public static function validUsername(string $username): bool
    {
        return preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username) === 1;
    }

    /**
     * Enforces password complexity rules.
     *
     * Rules: 8-128 chars, at least one lowercase, one uppercase, and one digit.
     */
    public static function validPassword(string $password): bool
    {
        if (strlen($password) < 8 || strlen($password) > 128) {
            return false;
        }

        $hasLower = preg_match('/[a-z]/', $password) === 1;
        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasDigit = preg_match('/[0-9]/', $password) === 1;

        return $hasLower && $hasUpper && $hasDigit;
    }
}
