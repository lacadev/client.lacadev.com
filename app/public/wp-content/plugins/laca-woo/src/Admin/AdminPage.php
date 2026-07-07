<?php

namespace LacaWoo\Admin;

final class AdminPage
{
    public function render(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die(esc_html__('Bạn không có quyền truy cập Laca Woo.', 'laca-woo'));
        }
        ?>
        <div class="wrap laca-woo-page">
            <div class="laca-woo-header">
                <div>
                    <h1><?php echo esc_html__('Laca Woo', 'laca-woo'); ?></h1>
                    <p><?php echo esc_html__('Theo dõi doanh thu, đơn hàng, sản phẩm và tồn kho WooCommerce ở một nơi.', 'laca-woo'); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wc-admin')); ?>">
                    <?php echo esc_html__('Mở WooCommerce Analytics', 'laca-woo'); ?>
                </a>
            </div>

            <nav class="laca-woo-tabs" aria-label="<?php echo esc_attr__('Laca Woo sections', 'laca-woo'); ?>">
                <button type="button" class="is-active" data-panel="overview"><?php echo esc_html__('Tổng quan', 'laca-woo'); ?></button>
                <button type="button" data-panel="revenue"><?php echo esc_html__('Doanh thu', 'laca-woo'); ?></button>
                <button type="button" data-panel="products"><?php echo esc_html__('Sản phẩm', 'laca-woo'); ?></button>
                <button type="button" data-panel="orders"><?php echo esc_html__('Đơn hàng', 'laca-woo'); ?></button>
                <button type="button" data-panel="inventory"><?php echo esc_html__('Kho hàng', 'laca-woo'); ?></button>
            </nav>

            <section class="laca-woo-panel is-active" data-panel="overview">
                <div class="laca-woo-metrics" data-laca-woo-metrics></div>
                <div class="laca-woo-grid laca-woo-grid--two">
                    <div class="laca-woo-card">
                        <header><h2><?php echo esc_html__('Sản phẩm bán chạy', 'laca-woo'); ?></h2></header>
                        <div data-laca-woo-top-products></div>
                    </div>
                    <div class="laca-woo-card">
                        <header><h2><?php echo esc_html__('Đơn hàng mới nhất', 'laca-woo'); ?></h2></header>
                        <div data-laca-woo-recent-orders></div>
                    </div>
                </div>
            </section>

            <section class="laca-woo-panel" data-panel="revenue">
                <div class="laca-woo-toolbar">
                    <div class="laca-woo-periods">
                        <button type="button" data-period="today"><?php echo esc_html__('Ngày', 'laca-woo'); ?></button>
                        <button type="button" data-period="week"><?php echo esc_html__('Tuần', 'laca-woo'); ?></button>
                        <button type="button" class="is-active" data-period="month"><?php echo esc_html__('Tháng', 'laca-woo'); ?></button>
                        <button type="button" data-period="year"><?php echo esc_html__('Năm', 'laca-woo'); ?></button>
                        <button type="button" data-period="custom"><?php echo esc_html__('Custom', 'laca-woo'); ?></button>
                    </div>
                    <div class="laca-woo-custom-range" data-laca-woo-custom-range hidden>
                        <input type="date" data-laca-woo-start>
                        <input type="date" data-laca-woo-end>
                        <button type="button" class="button button-small" data-laca-woo-apply-range><?php echo esc_html__('Áp dụng', 'laca-woo'); ?></button>
                    </div>
                </div>
                <div class="laca-woo-metrics laca-woo-metrics--compact" data-laca-woo-metrics></div>
                <div class="laca-woo-card laca-woo-chart-card">
                    <header>
                        <h2><?php echo esc_html__('Biểu đồ doanh thu', 'laca-woo'); ?></h2>
                        <span data-laca-woo-range-label></span>
                    </header>
                    <canvas id="laca-woo-revenue-chart" height="120"></canvas>
                    <p class="laca-woo-chart-empty" data-laca-woo-chart-empty hidden><?php echo esc_html__('Không có doanh thu trong khoảng thời gian này.', 'laca-woo'); ?></p>
                </div>
            </section>

            <section class="laca-woo-panel" data-panel="products">
                <div class="laca-woo-toolbar">
                    <label>
                        <span><?php echo esc_html__('Filter sản phẩm', 'laca-woo'); ?></span>
                        <select data-laca-woo-product-filter>
                            <option value="top_selling"><?php echo esc_html__('Bán nhiều nhất', 'laca-woo'); ?></option>
                            <option value="low_selling"><?php echo esc_html__('Bán ít nhất', 'laca-woo'); ?></option>
                            <option value="price_high"><?php echo esc_html__('Giá cao nhất', 'laca-woo'); ?></option>
                            <option value="price_low"><?php echo esc_html__('Giá thấp nhất', 'laca-woo'); ?></option>
                            <option value="low_stock"><?php echo esc_html__('Sắp hết hàng', 'laca-woo'); ?></option>
                            <option value="out_of_stock"><?php echo esc_html__('Hết hàng', 'laca-woo'); ?></option>
                            <option value="no_sales"><?php echo esc_html__('Chưa bán được', 'laca-woo'); ?></option>
                            <option value="on_sale"><?php echo esc_html__('Đang sale', 'laca-woo'); ?></option>
                        </select>
                    </label>
                </div>
                <div class="laca-woo-card">
                    <div data-laca-woo-products-table></div>
                </div>
            </section>

            <section class="laca-woo-panel" data-panel="orders">
                <div class="laca-woo-toolbar">
                    <label>
                        <span><?php echo esc_html__('Trạng thái đơn', 'laca-woo'); ?></span>
                        <select data-laca-woo-order-status>
                            <option value="any"><?php echo esc_html__('Tất cả', 'laca-woo'); ?></option>
                            <?php foreach (wc_get_order_statuses() as $status => $label) : ?>
                                <option value="<?php echo esc_attr(str_replace('wc-', '', $status)); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="laca-woo-grid laca-woo-grid--two">
                    <div class="laca-woo-card">
                        <header><h2><?php echo esc_html__('Tổng đơn theo trạng thái', 'laca-woo'); ?></h2></header>
                        <div data-laca-woo-order-statuses></div>
                    </div>
                    <div class="laca-woo-card">
                        <header><h2><?php echo esc_html__('Danh sách đơn hàng', 'laca-woo'); ?></h2></header>
                        <div data-laca-woo-orders-table></div>
                    </div>
                </div>
            </section>

            <section class="laca-woo-panel" data-panel="inventory">
                <div class="laca-woo-grid laca-woo-grid--two">
                    <div class="laca-woo-card">
                        <header><h2><?php echo esc_html__('Cảnh báo tồn kho', 'laca-woo'); ?></h2></header>
                        <div data-laca-woo-inventory-summary></div>
                    </div>
                    <div class="laca-woo-card">
                        <header><h2><?php echo esc_html__('Sản phẩm cần nhập hàng', 'laca-woo'); ?></h2></header>
                        <div data-laca-woo-low-stock></div>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }
}
