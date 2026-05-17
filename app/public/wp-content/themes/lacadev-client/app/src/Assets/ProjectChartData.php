<?php

namespace App\Assets;

/**
 * Builds project dashboard chart data for the admin bundle.
 */
final class ProjectChartData
{
    private const STATUS_LABELS = [
        'pending' => '🕐 Chờ làm',
        'in_progress' => '🔨 Đang làm',
        'done' => '✅ Đã xong',
        'maintenance' => '🔧 Đang bảo trì',
        'paused' => '⏸️ Tạm dừng',
    ];

    public static function shouldLocalize(mixed $screen): bool
    {
        $screenId = (string) ($screen->id ?? '');

        return $screen
            && in_array($screenId, ['dashboard', 'toplevel_page_lacadev-dashboard'], true)
            && function_exists('post_type_exists')
            && post_type_exists('project');
    }

    public static function build(object $wpdb): array
    {
        return [
            'primary' => '#2563eb',
            'byStatus' => self::formatStatusRows($wpdb->get_results(self::statusSql($wpdb))),
            'byMonth' => self::formatMonthRows($wpdb->get_results(self::monthSql($wpdb))),
        ];
    }

    public static function formatStatusRows(array $rows): array
    {
        $byStatus = [];

        foreach ($rows as $row) {
            $key = (string) ($row->key ?? 'pending');
            $byStatus[] = [
                'key' => $key,
                'label' => self::STATUS_LABELS[$key] ?? ucfirst($key),
                'count' => (int) ($row->count ?? 0),
            ];
        }

        return $byStatus;
    }

    public static function formatMonthRows(array $rows, ?int $baseTimestamp = null): array
    {
        $monthMap = [];
        foreach ($rows as $row) {
            $monthMap[(string) ($row->ym ?? '')] = (int) ($row->cnt ?? 0);
        }

        $baseTimestamp ??= time();
        $byMonth = [];

        for ($i = 11; $i >= 0; $i--) {
            $timestamp = strtotime("-{$i} months", $baseTimestamp);
            $ym = date('Y-m', $timestamp);

            $byMonth[] = [
                'month' => 'T' . (int) date('n', $timestamp),
                'count' => $monthMap[$ym] ?? 0,
            ];
        }

        return $byMonth;
    }

    private static function statusSql(object $wpdb): string
    {
        return "
            SELECT
                COALESCE(pm.meta_value, 'pending') AS `key`,
                COUNT(*) AS `count`
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id AND pm.meta_key = '_project_status'
            WHERE p.post_type = 'project'
              AND p.post_status NOT IN ('trash','auto-draft','inherit')
            GROUP BY `key`
        ";
    }

    private static function monthSql(object $wpdb): string
    {
        return "
            SELECT
                DATE_FORMAT(post_date, '%Y-%m') AS ym,
                COUNT(*) AS cnt
            FROM {$wpdb->posts}
            WHERE post_type = 'project'
              AND post_status NOT IN ('trash','auto-draft','inherit')
              AND post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY ym
            ORDER BY ym ASC
        ";
    }
}
