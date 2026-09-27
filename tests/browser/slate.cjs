// Render fixture HTML first with tests/render-slate.php, then serve it alongside assets.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = process.env.SLATE_PREVIEW_URL || 'http://127.0.0.1:18892/previews';
const output = process.env.SLATE_BROWSER_OUTPUT || '/tmp/slate-browser';

(async () => {
    fs.mkdirSync(output, {recursive: true});
    const browser = await chromium.launch({channel: 'chrome', headless: true});
    try {
        for (const width of [1366, 390, 320]) {
            const page = await browser.newPage({viewport: {width, height: 1000}});
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            for (const kind of ['invoice', 'estimate', 'paid', 'tax-discount', 'properties']) {
                const failed = [];
                page.on('response', response => { if (response.status() >= 400) failed.push(response.url()); });
                await page.goto(`${base}/slate-${kind}.html`);
                await page.locator('.slate-banner').waitFor();
                const title = await page.locator('.slate-title').innerText();
                assert.equal(title, kind === 'estimate' ? 'ESTIMATE' : 'INVOICE');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${kind} at ${width}px overflows`);
                assert.equal(await page.locator('.slate-items td[data-label]').evaluateAll(cells => cells.every(cell => cell.getBoundingClientRect().right <= innerWidth + 1)), true, `${kind} hides item amounts at ${width}px`);
                const download = await page.getByRole('link', {name: 'Download PDF'}).getAttribute('href');
                assert(download.endsWith('/fixture-document-key/1/Slate'));
                assert(download.includes(kind === 'estimate' ? 'generate_quote_pdf' : 'generate_invoice_pdf'));
                if (kind === 'estimate') {
                    assert.equal(await page.locator('form[method="post"] input[name="csrf_token"]').count(), 2);
                    assert.equal(await page.getByRole('button', {name: 'Approve estimate'}).count(), 1);
                }
                if (kind === 'invoice') assert.equal(await page.getByRole('link', {name: 'Pay Now'}).count(), 1);
                if (kind === 'paid') assert.equal(await page.getByRole('link', {name: 'Pay Now'}).count(), 0);
                if (kind === 'tax-discount') assert.equal(await page.locator('.slate-items thead th').count(), 5);
                assert.deepEqual(failed, []);
                await page.screenshot({path: `${output}/${kind}-${width}.png`, fullPage: true});
                console.log(`PASS ${kind} ${width}px: layout, assets, labels and controls`);
            }
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
