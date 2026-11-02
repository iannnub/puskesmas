const puppeteer = require('puppeteer-core');

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://localhost/puskesmas/';

async function measureCWV() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--window-size=1440,900']
    });

    const page = await browser.newPage();
    await page.goto(BASE_URL + 'auth/login.php', { waitUntil: 'networkidle0' });
    await page.type('#username', 'superadmin');
    await page.type('input[type="password"]', 'superadmin');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button[type="submit"]')
    ]);

    const urls = [
        { name: 'Landing Page', url: BASE_URL + 'index.html' },
        { name: 'Login Page', url: BASE_URL + 'auth/login.php' },
        { name: 'Dashboard', url: BASE_URL + 'dashboard.php' },
        { name: 'Master Data Obat', url: BASE_URL + 'master/data_obat.php' },
        { name: 'Laporan Bulanan', url: BASE_URL + 'laporan/bulanan.php' }
    ];

    console.log('=== CORE WEB VITALS MEASUREMENT ===');
    for (const item of urls) {
        await page.goto(item.url, { waitUntil: 'networkidle0' });
        
        const cwv = await page.evaluate(() => {
            return new Promise((resolve) => {
                let cls = 0;
                let lcp = 0;

                try {
                    const po = new PerformanceObserver((entryList) => {
                        for (const entry of entryList.getEntries()) {
                            if (!entry.hadRecentInput) {
                                cls += entry.value;
                            }
                        }
                    });
                    po.observe({ type: 'layout-shift', buffered: true });

                    const poLcp = new PerformanceObserver((entryList) => {
                        const entries = entryList.getEntries();
                        if (entries.length > 0) {
                            lcp = entries[entries.length - 1].startTime;
                        }
                    });
                    poLcp.observe({ type: 'largest-contentful-paint', buffered: true });
                } catch (e) {}

                setTimeout(() => {
                    const nav = performance.getEntriesByType('navigation')[0] || {};
                    resolve({
                        cls: Number(cls.toFixed(4)),
                        lcp: Math.round(lcp || nav.domContentLoadedEventEnd || 0),
                        ttfb: Math.round(nav.responseStart - nav.requestStart || 0),
                        domLoad: Math.round(nav.domContentLoadedEventEnd - nav.startTime || 0)
                    });
                }, 300);
            });
        });

        console.log(`${item.name.padEnd(20)} | LCP: ${cwv.lcp}ms | CLS: ${cwv.cls} | TTFB: ${cwv.ttfb}ms | DomReady: ${cwv.domLoad}ms`);
    }

    await browser.close();
}

measureCWV();
