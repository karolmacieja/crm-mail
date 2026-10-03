const { chromium } = require('playwright')
/**
 * Generuje docs/GastroFlowx-instrukcja.pdf z docs/instrukcja/index.html.
 * Wymaga Playwright (Chromium) i pdfunite (poppler-utils):
 *   node docs/instrukcja/build-pdf.cjs
 * Okładka jest renderowana bez stopki, treść z numerami stron, potem oba pliki są łączone.
 */
const { execFileSync } = require('node:child_process')
const { mkdtempSync } = require('node:fs')
const { join, resolve } = require('node:path')
const { tmpdir } = require('node:os')
const S = mkdtempSync(join(tmpdir(), 'gfx-pdf-'))
;(async () => {
  const b = await chromium.launch()
  const p = await b.newPage()
  const url = 'file://' + resolve(__dirname, 'index.html')
  // Cover without footer
  await p.goto(url, { waitUntil: 'load' })
  await p.addStyleTag({ content: 'section:not(.cover){display:none!important}' })
  await p.pdf({ path: S + '/cover.pdf', format: 'A4', printBackground: true, preferCSSPageSize: true })
  // Body with page footer
  await p.goto(url, { waitUntil: 'load' })
  await p.addStyleTag({ content: '.cover{display:none!important} section.chapter:first-of-type{break-before:auto} @page :first { margin: 18mm 16mm 20mm; }' })
  await p.waitForTimeout(400)
  const foot = `<div style="width:100%;font:8px Arial,sans-serif;color:#9ca3af;padding:0 16mm;display:flex;justify-content:space-between">
    <span>GastroFlowx – opis systemu i instrukcja obsługi</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`
  await p.pdf({ path: S + '/body.pdf', format: 'A4', printBackground: true, preferCSSPageSize: true,
    displayHeaderFooter: true, headerTemplate: '<span></span>', footerTemplate: foot })
  await b.close()
  execFileSync('pdfunite', [S + '/cover.pdf', S + '/body.pdf', resolve(__dirname, '../GastroFlowx-instrukcja.pdf')])
  console.log('✓ docs/GastroFlowx-instrukcja.pdf')
})()
