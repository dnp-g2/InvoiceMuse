const {chromium} = require('playwright');
const fs = require('fs');
const assert = require('node:assert/strict');
const output = process.env.PROPERTY_BROWSER_OUTPUT || '/tmp/ip-invoice-workspace';
const loginFile = process.env.PROPERTY_TEST_LOGIN_FILE;
if (!loginFile) throw Error('Set PROPERTY_TEST_LOGIN_FILE to the isolated fixture password file');
fs.mkdirSync(output, {recursive:true});
const base = 'http://127.0.0.1:18888/index.php/';
(async () => {
    const browser = await chromium.launch({channel:'chrome',headless:true});
    const page = await browser.newPage({viewport:{width:1440,height:1100}});
    page.setDefaultTimeout(12000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base+'sessions/login');
    await page.locator('#email').fill('admin@example.invalid');
    await page.locator('#password').fill(fs.readFileSync(loginFile,'utf8'));
    await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);
    async function api(path, data) {
        return page.evaluate(({path,data}) => new Promise((resolve,reject) => $.post('/index.php/'+path,data,result => {
            try { resolve(typeof result === 'string' ? JSON.parse(result) : result); } catch (_) { reject(Error('Non-JSON test response')); }
        }).fail(xhr => reject(Error('Test request failed: '+xhr.status)))), {path,data});
    }
    async function create() {
        const result = await api('invoices/ajax/create', {client_id:1,user_id:1,invoice_date_created:'09/17/2026',invoice_group_id:3});
        assert.equal(result.success,1);
        await page.goto(base+'invoices/view/'+result.invoice_id);
        return result.invoice_id;
    }
    async function save() {
        await page.locator('#btn_save_invoice').click();
        await page.waitForURL('**mode=summary');
        await page.waitForSelector('.iw-summary-row');
    }
    async function edit() { await page.getByRole('link',{name:'Edit',exact:true}).click(); await page.waitForSelector('#btn_save_invoice'); }
    async function cancel() { page.once('dialog', d=>d.accept()); await page.locator('#invoice-cancel').click(); await page.waitForURL('**mode=summary'); }
    const id = await create();
    const first = page.locator('#item_table .item').first();
    await first.locator('[name=item_name]').fill('Monthly Lawn Maintenance Service');
    await first.locator('[name=item_description]').fill('April 2026 · Main home');
    await first.locator('[name=item_quantity]').fill('1');
    await first.locator('[name=item_price]').fill('120');
    const opts = await page.locator('.iw-assign-group option').evaluateAll(nodes=>nodes.map(n=>n.value).filter(Boolean));
    assert(opts.length>=2);
    await page.locator('.iw-assign-group').selectOption(opts[0]);
    await page.locator('#invoice-property-picker').selectOption(opts[1]);
    await page.locator('#invoice-add-property').click();
    const second = page.locator('#item_table .item').last();
    await second.locator('[name=item_name]').fill('Monthly Lawn Maintenance Service');
    await second.locator('[name=item_description]').fill('April 2026 · Rental');
    await second.locator('[name=item_quantity]').fill('1');
    await second.locator('[name=item_price]').fill('160');
    await page.screenshot({path:output+'/invoice-editor-desktop.png',fullPage:true});
    await save();
    assert.equal(await page.locator('.iw-balance').innerText(),'$280.00');
    assert.equal(await page.locator('.iw-property').count(),2);
    assert.equal(await page.locator('#item_table input').count(),0);
    await page.screenshot({path:output+'/invoice-summary-desktop.png',fullPage:true});
    console.log('PASS: grouped draft save and readable summary');

    await edit();
    await page.locator('#item_table .item').first().locator('.iw-row-more summary').click();
    await page.locator('#item_table .item').first().locator('.iw-remove').click();
    await page.locator('#item_table .item').first().locator('[name=item_price]').fill('999');
    await cancel();
    assert.equal(await page.locator('.iw-summary-row').count(),2);
    assert.equal(await page.locator('.iw-balance').innerText(),'$280.00');
    await edit();
    await page.locator('#item_table .item').first().locator('.iw-row-more summary').click();
    await page.locator('#item_table .item').first().locator('.iw-remove').click();
    await save();
    assert.equal(await page.locator('.iw-summary-row').count(),1);
    assert.equal(await page.locator('.iw-balance').innerText(),'$160.00');
    console.log('PASS: Cancel preserves saved charges; removal occurs only on Save');

    await edit();
    await page.locator('#invoice-property-picker').selectOption(opts[0]);
    await page.locator('#invoice-add-property').click();
    const added = page.locator('#item_table .item').last();
    await added.locator('[name=item_name]').fill('Monthly Lawn Maintenance Service');
    await added.locator('[name=item_quantity]').fill('1');
    await added.locator('[name=item_price]').fill('120');
    await page.locator('#invoice-settings > summary').click();
    await page.locator('#invoice_number').fill('invalid<number');
    // Read the body in flight: Chrome keeps no copy of no-store responses for response.json().
    let rejected;
    await page.route('**/invoices/ajax/save', async route => { const res = await route.fetch(); const body = await res.text(); rejected = JSON.parse(body); await route.fulfill({response: res, body}); }, {times: 1});
    await page.locator('#btn_save_invoice').click();
    await page.locator('#invoice-errors').waitFor({state: 'visible'});
    assert.equal(rejected.success,0);
    assert.equal(await added.locator('[name=item_price]').inputValue(),'120');
    await page.locator('#invoice_number').fill('WORKSPACE-'+id);
    await page.locator('#invoice_status_id').selectOption('2');
    await save();
    assert.equal(await page.locator('.iw-balance').innerText(),'$280.00');
    assert(await page.getByRole('button',{name:'Record payment',exact:true}).isVisible());
    await page.goto(base+'invoices/view/'+id);
    assert.equal(await page.locator('#btn_save_invoice').count(),0);
    await edit();
    assert.equal(await page.locator('.iw-remove').count(),0);
    assert.equal(await page.locator('#invoice-add-property').count(),0);
    assert.equal(await page.locator('#item_table [name=item_price]').first().isDisabled(),true);
    await page.locator('#invoice-settings > summary').click();
    await page.locator('#invoice_status_id').selectOption('3');
    await save();
    assert.equal(await page.locator('.iw-balance').innerText(),'$280.00');
    console.log('PASS: validation retains entries; issued invoices default to summary and lock addresses');

    for (const width of [1440,768,390]) {
        await page.setViewportSize({width,height:1000});
        await page.screenshot({path:output+'/invoice-summary-'+width+'.png',fullPage:true});
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Horizontal overflow at '+width);
    }
    await page.setViewportSize({width:1440,height:1100});
    await page.getByRole('button',{name:'Record payment',exact:true}).click();
    await page.waitForSelector('#modal-placeholder .modal',{state:'visible'});
    await page.locator('#modal-placeholder [data-dismiss=modal]').first().click();
    await page.waitForSelector('#modal-placeholder .modal',{state:'hidden'});
    const pdf = await page.request.get(base+'service-properties/preview/invoice/'+id);
    assert(pdf.ok()); assert.match(pdf.headers()['content-type'],/pdf/);
    fs.writeFileSync(output+'/invoice-regression.pdf',await pdf.body());
    const publicPath = await page.evaluate(()=>document.querySelector('#invoice-copy-link') !== null);
    assert(publicPath);
    fs.writeFileSync(output+'/ids.json',JSON.stringify({invoice:id}));
    // Existing catalog/task dialogs must populate the selected property, not the last group.
    const importId = await create();
    await page.locator('.iw-assign-group').selectOption(opts[0]);
    let group = page.locator('#item_table .iw-property:visible').first();
    await group.locator('.iw-group-actions .dropdown-toggle').click();
    await group.locator('.btn_add_product').click();
    await page.waitForSelector('#modal-choose-items', {state:'visible'});
    await page.locator('input[name="product_ids[]"]').first().check();
    await page.locator('#modal-choose-items .select-items-confirm').click();
    await page.waitForSelector('#modal-choose-items', {state:'hidden'});
    assert.equal(await page.locator('#item_table .item').last().locator('[name=item_price]').inputValue(), '45.00');
    assert.equal(await page.locator('#item_table .item').last().locator('[name=item_service_property_id]').inputValue(), opts[0]);
    await group.locator('.iw-group-actions .dropdown-toggle').click();
    await group.locator('.btn_add_task').click();
    await page.waitForSelector('#modal-choose-items', {state:'visible'});
    await page.locator('input[name="task_ids[]"]:not(:disabled)').first().check();
    await page.locator('#modal-choose-items .select-items-confirm').click();
    await page.waitForSelector('#modal-choose-items', {state:'hidden'});
    assert.equal(await page.locator('#item_table .item').last().locator('[name=item_price]').inputValue(), '35.00');
    const taskRow = page.locator('#item_table .item').last();
    await taskRow.locator('.iw-row-more summary').click();
    await taskRow.locator('.iw-up').click();
    await save();
    assert.equal(await page.locator('.iw-summary-row').count(),2);
    assert.equal(await page.locator('.iw-balance').innerText(),'$80.00');
    assert.match(await page.locator('.iw-summary-row').first().innerText(),/Workspace test task/);
    await edit();
    for (const width of [768,390]) {
        await page.setViewportSize({width,height:1000});
        await page.screenshot({path:output+'/invoice-editor-'+width+'.png',fullPage:true});
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Editor overflow at '+width);
    }
    await page.locator('#invoice-cancel').click();
    await edit();
    while (await page.locator('#item_table .item').count()) {
        const row = page.locator('#item_table .item').first();
        await row.locator('.iw-row-more summary').click();
        await row.locator('.iw-remove').click();
    }
    await page.locator('#btn_save_invoice').click();
    await page.waitForURL('**mode=summary');
    assert.equal(await page.locator('.iw-summary-row').count(),0);
    assert.equal(await page.locator('.iw-balance').innerText(),'$0.00');
    console.log('PASS: product/task dialogs retain property assignment, reordering and mobile editing');
    await create();
    await page.locator('#item_table [name=item_name]').first().fill('Keep this unsaved entry');
    await page.route('**/invoices/ajax/save', route=>route.abort());
    await page.locator('#btn_save_invoice').click();
    await page.waitForSelector('#invoice-errors:not([hidden])');
    assert.equal(await page.locator('#item_table [name=item_name]').first().inputValue(),'Keep this unsaved entry');
    assert.equal(await page.locator('#btn_save_invoice').isDisabled(),true);
    console.log('PASS: uncertain save retains entries and prevents duplicate resubmission');
    assert.deepEqual(errors,[]);
    console.log('PASS: responsive widths, payment dialog, PDF rendering and clean browser console');
    await browser.close();
})().catch(error=>{console.error(error.message);process.exit(1)});
