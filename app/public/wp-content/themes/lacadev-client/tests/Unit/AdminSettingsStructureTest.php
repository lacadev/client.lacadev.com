<?php

declare(strict_types=1);

test('AdminSettings delegates option registration and groups bootstrap responsibilities', function (): void {
    $file = dirname(__DIR__, 2) . '/app/src/Settings/AdminSettings.php';
    $source = file_get_contents($file);

    assert_true(is_string($source) && $source !== '', 'Unable to read AdminSettings.php');
    assert_true(str_contains($source, 'AdminOptionsRegistrar::register();'));
    assert_true(str_contains($source, '$this->bootRestrictedAdminExperience();'));
    assert_true(str_contains($source, '$this->bootSharedAdminUi();'));
    assert_true(str_contains($source, '$this->bootMediaEnhancements();'));
    assert_true(str_contains($source, '$this->applyOptionDrivenToggles();'));
});
