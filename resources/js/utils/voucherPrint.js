// Shared print engine for voucher-style A4 documents (BookingReceiptDoc and
// anything else built on the same .voucher-doc markup/CSS, e.g. ticket prints).
// Kept framework-free so it can be reused from plain functions, not just components.

import { relayoutA4Documents, a4PageRuleFor } from './a4Print'

// Icon/web fonts land after the document is written and change text metrics — measuring
// before they settle bakes in heights that are wrong by the time the page actually prints.
function waitForFonts(doc) {
    const ready = doc.fonts?.ready
    if (!ready) return Promise.resolve()
    // A stylesheet that never loads (offline CDN) must not block printing forever
    return Promise.race([ready, new Promise((resolve) => setTimeout(resolve, 3000))])
}

export function waitForImages(root) {
    const imgs = root?.images
        ? [...root.images]
        : [...(root?.querySelectorAll?.('img') ?? [])]
    if (!imgs.length) return Promise.resolve()
    return Promise.all(imgs.map((img) => (
        img.complete
            ? Promise.resolve()
            : new Promise((resolve) => {
                img.onload = resolve
                img.onerror = resolve
            })
    )))
}

function stripPrintMedia(css) {
    let result = ''
    let i = 0
    const lower = css.toLowerCase()

    while (i < css.length) {
        const idx = lower.indexOf('@media print', i)
        if (idx === -1) {
            result += css.slice(i)
            break
        }

        result += css.slice(i, idx)
        const braceStart = css.indexOf('{', idx)
        if (braceStart === -1) break

        let depth = 1
        let j = braceStart + 1
        while (j < css.length && depth > 0) {
            if (css[j] === '{') depth += 1
            if (css[j] === '}') depth -= 1
            j += 1
        }
        i = j
    }

    return result
}

// Dev's Vite server injects component CSS as inline <style> tags, but a production
// build extracts it into an external .css <link> instead — read cssRules back out so
// print still finds the voucher/a4-doc rules after `vite build`.
function externalSheetCss(sheet) {
    try {
        return [...sheet.cssRules].map((rule) => rule.cssText).join('\n')
    } catch {
        // Cross-origin sheet (e.g. a CDN font) — inaccessible, and never voucher CSS anyway
        return ''
    }
}

// Pulls every stylesheet (inline <style> or app <link>) that styles voucher/receipt/A4-shell
// markup so the print iframe (a blank document with no access to the app's bundled
// stylesheets) still looks right.
export function collectVoucherPrintCss() {
    const inline = [...document.querySelectorAll('style')].map((el) => el.textContent)
    const linked = [...document.styleSheets]
        .filter((sheet) => sheet.ownerNode?.tagName === 'LINK')
        .map(externalSheetCss)

    return [...inline, ...linked]
        .filter((css) => (
            css.includes('voucher-')
            || css.includes('receipt-')
            || css.includes('a4-doc')
        ))
        .map(stripPrintMedia)
        .join('\n')
}

export function voucherPrintResetStyles() {
    return `
@page {
    size: A4;
    /* Uniform on every page — the doc is laid out at printable width only, so page 1 and
       every continuation page get the exact same usable height */
    margin: 10mm;
}
html, body {
    margin: 0;
    padding: 0;
    background: #fff;
    overflow: visible !important;
    height: auto !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.voucher-print-body,
.voucher-doc,
.a4-sheet,
.a4-doc,
.receipt-modal__body {
    overflow: visible !important;
    max-height: none !important;
    border-radius: 0;
}
/* On paper the @page margin already provides the edges — drop the on-screen sheet padding */
.a4-sheet {
    width: auto !important;
    min-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}
.a4-page-edge {
    display: none !important;
}
.voucher-print-body {
    margin: 0;
    padding: 0;
}
.voucher-print-body,
.voucher-print-body * {
    visibility: visible !important;
}
/* Bold phrases must survive print/PDF */
.voucher-notes b,
.voucher-notice b {
    font-weight: 700 !important;
    color: inherit !important;
}
@media print {
    html, body {
        overflow: visible !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    * {
        overflow: visible !important;
        max-height: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`
}

// Opens a hidden iframe with its own document, injects the given body HTML plus
// the app's voucher/receipt CSS, waits for images, then triggers window.print().
// bodyHtml is one or more already-rendered .voucher-doc blocks (outerHTML).
export function openVoucherPrintWindow(bodyHtml, title, extraCss = '') {
    const iframe = document.createElement('iframe')
    iframe.setAttribute('aria-hidden', 'true')
    iframe.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;border:0;visibility:hidden;pointer-events:none'
    document.body.appendChild(iframe)

    const win = iframe.contentWindow
    const doc = win.document

    // Blank title + @page margin → avoid browser chrome when headers/footers off
    doc.open()
    doc.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>${title}</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
<style>
${collectVoucherPrintCss()}
${voucherPrintResetStyles()}
${extraCss}
</style>
</head><body class="voucher-print-body">${bodyHtml}</body></html>`)
    doc.close()

    const cleanup = () => iframe.remove()

    const triggerPrint = () => {
        win.onafterprint = cleanup
        win.focus()
        win.print()
        setTimeout(cleanup, 120_000)
    }

    // Appended last so it beats the default @page in the reset stylesheet
    const pageRule = a4PageRuleFor(doc)
    if (pageRule) {
        const style = doc.createElement('style')
        style.textContent = pageRule
        doc.head.appendChild(style)
    }

    Promise.all([waitForImages(doc), waitForFonts(doc)]).then(() => {
        requestAnimationFrame(() => {
            // Re-pin footers against THIS document's settled metrics. The spacer heights
            // baked in on the app page are only an estimate — a few pixels of drift is
            // enough to spill a footer onto a blank extra page.
            relayoutA4Documents(doc)
            requestAnimationFrame(triggerPrint)
        })
    })
}
