<?php

declare(strict_types=1);

use App\Settings\Tracker\RemoteUpdateMeta;

test('RemoteUpdateMeta builds stable metadata with optional rollback note', function (): void {
    $meta = RemoteUpdateMeta::build(
        ['ok' => true],
        ['before' => true],
        ['after' => true],
        true,
        'Rollback here'
    );

    assert_same(['ok' => true], $meta['preflight']);
    assert_same(['before' => true], $meta['snapshot_before']);
    assert_same(['after' => true], $meta['snapshot_after']);
    assert_true($meta['temporary_maintenance']);
    assert_same('Rollback here', $meta['rollback_note']);

    $withoutRollback = RemoteUpdateMeta::build([], [], [], false);
    assert_true(!array_key_exists('rollback_note', $withoutRollback));
});
