<?php

declare(strict_types=1);

use App\Settings\Tracker\RemoteUpdatePreflight;

test('RemoteUpdatePreflight result reports successful checks without errors', function (): void {
    $result = RemoteUpdatePreflight::result([], ['Filesystem warning'], ['type' => 'plugin']);

    assert_true($result['ok']);
    assert_true(!$result['skip']);
    assert_same([], $result['errors']);
    assert_same(['Filesystem warning'], $result['warnings']);
    assert_same(['type' => 'plugin'], $result['target']);
});

test('RemoteUpdatePreflight result reports failed checks with errors', function (): void {
    $result = RemoteUpdatePreflight::result(['Missing plugin'], [], []);

    assert_true(!$result['ok']);
    assert_true(!$result['skip']);
    assert_same(['Missing plugin'], $result['errors']);
});
