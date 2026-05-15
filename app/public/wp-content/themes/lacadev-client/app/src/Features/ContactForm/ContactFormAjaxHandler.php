<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;
use App\Support\ClientIpResolver;

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

        // 3. Validate & sanitize submitted fields.
        $validation = ContactFormSubmissionValidator::validate($fields, $_POST);
        $data = $validation['data'];
        $errors = $validation['errors'];

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
        $ip = ClientIpResolver::fromGlobals('unknown');

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

        <?php ContactFormFrontendScripts::printSingleStepScript($formElId, $ajaxUrl, self::getCspNonceAttribute()); ?>
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
        ContactFormFrontendScripts::printMultiStepScript($formElId, $ajaxUrl, self::getCspNonceAttribute());
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

}
