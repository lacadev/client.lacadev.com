<?php

namespace LacaWoo\Rest;

use LacaWoo\Admin\AdminMenu;
use LacaWoo\Services\DateRange;
use LacaWoo\Services\OrderReportService;
use LacaWoo\Services\ProductReportService;
use LacaWoo\Services\RevenueReportService;
use WP_REST_Request;

final class AnalyticsController
{
    private const NAMESPACE = 'laca-woo/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'summary'],
            'permission_callback' => [$this, 'canView'],
        ]);

        register_rest_route(self::NAMESPACE, '/revenue', [
            'methods' => 'GET',
            'callback' => [$this, 'revenue'],
            'permission_callback' => [$this, 'canView'],
        ]);

        register_rest_route(self::NAMESPACE, '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'products'],
            'permission_callback' => [$this, 'canView'],
        ]);

        register_rest_route(self::NAMESPACE, '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'orders'],
            'permission_callback' => [$this, 'canView'],
        ]);
    }

    public function canView(): bool
    {
        return current_user_can(AdminMenu::CAPABILITY);
    }

    public function summary(WP_REST_Request $request)
    {
        $period = sanitize_key((string) $request->get_param('period')) ?: 'month';
        $range = (new DateRange())->resolve($period);
        $revenue = (new RevenueReportService())->report($range);
        $products = new ProductReportService();
        $orders = new OrderReportService();

        return rest_ensure_response([
            'revenue' => $revenue,
            'top_products' => $products->topProducts(8),
            'low_stock_products' => $products->lowStockProducts(10),
            'inventory' => $products->inventorySummary(),
            'order_statuses' => $orders->statusSummary(),
            'actionable_orders' => $orders->actionableCounts(),
            'recent_orders' => $orders->recentOrders('any', 8),
        ]);
    }

    public function revenue(WP_REST_Request $request)
    {
        $range = (new DateRange())->resolve(
            sanitize_key((string) $request->get_param('period')),
            sanitize_text_field((string) $request->get_param('start')),
            sanitize_text_field((string) $request->get_param('end'))
        );

        return rest_ensure_response((new RevenueReportService())->report($range));
    }

    public function products(WP_REST_Request $request)
    {
        $filter = sanitize_key((string) $request->get_param('filter')) ?: 'top_selling';
        $limit = absint($request->get_param('limit') ?: 30);

        return rest_ensure_response([
            'filter' => $filter,
            'items' => (new ProductReportService())->products($filter, $limit),
        ]);
    }

    public function orders(WP_REST_Request $request)
    {
        $status = sanitize_key((string) $request->get_param('status')) ?: 'any';
        $limit = absint($request->get_param('limit') ?: 12);
        $orders = new OrderReportService();

        return rest_ensure_response([
            'status' => $status,
            'statuses' => $orders->statusSummary(),
            'items' => $orders->recentOrders($status, $limit),
        ]);
    }
}
