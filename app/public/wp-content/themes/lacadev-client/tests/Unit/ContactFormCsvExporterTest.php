<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormCsvExporter;

test('ContactFormCsvExporter builds stable filenames and headers', function (): void {
    assert_same('submissions-form-12-2026-05-15.csv', ContactFormCsvExporter::filename(12, '2026-05-15'));

    assert_same([
        '#',
        'Đọc',
        'IP',
        'Thời gian',
        'Email',
        'Dịch vụ',
    ], ContactFormCsvExporter::headers([
        ['label' => 'Email'],
        ['label' => 'Dịch vụ'],
    ]));
});

test('ContactFormCsvExporter builds submission rows from JSON data', function (): void {
    $row = ContactFormCsvExporter::row([
        'id' => 9,
        'is_read' => 0,
        'ip_address' => '203.0.113.10',
        'created_at' => '2026-05-15 10:00:00',
        'data' => json_encode([
            'email' => 'hello@example.test',
            'services' => ['Web', 'SEO'],
        ]),
    ], [
        ['name' => 'email'],
        ['name' => 'services'],
        ['name' => 'missing'],
    ], static fn(int $timestamp): string => date('Y-m-d H:i', $timestamp));

    assert_same([
        9,
        'Chưa đọc',
        '203.0.113.10',
        '2026-05-15 10:00',
        'hello@example.test',
        'Web, SEO',
        '',
    ], $row);
});
