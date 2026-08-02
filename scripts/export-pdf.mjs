#!/usr/bin/env node
/**
 * Clean A4 PDF export (no browser title/URL/date headers).
 * Usage: node scripts/export-pdf.mjs <url> <outfile.pdf>
 */
import { createRequire } from 'node:module';
import { existsSync, readdirSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');

function findChrome() {
  const candidates = [
    process.env.CHROME_PATH,
    process.env.PUPPETEER_EXECUTABLE_PATH,
    '/usr/bin/google-chrome-stable',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
  ].filter(Boolean);

  const browsersRoot = join(root, 'bin', 'browsers');
  if (existsSync(browsersRoot)) {
    const walk = (dir, depth = 0) => {
      if (depth > 6) return;
      let entries = [];
      try {
        entries = readdirSync(dir);
      } catch {
        return;
      }
      for (const name of entries) {
        const full = join(dir, name);
        let st;
        try {
          st = statSync(full);
        } catch {
          continue;
        }
        if (st.isDirectory()) {
          if (name === 'chrome-linux64' || name === 'chrome-linux') {
            const bin = join(full, 'chrome');
            if (existsSync(bin)) candidates.push(bin);
          }
          walk(full, depth + 1);
        } else if (name === 'chrome' || name === 'google-chrome' || name === 'chromium') {
          candidates.push(full);
        }
      }
    };
    walk(browsersRoot);
  }

  for (const path of candidates) {
    if (path && existsSync(path)) return path;
  }
  return null;
}

const url = process.argv[2];
const outfile = process.argv[3];

if (!url || !outfile) {
  console.error('Usage: node scripts/export-pdf.mjs <url> <outfile.pdf>');
  process.exit(2);
}

const executablePath = findChrome();
if (!executablePath) {
  console.error('No Chrome/Chromium found. Set CHROME_PATH or install Google Chrome.');
  process.exit(3);
}

const browser = await puppeteer.launch({
  executablePath,
  headless: true,
  args: [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-dev-shm-usage',
    '--font-render-hinting=none',
  ],
});

try {
  const page = await browser.newPage();
  await page.goto(url, { waitUntil: 'networkidle0', timeout: 60000 });
  await page.emulateMediaType('print');
  // Stretch themed docs to full A4 page(s) so backgrounds don't stop mid-page.
  // Must use setProperty(..., 'important') because print CSS uses !important.
  const padInfo = await page.evaluate(() => {
    const theme = document.body.getAttribute('data-theme') || '';
    const colors = {
      sage: '#f3f1ec',
      midnight: '#1a1a1a',
    };
    const bg = colors[theme] || null;

    const fixed = document.createElement('div');
    fixed.setAttribute('data-print-bg', '1');
    fixed.style.cssText = [
      'position:fixed',
      'inset:0',
      'width:100%',
      'height:100%',
      `background:${bg || '#ffffff'}`,
      'z-index:-1',
      '-webkit-print-color-adjust:exact',
      'print-color-adjust:exact',
    ].join(';');
    document.body.prepend(fixed);

    const el = document.querySelector('.resume, .cover-letter');
    if (!el) return { pages: 0 };

    const probe = document.createElement('div');
    probe.style.cssText = 'position:absolute;left:-99999px;top:0;height:297mm;width:0;';
    document.body.appendChild(probe);
    const pagePx = probe.offsetHeight || 1;
    probe.remove();

    el.style.setProperty('min-height', '0px', 'important');
    const shell = document.querySelector('.site-shell, .site-shell-embed');
    if (shell) shell.style.setProperty('min-height', '0px', 'important');
    document.body.style.setProperty('min-height', '0px', 'important');
    document.documentElement.style.setProperty('min-height', '0px', 'important');

    const height = Math.max(el.scrollHeight, el.getBoundingClientRect().height);
    const pages = Math.max(1, Math.ceil((height - 1) / pagePx));
    const minH = `${pages * 297}mm`;

    el.style.setProperty('min-height', minH, 'important');
    if (bg) el.style.setProperty('background', bg, 'important');
    if (shell) {
      shell.style.setProperty('min-height', minH, 'important');
      if (bg) shell.style.setProperty('background', bg, 'important');
    }
    document.body.style.setProperty('min-height', minH, 'important');
    document.documentElement.style.setProperty('min-height', minH, 'important');
    if (bg) {
      document.body.style.setProperty('background', bg, 'important');
      document.documentElement.style.setProperty('background', bg, 'important');
    }

    return { pagePx, height, pages, minH, theme, bg };
  });
  if (process.env.MNK_PDF_DEBUG) {
    console.error('pad', JSON.stringify(padInfo));
  }
  await page.pdf({
    path: outfile,
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: true,
    margin: { top: '0', right: '0', bottom: '0', left: '0' },
    displayHeaderFooter: false,
  });
  console.log(outfile);
} finally {
  await browser.close();
}
