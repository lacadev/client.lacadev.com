<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormSubmissionValidator;

test('ContactFormSubmissionValidator sanitizes valid submitted fields', function (): void {
    $result = ContactFormSubmissionValidator::validate([
        [
            'type' => 'text',
            'name' => 'name',
            'label' => 'Tên',
            'required' => true,
        ],
        [
            'type' => 'email',
            'name' => 'email',
            'label' => 'Email',
            'required' => true,
        ],
        [
            'type' => 'checkbox',
            'name' => 'services',
            'label' => 'Dịch vụ',
            'options' => ['Web', 'SEO'],
        ],
    ], [
        'name' => ' <strong>Laca</strong> ',
        'email' => 'hello@example.test',
        'services' => ['Web', 'Invalid'],
    ]);

    assert_same([], $result['errors']);
    assert_same('Laca', $result['data']['name']);
    assert_same('hello@example.test', $result['data']['email']);
    assert_same([0 => 'Web'], $result['data']['services']);
});

test('ContactFormSubmissionValidator reports required and format errors', function (): void {
    $result = ContactFormSubmissionValidator::validate([
        [
            'type' => 'text',
            'name' => 'name',
            'label' => 'Tên',
            'required' => true,
        ],
        [
            'type' => 'email',
            'name' => 'email',
            'label' => 'Email',
            'required' => false,
        ],
    ], [
        'name' => '',
        'email' => 'not-an-email',
    ]);

    assert_same(['Tên là bắt buộc.', 'Email: Địa chỉ email không hợp lệ.'], $result['errors']);
    assert_same([], $result['data']);
});

test('ContactFormSubmissionValidator skips fields whose conditions do not match', function (): void {
    $result = ContactFormSubmissionValidator::validate([
        [
            'type' => 'text',
            'name' => 'details',
            'label' => 'Chi tiết',
            'required' => true,
            'condition' => [
                'field' => 'need_details',
                'operator' => 'equals',
                'value' => 'yes',
            ],
        ],
    ], [
        'need_details' => 'no',
        'details' => '',
    ]);

    assert_same([], $result['errors']);
    assert_same([], $result['data']);
});
