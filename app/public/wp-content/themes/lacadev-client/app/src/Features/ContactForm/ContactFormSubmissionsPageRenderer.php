<?php

namespace App\Features\ContactForm;

final class ContactFormSubmissionsPageRenderer
{
    public static function render(array $context): string
    {
        ob_start();
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1>📥 Submissions — <?php echo esc_html((string) $context['form_name']); ?></h1>
                    <p class="laca-cf-subtitle"><a href="<?php echo esc_url((string) $context['page_url']); ?>">← Quay lại danh sách</a></p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span class="laca-cf-badge laca-cf-badge--total"><?php echo esc_html((string) $context['total']); ?> submission</span>
                    <?php if (!empty($context['export_url'])): ?>
                        <a href="<?php echo esc_url((string) $context['export_url']); ?>" class="button button-secondary">
                            ⬇ Xuất CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($context['message'])): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($context['message']['type']); ?>">
                    <?php echo esc_html($context['message']['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($context['items'])): ?>
                <div class="laca-cf-empty"><p>Chưa có submission nào.</p></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped laca-cf-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th style="width:60px">Đọc</th>
                            <?php foreach ((array) $context['fields'] as $field): ?>
                                <th><?php echo esc_html((string) $field['label']); ?></th>
                            <?php endforeach; ?>
                            <th style="width:120px">IP</th>
                            <th style="width:150px">Thời gian</th>
                            <th style="width:80px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) $context['items'] as $item): ?>
                            <tr class="<?php echo !empty($item['is_read']) ? '' : 'laca-cf-row-unread'; ?>">
                                <td><?php echo esc_html((string) $item['id']); ?></td>
                                <td>
                                    <?php if (!empty($item['is_read'])): ?>
                                        <span title="Đã đọc" style="color:#5cb85c">✓</span>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url((string) $item['mark_url']); ?>" title="Đánh dấu đã đọc" class="laca-cf-mark-read">👁</a>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ((array) $item['values'] as $value): ?>
                                    <td><?php echo esc_html((string) $value); ?></td>
                                <?php endforeach; ?>
                                <td><?php echo esc_html((string) $item['ip_address']); ?></td>
                                <td><?php echo esc_html((string) $item['created_at']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url((string) $item['delete_url']); ?>"
                                       class="button button-small button-link-delete laca-cf-delete-sub">Xoá</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ((int) $context['pages'] > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php for ($i = 1; $i <= (int) $context['pages']; $i++): ?>
                                <a href="<?php echo esc_url((string) $context['page_url'] . '&action=submissions&id=' . (int) $context['form_id'] . '&paged=' . $i); ?>"
                                   class="button button-small <?php echo $i === (int) $context['page'] ? 'button-primary' : ''; ?>">
                                    <?php echo esc_html((string) $i); ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
