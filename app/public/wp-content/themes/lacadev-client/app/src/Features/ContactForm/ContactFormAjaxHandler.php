<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * ContactFormAjaxHandler
 *
 * Xử lý frontend AJAX submission và đăng ký shortcode.
 *
 * Shortcode: [laca_contact_form id="X"]
 *   → Render HTML form và JS validation (Pristine.js)
 *
 * AJAX endpoint: wp_ajax_nopriv_laca_contact_submit (cả logged-in lẫn guest)
 *   → Validate → Lưu DB → Gửi email → Trả JSON
 */
class ContactFormAjaxHandler
{
    public function init(): void
    {
        add_action('wp_ajax_laca_contact_submit',        [$this, 'handleSubmit']);
        add_action('wp_ajax_nopriv_laca_contact_submit', [$this, 'handleSubmit']);
        add_shortcode('laca_contact_form', [$this, 'renderShortcode']);
    }

    // =========================================================================
    // AJAX SUBMIT HANDLER
    // =========================================================================

    public function handleSubmit(): void
    {
        // 1. Nonce check
        if (!check_ajax_referer('laca_contact_submit_nonce', '_nonce', false)) {
            wp_send_json_error(['message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'], 403);
        }

        // 2. Form ID
        $formId = absint($_POST['form_id'] ?? 0);
        if (!$formId) {
            wp_send_json_error(['message' => 'Form không hợp lệ.'], 400);
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form) {
            wp_send_json_error(['message' => 'Form không tồn tại.'], 404);
        }

        $fields = ContactFormSchema::extractFlatFields($form);

        // 3. Validate & Sanitize từng field
        $data   = [];
        $errors = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'step_break') {
                continue;
            }

            if (!ContactFormSchema::isFieldConditionMatched($field, $_POST)) {
                continue;
            }

            $name     = $field['name'];
            $label    = $field['label'];
            $required = !empty($field['required']);
            $type     = $field['type'];

            // Lấy giá trị raw từ POST
            $rawValue = $_POST[$name] ?? '';

            // Multiselect / checkbox gửi dạng array
            if (in_array($type, ['multiselect', 'checkbox'], true)) {
                $rawValue = is_array($rawValue) ? $rawValue : [];
            }

            // Validate required
            if ($required) {
                $isEmpty = is_array($rawValue) ? empty($rawValue) : (trim((string) $rawValue) === '');
                if ($isEmpty) {
                    $errors[] = $label . ' là bắt buộc.';
                    continue;
                }
            }

            // Sanitize theo type
            $cleanValue = ContactFormSchema::sanitizeByType($type, $rawValue, $field);

            // Validate format
            $formatError = ContactFormSchema::validateFormat($type, $cleanValue, $label);
            if ($formatError) {
                $errors[] = $formatError;
                continue;
            }

