<?php

namespace App\Settings\ThemeControlTabs;

class Assets
{
    public function inlineCss(): string
    {
        return '
.laca-cc { max-width: 1200px; }
.laca-cc__heading { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.laca-cc__heading span:first-child { font-size: 28px; }
.laca-cc__version { font-size: 12px; color: #999; font-weight: normal; background: #f0f0f0; padding: 2px 8px; border-radius: 10px; }
.laca-cc__panel { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; }
.laca-cc__panel-content { min-height: 200px; }
.laca-cc__panel-footer { padding-top: 16px; border-top: 1px solid #f0f0f0; margin-top: 20px; }
.laca-cc-inline-check { display: inline-flex; align-items: center; gap: 6px; margin-right: 18px; }
.laca-cta-admin { display: grid; grid-template-columns: minmax(420px, 1fr) minmax(340px, 460px); gap: 28px; align-items: start; }
.laca-cta-admin .form-table { margin-top: 0; }
.laca-cta-admin .regular-text { width: min(100%, 350px); }
.laca-cta-color-grid { display: grid; gap: 10px 16px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); max-width: 520px; }
.laca-cta-color-grid label { align-items: center; display: flex; font-weight: 600; gap: 8px; justify-content: space-between; }
.laca-cta-color-grid input { height: 32px; width: 46px; }
.laca-cta-preview { border: 1px solid #dbe1ea; border-radius: 10px; background: #f8fafc; padding: 18px; position: sticky; top: 52px; }
.laca-cta-preview__head { align-items: center; display: flex; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.laca-cta-preview__head h3 { margin: 0; font-size: 14px; }
.laca-cta-preview__grid { display: grid; gap: 16px; }
.laca-cta-preview__label { color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .04em; margin-bottom: 6px; text-transform: uppercase; }
.laca-cta-preview__screen { background: #eef2f7; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; position: relative; }
.laca-cta-preview__screen--desktop { height: 180px; }
.laca-cta-preview__screen--mobile { height: 280px; margin: 0 auto; max-width: 210px; border-radius: 20px; }
.laca-cta-preview__page { height: 100%; background: linear-gradient(#fff 0 0) 24px 22px / 45% 10px no-repeat, linear-gradient(#e2e8f0 0 0) 24px 44px / 72% 8px no-repeat, linear-gradient(#e2e8f0 0 0) 24px 62px / 56% 8px no-repeat, #f8fafc; }
.laca-cta-preview__bar { align-items: center; background: #2563eb; bottom: 0; box-shadow: 0 -8px 20px rgba(15, 23, 42, .16); color: #fff; display: flex; gap: 10px; justify-content: center; left: 0; min-height: 42px; padding: 8px 14px; position: absolute; right: 0; }
.laca-cta-preview__button { color: #fff; display: inline-flex; font-size: 13px; font-weight: 700; max-width: calc(100% - 34px); overflow: hidden; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.laca-cta-preview__bar button { align-items: center; background: transparent; border: 0; color: rgba(255,255,255,.8); display: inline-flex; font-size: 18px; height: 24px; justify-content: center; line-height: 1; margin-left: auto; padding: 0; width: 24px; }
.laca-cta-preview__hidden { align-items: center; background: rgba(248,250,252,.9); color: #64748b; display: none; font-size: 13px; font-weight: 700; inset: 0; justify-content: center; position: absolute; text-align: center; }
.laca-cta-preview__device.is-hidden .laca-cta-preview__bar { display: none; }
.laca-cta-preview__device.is-hidden .laca-cta-preview__hidden { display: flex; }
@media (max-width: 1100px) {
    .laca-cta-admin { grid-template-columns: 1fr; }
    .laca-cta-preview { position: static; }
}
';
    }
}
