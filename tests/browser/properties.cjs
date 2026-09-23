const {chromium}=require('playwright');const fs=require('fs');
const output=process.env.PROPERTY_BROWSER_OUTPUT || '/tmp/ip-browser-test';fs.mkdirSync(output,{recursive:true});
const loginFile=process.env.PROPERTY_TEST_LOGIN_FILE;if(!loginFile)throw new Error('Set PROPERTY_TEST_LOGIN_FILE to the isolated fixture password file');
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});const page=await browser.newPage({viewport:{width:1400,height:1000}});page.on('requestfailed',r=>console.log('Request failure',r.failure()?.errorText)); page.on('pageerror',e=>console.log('Browser error:',e.message)); page.on('response',async r=>{if(r.status()>=400 && r.url().includes('/ajax/')){const b=await r.text();console.log('Failed AJAX status',r.status());fs.writeFileSync(output+'/error.html',b);}});
await page.goto('http://127.0.0.1:18888/index.php/sessions/login');
await page.locator('#email').fill('admin@example.invalid');await page.locator('#password').fill(fs.readFileSync(loginFile,'utf8'));
await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);
await page.goto('http://127.0.0.1:18888/index.php/service-properties/client/1');
console.log('Property screen status',await page.locator('body').innerText().then(s=>s.includes('Add service property')));

for(const [label,street] of (await page.locator('.property-card').count())>0?[]:[['Home','200 Service Road'],['Rental','300 Other Road']]){
 await page.getByRole('button',{name:'Add property',exact:true}).first().click();
 const form=page.locator('#property-new form');
 for(const [key,value] of Object.entries({label,address_1:street,city:'Springfield',state:'IL',zip:'62701'}))await form.locator('[name="address['+key+']"]').fill(value);
 await Promise.all([page.waitForNavigation(),form.locator('button[type=submit]').click()]);
}
await page.screenshot({path:output+'/properties.png',fullPage:true});
async function api(path,data){return page.evaluate(async ({path,data})=>await new Promise((resolve,reject)=>$.post('/index.php/'+path,data,r=>{try{resolve(typeof r==='string'?JSON.parse(r):r)}catch(e){reject(new Error('Non-JSON response'))}}).fail((xhr,status,error)=>reject(new Error('Request failed: '+path+' HTTP '+xhr.status+' '+status+' '+error+' '+String(xhr.responseText||'').replace(/<[^>]+>/g,' ').replace(/[A-Za-z0-9+\/=_-]{24,}/g,'[redacted]').slice(0,1000))))),{path,data});}
const created=await api('invoices/ajax/create',{client_id:1,user_id:1,invoice_date_created:'09/17/2026',invoice_group_id:3});
if(!created.success)throw new Error('Create invoice: '+JSON.stringify(created.validation_errors));
const id=created.invoice_id;fs.writeFileSync(output+'/ids.json',JSON.stringify({invoice:id}));
await page.goto('http://127.0.0.1:18888/index.php/invoices/view/'+id);
console.log('Invoice property groups:',await page.locator('#item_table .iw-property').count());
const first=page.locator('#item_table .item').first();
await first.locator('[name=item_name]').fill('Tree trimming');await first.locator('[name=item_quantity]').fill('1');await first.locator('[name=item_price]').fill('300');
const opts=await page.locator('.iw-assign-group option').evaluateAll(xs=>xs.map(x=>x.value).filter(Boolean));
await page.locator('.iw-assign-group').selectOption(opts[0]);
await page.locator('#invoice-property-picker').selectOption(opts[1]);await page.locator('#invoice-add-property').click();const second=page.locator('#item_table .item').last();
await second.locator('[name=item_name]').fill('Palm trimming');await second.locator('[name=item_quantity]').fill('1');await second.locator('[name=item_price]').fill('250');
await Promise.all([page.waitForNavigation(),page.locator('#btn_save_invoice').click()]);
await page.waitForLoadState('networkidle');
await page.screenshot({path:output+'/invoice-editor.png',fullPage:true});
const pdf=await page.request.get('http://127.0.0.1:18888/index.php/service-properties/preview/invoice/'+id);
if(!pdf.ok() || !(pdf.headers()['content-type']||'').includes('pdf'))throw new Error('PDF generation failed '+pdf.status());
fs.writeFileSync(output+'/two-properties.pdf',await pdf.body());
await page.getByRole('link',{name:'Edit',exact:true}).click();await page.locator('#invoice-settings > summary').click();await page.locator('#invoice_status_id').selectOption('2');await Promise.all([page.waitForNavigation(),page.locator('#btn_save_invoice').click()]);await page.waitForLoadState('networkidle');
await page.context().grantPermissions(['clipboard-read','clipboard-write']);await page.getByRole('button',{name:'More actions',exact:true}).click();await page.locator('#invoice-copy-link').click();await page.waitForFunction(()=>document.querySelector('#invoice-feedback').textContent.includes('copied'));const publicUrl=await page.evaluate(()=>navigator.clipboard.readText());
const publicResponse=await page.request.get(publicUrl);const publicBody=await publicResponse.text();if(!publicResponse.ok()||!publicBody.includes('Service property:')||!publicBody.includes('Property line total')||!publicBody.includes('Bill To'))throw new Error('Public invoice grouping failed');
console.log('Browser invoice creation, property selection, save, PDF and public grouping passed. Invoice',id);