            $data[$name] = $cleanValue;
        }

        if (!empty($errors)) {
            wp_send_json_error(['message' => implode('<br>', $errors), 'errors' => $errors], 422);
        }

        // 3.5. Verify reCAPTCHA
        $isRecaptchaEnabled = function_exists('getOption') ? getOption('enable_recaptcha_contact') : false;
        if ($isRecaptchaEnabled) {
            $token  = $_POST['laca_recaptcha_response'] ?? '';
            $verify = apply_filters('laca_verify_recaptcha', true, $token);
            if (is_wp_error($verify)) {
                wp_send_json_error(['message' => $verify->get_error_message()], 400);
            }
        }

        // 4. Lấy IP
        $ip = self::getClientIp();

        // 5. Lưu DB
        ContactFormTable::insertSubmission($formId, $data, $ip);

        // 6. Gửi email
        ContactFormEmailService::sendAll($form, $data, $ip);

        wp_send_json_success(['message' => 'Gửi thành công! Chúng tôi sẽ liên hệ lại sớm.']);
    }

    // =========================================================================
    // SHORTCODE RENDERER
    // =========================================================================

    public function renderShortcode(array $atts): string
    {
        $atts   = shortcode_atts(['id' => 0, 'class' => ''], $atts, 'laca_contact_form');
        $formId = absint($atts['id']);

        if (!$formId) {
            return '<p class="laca-cf-error">Thiếu ID form. Dùng: [laca_contact_form id="X"]</p>';
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form || !$form['is_active']) {
            return '<p class="laca-cf-error">Form không tồn tại hoặc đã bị tắt.</p>';
        }

        $rawData    = json_decode($form['fields'] ?? '[]', true) ?: [];
        $isRowBased = !empty($rawData) && isset($rawData[0]['cols']);
        $nonce      = wp_create_nonce('laca_contact_submit_nonce');
        $ajaxUrl    = admin_url('admin-ajax.php');
        $extraClass = sanitize_html_class($atts['class']);
        $formElId   = 'laca-cf-form-' . $formId;
        $wrapId     = 'laca-cf-' . $formId;

        // Build scoped CSS vars từ style_settings
        $styleSettings = json_decode($form['style_settings'] ?? '{}', true) ?: [];
        $scopedCss     = ContactFormSchema::buildScopedCss($wrapId, $styleSettings);

        // Enqueue inline CSS once
        if (!wp_style_is('laca-contact-form', 'done')) {
            add_action('wp_footer', [__CLASS__, 'printInlineCss'], 5);
        }

        if (ContactFormSchema::shouldRenderMultiStep($rawData, $styleSettings)) {
            return $this->renderMultiStepForm(
                $rawData,
                $formId,
                $nonce,
                $ajaxUrl,
                $extraClass,
                $formElId,
                $wrapId,
                $styleSettings,
                $scopedCss
            );
        }

        ob_start();
        ?>
        <?php if ($scopedCss): ?>
        <style><?php echo $scopedCss; // Đã sanitize qua esc_attr trên từng giá trị ?></style>
        <?php endif; ?>
        <div class="laca-contact-form-wrap <?php echo esc_attr($extraClass); ?>" id="<?php echo esc_attr($wrapId); ?>">
            <form class="laca-contact-form" id="<?php echo esc_attr($formElId); ?>" novalidate>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                <input type="hidden" name="action" value="laca_contact_submit">
                <?php if (function_exists('getOption') && getOption('enable_recaptcha_contact')): ?>
                    <input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" value="">
                <?php endif; ?>

                <?php if ($isRowBased): ?>
                    <?php foreach ($rawData as $row): ?>
                        <?php
                        // Skip rows that have no fields at all
                        $hasAnyField = false;
                        foreach ($row['cols'] as $col) {
                            if (!empty($col['fields'])) { $hasAnyField = true; break; }
                        }
                        if (!$hasAnyField) continue;

                        // Build CSS grid-template-columns from col spans
                        $gridCols = implode(' ', array_map(
                            fn($c) => $c['span'] . 'fr',
                            $row['cols']
                        ));
                        ?>
                        <div class="laca-cf-layout-row" style="display:grid;grid-template-columns:<?php echo esc_attr($gridCols); ?>;gap:12px;align-items:start">
                            <?php foreach ($row['cols'] as $col): ?>
                                <?php if (!empty($col['fields'])): ?>
                                    <div class="laca-cf-col-group" style="display:flex;flex-direction:column;gap:12px">
                                        <?php foreach ($col['fields'] as $field): ?>
                                            <?php $this->renderField($field); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($rawData as $field): ?>
                        <?php $this->renderField($field); ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="laca-cf-form-row laca-cf-submit-row">
                    <button type="submit" class="laca-cf-submit-btn" aria-busy="false">
                        <span class="laca-cf-btn-text">Gửi thông tin</span>
                        <span class="laca-cf-btn-loading" hidden aria-hidden="true">
                            <svg class="laca-cf-spinner" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="31.4"/>
                            </svg>
                            Đang gửi...
                        </span>
                    </button>
                </div>
                <p class="laca-cf-fallback-msg" role="status" aria-live="polite" hidden></p>
            </form>
        </div>

        <script<?php echo self::getCspNonceAttribute(); ?>>
        (function() {
            const FORM_ID  = '<?php echo esc_js($formElId); ?>';
            const AJAX_URL = '<?php echo esc_js($ajaxUrl); ?>';

            // Wait for DOM + theme.js to expose window.Swal
            function boot() {
                const formEl = document.getElementById(FORM_ID);
                if (!formEl) return;

                // ── Helpers ──────────────────────────────────────────────────

                const getThemeColors = () => ({
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                    color:      document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff'    : '#000',
                });

                const showSwal = (opts) => {
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({ ...opts, ...getThemeColors() });
                    } else {
                        // Fallback khi Swal chưa load: dùng banner trong form, không dùng native alert.
                        const banner = formEl.querySelector('.laca-cf-fallback-msg');
                        if (banner) {
                            const type = opts.icon === 'success' ? 'success' : 'error';
                            banner.className = 'laca-cf-fallback-msg laca-cf-fallback-msg--' + type;
                            banner.textContent = opts.text || opts.title || 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            banner.hidden = false;
                            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                };

                // Show inline error dưới field
                const showFieldError = (fieldEl, message) => {
                    if (!fieldEl) return;
                    fieldEl.classList.add('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'true');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const errEl = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (errEl) { errEl.textContent = message; errEl.hidden = false; }
                };

                const clearFieldError = (fieldEl) => {
                    if (!fieldEl) return;
                    fieldEl.classList.remove('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'false');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const errEl = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (errEl) { errEl.textContent = ''; errEl.hidden = true; }
                };

                const clearAllErrors = () => {
                    formEl.querySelectorAll('.laca-cf-field-invalid').forEach(clearFieldError);
                };

                const getFieldValue = (name) => {
                    const fields = Array.from(formEl.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]'));
                    if (!fields.length) return '';
                    if (fields[0].type === 'radio') {
                        const checked = fields.find((field) => field.checked);
                        return checked ? checked.value : '';
                    }
                    if (fields[0].type === 'checkbox') {
                        return fields.filter((field) => field.checked).map((field) => field.value);
                    }
                    if (fields[0].tagName === 'SELECT' && fields[0].multiple) {
                        return Array.from(fields[0].selectedOptions).map((option) => option.value);
                    }
                    return fields[0].value || '';
                };

                const conditionMatches = (row) => {
                    const field = row.dataset.conditionField;
                    if (!field) return true;
                    const operator = row.dataset.conditionOperator || 'equals';
                    const expected = row.dataset.conditionValue || '';
                    const value = getFieldValue(field);
                    const values = Array.isArray(value) ? value : [value];
                    const valueString = values.join(', ');

                    switch (operator) {
                        case 'not_equals':
                            return !values.includes(expected);
                        case 'contains':
                            return expected !== '' && valueString.includes(expected);
                        case 'not_empty':
                            return valueString.trim() !== '';
                        case 'empty':
                            return valueString.trim() === '';
                        default:
                            return values.includes(expected);
                    }
                };

                const syncConditionalFields = () => {
                    formEl.querySelectorAll('.laca-cf-form-row[data-condition-field]').forEach(function(row) {
                        const visible = conditionMatches(row);
                        row.classList.toggle('laca-cf-conditional-hidden', !visible);
                        row.hidden = !visible;
                        row.querySelectorAll('input, select, textarea').forEach(function(input) {
                            input.disabled = !visible;
                            if (!visible) {
                                clearFieldError(input);
                            }
                        });
                    });
                };

                // ── Client-side validation ────────────────────────────────────

                const isPhoneValid = (value) => {
                    const normalized = String(value || '').trim();
                    const digits = normalized.replace(/\D/g, '');
                    return /^\+?[0-9\s().-]+$/.test(normalized) && digits.length >= 8 && digits.length <= 15;
                };

                const getFieldLabel = (row) => {
                    const label = row ? row.querySelector('.laca-cf-label') : null;
                    return (label && label.textContent ? label.textContent.replace('*', '').trim() : '') || 'Trường này';
                };

                const getFormatError = (input, row) => {
                    if (!input || input.disabled || input.type === 'hidden') return '';
                    const value = String(input.value || '').trim();
                    if (!value) return '';
                    const label = getFieldLabel(row);

                    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'url' && input.validity && !input.validity.valid) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'tel' && !isPhoneValid(value)) {
                        return label + ' không hợp lệ. Vui lòng nhập tối thiểu 8 chữ số.';
                    }
                    if (input.type === 'number' && (input.validity?.badInput || Number.isNaN(Number(value)))) {
                        return label + ' phải là số hợp lệ.';
                    }

                    return '';
                };

                const isControlEmpty = (control) => {
                    if (!control) return true;
                    if (control.type === 'checkbox' || control.type === 'radio') {
                        const row = control.closest('.laca-cf-form-row');
                        return !row || !row.querySelector('input:checked');
                    }
                    if (control.tagName === 'SELECT' && control.multiple) {
                        return control.selectedOptions.length === 0;
                    }
                    return !String(control.value || '').trim();
                };

                const validateForm = () => {
                    syncConditionalFields();
                    clearAllErrors();
                    let valid = true;
                    let firstInvalid = null;

                    formEl.querySelectorAll('.laca-cf-form-row').forEach(function(row) {
                        if (!row || row.hidden || row.classList.contains('laca-cf-conditional-hidden')) {
                            return;
                        }

                        const controls = Array.from(row.querySelectorAll('input, select, textarea')).filter(function(input) {
                            return !input.disabled && input.type !== 'hidden';
                        });
                        if (!controls.length) {
                            return;
                        }

                        const firstControl = controls[0];
                        const isRequired = !!row.querySelector('[data-required="true"], [required]');
                        if (isRequired && isControlEmpty(firstControl)) {
                            showFieldError(firstControl, getFieldLabel(row) + ' là bắt buộc.');
                            if (!firstInvalid) firstInvalid = firstControl;
                            valid = false;
                        }

                        controls.forEach(function(input) {
                            const message = getFormatError(input, row);
                            if (!message) return;
                            showFieldError(input, message);
                            if (!firstInvalid) firstInvalid = input;
                            valid = false;
                        });
                    });

                    if (firstInvalid && firstInvalid.focus) {
                        firstInvalid.focus({ preventScroll: true });
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    return valid;
                };

                // ── Real-time clear errors on input ───────────────────────────

                formEl.querySelectorAll('input, select, textarea').forEach(function(el) {
                    el.addEventListener('input', function() {
                        clearFieldError(el);
                        syncConditionalFields();
                    });
                    el.addEventListener('change', syncConditionalFields);
                    el.addEventListener('blur', function() {
                        if (el.getAttribute('data-required') === 'true' && !el.value.trim()) {
                            showFieldError(el, 'Trường này là bắt buộc.');
                        } else {
                            clearFieldError(el);
                        }
                    });
                });

                syncConditionalFields();

                // ── Submit handler ────────────────────────────────────────────

                formEl.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (!validateForm()) return;

                    const btn     = formEl.querySelector('.laca-cf-submit-btn');
                    const btnText = btn.querySelector('.laca-cf-btn-text');
                    const btnLoad = btn.querySelector('.laca-cf-btn-loading');

                    // Loading state
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    btnText.hidden = true;
                    btnLoad.hidden = false;

                    fetch(AJAX_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(formEl),
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(json) {
                        if (json.success) {
                            showSwal({
                                title: '✓ Thành công!',
                                text: json.data.message || 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất!',
                                icon: 'success',
                                confirmButtonText: 'Đóng',
                            });
                            formEl.reset();
                            clearAllErrors();
                        } else {
                            const msg = (json.data && json.data.message)
                                ? json.data.message
                                : 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            showSwal({
                                title: '✕ Thất bại',
                                html: '<p>' + msg + '</p>',
                                icon: 'error',
                                confirmButtonText: 'Thử lại',
                            });
                        }
                    })
                    .catch(function() {
                        showSwal({
                            title: '✕ Lỗi kết nối',
                            text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối internet.',
                            icon: 'error',
                            confirmButtonText: 'Đã hiểu',
                        });
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.setAttribute('aria-busy', 'false');
                        btnText.hidden = false;
                        btnLoad.hidden = true;
                    });
                });
            }

            // Boot sau khi DOM ready — Swal sẽ available vì theme.js chạy trước
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private function renderMultiStepForm(
        array $rawData,
        int $formId,
        string $nonce,
        string $ajaxUrl,
        string $extraClass,
        string $formElId,
        string $wrapId,
        array $styleSettings,
        string $scopedCss
    ): string {
        $steps = ContactFormSchema::splitRowsIntoSteps($rawData);
        $totalSteps = max(1, count($steps));
        $nextText = $styleSettings['step_next_text'] ?? 'Tiếp theo';
        $prevText = $styleSettings['step_prev_text'] ?? 'Quay lại';
        $submitText = $styleSettings['step_submit_text'] ?? ($styleSettings['btn_text'] ?? 'Gửi thông tin');

        ob_start();
        ?>
        <?php if ($scopedCss): ?>
        <style><?php echo $scopedCss; ?></style>
        <?php endif; ?>
        <div class="laca-contact-form-wrap laca-contact-form-wrap--multistep <?php echo esc_attr($extraClass); ?>" id="<?php echo esc_attr($wrapId); ?>">
            <form class="laca-contact-form laca-contact-form--multistep" id="<?php echo esc_attr($formElId); ?>" novalidate data-total-steps="<?php echo esc_attr($totalSteps); ?>">
                <input type="hidden" name="_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                <input type="hidden" name="action" value="laca_contact_submit">
                <?php if (function_exists('getOption') && getOption('enable_recaptcha_contact')): ?>
                    <input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" value="">
                <?php endif; ?>

                <div class="laca-cf-step-progress" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr($totalSteps); ?>" aria-valuenow="1">
                    <div class="laca-cf-step-progress__track">
                        <div class="laca-cf-step-progress__fill" style="width:<?php echo esc_attr((string) round(100 / $totalSteps)); ?>%"></div>
                    </div>
                    <ol class="laca-cf-step-list">
                        <?php foreach ($steps as $index => $step): ?>
                            <li class="laca-cf-step-dot <?php echo $index === 0 ? 'is-active' : ''; ?>" data-step-dot="<?php echo esc_attr((string) $index); ?>">
                                <span><?php echo esc_html((string) ($index + 1)); ?></span>
                                <strong><?php echo esc_html($step['label']); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <p class="laca-cf-step-notice" role="alert" aria-live="polite" hidden></p>

                <?php foreach ($steps as $index => $step): ?>
                    <section class="laca-cf-step-panel <?php echo $index === 0 ? 'is-active' : ''; ?>" data-step-panel="<?php echo esc_attr((string) $index); ?>" <?php echo $index === 0 ? '' : 'hidden'; ?>>
                        <?php foreach ($step['rows'] as $row): ?>
                            <?php $this->renderLayoutRow($row); ?>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                <div class="laca-cf-step-actions">
                    <button type="button" class="laca-cf-step-btn laca-cf-step-btn--prev" hidden><?php echo esc_html($prevText); ?></button>
                    <button type="button" class="laca-cf-step-btn laca-cf-step-btn--next"><?php echo esc_html($nextText); ?></button>
                    <button type="submit" class="laca-cf-submit-btn laca-cf-step-btn--submit" aria-busy="false" hidden>
                        <span class="laca-cf-btn-text"><?php echo esc_html($submitText); ?></span>
                        <span class="laca-cf-btn-loading" hidden aria-hidden="true">
                            <svg class="laca-cf-spinner" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="31.4"/>
                            </svg>
                            Đang gửi...
                        </span>
                    </button>
                </div>
                <p class="laca-cf-fallback-msg" role="status" aria-live="polite" hidden></p>
            </form>
        </div>
        <?php $this->printMultiStepScript($formElId, $ajaxUrl); ?>
        <?php
        return ob_get_clean();
    }

    private function renderLayoutRow(array $row): void
    {
        $cols = $row['cols'] ?? [];
        $hasAnyField = false;
        foreach ($cols as $col) {
            foreach ($col['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'step_break') {
                    $hasAnyField = true;
                    break 2;
                }
            }
        }

        if (!$hasAnyField) {
            return;
        }

        $gridCols = implode(' ', array_map(
            fn($c) => ((int) ($c['span'] ?? 12)) . 'fr',
            $cols
        ));
        ?>
        <div class="laca-cf-layout-row" style="display:grid;grid-template-columns:<?php echo esc_attr($gridCols); ?>;gap:12px;align-items:start">
            <?php foreach ($cols as $col): ?>
                <?php if (!empty($col['fields'])): ?>
                    <div class="laca-cf-col-group" style="display:flex;flex-direction:column;gap:12px">
                        <?php foreach ($col['fields'] as $field): ?>
                            <?php $this->renderField($field); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    // =========================================================================
    // RENDER FIELD HELPERS
    // =========================================================================

    public static function renderSingleField(array $field): string
    {
        return ContactFormFieldRenderer::renderSingle($field);
    }

    private function renderField(array $field): void
    {
        (new ContactFormFieldRenderer())->render($field);
    }

    // =========================================================================
    // INLINE CSS
    // =========================================================================

    private function printMultiStepScript(string $formElId, string $ajaxUrl): void
    {
        ?>
        <script<?php echo self::getCspNonceAttribute(); ?>>
        (function() {
            const SCRIPT_EL = document.currentScript;
            const FORM_ID = '<?php echo esc_js($formElId); ?>';
            const AJAX_URL = '<?php echo esc_js($ajaxUrl); ?>';

            function boot() {
                const scopedWrap = SCRIPT_EL ? SCRIPT_EL.previousElementSibling : null;
                const formEl = scopedWrap && scopedWrap.querySelector
                    ? scopedWrap.querySelector('#' + FORM_ID + '.laca-contact-form--multistep')
                    : document.getElementById(FORM_ID);
                if (!formEl) return;
                if (formEl.dataset.lacaMultiStepReady === '1') return;
                formEl.dataset.lacaMultiStepReady = '1';

                const panels = Array.from(formEl.querySelectorAll('.laca-cf-step-panel'));
                if (!panels.length) return;
                const btnPrev = formEl.querySelector('.laca-cf-step-btn--prev');
                const btnNext = formEl.querySelector('.laca-cf-step-btn--next');
                const btnSubmit = formEl.querySelector('.laca-cf-step-btn--submit');
                const notice = formEl.querySelector('.laca-cf-step-notice');
                const fill = formEl.querySelector('.laca-cf-step-progress__fill');
                const progress = formEl.querySelector('.laca-cf-step-progress');
                let current = 0;

                const getThemeColors = () => ({
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000',
                });

                const showSwal = (opts) => {
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({ ...opts, ...getThemeColors() });
                    } else {
                        const banner = formEl.querySelector('.laca-cf-fallback-msg');
                        if (banner) {
                            const type = opts.icon === 'success' ? 'success' : 'error';
                            banner.className = 'laca-cf-fallback-msg laca-cf-fallback-msg--' + type;
                            banner.textContent = opts.text || opts.title || 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            banner.hidden = false;
                            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                };

                const showNotice = (message) => {
                    if (!notice) return;
                    notice.textContent = message;
                    notice.hidden = false;
                    notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                };

                const clearNotice = () => {
                    if (!notice) return;
                    notice.textContent = '';
                    notice.hidden = true;
                };

                const getFieldValue = (name) => {
                    const fields = Array.from(formEl.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]'));
                    if (!fields.length) return '';
                    if (fields[0].type === 'radio') {
                        const checked = fields.find((field) => field.checked);
                        return checked ? checked.value : '';
                    }
                    if (fields[0].type === 'checkbox') {
                        return fields.filter((field) => field.checked).map((field) => field.value);
                    }
                    if (fields[0].tagName === 'SELECT' && fields[0].multiple) {
                        return Array.from(fields[0].selectedOptions).map((option) => option.value);
                    }
                    return fields[0].value || '';
                };

                const conditionMatches = (row) => {
                    const field = row.dataset.conditionField;
                    if (!field) return true;
                    const operator = row.dataset.conditionOperator || 'equals';
                    const expected = row.dataset.conditionValue || '';
                    const value = getFieldValue(field);
                    const values = Array.isArray(value) ? value : [value];
                    const valueString = values.join(', ');

                    switch (operator) {
                        case 'not_equals':
                            return !values.includes(expected);
                        case 'contains':
                            return expected !== '' && valueString.includes(expected);
                        case 'not_empty':
                            return valueString.trim() !== '';
                        case 'empty':
                            return valueString.trim() === '';
                        default:
                            return values.includes(expected);
                    }
                };

                const syncConditionalFields = () => {
                    formEl.querySelectorAll('.laca-cf-form-row[data-condition-field]').forEach((row) => {
                        const visible = conditionMatches(row);
                        row.classList.toggle('laca-cf-conditional-hidden', !visible);
                        row.hidden = !visible;
                        row.querySelectorAll('input, select, textarea').forEach((input) => {
                            input.disabled = !visible;
                            if (!visible) {
                                input.classList.remove('laca-cf-field-invalid');
                                input.setAttribute('aria-invalid', 'false');
                                const err = row.querySelector('.laca-cf-field-error');
                                if (err) {
                                    err.textContent = '';
                                    err.hidden = true;
                                }
                            }
                        });
                    });
                };

                const showFieldError = (fieldEl, message) => {
                    if (!fieldEl) return;
                    fieldEl.classList.add('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'true');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const err = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (err) {
                        err.textContent = message;
                        err.hidden = false;
                    }
                };

                const clearFieldError = (fieldEl) => {
                    if (!fieldEl) return;
                    fieldEl.classList.remove('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'false');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const err = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (err) {
                        err.textContent = '';
                        err.hidden = true;
                    }
                };

                const isPhoneValid = (value) => {
                    const normalized = String(value || '').trim();
                    const digits = normalized.replace(/\D/g, '');
                    return /^\+?[0-9\s().-]+$/.test(normalized) && digits.length >= 8 && digits.length <= 15;
                };

                const getFieldLabel = (row) => {
                    const label = row ? row.querySelector('.laca-cf-label') : null;
                    return (label && label.textContent ? label.textContent.replace('*', '').trim() : '') || 'Trường này';
                };

                const getFormatError = (input, row) => {
                    if (!input || input.disabled || input.type === 'hidden') return '';
                    const value = String(input.value || '').trim();
                    if (!value) return '';
                    const label = getFieldLabel(row);

                    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'url' && input.validity && !input.validity.valid) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'tel' && !isPhoneValid(value)) {
                        return label + ' không hợp lệ. Vui lòng nhập tối thiểu 8 chữ số.';
                    }
                    if (input.type === 'number' && (input.validity?.badInput || Number.isNaN(Number(value)))) {
                        return label + ' phải là số hợp lệ.';
                    }

                    return '';
                };

                const isControlEmpty = (control) => {
                    if (!control) return true;
                    if (control.type === 'checkbox' || control.type === 'radio') {
                        const row = control.closest('.laca-cf-form-row');
                        return !row || !row.querySelector('input:checked');
                    }
                    if (control.tagName === 'SELECT' && control.multiple) {
                        return control.selectedOptions.length === 0;
                    }
                    return !String(control.value || '').trim();
                };

                const validatePanel = (panel) => {
                    syncConditionalFields();
                    let valid = true;
                    let firstInvalid = null;

                    panel.querySelectorAll('.laca-cf-field-error').forEach((err) => {
                        err.textContent = '';
                        err.hidden = true;
                    });
                    panel.querySelectorAll('.laca-cf-field-invalid').forEach(clearFieldError);

                    panel.querySelectorAll('.laca-cf-form-row').forEach((row) => {
                        if (!row || row.hidden || row.classList.contains('laca-cf-conditional-hidden')) {
                            return;
                        }

                        const controls = Array.from(row.querySelectorAll('input, select, textarea')).filter((input) => {
                            return !input.disabled && input.type !== 'hidden';
                        });
                        if (!controls.length) {
                            return;
                        }

                        const firstControl = controls[0];
                        const isRequired = !!row.querySelector('[data-required="true"], [required]');
                        if (isRequired && isControlEmpty(firstControl)) {
                            showFieldError(firstControl, getFieldLabel(row) + ' là bắt buộc.');
                            if (!firstInvalid) {
                                firstInvalid = firstControl;
                            }
                            valid = false;
                        }

                        controls.forEach((input) => {
                            const message = getFormatError(input, row);
                            if (!message) return;
                            showFieldError(input, message);
                            if (!firstInvalid) {
                                firstInvalid = input;
                            }
                            valid = false;
                        });
                    });

                    if (firstInvalid) {
                        firstInvalid.focus({ preventScroll: true });
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    return valid;
                };

                const showStep = (step) => {
                    panels.forEach((panel, index) => {
                        const active = index === step;
                        panel.hidden = !active;
                        panel.classList.toggle('is-active', active);
                    });

                    btnPrev && (btnPrev.hidden = step === 0);
                    btnNext && (btnNext.hidden = step >= panels.length - 1);
                    btnSubmit && (btnSubmit.hidden = step < panels.length - 1);

                    const pct = Math.round(((step + 1) / panels.length) * 100);
                    if (fill) fill.style.width = pct + '%';
                    if (progress) progress.setAttribute('aria-valuenow', String(step + 1));
                    formEl.querySelectorAll('.laca-cf-step-dot').forEach((dot, index) => {
                        dot.classList.toggle('is-active', index === step);
                        dot.classList.toggle('is-done', index < step);
                    });

                    clearNotice();
                    syncConditionalFields();

                    const first = panels[step] ? panels[step].querySelector('input:not([type="hidden"]), select, textarea') : null;
                    if (first) first.focus({ preventScroll: true });
                };

                formEl.querySelectorAll('input, select, textarea').forEach((el) => {
                    el.addEventListener('input', () => {
                        clearFieldError(el);
                        syncConditionalFields();
                    });
                    el.addEventListener('change', syncConditionalFields);
                });

                const goNext = () => {
                    if (!validatePanel(panels[current])) {
                        showNotice('Vui lòng kiểm tra lại các trường được đánh dấu.');
                        return;
                    }
                    current = Math.min(panels.length - 1, current + 1);
                    showStep(current);
                };

                const goPrev = () => {
                    current = Math.max(0, current - 1);
                    showStep(current);
                };

                formEl.addEventListener('click', function(e) {
                    const nextButton = e.target.closest('.laca-cf-step-btn--next');
                    const prevButton = e.target.closest('.laca-cf-step-btn--prev');
                    if (nextButton && formEl.contains(nextButton)) {
                        e.preventDefault();
                        goNext();
                    }
                    if (prevButton && formEl.contains(prevButton)) {
                        e.preventDefault();
                        goPrev();
                    }
                });

                formEl.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!validatePanel(panels[current])) {
                        showNotice('Vui lòng kiểm tra lại các trường được đánh dấu.');
                        return;
                    }

                    const btnText = btnSubmit.querySelector('.laca-cf-btn-text');
                    const btnLoad = btnSubmit.querySelector('.laca-cf-btn-loading');
                    btnSubmit.disabled = true;
                    btnSubmit.setAttribute('aria-busy', 'true');
                    btnText.hidden = true;
                    btnLoad.hidden = false;

                    fetch(AJAX_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(formEl),
                    })
                    .then((res) => res.json())
                    .then((json) => {
                        if (json.success) {
                            showSwal({
                                title: 'Thành công',
                                text: json.data.message || 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất.',
                                icon: 'success',
                                confirmButtonText: 'Đóng',
                            });
                            formEl.reset();
                            current = 0;
                            showStep(current);
                        } else {
                            showSwal({
                                title: 'Thất bại',
                                html: '<p>' + ((json.data && json.data.message) ? json.data.message : 'Đã có lỗi xảy ra. Vui lòng thử lại.') + '</p>',
                                icon: 'error',
                                confirmButtonText: 'Thử lại',
                            });
                        }
                    })
                    .catch(function() {
                        showSwal({
                            title: 'Lỗi kết nối',
                            text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối internet.',
                            icon: 'error',
                            confirmButtonText: 'Đã hiểu',
                        });
                    })
                    .finally(function() {
                        btnSubmit.disabled = false;
                        btnSubmit.setAttribute('aria-busy', 'false');
                        btnText.hidden = false;
                        btnLoad.hidden = true;
                    });
                });

                showStep(0);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
        </script>
        <?php
    }

    public static function printInlineCss(): void
    {
        ContactFormFrontendAssets::printInlineCss();
    }

    private static function getCspNonceAttribute(): string
    {
        return defined('LACA_CSP_NONCE') ? ' nonce="' . esc_attr(LACA_CSP_NONCE) . '"' : '';
    }

    public static function extractFlatFields(array $form): array
    {
        return ContactFormSchema::extractFlatFields($form);
    }

    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return 'unknown';
    }
}
