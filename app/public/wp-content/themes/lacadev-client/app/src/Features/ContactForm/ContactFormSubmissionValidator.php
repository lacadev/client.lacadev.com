<?php

namespace App\Features\ContactForm;

/**
 * Validates and sanitizes frontend contact-form submissions.
 */
final class ContactFormSubmissionValidator
{
    public static function validate(array $fields, array $input): array
    {
        $data = [];
        $errors = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'step_break') {
                continue;
            }

            if (!ContactFormSchema::isFieldConditionMatched($field, $input)) {
                continue;
            }

            $name = $field['name'];
            $label = $field['label'];
            $required = !empty($field['required']);
            $type = $field['type'];
            $rawValue = $input[$name] ?? '';

            if (in_array($type, ['multiselect', 'checkbox'], true)) {
                $rawValue = is_array($rawValue) ? $rawValue : [];
            }

            if ($required) {
                $isEmpty = is_array($rawValue) ? empty($rawValue) : (trim((string) $rawValue) === '');
                if ($isEmpty) {
                    $errors[] = $label . ' là bắt buộc.';
                    continue;
                }
            }

            $cleanValue = ContactFormSchema::sanitizeByType($type, $rawValue, $field);
            $formatError = is_array($cleanValue) ? '' : ContactFormSchema::validateFormat($type, $cleanValue, $label);
            if ($formatError) {
                $errors[] = $formatError;
                continue;
            }

            $data[$name] = $cleanValue;
        }

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }
}
