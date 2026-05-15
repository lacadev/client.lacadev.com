<?php

namespace App\Features\ContactForm;

final class ContactFormEditPageRenderer
{
    public static function render(array $context): string
    {
        ob_start();
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1><?php echo !empty($context['is_new']) ? '+ Tạo Form Mới' : '✏️ Sửa Form: ' . esc_html($context['form_name']); ?></h1>
                    <p class="laca-cf-subtitle">
                        <a href="<?php echo esc_url($context['page_url']); ?>">← Quay lại danh sách</a>
                        <?php if (empty($context['is_new'])): ?>
                            &nbsp;|&nbsp;
                            Shortcode: <code onclick="navigator.clipboard.writeText('[laca_contact_form id=&quot;<?php echo esc_js((string) $context['form_id']); ?>&quot;]').then(()=>alert('Đã copy!'))" style="cursor:pointer" title="Click để copy">[laca_contact_form id="<?php echo esc_html($context['form_id']); ?>"]</code>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($context['message'])): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($context['message']['type']); ?>">
                    <?php echo esc_html($context['message']['text']); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url($context['form_action']); ?>" id="laca-cf-form">
                <?php wp_nonce_field((string) $context['nonce_action'], (string) $context['nonce_field']); ?>
                <input type="hidden" name="action" value="laca_cf_save">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($context['form_id']); ?>">
                <input type="hidden" name="fields_json" id="fields-json-input" value="<?php echo esc_attr(wp_json_encode($context['rows'])); ?>">
                <input type="hidden" name="style_json" id="style-json-input" value="<?php echo esc_attr((string) $context['style_json']); ?>">

                <div class="laca-cf-builder-shell">
                    <div class="laca-cf-builder-controls">
                        <div class="lcf-tabs">
                            <button type="button" class="lcf-tab-btn is-active" data-tab="settings">⚙ Cài đặt</button>
                            <button type="button" class="lcf-tab-btn" data-tab="fields">⊞ Trường</button>
                            <button type="button" class="lcf-tab-btn" data-tab="styles">✦ Giao diện</button>
                            <button type="button" class="lcf-tab-btn" data-tab="emails">✉ Email</button>
                        </div>

