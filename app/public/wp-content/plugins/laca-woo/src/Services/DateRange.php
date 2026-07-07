<?php

namespace LacaWoo\Services;

final class DateRange
{
    public function resolve(string $period, string $start = '', string $end = ''): array
    {
        $now = current_time('timestamp');
        $period = sanitize_key($period ?: 'month');

        if ($period === 'custom' && $start && $end) {
            $startTs = strtotime($start . ' 00:00:00');
            $endTs = strtotime($end . ' 23:59:59');
        } elseif ($period === 'today') {
            $startTs = strtotime(date('Y-m-d 00:00:00', $now));
            $endTs = strtotime(date('Y-m-d 23:59:59', $now));
        } elseif ($period === 'week') {
            $startTs = strtotime('monday this week 00:00:00', $now);
            $endTs = strtotime('sunday this week 23:59:59', $now);
        } elseif ($period === 'year') {
            $startTs = strtotime(date('Y-01-01 00:00:00', $now));
            $endTs = strtotime(date('Y-12-31 23:59:59', $now));
        } else {
            $period = 'month';
            $startTs = strtotime(date('Y-m-01 00:00:00', $now));
            $endTs = strtotime(date('Y-m-t 23:59:59', $now));
        }

        if (!$startTs || !$endTs || $startTs > $endTs) {
            $period = 'month';
            $startTs = strtotime(date('Y-m-01 00:00:00', $now));
            $endTs = strtotime(date('Y-m-t 23:59:59', $now));
        }

        return [
            'period' => $period,
            'start' => $startTs,
            'end' => $endTs,
            'start_mysql' => date('Y-m-d H:i:s', $startTs),
            'end_mysql' => date('Y-m-d H:i:s', $endTs),
        ];
    }
}
