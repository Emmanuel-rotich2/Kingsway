const fs = require('fs');
const puppeteer = require('puppeteer');

const baseUrl = process.env.BASE_URL || 'https://localhost/Kingsway';

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

(async () => {
    const executablePath = process.env.PUPPETEER_EXECUTABLE_PATH
        || ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium']
            .find(candidate => fs.existsSync(candidate));
    const browser = await puppeteer.launch({
        headless: true,
        ...(executablePath ? { executablePath } : {}),
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors'],
    });

    try {
        const page = await browser.newPage();
        const pageErrors = [];
        const failedAssets = [];

        page.on('pageerror', error => pageErrors.push(error.message));
        page.on('requestfailed', request => {
            if (request.url().startsWith(baseUrl)) {
                failedAssets.push(`${request.failure()?.errorText || 'failed'} ${request.url()}`);
            }
        });

        // CI has no school database or credentials. Mock only the public API
        // boundary so the test remains deterministic and creates no database.
        await page.setRequestInterception(true);
        page.on('request', request => {
            const url = new URL(request.url());
            if (url.origin === baseUrl && url.pathname.startsWith('/api/website/')) {
                request.respond({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ success: true, status: 'success', data: [], message: 'CI fixture' }),
                });
                return;
            }
            request.continue();
        });

        const response = await page.goto(`${baseUrl}/index.php`, {
            // The application intentionally keeps realtime polling active, so
            // networkidle2 is not a valid readiness signal.
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        });
        await new Promise(resolve => setTimeout(resolve, 2000));
        assert(response && response.status() === 200, 'Public homepage did not return HTTP 200.');

        const result = await page.evaluate(() => ({
            title: document.title.trim(),
            bodyLength: (document.body?.innerText || '').trim().length,
            hasHeader: Boolean(document.querySelector('header, nav, .navbar')),
            hasMain: Boolean(document.querySelector('main, section.hero, section')),
            hasFatalOutput: /(Fatal error|Parse error|Uncaught Error|Warning:\s)/i.test(document.body?.innerText || ''),
        }));

        assert(result.title.length > 0, 'Document title is empty.');
        assert(result.bodyLength > 200, 'Homepage contains too little rendered content.');
        assert(result.hasHeader, 'Homepage header/navigation was not rendered.');
        assert(result.hasMain, 'Homepage main content was not rendered.');
        assert(!result.hasFatalOutput, 'PHP diagnostic output was rendered into the page.');
        assert(pageErrors.length === 0, `Browser errors: ${pageErrors.join(' | ')}`);
        assert(failedAssets.length === 0, `Local assets failed: ${failedAssets.join(' | ')}`);

        console.log('Public UI smoke test passed.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(`Public UI smoke test failed: ${error.message}`);
    process.exit(1);
});
