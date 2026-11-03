/* eslint-disable no-console */
/**
 * Critical CSS Generator
 * Run via: npm run critical
 */
// Critical module will be imported dynamically
const path = require('path');
const config = require('../../config.json');

// Allow skipping critical CSS generation in environments where Chromium can't launch
// Usage: SKIP_CRITICAL=1 yarn build
if (process.env.SKIP_CRITICAL === '1') {
    console.log('ℹ️  SKIP_CRITICAL=1, skipping Critical CSS generation.');
    process.exit(0);
}

// Get dev URL from config or fallback
const baseUrl = (config.development && config.development.url) ? config.development.url.replace(/\/+$/, '') : 'http://lacadev-client.local';

// Mỗi loại trang 1 file critical-{type}.css — khớp filter lacadev_critical_css_path
// trong app/hooks.php. "home" đồng thời ghi ra critical.css làm fallback chung.
// ?lacadev_no_critical=1: lấy CSS gốc dạng blocking; nếu không, link đã bị đổi
// sang media="print" (async) và critical bỏ sót rule → layout nhảy (CLS).
const defaultPages = {
    home: '/',
    single: '/',
    page: '/',
};
const configuredPages = (config.critical && config.critical.pages) ? config.critical.pages : defaultPages;
const sampleUrls = {};
for (const [type, uri] of Object.entries(configuredPages)) {
    sampleUrls[type] = uri.startsWith('http') ? uri : `${baseUrl}${uri.startsWith('/') ? '' : '/'}${uri}`;
}

console.log(`Starting Critical CSS generation for ${Object.keys(sampleUrls).length} page types...`);

// Resolve paths
// theme/dist is where we want to save
const distPath = path.resolve(__dirname, '../../dist/');

// Dimensions to check
const dimensions = [
    {
        height: 640,
        width: 360, // Mobile
    },
    {
        height: 1024,
        width: 768, // Tablet
    },
    {
        height: 900,
        width: 1200, // Desktop
    },
];

(async () => {
    try {
        const { generate } = await import('critical');
        const puppeteer = require('puppeteer');
        const fs = require('fs');
        // Prefer system Chrome over old bundled Chromium (r722234) which times out on macOS 15+
        const systemChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
        const browserLaunchOptions = {
            headless: true,
            timeout: 120000,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors'],
        };
        const penthouseArgs = {
            // Local WP pages may include many third-party scripts; block JS requests
            // so Penthouse can focus on CSS and avoid waiting on long-running assets.
            blockJSRequests: true,
            // 30s default is often too short for local environments.
            timeout: 120000,
            // Stop waiting for full page load after a grace period.
            pageLoadSkipTimeout: 15000,
            // Small buffer to let above-the-fold styles settle.
            renderWaitTime: 1000,
            // Critical trích từ site LOCAL, mà local thiếu dữ liệu header của
            // production (logo dạng ảnh, menu trái) → rule header bị loại vì "không
            // dùng" → trên production logo hiện ảnh gốc 2560px rồi co lại (CLS 0.35).
            // Header luôn ở màn hình đầu nên giữ toàn bộ rule của nó.
            forceInclude: [/\.header/, /\.menu-item/],
            // Penthouse supports custom browser via puppeteer.getBrowser.
            // This avoids fallback to old bundled Chromium revisions.
            puppeteer: {
                getBrowser: () => puppeteer.launch(browserLaunchOptions),
                pageGotoOptions: {
                    waitUntil: 'domcontentloaded',
                    timeout: 60000,
                },
            },
        };
        if (fs.existsSync(systemChrome)) {
            browserLaunchOptions.executablePath = systemChrome;
        }

        const stylesDir = path.join(distPath, 'styles');
        fs.mkdirSync(stylesDir, { recursive: true });

        // @font-face của child (font thật + fallback metric) phải có ngay trong
        // critical: thiếu thì lần render đầu dùng sans-serif mặc định rồi nhảy khi
        // child.css async tải xong. Penthouse bỏ @font-face (ignore bên dưới) vì nó
        // gom cả Quicksand của parent → tự lấy từ child.css, đổi url tương đối
        // ../fonts/ sang đường dẫn từ gốc site (inline <style> resolve theo URL trang).
        const themeDirName = path.basename(path.resolve(__dirname, '../..'));
        const fontBaseUrl = `/wp-content/themes/${themeDirName}/dist/fonts/`;
        const childCssPath = path.join(stylesDir, 'child.css');
        const fontFaceCss = fs.existsSync(childCssPath)
            ? (fs.readFileSync(childCssPath, 'utf8').match(/@font-face\{[^}]*\}/g) || [])
                  .join('')
                  .replace(/url\(\.\.\/fonts\//g, `url(${fontBaseUrl}`)
            : '';

        const cookie = process.env.CRITICAL_COOKIE || '';

        for (const [type, url] of Object.entries(sampleUrls)) {
            try {
                const src = `${url}${url.includes('?') ? '&' : '?'}lacadev_no_critical=1`;
                let requestHeaders = {};
                const html = await fetch(src).then((r) => r.text());
                if (html.includes('woocommerce-coming-soon-store-only')) {
                    if (!cookie) {
                        const keptFile = path.join(stylesDir, `critical-${type}.css`);
                        if (fs.existsSync(keptFile)) {
                            const kept = fs.readFileSync(keptFile, 'utf8').replace(/@font-face\{[^}]*\}/g, '').replace(/^\n+/, '');
                            fs.writeFileSync(keptFile, fontFaceCss + '\n' + kept);
                        }
                        console.warn(`⚠️ [${type}] đang là trang Coming soon — giữ critical-${type}.css cũ.`);
                        continue;
                    }
                    requestHeaders = { Cookie: cookie };
                }
                console.log(`Extracting critical CSS [${type}] from: ${src}`);
                const result = await generate({
                    base: distPath,
                    src,
                    inline: false,
                    extract: false,
                    dimensions: dimensions,
                    ignore: {
                        atrule: ['@font-face'],
                    },
                    request: { headers: requestHeaders },
                    penthouse: { ...penthouseArgs, customPageHeaders: requestHeaders },
                });
                let css = result && result.css ? result.css : '';
                if (!css) {
                    console.warn(`⚠️ Empty critical CSS for ${type}, skipped.`);
                    continue;
                }

                if (fontFaceCss) {
                    css = fontFaceCss + '\n' + css;
                }

                fs.writeFileSync(path.join(stylesDir, `critical-${type}.css`), css);
                if (type === 'home') {
                    fs.writeFileSync(path.join(stylesDir, 'critical.css'), css);
                }
                console.log(`✅ critical-${type}.css (${(css.length / 1024).toFixed(1)} KB)`);
            } catch (pageErr) {
                console.warn(`⚠️ Warning: could not extract critical CSS for ${url}:`, pageErr.message);
            }
        }
    } catch (err) {
        console.error('❌ Critical CSS Generation Failed:', err);
        if (process.env.CRITICAL_STRICT === '1') {
            process.exit(1);
        }
        console.log('ℹ️  Continuing build (set CRITICAL_STRICT=1 to fail on critical errors).');
        process.exit(0);
    }
})();
