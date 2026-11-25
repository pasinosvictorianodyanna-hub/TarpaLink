// Print a local HTML file to PDF through the DevTools protocol, so the document gets a
// running header and "page N of M" footer. Chrome's --print-to-pdf cannot do either.
//
//   node pdf.mjs <file.html> <out.pdf> [header-title]
//
// Node 22 ships a global WebSocket, so this needs no dependency.

import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const [, , htmlPath, outPath, title = 'TarpaLink'] = process.argv;
if (!htmlPath || !outPath) {
  console.error('usage: node pdf.mjs <file.html> <out.pdf> [title]');
  process.exit(2);
}

const port = 9333 + Math.floor(Math.random() * 200);
const profile = mkdtempSync(join(tmpdir(), 'tl-pdf-'));

const chrome = spawn(CHROME, [
  '--headless=new',
  `--remote-debugging-port=${port}`,
  `--user-data-dir=${profile}`,
  '--disable-gpu',
  '--no-first-run',
  '--no-default-browser-check',
  '--allow-file-access-from-files',
  'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function version() {
  for (let i = 0; i < 100; i++) {
    try {
      const r = await fetch(`http://127.0.0.1:${port}/json/version`);
      if (r.ok) return await r.json();
    } catch {}
    await sleep(150);
  }
  throw new Error('chrome did not open its debugging port');
}

const fileUrl = 'file:///' + htmlPath.replace(/\\/g, '/').replace(/^\//, '');

await version();
const target = await (await fetch(`http://127.0.0.1:${port}/json/new?${encodeURIComponent(fileUrl)}`, { method: 'PUT' })).json();

const ws = new WebSocket(target.webSocketDebuggerUrl);
let id = 0;
const pending = new Map();
const events = new Map();

ws.addEventListener('message', (ev) => {
  const msg = JSON.parse(ev.data);
  if (msg.id && pending.has(msg.id)) {
    pending.get(msg.id)(msg);
    pending.delete(msg.id);
  } else if (msg.method && events.has(msg.method)) {
    events.get(msg.method)(msg.params);
    events.delete(msg.method);
  }
});

const send = (method, params = {}) => new Promise((resolve) => {
  const n = ++id;
  pending.set(n, resolve);
  ws.send(JSON.stringify({ id: n, method, params }));
});

const once = (method) => new Promise((resolve) => events.set(method, resolve));

await new Promise((resolve, reject) => {
  ws.addEventListener('open', resolve);
  ws.addEventListener('error', reject);
});

await send('Page.enable');
// The target was created with the file URL, so it may already have loaded; reload to be sure
// the load event we wait on belongs to this navigation.
const loaded = once('Page.loadEventFired');
await send('Page.navigate', { url: fileUrl });
await Promise.race([loaded, sleep(15000)]);
await sleep(600); // fonts, inline SVG layout

const header = `
<div style="font-family:Arial,Helvetica,sans-serif;font-size:7pt;color:#777;width:100%;padding:0 18mm;display:flex;justify-content:space-between;">
  <span>${title}</span><span>Final Documentation</span>
</div>`;

const footer = `
<div style="font-family:Arial,Helvetica,sans-serif;font-size:7pt;color:#777;width:100%;padding:0 18mm;display:flex;justify-content:space-between;">
  <span>Web Systems and Technologies 2 · System Integration and Architecture 1</span>
  <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
</div>`;

const res = await send('Page.printToPDF', {
  printBackground: true,
  paperWidth: 8.27,
  paperHeight: 11.69,
  marginTop: 0.85,
  marginBottom: 0.85,
  marginLeft: 0.72,
  marginRight: 0.72,
  displayHeaderFooter: true,
  headerTemplate: header,
  footerTemplate: footer,
  preferCSSPageSize: false,
});

if (!res.result || !res.result.data) {
  console.error('printToPDF failed:', JSON.stringify(res).slice(0, 400));
  process.exit(1);
}

const { writeFileSync } = await import('node:fs');
writeFileSync(outPath, Buffer.from(res.result.data, 'base64'));
console.log('wrote', outPath);

ws.close();
chrome.kill();
await sleep(300);
try { rmSync(profile, { recursive: true, force: true }); } catch {}
process.exit(0);
