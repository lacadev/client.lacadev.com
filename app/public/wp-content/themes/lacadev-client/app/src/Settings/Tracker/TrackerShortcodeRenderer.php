<?php

namespace App\Settings\Tracker;

/**
 * HTML renderers for tracker-facing public shortcodes.
 */
final class TrackerShortcodeRenderer
{
    public static function supportCenter(
        string $title,
        string $extraClass,
        string $endpoint,
        string $formId,
        string $pageUrl
    ): string {
        ob_start();
        ?>
        <style>
            .laca-support-center {
                background: #ffffff;
                border: 1px solid #dbe1ea;
                border-radius: 8px;
                margin: 24px 0;
                padding: clamp(18px, 3vw, 28px);
            }

            .laca-support-center__header {
                margin-bottom: 20px;
            }

            .laca-support-center__header h2 {
                font-size: 24px;
                line-height: 1.25;
                margin: 0 0 6px;
            }

            .laca-support-center__header p,
            .laca-support-center__status {
                color: #64748b;
                margin: 0;
            }

            .laca-support-center__grid {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .laca-support-center label {
                display: grid;
                gap: 7px;
            }

            .laca-support-center label span {
                color: #334155;
                font-size: 13px;
                font-weight: 700;
            }

            .laca-support-center input,
            .laca-support-center select,
            .laca-support-center textarea {
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                font: inherit;
                min-height: 42px;
                padding: 10px 12px;
                width: 100%;
            }

            .laca-support-center textarea {
                min-height: 140px;
                resize: vertical;
            }

            .laca-support-center__wide {
                grid-column: 1 / -1;
            }

            .laca-support-center button {
                background: #0f172a;
                border: 0;
                border-radius: 8px;
                color: #ffffff;
                cursor: pointer;
                font-weight: 700;
                margin-top: 16px;
                min-height: 44px;
                padding: 0 18px;
            }

            .laca-support-center button:disabled {
                cursor: wait;
                opacity: 0.7;
            }

            .laca-support-center__status {
                margin-top: 12px;
            }

            @media (max-width: 720px) {
                .laca-support-center__grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <section class="laca-support-center <?php echo esc_attr($extraClass); ?>" data-laca-support>
            <form id="<?php echo esc_attr($formId); ?>" class="laca-support-center__form" enctype="multipart/form-data">
                <div class="laca-support-center__header">
                    <h2><?php echo esc_html($title); ?></h2>
                    <p>Yêu cầu sẽ được chuyển trực tiếp về hệ thống hỗ trợ LacaDev.</p>
                </div>

                <div class="laca-support-center__grid">
                    <label>
                        <span>Loại yêu cầu</span>
                        <select name="request_type">
                            <option value="request">Yêu cầu hỗ trợ</option>
                            <option value="bug">Báo lỗi</option>
                            <option value="content">Cập nhật nội dung</option>
                            <option value="maintenance">Bảo trì</option>
                            <option value="billing">Thanh toán</option>
                        </select>
                    </label>
                    <label>
                        <span>Họ tên</span>
                        <input type="text" name="contact_name" autocomplete="name">
                    </label>
                    <label>
                        <span>Email</span>
                        <input type="email" name="contact_email" autocomplete="email">
                    </label>
                    <label class="laca-support-center__wide">
                        <span>Nội dung</span>
                        <textarea name="message" rows="5" required></textarea>
                    </label>
                    <label class="laca-support-center__wide">
                        <span>Ảnh đính kèm</span>
                        <input type="file" name="attachments[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
                    </label>
                </div>

                <input type="hidden" name="page_url" value="<?php echo esc_url($pageUrl); ?>">
                <button type="submit">Gửi yêu cầu</button>
                <p class="laca-support-center__status" role="status" aria-live="polite"></p>
            </form>
        </section>
        <script>
        (function() {
            const form = document.getElementById('<?php echo esc_js($formId); ?>');
            if (!form) return;
            const status = form.querySelector('.laca-support-center__status');
            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                const data = new FormData(form);
                button.disabled = true;
                status.textContent = 'Đang gửi...';
                try {
                    const response = await fetch('<?php echo esc_url_raw($endpoint); ?>', {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin'
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Không gửi được yêu cầu.');
                    }
                    form.reset();
                    status.textContent = payload.message || 'Yêu cầu đã được gửi.';
                } catch (error) {
                    status.textContent = error.message || 'Không gửi được yêu cầu.';
                } finally {
                    button.disabled = false;
                }
            });
        })();
        </script>
        <?php

        return (string) ob_get_clean();
    }

    public static function maintenanceTimeline(string $title, string $extraClass, array $items): string
    {
        ob_start();
        ?>
        <style>
            .laca-maintenance-timeline {
                background: #ffffff;
                border: 1px solid #dbe1ea;
                border-radius: 8px;
                margin: 24px 0;
                padding: clamp(18px, 3vw, 28px);
            }

            .laca-maintenance-timeline__head {
                align-items: flex-start;
                display: flex;
                gap: 16px;
                justify-content: space-between;
                margin-bottom: 18px;
            }

            .laca-maintenance-timeline h2 {
                font-size: 24px;
                line-height: 1.25;
                margin: 0 0 6px;
            }

            .laca-maintenance-timeline__head p,
            .laca-maintenance-timeline__empty,
            .laca-maintenance-timeline__time {
                color: #64748b;
                margin: 0;
            }

            .laca-maintenance-timeline__count {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 999px;
                color: #334155;
                font-size: 13px;
                font-weight: 700;
                padding: 6px 10px;
                white-space: nowrap;
            }

            .laca-maintenance-timeline__list {
                display: grid;
                gap: 12px;
                margin: 0;
                padding: 0;
            }

            .laca-maintenance-timeline__item {
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                display: grid;
                gap: 8px;
                list-style: none;
                padding: 14px;
            }

            .laca-maintenance-timeline__meta {
                align-items: center;
                display: flex;
                gap: 8px;
                justify-content: space-between;
            }

            .laca-maintenance-timeline__title {
                color: #0f172a;
                font-size: 16px;
                font-weight: 700;
                margin: 0;
            }

            .laca-maintenance-timeline__message {
                color: #334155;
                line-height: 1.55;
                margin: 0;
            }

            .laca-maintenance-timeline__badge {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 999px;
                color: #475569;
                font-size: 12px;
                font-weight: 700;
                padding: 4px 8px;
                white-space: nowrap;
            }

            .laca-maintenance-timeline__badge--done {
                background: #f0fdf4;
                border-color: #bbf7d0;
                color: #047857;
            }

            .laca-maintenance-timeline__badge--attention {
                background: #fef2f2;
                border-color: #fecaca;
                color: #b91c1c;
            }

            .laca-maintenance-timeline__badge--pending {
                background: #fffbeb;
                border-color: #fde68a;
                color: #92400e;
            }

            @media (max-width: 640px) {
                .laca-maintenance-timeline__head,
                .laca-maintenance-timeline__meta {
                    display: grid;
                }
            }
        </style>
        <section class="laca-maintenance-timeline <?php echo esc_attr($extraClass); ?>">
            <div class="laca-maintenance-timeline__head">
                <div>
                    <h2><?php echo esc_html($title); ?></h2>
                    <p><?php echo esc_html__('Các cập nhật, bảo trì và yêu cầu hỗ trợ đã được ghi nhận cho website này.', 'laca'); ?></p>
                </div>
                <span class="laca-maintenance-timeline__count"><?php echo esc_html((string) count($items)); ?> <?php echo esc_html__('mục', 'laca'); ?></span>
            </div>

            <?php if (empty($items)) : ?>
                <p class="laca-maintenance-timeline__empty"><?php echo esc_html__('Chưa có hoạt động bảo trì nào được ghi nhận.', 'laca'); ?></p>
            <?php else : ?>
                <ol class="laca-maintenance-timeline__list">
                    <?php foreach ($items as $item) : ?>
                        <li class="laca-maintenance-timeline__item">
                            <div class="laca-maintenance-timeline__meta">
                                <p class="laca-maintenance-timeline__title"><?php echo esc_html($item['title']); ?></p>
                                <span class="laca-maintenance-timeline__badge laca-maintenance-timeline__badge--<?php echo esc_attr($item['tone']); ?>">
                                    <?php echo esc_html($item['status_label']); ?>
                                </span>
                            </div>
                            <p class="laca-maintenance-timeline__message"><?php echo esc_html($item['message']); ?></p>
                            <p class="laca-maintenance-timeline__time"><?php echo esc_html(TrackerTimelinePresenter::formatDate($item['time'])); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}
