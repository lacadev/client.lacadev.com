<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormFrontendScripts;

if (!function_exists('esc_js')) {
    function esc_js(mixed $value): string
    {
        return addslashes((string) $value);
    }
}

test('ContactFormFrontendScripts renders single-step AJAX boot script', function (): void {
    ob_start();
    ContactFormFrontendScripts::printSingleStepScript('form-1', 'https://example.test/admin-ajax.php', ' nonce="abc"');
    $html = ob_get_clean();

    assert_true(str_contains($html, '<script nonce="abc">'));
    assert_true(str_contains($html, "const FORM_ID  = 'form-1';"));
    assert_true(str_contains($html, "const AJAX_URL = 'https://example.test/admin-ajax.php';"));
    assert_true(str_contains($html, "formEl.addEventListener('submit'"));
    assert_true(str_contains($html, "new FormData(formEl)"));
});

test('ContactFormFrontendScripts renders multi-step navigation and submit script', function (): void {
    ob_start();
    ContactFormFrontendScripts::printMultiStepScript('form-steps', 'https://example.test/admin-ajax.php');
    $html = ob_get_clean();

    assert_true(str_contains($html, "'.laca-contact-form--multistep'"));
    assert_true(str_contains($html, "formEl.dataset.lacaMultiStepReady"));
    assert_true(str_contains($html, "const goNext = () =>"));
    assert_true(str_contains($html, "showStep(0);"));
    assert_true(str_contains($html, "new FormData(formEl)"));
});
