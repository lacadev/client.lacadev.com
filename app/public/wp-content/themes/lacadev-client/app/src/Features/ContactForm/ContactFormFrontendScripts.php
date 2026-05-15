<?php

namespace App\Features\ContactForm;

/**
 * Inline scripts used by rendered contact forms.
 */
final class ContactFormFrontendScripts
{
    public static function printSingleStepScript(string $formElId, string $ajaxUrl, string $nonceAttribute = ''): void
    {
        ?>
        <script<?php echo $nonceAttribute; ?>>
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
    }

    public static function printMultiStepScript(string $formElId, string $ajaxUrl, string $nonceAttribute = ''): void
    {
        ?>
        <script<?php echo $nonceAttribute; ?>>
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
}
