<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormFrontendAssets;

test('ContactFormFrontendAssets keeps the shared form CSS selectors', function (): void {
    $css = ContactFormFrontendAssets::inlineCss();

    assert_true(str_contains($css, '.laca-contact-form-wrap'));
    assert_true(str_contains($css, '.laca-cf-step-progress'));
    assert_true(str_contains($css, '.laca-cf-submit-btn'));
    assert_true(str_contains($css, '.laca-cf-fallback-msg--error'));
});

test('ContactFormFrontendAssets prints the expected style wrapper', function (): void {
    ob_start();
    ContactFormFrontendAssets::printInlineCss();
    $html = ob_get_clean();

    assert_true(str_contains($html, '<style id="laca-contact-form-css">'));
    assert_true(str_contains($html, '.laca-cf-field-invalid'));
    assert_true(str_contains($html, '</style>'));
});