                        <div id="lcf-panel-settings" class="lcf-tab-panel is-active">
                            <div class="lcf-panel-inner">
                                <div class="laca-cf-field-group">
                                    <label for="cf-name" class="lcf-form-label">Tên form <span class="required">*</span></label>
                                    <input type="text" id="cf-name" name="form_name" class="widefat"
                                           value="<?php echo esc_attr((string) $context['form_name']); ?>"
                                           placeholder="VD: Form Tư Vấn Miễn Phí" required>
                                </div>
                                <div class="laca-cf-field-group">
                                    <label for="cf-notify-email" class="lcf-form-label">Email nhận thông báo</label>
                                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;cursor:pointer;font-weight:600;font-size:13px;">
                                        <input type="checkbox" id="cf-use-admin-email" name="use_admin_notify_email" value="1"
                                               <?php checked(true, (bool) $context['use_admin_notify_email']); ?>>
                                        Dùng Administration Email Address
                                        <code><?php echo esc_html((string) $context['admin_email']); ?></code>
                                    </label>
                                    <input type="email" id="cf-notify-email" name="notify_email" class="widefat"
                                           value="<?php echo esc_attr((string) $context['notify_email']); ?>"
                                           placeholder="Nhập email khác nếu không muốn dùng Administration Email Address"
                                           <?php disabled((bool) $context['use_admin_notify_email']); ?>>
                                    <p class="description">Mặc định form sẽ gửi thông báo về Administration Email Address. Chỉ nhập email ở ô trên khi muốn dùng email nhận thông báo khác.</p>
                                </div>
                                <div class="laca-cf-field-group">
                                    <label for="s-form-mode" class="lcf-form-label">Kiểu hiển thị form</label>
                                    <select id="s-form-mode" class="widefat" onchange="lcfStyleUpdate('form_mode',this.value)">
                                        <option value="standard">Form thường</option>
                                        <option value="multi_step">Form từng bước</option>
                                    </select>
                                    <p class="description">Dùng "Ngắt bước (Step)" trong tab Trường để chia form thành nhiều bước.</p>
                                </div>
                                <div id="lcf-step-settings" class="lcf-step-settings">
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Nút tiếp theo</label>
                                        <input type="text" id="s-step-next-text" class="widefat"
                                               oninput="lcfStyleUpdate('step_next_text',this.value)"
                                               placeholder="Tiếp theo">
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Nút quay lại</label>
                                        <input type="text" id="s-step-prev-text" class="widefat"
                                               oninput="lcfStyleUpdate('step_prev_text',this.value)"
                                               placeholder="Quay lại">
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Nút gửi cuối</label>
                                        <input type="text" id="s-step-submit-text" class="widefat"
                                               oninput="lcfStyleUpdate('step_submit_text',this.value)"
                                               placeholder="Gửi thông tin">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="lcf-panel-fields" class="lcf-tab-panel">
                            <div class="lcf-panel-inner">
                                <p class="description" style="margin-bottom:12px;color:#888">
                                    Thêm hàng layout, rồi thêm field vào từng cột. Kéo để di chuyển.
                                </p>
                                <div id="rows-builder" class="laca-cf-rows-builder"></div>
                                <p id="rows-empty-msg" class="laca-cf-fields-empty" style="<?php echo empty($context['rows']) ? '' : 'display:none'; ?>">
                                    Chưa có hàng nào. Thêm hàng bên dưới.
                                </p>
                                <div class="laca-cf-add-row-palette">
                                    <span class="lcf-palette-label">+ Thêm hàng:</span>
                                    <button type="button" class="lcf-add-row-btn lcf-add-step-btn" onclick="lcfAddStep()">
                                        + Thêm bước
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('1')">
                                        <span class="lcf-row-preview lcf-rp-1"></span>1 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('2')">
                                        <span class="lcf-row-preview lcf-rp-2"></span>2 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('3')">
                                        <span class="lcf-row-preview lcf-rp-3"></span>3 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('4')">
                                        <span class="lcf-row-preview lcf-rp-4"></span>4 cột
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('1-2')">
                                        <span class="lcf-row-preview lcf-rp-1-2"></span>1/3 + 2/3
                                    </button>
                                    <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('2-1')">
                                        <span class="lcf-row-preview lcf-rp-2-1"></span>2/3 + 1/3
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="lcf-panel-styles" class="lcf-tab-panel">
                            <div class="lcf-panel-inner">
                                <div class="lcf-style-grid">
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu chính (Nút bấm)</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-primary-color" oninput="lcfStyleUpdate('primary_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-primary-color-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('primary_color',this.value);document.getElementById('s-primary-color').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu phụ (Hover nút)</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-secondary-color" oninput="lcfStyleUpdate('secondary_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-secondary-color-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('secondary_color',this.value);document.getElementById('s-secondary-color').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu viền Input</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-input-border" oninput="lcfStyleUpdate('input_border_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-input-border-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('input_border_color',this.value);document.getElementById('s-input-border').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Màu chữ Label</label>
                                        <div class="lcf-color-row">
                                            <input type="color" id="s-label-color" oninput="lcfStyleUpdate('label_color',this.value)">
                                            <input type="text" class="lcf-color-text" id="s-label-color-text" maxlength="7"
                                                   oninput="lcfStyleUpdate('label_color',this.value);document.getElementById('s-label-color').value=this.value">
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Bo góc Nút bấm (px)</label>
                                        <div class="lcf-range-row">
                                            <input type="range" min="0" max="50" id="s-btn-radius"
                                                   oninput="lcfStyleUpdate('btn_border_radius',this.value);document.getElementById('s-btn-radius-num').value=this.value">
                                            <input type="number" min="0" max="50" id="s-btn-radius-num" class="lcf-range-num"
                                                   oninput="lcfStyleUpdate('btn_border_radius',this.value);document.getElementById('s-btn-radius').value=this.value">
                                            <span class="lcf-range-unit">px</span>
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Bo góc Input (px)</label>
                                        <div class="lcf-range-row">
                                            <input type="range" min="0" max="50" id="s-input-radius"
                                                   oninput="lcfStyleUpdate('input_border_radius',this.value);document.getElementById('s-input-radius-num').value=this.value">
                                            <input type="number" min="0" max="50" id="s-input-radius-num" class="lcf-range-num"
                                                   oninput="lcfStyleUpdate('input_border_radius',this.value);document.getElementById('s-input-radius').value=this.value">
                                            <span class="lcf-range-unit">px</span>
                                        </div>
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Khoảng cách nhập (Padding CSS)</label>
                                        <input type="text" class="widefat" id="s-input-spacing"
                                               oninput="lcfStyleUpdate('input_spacing',this.value)"
                                               placeholder="Ví dụ: 10px 14px">
                                    </div>
                                    <div class="laca-cf-field-group" style="display:flex;align-items:center;">
                                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:13px;margin-top:10px;">
                                            <input type="checkbox" id="s-show-label" onchange="lcfStyleUpdate('show_label',this.checked)">
                                            Hiển thị Label các trường
                                        </label>
                                    </div>
                                    <div class="laca-cf-field-group" style="grid-column:1/-1">
                                        <label class="lcf-form-label">Chữ nút Submit</label>
                                        <input type="text" class="widefat" id="s-btn-text"
                                               oninput="lcfStyleUpdate('btn_text',this.value)"
                                               placeholder="Gửi thông tin">
                                    </div>
                                    <div class="laca-cf-field-group" style="grid-column:1/-1">
                                        <label class="lcf-form-label">Custom CSS</label>
                                        <textarea class="widefat laca-cf-email-body" id="s-custom-css" rows="5"
                                                  oninput="lcfStyleUpdate('custom_css',this.value)"
                                                  placeholder="/* Nhập CSS tuỳ chỉnh...\n Dùng __FORM__ để ám chỉ class chứa form (ví dụ: __FORM__ .laca-cf-input { ... }) */"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="lcf-panel-emails" class="lcf-tab-panel">
                            <div class="lcf-panel-inner">
                                <p class="description" style="margin-bottom:12px;color:#888">
                                    Dùng <code>$tên_field</code> để chèn giá trị. Hỗ trợ HTML — preview hiển thị bên phải.
                                </p>
                                <div class="lcf-email-section">
                                    <h3 class="lcf-email-section-title">Email Admin</h3>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Tiêu đề (Subject)</label>
                                        <input type="text" name="email_admin_subject" class="widefat"
                                               value="<?php echo esc_attr((string) $context['email_admin_subject']); ?>">
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Nội dung (Body — hỗ trợ HTML)</label>
                                        <textarea name="email_admin_body" id="email-admin-body" class="widefat laca-cf-email-body" rows="8"
                                                  oninput="lcfUpdateEmailPreview('admin')"><?php echo esc_textarea((string) $context['email_admin_body']); ?></textarea>
                                    </div>
                                    <div class="laca-cf-var-hint">
                                        <strong>Biến:</strong>
                                        <code>$name</code> <code>$email</code> <code>$phone_number</code>
                                        <code>$message</code> <code>$ip</code> <code>$date</code> <code>$time</code>
                                    </div>
                                </div>
                                <div class="lcf-email-section" style="margin-top:20px">
                                    <h3 class="lcf-email-section-title">Email Khách hàng</h3>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Tiêu đề (Subject) — để trống = không gửi</label>
                                        <input type="text" name="email_customer_subject" class="widefat"
                                               value="<?php echo esc_attr((string) $context['email_customer_subject']); ?>">
                                    </div>
                                    <div class="laca-cf-field-group">
                                        <label class="lcf-form-label">Nội dung (Body — hỗ trợ HTML)</label>
                                        <textarea name="email_customer_body" id="email-customer-body" class="widefat laca-cf-email-body" rows="6"
                                                  oninput="lcfUpdateEmailPreview('customer')"><?php echo esc_textarea((string) $context['email_customer_body']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="lcf-actions-bar">
                            <a href="<?php echo esc_url($context['page_url']); ?>" class="button">Huỷ</a>
                            <button type="submit" class="button button-primary button-large">
                                <?php echo !empty($context['is_new']) ? 'Tạo Form' : 'Lưu Thay Đổi'; ?>
                            </button>
                        </div>
                    </div>

                    <div class="laca-cf-builder-preview">
                        <div class="lcf-preview-switcher">
                            <button type="button" class="lcf-pv-tab is-active" data-pv="form">Form</button>
                            <button type="button" class="lcf-pv-tab" data-pv="email-admin">Email Admin</button>
                            <button type="button" class="lcf-pv-tab" data-pv="email-customer">Email Khách</button>
                        </div>
                        <div class="lcf-preview-viewport">
                            <div id="lcf-pv-form" class="lcf-pv-panel is-active">
                                <div id="lcf-form-preview-output" class="lcf-pv-form-wrap"></div>
                            </div>
                            <div id="lcf-pv-email-admin" class="lcf-pv-panel">
                                <div id="lcf-email-admin-preview-output" class="lcf-pv-email-wrap"></div>
                            </div>
                            <div id="lcf-pv-email-customer" class="lcf-pv-panel">
                                <div id="lcf-email-customer-preview-output" class="lcf-pv-email-wrap"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
            window.LacaContactFormVars = {
                FIELD_TYPES: <?php echo wp_json_encode($context['field_types']); ?>,
                rows: <?php echo wp_json_encode($context['rows']); ?>
            };

            document.addEventListener('DOMContentLoaded', function () {
                var useAdminCheckbox = document.getElementById('cf-use-admin-email');
                var notifyEmailInput = document.getElementById('cf-notify-email');

                if (!useAdminCheckbox || !notifyEmailInput) {
                    return;
                }

                var syncNotifyEmailState = function () {
                    var useAdmin = useAdminCheckbox.checked;
                    notifyEmailInput.disabled = useAdmin;

                    if (useAdmin) {
                        notifyEmailInput.value = '';
                    }
                };

                useAdminCheckbox.addEventListener('change', syncNotifyEmailState);
                syncNotifyEmailState();
            });
        </script>
        <?php

        return (string) ob_get_clean();
    }
}