await page.waitForLoadState('networkidle');console.log('Creating quote');
const quote=await api('quotes/ajax/create',{client_id:1,user_id:1,quote_date_created:'09/17/2026',invoice_group_id:4});
if(!quote.success)throw new Error('Quote creation failed');
await page.goto('http://127.0.0.1:18888/index.php/quotes/view/'+quote.quote_id);
let qrow=page.locator('#item_table .item').first();await qrow.locator('[name=item_name]').fill('Tree trimming');await qrow.locator('[name=item_quantity]').fill('1');await qrow.locator('[name=item_price]').fill('300');await qrow.locator('[name=item_service_property_id]').selectOption(opts[0]);
await page.locator('.btn_add_row').first().click();qrow=page.locator('#item_table .item').last();await qrow.locator('[name=item_name]').fill('Palm trimming');await qrow.locator('[name=item_quantity]').fill('1');await qrow.locator('[name=item_price]').fill('250');await qrow.locator('[name=item_service_property_id]').selectOption(opts[1]);
await Promise.all([page.waitForNavigation(),page.locator('#btn_save_quote').click()]);
await page.waitForLoadState('networkidle');console.log('Quote saved');
const qpdf=await page.request.get('http://127.0.0.1:18888/index.php/service-properties/preview/quote/'+quote.quote_id);if(!qpdf.ok()||!(qpdf.headers()['content-type']||'').includes('pdf'))throw new Error('Quote PDF failed');fs.writeFileSync(output+'/two-properties-quote.pdf',await qpdf.body());
console.log('Quote PDF ready; converting');
const converted=await api('quotes/ajax/quote_to_invoice',{quote_id:quote.quote_id,client_id:1,user_id:1,invoice_date_created:'09/17/2026',invoice_group_id:3});if(!converted.success)throw new Error('Quote conversion failed');
await page.goto('http://127.0.0.1:18888/index.php/invoices/view/'+converted.invoice_id);const vals=await page.locator('#item_table .item [name=item_service_property_id]').evaluateAll(xs=>xs.map(x=>x.value));if(vals.join(',')!==opts.slice(0,2).join(','))throw new Error('Property assignments lost in conversion');
console.log('Quote save, quote PDF, and conversion to invoice passed.');

await page.waitForLoadState('networkidle');
const incomplete=await api('invoices/ajax/create',{client_id:1,user_id:1,invoice_date_created:'09/17/2026',invoice_group_id:3});
await page.goto('http://127.0.0.1:18888/index.php/invoices/view/'+incomplete.invoice_id);
const ir=page.locator('#item_table .item').first();await ir.locator('[name=item_name]').fill('Needs assignment');await ir.locator('[name=item_quantity]').fill('1');await ir.locator('[name=item_price]').fill('100');
await page.locator('#invoice-settings > summary').click();await page.locator('#invoice_status_id').selectOption('2');
let failedResponse=page.waitForResponse(r=>r.url().endsWith('/invoices/ajax/save'));await page.locator('#btn_save_invoice').click();const rejected=await (await failedResponse).json();if(rejected.success!==0)throw new Error('Incomplete invoice was issued');
await page.waitForFunction(()=>document.querySelector('.alert-danger'));
await page.locator('#invoice_status_id').selectOption('1');
await Promise.all([page.waitForNavigation(),page.locator('#btn_save_invoice').click()]);await page.waitForLoadState('networkidle');
const blocked=await page.request.get('http://127.0.0.1:18888/index.php/invoices/generate_pdf/'+incomplete.invoice_id);if(blocked.status()!==409)throw new Error('Incomplete final export was not blocked');
const dpdf=await page.request.get('http://127.0.0.1:18888/index.php/service-properties/preview/invoice/'+incomplete.invoice_id);if(!dpdf.ok())throw new Error('Incomplete draft preview failed');fs.writeFileSync(output+'/incomplete-draft.pdf',await dpdf.body());
await page.getByRole('link',{name:'Edit',exact:true}).click();await page.locator('.iw-assign-group').selectOption(opts[0]);await Promise.all([page.waitForNavigation(),page.locator('#btn_save_invoice').click()]);
console.log('Incomplete issue/export blocked, labelled preview generated, and correction/retry succeeded.');
const outsider=await browser.newContext();const unauth=await outsider.newPage();await unauth.goto('http://127.0.0.1:18888/index.php/service-properties/client/1');if(!unauth.url().includes('/sessions/login'))throw new Error('Property management lacks authentication');await outsider.close();
console.log('Unauthenticated property access blocked.');
await browser.close();})().catch(e=>{console.error(e.message);process.exit(1)});
