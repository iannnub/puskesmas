const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const VERCEL_URL = 'https://sivo-wuluhan.vercel.app/';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function verifyDeployment() {
    console.log('=== Verifying Vercel Live Deployment ===');
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--window-size=1440,900']
    });

    const page = await browser.newPage();
    const consoleErrors = [];

    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleErrors.push({ text: msg.text() });
            console.error('Vercel Console Error:', msg.text());
        }
    });

    // Wait up to 60s for Vercel to reflect new commit
    let updated = false;
    for (let attempt = 1; attempt <= 12; attempt++) {
        console.log(`Checking deployment attempt ${attempt}/12...`);
        try {
            await page.goto(VERCEL_URL + '?v=' + Date.now(), { waitUntil: 'networkidle2', timeout: 15000 });
            
            const check = await page.evaluate(() => {
                const hasOldGradient = !!document.querySelector('.bg-gradient');
                const orbCount = document.querySelectorAll('.floating-orb').length;
                const ctaBtn = document.querySelector('.btn-primary');
                const btnBg = ctaBtn ? window.getComputedStyle(ctaBtn).backgroundColor : null;
                const bodyBg = window.getComputedStyle(document.body).backgroundColor;
                return { hasOldGradient, orbCount, btnBg, bodyBg };
            });

            console.log('Live inspection status:', check);
            if (!check.hasOldGradient && check.orbCount === 0) {
                console.log('SUCCESS: Live Vercel site is running the updated clean UI!');
                updated = true;
                break;
            }
        } catch (e) {
            console.log('Attempt error:', e.message);
        }
        await sleep(5000);
    }

    // Capture Desktop screenshot
    await page.setViewport({ width: 1440, height: 900 });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'vercel_live_desktop_post_deploy.png') });

    // Capture Mobile screenshot
    await page.setViewport({ width: 375, height: 812, isMobile: true, hasTouch: true });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'vercel_live_mobile_post_deploy.png') });

    // Measure live Core Web Vitals
    await page.setViewport({ width: 1440, height: 900 });
    const cwv = await page.evaluate(() => {
        return new Promise(resolve => {
            let cls = 0;
            let lcp = 0;
            try {
                const po = new PerformanceObserver((entryList) => {
                    for (const entry of entryList.getEntries()) {
                        if (!entry.hadRecentInput) cls += entry.value;
                    }
                });
                po.observe({ type: 'layout-shift', buffered: true });

                const poLcp = new PerformanceObserver((entryList) => {
                    const entries = entryList.getEntries();
                    if (entries.length > 0) lcp = entries[entries.length - 1].startTime;
                });
                poLcp.observe({ type: 'largest-contentful-paint', buffered: true });
            } catch (e) {}

            setTimeout(() => {
                const nav = performance.getEntriesByType('navigation')[0] || {};
                resolve({
                    cls: Number(cls.toFixed(4)),
                    lcp: Math.round(lcp || nav.domContentLoadedEventEnd || 0),
                    ttfb: Math.round(nav.responseStart - nav.requestStart || 0),
                    domReady: Math.round(nav.domContentLoadedEventEnd - nav.startTime || 0)
                });
            }, 300);
        });
    });

    console.log('Live Vercel CWV:', cwv);
    console.log('Live Console Errors count:', consoleErrors.length);

    await browser.close();

    fs.writeFileSync(
        path.join(__dirname, 'vercel_audit_report.json'),
        JSON.stringify({ updated, cwv, consoleErrors }, null, 2)
    );
}

verifyDeployment();
