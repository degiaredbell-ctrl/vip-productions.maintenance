import puppeteer from 'puppeteer';

const BASE = process.env.BASE ?? 'http://vip_nginx';
const EMAIL = 'admin.pm_anwar@redbellgroup.com';
const PASSWORD = 'Anwar@teknis.vip';

const pages = [
  { name: 'Beranda    ', url: '/' },
  { name: 'Persetujuan', url: '/approvals' },
  { name: 'Laporan    ', url: '/reports' },
  { name: 'Mesin      ', url: '/machines' },
  { name: 'Utility    ', url: '/utility' },
  { name: 'Pengguna   ', url: '/admin/users' },
  { name: 'Roles      ', url: '/admin/roles' },
  { name: 'Template   ', url: '/admin/templates' },
  { name: 'Audit Logs ', url: '/admin/audit-logs' },
  { name: 'Profile    ', url: '/profile' },
];

const browser = await puppeteer.launch({
  headless: 'new',
  executablePath: process.env.CHROME_PATH,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'],
  dumpio: false,
});
const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 900 });

const errors = [];

page.on('console', (msg) => {
  if (msg.type() === 'error') errors.push('[console.error] ' + msg.text());
});
page.on('pageerror', (err) => errors.push('[pageerror] ' + err.message));
page.on('requestfailed', (req) => errors.push('[requestfailed] ' + req.url() + ' ' + (req.failure()?.errorText ?? '')));

try {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle0', timeout: 60000 });
  await new Promise((r) => setTimeout(r, 1000));

  await page.evaluate((e, p) => {
    const inp = document.querySelector('input[type=email], input[name=email]');
    const pw = document.querySelector('input[type=password], input[name=password]');
    const form = document.querySelector('form');
    const submitBtn = document.querySelector('button[type=submit]');
    if (!inp || !pw || !form) throw new Error('login form not found');
    const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(inp, e); inp.dispatchEvent(new Event('input', { bubbles: true }));
    setter.call(pw, p); pw.dispatchEvent(new Event('input', { bubbles: true }));
    submitBtn?.click();
  }, EMAIL, PASSWORD);

  await page.waitForFunction(() => location.pathname !== '/login', { timeout: 30000 }).catch(() => console.log('LOGIN-REDIRECT-FAILED'));
  await new Promise((r) => setTimeout(r, 4000));
  const homeText = await page.evaluate(() => document.body.innerText.slice(0, 80));
  console.log('AFTER LOGIN path=', await page.evaluate(() => location.pathname), 'body=', JSON.stringify(homeText));

  for (const p of pages) {
    errors.length = 0;
    await page.goto(BASE + p.url, { waitUntil: 'networkidle0', timeout: 60000 });
    await new Promise((r) => setTimeout(r, 2500));
    const info = await page.evaluate(() => ({
      path: location.pathname,
      bodyLen: document.body.innerText.length,
      text: document.body.innerText.slice(0, 60).replace(/\n/g, ' | '),
    }));
    const blank = info.bodyLen <= 5;
    console.log(`\n### ${p.name} ${p.url} -> blank=${blank} bodyLen=${info.bodyLen}`);
    console.log('    text:', JSON.stringify(info.text));
    if (errors.length) console.log('    ERRORS:\n    ' + errors.slice(0, 6).join('\n    '));
  }
} catch (e) {
  console.error('FATAL', e.message);
  const html = await page.content().catch(() => '');
  console.log(html.slice(0, 500));
} finally {
  await browser.close();
}