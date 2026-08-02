#!/usr/bin/env node
/**
 * Tiny host PDF service for DDEV.
 * Uses scripts/export-pdf.mjs (Chrome + full-page backgrounds, no headers).
 *
 * Start: node scripts/pdf-server.mjs
 * Or:    ddev pdf-server
 */
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, existsSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const PORT = Number(process.env.MNK_PDF_PORT || 18477);
const exporter = join(root, 'scripts', 'export-pdf.mjs');

function exportPdf(url) {
  return new Promise((resolve, reject) => {
    const dir = mkdtempSync(join(tmpdir(), 'mnk-pdf-'));
    const outfile = join(dir, 'out.pdf');
    const child = spawn(process.execPath, [exporter, url, outfile], {
      cwd: root,
      env: process.env,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stderr = '';
    child.stderr.on('data', (d) => {
      stderr += d.toString();
    });
    child.on('error', (err) => {
      rmSync(dir, { recursive: true, force: true });
      reject(err);
    });
    child.on('close', (code) => {
      try {
        if (code !== 0 || !existsSync(outfile)) {
          throw new Error(stderr.trim() || `export-pdf exited ${code}`);
        }
        const buf = readFileSync(outfile);
        rmSync(dir, { recursive: true, force: true });
        resolve(buf);
      } catch (err) {
        rmSync(dir, { recursive: true, force: true });
        reject(err);
      }
    });
  });
}

const server = createServer(async (req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('ok');
    return;
  }

  if (req.method !== 'POST' || req.url !== '/pdf') {
    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('not found');
    return;
  }

  let body = '';
  for await (const chunk of req) body += chunk;
  let payload;
  try {
    payload = JSON.parse(body || '{}');
  } catch {
    res.writeHead(400, { 'Content-Type': 'text/plain' });
    res.end('invalid json');
    return;
  }

  const url = String(payload.url || '');
  if (!/^https?:\/\//i.test(url)) {
    res.writeHead(400, { 'Content-Type': 'text/plain' });
    res.end('url required');
    return;
  }

  try {
    const pdf = await exportPdf(url);
    res.writeHead(200, {
      'Content-Type': 'application/pdf',
      'Content-Length': String(pdf.length),
    });
    res.end(pdf);
  } catch (err) {
    res.writeHead(500, { 'Content-Type': 'text/plain' });
    res.end(String(err && err.message ? err.message : err));
  }
});

server.listen(PORT, '0.0.0.0', () => {
  writeFileSync(join(root, 'storage/pdfs/.pdf-server-port'), String(PORT));
  console.log(`MNK PDF server on http://127.0.0.1:${PORT}`);
});
