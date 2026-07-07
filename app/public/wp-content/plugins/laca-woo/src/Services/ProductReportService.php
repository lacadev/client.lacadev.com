<?php

namespace LacaWoo\Services;

final class ProductReportService
{
    public function products(string $filter = 'top_selling', int $limit = 30): array
    {
        $rows = $this->allProductRows();
        $rows = array_values(array_filter($rows, fn ($row) => $this->matchesFilter($row, $filter)));
        $rows = $this->sortRows($rows, $filter);

        return array_slice($rows, 0, max(1, min(100, $limit)));
    }

    public function inventorySummary(): array
    {
        $products = $this->allProductRows();

        $lowStock = 0;
        $outOfStock = 0;
        $notManaged = 0;
        $noSales = 0;

        foreach ($products as $product) {
            if ($product['stock_status'] === 'outofstock') {
                $outOfStock++;
            }
            if ($product['is_low_stock']) {
                $lowStock++;
            }
            if (!$product['manage_stock']) {
                $notManaged++;
            }
            if ($product['total_sales'] === 0) {
                $noSales++;
            }
        }

        return [
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'not_managed' => $notManaged,
            'no_sales' => $noSales,
            'total_products' => count($products),
        ];
    }

    public function lowStockProducts(int $limit = 15): array
    {
        return $this->products('low_stock', $limit);
    }

    public function topProducts(int $limit = 8): array
    {
        return $this->products('top_selling', $limit);
    }

    private function allProductRows(): array
    {
        $products = wc_get_products([
            'status' => ['publish', 'private', 'draft'],
            'limit' => -1,
            'return' => 'objects',
        ]);

        return array_map([$this, 'mapProduct'], $products);
    }

    private function mapProduct(\WC_Product $product): array
    {
        $stockQuantity = $product->get_stock_quantity();
        $lowStockAmount = function_exists('wc_get_low_stock_amount') ? wc_get_low_stock_amount($product) : 2;
        $isLowStock = $product->managing_stock() && $stockQuantity !== null && $stockQuantity <= $lowStockAmount && $product->get_stock_status() !== 'outofstock';
        $price = (float) $product->get_price();
        $totalSales = (int) $product->get_total_sales();
        $imageId = $product->get_image_id();

        return [
            'id' => $product->get_id(),
            'name' => html_entity_decode($product->get_name(), ENT_QUOTES, 'UTF-8'),
            'type' => $product->get_type(),
            'sku' => $product->get_sku(),
            'status' => $product->get_status(),
            'price' => $price,
            'regular_price' => (float) $product->get_regular_price(),
            'sale_price' => (float) $product->get_sale_price(),
            'price_html' => wp_strip_all_tags($product->get_price_html()),
            'stock_quantity' => $stockQuantity,
            'stock_status' => $product->get_stock_status(),
            'stock_label' => $this->stockLabel($product),
            'manage_stock' => $product->managing_stock(),
            'is_low_stock' => $isLowStock,
            'is_on_sale' => $product->is_on_sale(),
            'total_sales' => $totalSales,
            'estimated_revenue' => round($price * $totalSales, 2),
            'edit_url' => get_edit_post_link($product->get_id(), 'raw'),
            'image' => $imageId ? wp_get_attachment_image_url($imageId, 'thumbnail') : wc_placeholder_img_src('thumbnail'),
        ];
    }

    private function matchesFilter(array $row, string $filter): bool
    {
        return match ($filter) {
            'low_stock' => $row['is_low_stock'],
            'out_of_stock' => $row['stock_status'] === 'outofstock',
            'no_sales' => $row['total_sales'] === 0,
            'on_sale' => $row['is_on_sale'],
            default => true,
        };
    }

    private function sortRows(array $rows, string $filter): array
    {
        usort($rows, static function (array $a, array $b) use ($filter): int {
            return match ($filter) {
                'low_selling', 'no_sales' => $a['total_sales'] <=> $b['total_sales'],
                'price_high' => $b['price'] <=> $a['price'],
                'price_low' => $a['price'] <=> $b['price'],
                'low_stock' => (int) ($a['stock_quantity'] ?? PHP_INT_MAX) <=> (int) ($b['stock_quantity'] ?? PHP_INT_MAX),
                'out_of_stock' => $b['total_sales'] <=> $a['total_sales'],
                default => $b['total_sales'] <=> $a['total_sales'],
            };
        });

        return $rows;
    }

    private function stockLabel(\WC_Product $product): string
    {
        if ($product->get_stock_status() === 'outofstock') {
            return __('Hết hàng', 'laca-woo');
        }

        if ($product->managing_stock()) {
            $quantity = $product->get_stock_quantity();
            return $quantity === null ? __('Đang quản lý kho', 'laca-woo') : sprintf(__('%d trong kho', 'laca-woo'), (int) $quantity);
        }

        return $product->get_stock_status() === 'instock' ? __('Còn hàng', 'laca-woo') : __('Không rõ', 'laca-woo');
    }
}
