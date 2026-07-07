<?php

namespace LacaWoo\Services;

final class OrderReportService
{
    public function statusSummary(): array
    {
        $items = [];

        foreach (wc_get_order_statuses() as $status => $label) {
            $slug = str_replace('wc-', '', $status);
            $count = function_exists('wc_orders_count') ? wc_orders_count($slug) : 0;
            $items[] = [
                'status' => $slug,
                'label' => $label,
                'count' => (int) $count,
                'url' => $this->ordersStatusUrl($status),
            ];
        }

        return $items;
    }

    public function recentOrders(string $status = 'any', int $limit = 12): array
    {
        $args = [
            'limit' => max(1, min(50, $limit)),
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ];

        if ($status !== 'any') {
            $args['status'] = sanitize_key($status);
        }

        $orders = wc_get_orders($args);

        return array_map([$this, 'mapOrder'], $orders);
    }

    public function actionableCounts(): array
    {
        return [
            'pending' => function_exists('wc_orders_count') ? (int) wc_orders_count('pending') : 0,
            'processing' => function_exists('wc_orders_count') ? (int) wc_orders_count('processing') : 0,
            'on_hold' => function_exists('wc_orders_count') ? (int) wc_orders_count('on-hold') : 0,
            'failed' => function_exists('wc_orders_count') ? (int) wc_orders_count('failed') : 0,
        ];
    }

    private function mapOrder(\WC_Order $order): array
    {
        $created = $order->get_date_created();

        return [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'customer' => trim($order->get_formatted_billing_full_name()) ?: __('Khách lẻ', 'laca-woo'),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'total' => (float) $order->get_total(),
            'payment_method' => $order->get_payment_method_title(),
            'created' => $created ? date_i18n('d/m/Y H:i', $created->getTimestamp() + $created->getOffset()) : '',
            'edit_url' => $order->get_edit_order_url(),
        ];
    }

    private function ordersStatusUrl(string $status): string
    {
        if (
            class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            return admin_url('admin.php?page=wc-orders&status=' . rawurlencode($status));
        }

        return admin_url('edit.php?post_type=shop_order&post_status=' . rawurlencode($status));
    }
}
