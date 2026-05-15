<?php

declare(strict_types=1);

use App\Assets\ProjectChartData;

test('ProjectChartData formats status rows with known and fallback labels', function (): void {
    $rows = [
        (object) ['key' => 'pending', 'count' => '3'],
        (object) ['key' => 'custom_status', 'count' => 2],
    ];

    $data = ProjectChartData::formatStatusRows($rows);

    assert_same('pending', $data[0]['key']);
    assert_same('🕐 Chờ làm', $data[0]['label']);
    assert_same(3, $data[0]['count']);
    assert_same('Custom_status', $data[1]['label']);
});

test('ProjectChartData fills missing months across the last twelve months', function (): void {
    $base = strtotime('2026-05-15 12:00:00');
    $data = ProjectChartData::formatMonthRows([
        (object) ['ym' => '2026-04', 'cnt' => '5'],
        (object) ['ym' => '2026-05', 'cnt' => 2],
    ], $base);

    assert_same(12, count($data));
    assert_same('T6', $data[0]['month']);
    assert_same(0, $data[0]['count']);
    assert_same('T4', $data[10]['month']);
    assert_same(5, $data[10]['count']);
    assert_same('T5', $data[11]['month']);
    assert_same(2, $data[11]['count']);
});
