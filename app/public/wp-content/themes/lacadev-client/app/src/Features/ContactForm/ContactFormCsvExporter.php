<?php

namespace App\Features\ContactForm;

/**
 * Builds CSV headers and rows for contact-form submissions.
 */
final class ContactFormCsvExporter
{
    public static function filename(int $formId, string $date): string
    {
        return 'submissions-form-' . $formId . '-' . $date . '.csv';
    }

    public static function headers(array $fields): array
    {
        $headers = ['#', 'Đọc', 'IP', 'Thời gian'];

        foreach ($fields as $field) {
            $headers[] = $field['label'];
        }

        return $headers;
    }

    public static function row(array $submission, array $fields, callable $dateFormatter): array
    {
        $data = json_decode($submission['data'] ?? '{}', true) ?: [];
        $row = [
            $submission['id'],
            !empty($submission['is_read']) ? 'Đã đọc' : 'Chưa đọc',
            $submission['ip_address'],
            $dateFormatter(strtotime($submission['created_at'])),
        ];

        foreach ($fields as $field) {
            $value = $data[$field['name']] ?? '';
            $row[] = is_array($value) ? implode(', ', $value) : $value;
        }

        return $row;
    }
}
