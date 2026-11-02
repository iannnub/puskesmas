const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://localhost/puskesmas/';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function auditPages() {
    console.log('=== Starting Full DOM & Browser Audit ===');
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-gpu',
            '--window-size=1440,900'
        ]
    });

    const page = await browser.newPage();
    const results = [];
    const consoleErrors = [];

    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleErrors.push({ url: page.url(), text: msg.text() });
        }
    });

    page.on('pageerror', err => {
        consoleErrors.push({ url: page.url(), text: err.toString() });
    });

    // Helper to evaluate page performance & DOM
    async function auditCurrentPage(pageName, filenamePrefix, isMobile = false) {
        if (isMobile) {
            await page.setViewport({ width: 375, height: 812, isMobile: true, hasTouch: true });
        } else {
            await page.setViewport({ width: 1440, height: 900 });
        }

        const metrics = await page.evaluate(() => {
            const perf = performance.getEntriesByType('navigation')[0] || {};
            const paint = performance.getEntriesByType('paint') || [];
            const fcp = paint.find(p => p.name === 'first-contentful-paint')?.startTime || 0;
            
            // Check for gradients in inline styles and computed styles of major elements
            const gradientElements = [];
            document.querySelectorAll('*').forEach(el => {
                const style = window.getComputedStyle(el);
                const bgImage = style.backgroundImage || '';
                const bg = style.background || '';
                if (bgImage.includes('gradient') || bg.includes('gradient')) {
                    const tag = el.tagName.toLowerCase();
                    const cls = el.className || '';
                    const id = el.id ? `#${el.id}` : '';
                    gradientElements.push(`${tag}${id}${cls ? '.' + cls.split(' ').join('.') : ''} -> ${bgImage.substring(0, 50)}...`);
                }
            });

            // Check horizontal overflow (mobile reflow)
            const docWidth = document.documentElement.offsetWidth;
            const scrollWidth = document.documentElement.scrollWidth;
            const hasHorizontalScroll = scrollWidth > docWidth + 2;

            // Touch target size check (< 44px)
            const smallTouchTargets = [];
            document.querySelectorAll('button, a, input, select, .btn').forEach(el => {
                const rect = el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0 && (rect.width < 44 || rect.height < 44)) {
                    // Only flag visible controls
                    const style = window.getComputedStyle(el);
                    if (style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0') {
                        smallTouchTargets.push(`${el.tagName.toLowerCase()} (${Math.round(rect.width)}x${Math.round(rect.height)}px): "${(el.innerText || el.value || el.name || '').substring(0, 20).trim()}"`);
                    }
                }
            });

            return {
                title: document.title,
                url: window.location.href,
                ttfb: Math.round(perf.responseStart - perf.requestStart || 0),
                domContentLoaded: Math.round(perf.domContentLoadedEventEnd - perf.startTime || 0),
                fcp: Math.round(fcp),
                hasHorizontalScroll,
                docWidth,
                scrollWidth,
                gradientCount: gradientElements.length,
                gradientExamples: gradientElements.slice(0, 5),
                smallTouchTargetCount: smallTouchTargets.length,
                smallTouchTargetExamples: smallTouchTargets.slice(0, 5)
            };
        });

        const screenshotPath = path.join(SCREENSHOT_DIR, `${filenamePrefix}_${isMobile ? 'mobile' : 'desktop'}.png`);
        await page.screenshot({ path: screenshotPath, fullPage: false });

        return { pageName, isMobile, screenshotPath, ...metrics };
    }

    try {
        // 1. Audit Landing Page (index.html)
        console.log('Auditing index.html (Landing Page)...');
        await page.goto(BASE_URL + 'index.html', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Landing Page', '01_landing', false));
        results.push(await auditCurrentPage('Landing Page', '01_landing', true));

        // 2. Audit Login Page
        console.log('Auditing auth/login.php...');
        await page.goto(BASE_URL + 'auth/login.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Login Page', '02_login', false));
        results.push(await auditCurrentPage('Login Page', '02_login', true));

        // 3. Perform Login as Super Admin
        console.log('Performing login as superadmin...');
        await page.setViewport({ width: 1440, height: 900 });
        await page.type('#username', 'superadmin');
        await page.type('input[type="password"]', 'superadmin');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle0' }),
            page.click('button[type="submit"]')
        ]);

        console.log('Current URL after login:', page.url());

        // 4. Audit Dashboard
        console.log('Auditing dashboard.php...');
        results.push(await auditCurrentPage('Dashboard', '03_dashboard', false));
        results.push(await auditCurrentPage('Dashboard', '03_dashboard', true));

        // 5. Audit Master Data Obat
        console.log('Auditing master/data_obat.php...');
        await page.goto(BASE_URL + 'master/data_obat.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Master Data Obat', '04_master_obat', false));
        results.push(await auditCurrentPage('Master Data Obat', '04_master_obat', true));

        // 6. Audit Stok Terima
        console.log('Auditing stok/terima.php...');
        await page.goto(BASE_URL + 'stok/terima.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Stok Penerimaan', '05_stok_terima', false));
        results.push(await auditCurrentPage('Stok Penerimaan', '05_stok_terima', true));

        // 7. Audit Stok Transfer
        console.log('Auditing stok/transfer.php...');
        await page.goto(BASE_URL + 'stok/transfer.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Stok Transfer', '06_stok_transfer', false));
        results.push(await auditCurrentPage('Stok Transfer', '06_stok_transfer', true));

        // 8. Audit Permintaan Obat (Request Gudang)
        console.log('Auditing request/kelola_gudang.php...');
        await page.goto(BASE_URL + 'request/kelola_gudang.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Request Kelola Gudang', '07_request_gudang', false));
        results.push(await auditCurrentPage('Request Kelola Gudang', '07_request_gudang', true));

        // 9. Audit Resep Tambah
        console.log('Auditing resep/tambah.php...');
        await page.goto(BASE_URL + 'resep/tambah.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Input Resep Baru', '08_resep_tambah', false));
        results.push(await auditCurrentPage('Input Resep Baru', '08_resep_tambah', true));

        // 10. Audit Resep History
        console.log('Auditing resep/history.php...');
        await page.goto(BASE_URL + 'resep/history.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Riwayat Resep', '09_resep_history', false));
        results.push(await auditCurrentPage('Riwayat Resep', '09_resep_history', true));

        // 11. Audit Laporan Bulanan (LPLPO)
        console.log('Auditing laporan/bulanan.php...');
        await page.goto(BASE_URL + 'laporan/bulanan.php', { waitUntil: 'networkidle0' });
        results.push(await auditCurrentPage('Laporan Bulanan', '10_laporan_bulanan', false));
        results.push(await auditCurrentPage('Laporan Bulanan', '10_laporan_bulanan', true));

    } catch (err) {
        console.error('Audit execution error:', err);
    } finally {
        await browser.close();
    }

    const report = {
        timestamp: new Date().toISOString(),
        consoleErrors,
        results
    };

    fs.writeFileSync(path.join(__dirname, 'audit_browser_report.json'), JSON.stringify(report, null, 2));
    console.log('=== Audit Report Saved to tests/audit_browser_report.json ===');
    console.log(`Console Errors count: ${consoleErrors.length}`);
    console.log(`Pages Audited: ${results.length}`);
}

auditPages();
