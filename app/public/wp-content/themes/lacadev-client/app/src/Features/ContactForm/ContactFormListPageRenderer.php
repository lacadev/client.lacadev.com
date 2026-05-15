<?php

namespace App\Features\ContactForm;

final class ContactFormListPageRenderer
{
    public static function render(array $context): string
    {
        ob_start();
        $pageUrl = (string) $context['page_url'];
        $message = $context['message'];
        $items = (array) ($context['items'] ?? []);
        $nonceAction = (string) $context['nonce_action'];
        $nonceField = (string) $context['nonce_field'];
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1>📋 Quản lý Form Liên Hệ</h1>
                    <p class="laca-cf-subtitle">Tạo và quản lý các form liên hệ. Nhúng bằng shortcode <code>[laca_contact_form id="X"]</code></p>
                </div>
                <a href="<?php echo esc_url($pageUrl . '&action=new'); ?>" class="button button-primary laca-cf-btn-new">
                    + Tạo Form Mới
                </a>
            </div>

            <?php if ($message): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($message['type']); ?>">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($items)): ?>
                <div class="laca-cf-empty">
                    <p>Chưa có form nào. <a href="<?php echo esc_url($pageUrl . '&action=new'); ?>">Tạo form đầu tiên</a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped laca-cf-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th>Tên Form</th>
                            <th style="width:100px">Số Fields</th>
                            <th style="width:120px">Submissions</th>
                            <th style="width:120px">Chưa đọc</th>
                            <th style="width:140px">Shortcode</th>
                            <th style="width:200px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item['id']); ?></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url($item['edit_url']); ?>">
                                            <?php echo esc_html($item['name']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($item['field_count']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($item['submissions_url']); ?>">
                                        <?php echo esc_html($item['submission_count']); ?> lượt
                                    </a>
                                </td>
                                <td>
                                    <?php if ((int) $item['unread_count'] > 0): ?>
                                        <span class="laca-cf-badge laca-cf-badge--unread"><?php echo esc_html($item['unread_count']); ?> mới</span>
                                    <?php else: ?>
                                        <span style="color:#999">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code class="laca-cf-shortcode" title="Click để copy"
                                          onclick="navigator.clipboard.writeText('<?php echo esc_js($item['shortcode']); ?>').then(()=>alert('Đã copy shortcode!'))"
                                          style="cursor:pointer">
                                        <?php echo esc_html($item['shortcode']); ?>
                                    </code>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($item['edit_url']); ?>" class="button button-small">Sửa</a>
                                    <a href="<?php echo esc_url($item['submissions_url']); ?>" class="button button-small">Xem Submissions</a>
                                    <form method="post" action="<?php echo esc_url($item['delete_action']); ?>"
                                          style="display:inline"
                                          class="laca-cf-delete-form">
                                        <?php wp_nonce_field($nonceAction, $nonceField); ?>
                                        <input type="hidden" name="action" value="laca_cf_delete">
                                        <input type="hidden" name="form_id" value="<?php echo esc_attr($item['id']); ?>">
                                        <button type="submit" class="button button-small button-link-delete">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
