<?php

namespace App\Features\ContactForm;

/**
 * Data helpers for contact form definitions and submissions.
 *
 * The AJAX handler and admin manager both need to understand old flat forms,
 * new row-based forms, conditional fields, and multi-step markers. Keeping
 * those rules here makes the runtime handler easier to follow.
 */
final class ContactFormSchema
{
    /**
     * Extract a flat list of field objects from a stored form row.
     * Handles both old flat format and new row-based format.
     */
    public static function extractFlatFields(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }

        if (isset($raw[0]['type']) && !isset($raw[0]['cols'])) {
            return array_values(array_filter($raw, fn($field) => ($field['type'] ?? '') !== 'step_break'));
        }

        $fields = [];
        foreach ($raw as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    if (($field['type'] ?? '') === 'step_break') {
                        continue;
                    }
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    public static function shouldRenderMultiStep(array $rawData, array $styleSettings): bool
    {
        if (($styleSettings['form_mode'] ?? 'standard') === 'multi_step') {
            return true;
        }

        foreach (self::flattenRawFields($rawData) as $field) {
            if (($field['type'] ?? '') === 'step_break') {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert raw DB data to row-based format for the form builder.
     */
    public static function toRowsFormat(array $form, array $allowedSpans = [3, 4, 6, 8, 12]): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }

        if (isset($raw[0]['cols'])) {
            return $raw;
        }

        return array_map(static function ($field) use ($allowedSpans) {
            $span = in_array((int) ($field['col_width'] ?? 12), $allowedSpans, true)
                ? (int) $field['col_width']
                : 12;

            unset($field['col_width']);

            return [
                'id' => 'row_' . ($field['id'] ?? uniqid()),
                'cols' => [
                    [
                        'id' => 'col_' . ($field['id'] ?? uniqid()),
                        'span' => $span,
                        'fields' => [$field],
                    ],
                ],
            ];
        }, $raw);
    }

    public static function splitRowsIntoSteps(array $rawData): array
    {
        $rows = isset($rawData[0]['cols'])
            ? $rawData
            : array_map(fn($field) => [
                'id' => $field['id'] ?? uniqid('row_', true),
                'cols' => [[
                    'id' => uniqid('col_', true),
                    'span' => 12,
                    'fields' => [$field],
                ]],
            ], $rawData);

        $steps = [];
        $currentRows = [];
        $currentLabel = 'Bước 1';

        foreach ($rows as $row) {
            $marker = self::getStepMarker($row);
            if ($marker !== null) {
                if (!empty($currentRows)) {
                    $steps[] = [
                        'label' => $currentLabel,
                        'rows' => $currentRows,
                    ];
                    $currentRows = [];
                }

                $fallback = 'Bước ' . (count($steps) + 2);
                $currentLabel = trim((string) ($marker['label'] ?? '')) ?: $fallback;

                $rowWithoutMarkers = self::stripStepMarkersFromRow($row);
                if (self::rowHasRenderableFields($rowWithoutMarkers)) {
                    $currentRows[] = $rowWithoutMarkers;
                }

                continue;
            }

            if (self::rowHasRenderableFields($row)) {
                $currentRows[] = $row;
            }
        }

        if (!empty($currentRows) || $steps === []) {
            $steps[] = [
                'label' => $currentLabel,
                'rows' => $currentRows,
            ];
        }

        return $steps;
    }

    public static function buildConditionAttributes(array $field): string
    {
        $condition = $field['condition'] ?? [];
        if (empty($condition['field'])) {
            return '';
        }

        $operator = $condition['operator'] ?? 'equals';
        if (!in_array($operator, ['equals', 'not_equals', 'contains', 'not_empty', 'empty'], true)) {
            $operator = 'equals';
        }

        return sprintf(
            ' data-condition-field="%s" data-condition-operator="%s" data-condition-value="%s"',
            esc_attr($condition['field']),
            esc_attr($operator),
            esc_attr($condition['value'] ?? '')
        );
    }

    public static function isFieldConditionMatched(array $field, array $source): bool
    {
        $condition = $field['condition'] ?? [];
        if (empty($condition['field'])) {
            return true;
        }

        $operator = $condition['operator'] ?? 'equals';
        $expected = (string) ($condition['value'] ?? '');
        $actual = $source[$condition['field']] ?? '';

        if (is_array($actual)) {
            $actualValues = array_map('strval', $actual);
            $actualString = implode(', ', $actualValues);
        } else {
            $actualValues = [(string) $actual];
            $actualString = (string) $actual;
        }

        return match ($operator) {
            'not_equals' => !in_array($expected, $actualValues, true),
            'contains' => $expected !== '' && str_contains($actualString, $expected),
            'not_empty' => trim($actualString) !== '',
            'empty' => trim($actualString) === '',
            default => in_array($expected, $actualValues, true),
        };
    }

    public static function sanitizeByType(string $type, mixed $value, array $field): mixed
    {
        if (in_array($type, ['multiselect', 'checkbox'], true) && is_array($value)) {
            $allowed = $field['options'] ?? [];
            return array_filter($value, fn($v) => in_array($v, $allowed, true));
        }

        $value = (string) $value;

        return match ($type) {
            'email'  => sanitize_email($value),
            'url'    => esc_url_raw($value),
            'number' => sanitize_text_field($value),
            'date', 'datetime' => sanitize_text_field($value),
            'textarea' => sanitize_textarea_field($value),
            'select', 'radio' => in_array($value, $field['options'] ?? [], true) ? sanitize_text_field($value) : '',
            default   => sanitize_text_field($value),
        };
    }

    public static function validateFormat(string $type, mixed $value, string $label): string
    {
        $hasValue = trim((string) $value) !== '';

        if ($type === 'email' && $hasValue && !is_email($value)) {
            return $label . ': Địa chỉ email không hợp lệ.';
        }
        if ($type === 'url' && $hasValue && !filter_var($value, FILTER_VALIDATE_URL)) {
            return $label . ': Đường dẫn URL không hợp lệ.';
        }
        if ($type === 'phone' && $hasValue) {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (
                !preg_match('/^\+?[0-9\s().-]+$/', (string) $value) ||
                strlen($digits) < 8 ||
                strlen($digits) > 15
            ) {
                return $label . ': Số điện thoại không hợp lệ.';
            }
        }
        if ($type === 'number' && $hasValue && !is_numeric($value)) {
            return $label . ': Giá trị phải là số hợp lệ.';
        }

        return '';
    }

    /**
     * Build CSS variables scoped to the rendered form wrapper.
     */
    public static function buildScopedCss(string $wrapId, array $settings): string
    {
        if (empty($settings)) {
            return '';
        }

        $allowed = [
            'primary_color'      => '--cf-primary',
            'secondary_color'    => '--cf-secondary',
            'input_border_color' => '--cf-input-border',
            'label_color'        => '--cf-label-color',
        ];

        $vars = [];
        foreach ($allowed as $key => $var) {
            if (!empty($settings[$key])) {
                $value = preg_replace('/[^a-zA-Z0-9#()\s,%.+-]/', '', $settings[$key]);
                $vars[] = $var . ':' . $value;
            }
        }

        foreach (['btn_border_radius' => '--cf-btn-radius', 'input_border_radius' => '--cf-input-radius'] as $key => $var) {
            if (isset($settings[$key])) {
                $vars[] = $var . ':' . (int) $settings[$key] . 'px';
            }
        }

        if (!empty($settings['input_spacing'])) {
            $value = preg_replace('/[^0-9px\s]/', '', $settings['input_spacing']);
            if ($value) {
                $vars[] = '--cf-input-spacing:' . $value;
            }
        }

        if (isset($settings['show_label']) && !$settings['show_label']) {
            $vars[] = '--cf-label-display:none';
        }

        $css = '';
        if (!empty($vars)) {
            $css .= '#' . $wrapId . '{' . implode(';', $vars) . '}';
        }

        if (!empty($settings['custom_css'])) {
            $custom = wp_strip_all_tags($settings['custom_css']);
            $custom = str_replace('__FORM__', '#' . $wrapId, $custom);
            $css .= "\n" . $custom;
        }

        return $css;
    }

    private static function flattenRawFields(array $rawData): array
    {
        if (isset($rawData[0]['type']) && !isset($rawData[0]['cols'])) {
            return $rawData;
        }

        $fields = [];
        foreach ($rawData as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    private static function getStepMarker(array $row): ?array
    {
        foreach ($row['cols'] ?? [] as $col) {
            foreach ($col['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') === 'step_break') {
                    return $field;
                }
            }
        }

        return null;
    }

    private static function stripStepMarkersFromRow(array $row): array
    {
        foreach ($row['cols'] ?? [] as $colIndex => $col) {
            $row['cols'][$colIndex]['fields'] = array_values(array_filter(
                $col['fields'] ?? [],
                fn($field) => ($field['type'] ?? '') !== 'step_break'
            ));
        }

        return $row;
    }

    private static function rowHasRenderableFields(array $row): bool
    {
        foreach ($row['cols'] ?? [] as $col) {
            foreach ($col['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'step_break') {
                    return true;
                }
            }
        }

        return false;
    }
}
