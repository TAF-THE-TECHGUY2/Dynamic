// Renders design/og-default.html to public/images/og-default.png at 1200x630.
//
// The template references the self-hosted /fonts/*.woff2 files, so it is served
// from public/ for the duration of the render rather than loaded from disk.
//
//   php artisan serve &   # or any server on APP_URL
//   node design/og.mjs
import { copyFileSync, unlinkSync } from 'node:fs';
import puppeteer from 'puppeteer';

const BASE = process.env.APP_URL ?? 'http://127.0.0.1:8000';
const TEMP = 'public/__og-default.html';

copyFileSync('design/og-default.html', TEMP);

try {
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath:
            process.env.CHROME_PATH ??
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox'],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1200, height: 630, deviceScaleFactor: 1 });
    await page.goto(`${BASE}/__og-default.html`, { waitUntil: 'networkidle0' });
    await page.evaluateHandle('document.fonts.ready');
    await page.screenshot({ path: 'public/images/og-default.png' });

    await browser.close();
    console.log('wrote public/images/og-default.png');
} finally {
    unlinkSync(TEMP);
}
