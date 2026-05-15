<?php

namespace App\Features\ContactForm;

/**
 * Sanitizes Contact Form admin-builder payloads before persistence.
 */
final class ContactFormAdminSanitizer
{
    private const CONDITION_OPERATORS = ['equals', 'not_equals', 'contains', 'not_empty', 'empty'];

    public static function rows(array $rawData, array $fieldTypes, array $allowedSpans): array
    {
        $cleanRows = [];

        foreach ($rawData as $row) {
            if (!isset($row['cols'])) {
                continue;
            }

            $cleanCols = [];
            foreach ($row['cols'] as $col) {
                $cleanFields = [];

                foreach ($col['fields'] ?? [] as $field) {
                    $fieldType = in_array($field['type'] ?? '', array_keys($fieldTypes), true) ? $field['type'] : 'text';

                    if ($fieldType !== 'step_break' && (empty($field['name']) || empty($field['label']))) {
                        continue;
                    }

                    if ($fieldType === 'step_break' && empty($field['label'])) {
                        $field['label'] = 'Bước tiếp theo';
                    }

                    $cleanFields[] = [
                        'id' => sanitize_key($field['id'] ?? uniqid('field_', true)),
                        'type' => $fieldType,
                        'name' => $fieldType === 'step_break' ? '' : sanitize_key($field['name']),
                        'label' => sanitize_text_field($field['label']),
                        'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                        'required' => $fieldType !== 'step_break' && !empty($field['required']),
                        'options' => $fieldType === 'step_break' ? [] : array_map('sanitize_text_field', (array) ($field['options'] ?? [])),
                        'condition' => $fieldType === 'step_break' ? [] : self::fieldCondition($field['condition'] ?? []),
                    ];
                }

                $span = (int) ($col['span'] ?? 12);
                $cleanCols[] = [
                    'id' => sanitize_key($col['id'] ?? uniqid('col_', true)),
                    'span' => in_array($span, $allowedSpans, true) ? $span : 12,
                    'fields' => $cleanFields,
                ];
            }

            $cleanRows[] = [
                'id' => sanitize_key($row['id'] ?? uniqid('row_', true)),
                'cols' => $cleanCols,
            ];
        }

        return $cleanRows;
    }

    public static function style(array $rawStyle): array
    {
        $cleanStyle = [];

        foreach (['primary_color', 'secondary_color', 'input_border_color', 'label_color'] as $colorKey) {
            if (!empty($rawStyle[$colorKey])) {
                $hex = sanitize_hex_color($rawStyle[$colorKey]);
                if ($hex) {
                    $cleanStyle[$colorKey] = $hex;
                }
            }
        }

        foreach (['btn_border_radius', 'input_border_radius'] as $numKey) {
            if (isset($rawStyle[$numKey])) {
                $cleanStyle[$numKey] = max(0, min(50, (int) $rawStyle[$numKey]));
            }
        }

        if (!empty($rawStyle['btn_text'])) {
            $cleanStyle['btn_text'] = sanitize_text_field($rawStyle['btn_text']);
        }

        if (!empty($rawStyle['form_mode']) && in_array($rawStyle['form_mode'], ['standard', 'multi_step'], true)) {
            $cleanStyle['form_mode'] = sanitize_key($rawStyle['form_mode']);
        }

        foreach (['step_next_text', 'step_prev_text', 'step_submit_text'] as $textKey) {
            if (!empty($rawStyle[$textKey])) {
                $cleanStyle[$textKey] = sanitize_text_field($rawStyle[$textKey]);
            }
        }

        if (!empty($rawStyle['input_spacing'])) {
            $cleanStyle['input_spacing'] = sanitize_text_field($rawStyle['input_spacing']);
        }

        if (isset($rawStyle['hide_labels'])) {
            $cleanStyle['hide_labels'] = (bool) $rawStyle['hide_labels'];
        }

        if (!empty($rawStyle['custom_css'])) {
            $cleanStyle['custom_css'] = wp_strip_all_tags(stripslashes($rawStyle['custom_css']));
        }

        return $cleanStyle;
    }

    public static function fieldCondition(array $condition): array
    {
        $field = sanitize_key($condition['field'] ?? '');
        if ($field === '') {
            return [];
        }

        $operator = sanitize_key($condition['operator'] ?? 'equals');
        if (!in_array($operator, self::CONDITION_OPERATORS, true)) {
            $operator = 'equals';
        }

        return [
            'field' => $field,
            'operator' => $operator,
            'value' => sanitize_text_field($condition['value'] ?? ''),
        ];
    }
}
