<?php

namespace LacaWoo\Services;

final class RevenueReportService
{
    private const PAID_STATUSES = ['processing', 'completed'];

    public function report(array $range): array
    {
        $cacheKey = 'laca_woo_revenue_' . md5(wp_json_encode($range));
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $orders = $this->ordersForRange($range['start'], $range['end']);
        $previousRange = $this->previousRange($range['start'], $range['end']);
        $previousOrders = $this->ordersForRange($previousRange['start'], $previousRange['end']);

        $metrics = $this->metricsFromOrders($orders);
        $previous = $this->metricsFromOrders($previousOrders);
        $series = $this->seriesFromOrders($orders, $range['start'], $range['end'], $range['period']);

        $report = [
            'period' => $range['period'],
            'start' => date_i18n('Y-m-d', $range['start']),
            'end' => date_i18n('Y-m-d', $range['end']),
            'range_label' => date_i18n('d/m/Y', $range['start']) . ' - ' . date_i18n('d/m/Y', $range['end']),
            'metrics' => $metrics,
            'previous' => $previous,
            'comparison' => [
                'gross_sales' => $this->percentChange($metrics['gross_sales'], $previous['gross_sales']),
                'net_sales' => $this->percentChange($metrics['net_sales'], $previous['net_sales']),
                'orders' => $this->percentChange($metrics['orders'], $previous['orders']),
                'average_order_value' => $this->percentChange($metrics['average_order_value'], $previous['average_order_value']),
            ],
            'series' => $series,
        ];

        set_transient($cacheKey, $report, 5 * MINUTE_IN_SECONDS);

        return $report;
    }

    private function ordersForRange(int $start, int $end): array
    {
        return wc_get_orders([
            'status' => self::PAID_STATUSES,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'date_created' => $start . '...' . $end,
            'return' => 'objects',
        ]);
    }

    private function metricsFromOrders(array $orders): array
    {
        $gross = 0.0;
        $refunds = 0.0;
        $tax = 0.0;
        $shipping = 0.0;
        $discounts = 0.0;

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $gross += (float) $order->get_total();
            $refunds += (float) $order->get_total_refunded();
            $tax += (float) $order->get_total_tax();
            $shipping += (float) $order->get_shipping_total();
            $discounts += (float) $order->get_discount_total();
        }

        $count = count($orders);
        $net = max(0, $gross - $refunds - $tax - $shipping);

        return [
            'gross_sales' => round($gross, 2),
            'net_sales' => round($net, 2),
            'refunds' => round($refunds, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'discounts' => round($discounts, 2),
            'orders' => $count,
            'average_order_value' => $count > 0 ? round($gross / $count, 2) : 0,
        ];
    }

    private function seriesFromOrders(array $orders, int $start, int $end, string $period): array
    {
        $buckets = $this->emptyBuckets($start, $end, $period);

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $created = $order->get_date_created();
            if (!$created) {
                continue;
            }

            $timestamp = $created->getTimestamp() + $created->getOffset();
            $key = $this->bucketKey($timestamp, $period, $start, $end);
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['label' => $key, 'gross_sales' => 0, 'net_sales' => 0, 'orders' => 0];
            }

            $gross = (float) $order->get_total();
            $net = max(0, $gross - (float) $order->get_total_refunded() - (float) $order->get_total_tax() - (float) $order->get_shipping_total());

            $buckets[$key]['gross_sales'] = round($buckets[$key]['gross_sales'] + $gross, 2);
            $buckets[$key]['net_sales'] = round($buckets[$key]['net_sales'] + $net, 2);
            $buckets[$key]['orders']++;
        }

        return array_values($buckets);
    }

    private function emptyBuckets(int $start, int $end, string $period): array
    {
        $buckets = [];

        if ($period === 'today') {
            for ($hour = 0; $hour < 24; $hour++) {
                $label = str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00';
                $buckets[$label] = ['label' => $label, 'gross_sales' => 0, 'net_sales' => 0, 'orders' => 0];
            }
            return $buckets;
        }

        if ($period === 'year' || (($end - $start) > 92 * DAY_IN_SECONDS)) {
            $cursor = strtotime(date('Y-m-01 00:00:00', $start));
            while ($cursor <= $end) {
                $label = date_i18n('m/Y', $cursor);
                $buckets[$label] = ['label' => $label, 'gross_sales' => 0, 'net_sales' => 0, 'orders' => 0];
                $cursor = strtotime('+1 month', $cursor);
            }
            return $buckets;
        }

        $cursor = strtotime(date('Y-m-d 00:00:00', $start));
        while ($cursor <= $end) {
            $label = date_i18n('d/m', $cursor);
            $buckets[$label] = ['label' => $label, 'gross_sales' => 0, 'net_sales' => 0, 'orders' => 0];
            $cursor = strtotime('+1 day', $cursor);
        }

        return $buckets;
    }

    private function bucketKey(int $timestamp, string $period, int $start, int $end): string
    {
        if ($period === 'today') {
            return date_i18n('H:00', $timestamp);
        }

        if ($period === 'year' || (($end - $start) > 92 * DAY_IN_SECONDS)) {
            return date_i18n('m/Y', $timestamp);
        }

        return date_i18n('d/m', $timestamp);
    }

    private function previousRange(int $start, int $end): array
    {
        $seconds = max(DAY_IN_SECONDS, $end - $start + 1);

        return [
            'start' => $start - $seconds,
            'end' => $start - 1,
        ];
    }

    private function percentChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
