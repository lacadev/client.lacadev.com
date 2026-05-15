<?php

namespace App\Features\ContactForm;

/**
 * Shared frontend CSS for rendered contact forms.
 */
final class ContactFormFrontendAssets
{
    public static function printInlineCss(): void
    {
        echo '<style id="laca-contact-form-css">' . "\n" . self::inlineCss() . "\n" . '</style>' . "\n";
    }

    public static function inlineCss(): string
    {
        return <<<'CSS'
        .laca-contact-form-wrap { max-width: 700px; }
        /* New row-based layout: flex column of layout rows */
        .laca-contact-form { display: flex; flex-direction: column; gap: 16px; align-items: stretch; }
        /* Each layout row uses CSS grid (inline style sets grid-template-columns) */
        .laca-cf-layout-row { align-items: start; }
        /* Mobile: force single column */
        @media (max-width: 640px) {
            .laca-cf-layout-row { grid-template-columns: 1fr !important; }
        }
        /* Old flat-format fields (fallback) */
        .laca-cf-col-12  { grid-column: span 12; }
        .laca-cf-col-6   { grid-column: span 6; }
        .laca-cf-col-4   { grid-column: span 4; }
        .laca-cf-col-3   { grid-column: span 3; }
        .laca-cf-form-row { display: flex; flex-direction: column; gap: 5px; }
        .laca-cf-label { font-weight: 600; font-size: 14px; }
        .laca-cf-required { color: #d9534f; margin-left: 2px; }
        .laca-cf-input,
        .laca-cf-textarea,
        .laca-cf-select {
            width: 100%; padding: 10px 14px; border: 1px solid #ccc;
            border-radius: 6px; font-size: 14px; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
        }
        .laca-cf-input:focus,
        .laca-cf-textarea:focus,
        .laca-cf-select:focus {
            outline: none;
            border-color: var(--cf-primary, var(--primary-color, #2271b1));
            box-shadow: 0 0 0 3px rgba(34,113,177,.15);
        }
        .laca-cf-label { color: var(--cf-label-color, inherit); display: var(--cf-label-display, block); }
        .laca-cf-input, .laca-cf-textarea, .laca-cf-select {
            border-color: var(--cf-input-border, #ccc) !important;
            border-radius: var(--cf-input-radius, 6px) !important;
            padding: var(--cf-input-spacing, 10px 14px) !important;
        }
        .laca-cf-field-invalid { border-color: #d9534f !important; box-shadow: 0 0 0 3px rgba(217,83,79,.15) !important; }
        .laca-cf-field-error { color: #d9534f; font-size: 12px; margin-top: 2px; }
        .laca-cf-conditional-hidden { display: none !important; }
        .laca-cf-radio-group, .laca-cf-checkbox-group { display: flex; flex-direction: column; gap: 8px; }
        .laca-cf-radio-label, .laca-cf-checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; }
        .laca-cf-multiselect { padding: 4px; }
        .laca-cf-hint { margin: 4px 0 0; font-size: 12px; color: #888; }
        .laca-contact-form-wrap--multistep { max-width: 760px; }
        .laca-contact-form--multistep { gap: 20px; }
        .laca-cf-step-progress { display: grid; gap: 14px; margin-bottom: 4px; }
        .laca-cf-step-progress__track { background: #e5e7eb; border-radius: 999px; height: 6px; overflow: hidden; }
        .laca-cf-step-progress__fill { background: var(--cf-primary, var(--primary-color, #2271b1)); border-radius: inherit; height: 100%; transition: width .2s ease; }
        .laca-cf-step-list { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); list-style: none; margin: 0; padding: 0; }
        .laca-cf-step-dot { align-items: center; color: #64748b; display: flex; font-size: 12px; font-weight: 600; gap: 8px; min-width: 0; }
        .laca-cf-step-dot span { align-items: center; background: #f8fafc; border: 1px solid #dbe3ef; border-radius: 999px; display: inline-flex; height: 24px; justify-content: center; width: 24px; }
        .laca-cf-step-dot strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .laca-cf-step-dot.is-active { color: var(--cf-primary, var(--primary-color, #2271b1)); }
        .laca-cf-step-dot.is-active span,
        .laca-cf-step-dot.is-done span { background: var(--cf-primary, var(--primary-color, #2271b1)); border-color: var(--cf-primary, var(--primary-color, #2271b1)); color: #fff; }
        .laca-cf-step-panel { animation: laca-cf-step-in .18s ease; display: flex; flex-direction: column; gap: 16px; }
        @keyframes laca-cf-step-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .laca-cf-step-notice { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; margin: 0; padding: 10px 12px; }
        .laca-cf-step-actions { align-items: center; display: flex; gap: 10px; justify-content: flex-end; }
        .laca-cf-step-btn { border-radius: var(--cf-btn-radius, 6px); cursor: pointer; font-size: 15px; font-weight: 600; padding: 11px 22px; }
        .laca-cf-step-btn--prev { background: #fff; border: 1px solid #d1d5db; color: #374151; margin-right: auto; }
        .laca-cf-step-btn--next { background: var(--cf-primary, var(--primary-color, #2271b1)); border: 0; color: #fff; }
        .laca-cf-step-btn--next:hover,
        .laca-cf-step-btn--submit:hover { background: var(--cf-secondary, var(--secondary-color, #1a5a9e)); }
        /* Submit row */
        .laca-cf-submit-row { flex-direction: row; align-items: center; justify-content: flex-end; }
        .laca-cf-submit-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 28px; background: var(--cf-primary, var(--primary-color, #2271b1));
            color: #fff; border: none; border-radius: var(--cf-btn-radius, 6px); font-size: 15px;
            font-weight: 600; cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .laca-cf-submit-btn:hover  { background: var(--cf-secondary, var(--secondary-color, #1a5a9e)); }
        .laca-cf-submit-btn:active { transform: scale(0.98); }
        .laca-cf-submit-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }
        /* hidden attribute must not be overridden by display:flex */
        [hidden] { display: none !important; }
        .laca-cf-btn-loading { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; }
        /* Spinner */
        @keyframes laca-spin { to { stroke-dashoffset: -31.4; } }
        .laca-cf-spinner circle {
            animation: laca-spin 0.8s linear infinite;
            transform-origin: center;
        }
        /* Fallback message (no Swal) */
        .laca-cf-fallback-msg {
            margin-top: 12px; padding: 12px 14px; border-radius: 8px; font-size: 14px;
            border: 1px solid transparent;
        }
        .laca-cf-fallback-msg:not([hidden]) { display: block; }
        .laca-cf-fallback-msg--success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .laca-cf-fallback-msg--error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .laca-cf-error { color: #d9534f; font-style: italic; }
CSS;
    }
}
