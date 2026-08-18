// A4 page geometry + pagination engine for printable documents.
//
// Page model (deliberately uniform): the browser owns the paper margins through
// `@page { margin: <m>mm }`, and the document element is laid out at the printable
// width only. That makes every page — first, middle, last — have the exact same
// usable height, so a single modulo tells us where any element lands. The old model
// mixed doc padding (page 1) with @page margin (page 2+), which made page 1 taller
// than the rest and pushed footers onto near-blank extra pages.
//
// Kept framework-free so plain functions can drive it, not just Vue components.

export const A4_WIDTH_MM = 210
export const A4_HEIGHT_MM = 297

// Below this, a "keep together" block is too tall to ever fit one page — leave it alone
// instead of pushing it forever.
const MAX_PUSH_PASSES = 20
// Gap kept between the footer's bottom edge and the true page boundary.
//
// Without it the footer ends EXACTLY on the boundary, which means zero tolerance: any
// reflow after the spacer is sized — a webfont landing, an icon font loading, a rounding
// difference — grows the content by a pixel, the footer crosses the edge, and
// break-inside:avoid throws the whole bar onto a blank next page. That was the "footer
// alone on page 2" bug. A few mm of reserve absorbs that drift and is invisible on paper.
const FOOTER_BOTTOM_RESERVE_MM = 8

export function contentWidthMm(marginMm) {
    return A4_WIDTH_MM - (marginMm * 2)
}

// Top margin is separate because documents usually want more breathing room above the
// header than at the sides; the bottom stays on the shared margin.
export function pageCapacityMm(marginMm, marginTopMm = marginMm) {
    return A4_HEIGHT_MM - marginTopMm - marginMm
}

// Measured rather than assumed, and always in the document the element actually lives in —
// the print iframe is a separate document and can resolve mm differently from the app page.
export function mmToPx(mm, ownerDoc = document) {
    const probe = ownerDoc.createElement('div')
    probe.style.cssText = `position:absolute;left:-99999px;top:0;height:${mm}mm;width:1px;`
    ownerDoc.body.appendChild(probe)
    const px = probe.getBoundingClientRect().height
    probe.remove()
    return px || ((mm / 25.4) * 96)
}

function yInDoc(el, doc) {
    return el.getBoundingClientRect().top - doc.getBoundingClientRect().top
}

function clearPushMargins(body) {
    body.querySelectorAll('[data-a4-push]').forEach((el) => {
        el.style.marginTop = ''
        el.removeAttribute('data-a4-push')
    })
}

// A block that would be sliced by a page edge gets a top margin big enough to start it
// on the next page instead. Runs in passes because moving one block shifts every block
// after it.
function avoidBlockSplit(doc, body, capacity, keepSelector) {
    clearPushMargins(body)
    if (!keepSelector) return

    const blocks = [...body.querySelectorAll(keepSelector)]

    for (let pass = 0; pass < MAX_PUSH_PASSES; pass++) {
        let moved = false

        for (const el of blocks) {
            // Taller than a page — no amount of pushing helps, let it split
            if (el.offsetHeight > capacity - 20) continue

            const top = yInDoc(el, doc)
            const bottom = top + el.offsetHeight
            const startPage = Math.floor(top / capacity)
            const endPage = Math.floor(Math.max(0, bottom - 0.5) / capacity)

            if (endPage > startPage) {
                const push = ((startPage + 1) * capacity) - top
                if (push > 0.5) {
                    const prev = parseFloat(el.style.marginTop) || 0
                    el.style.marginTop = `${prev + push}px`
                    el.setAttribute('data-a4-push', '1')
                    moved = true
                    break
                }
            }
        }

        if (!moved) break
    }
}

// Stretches the spacer so the footer sits at the bottom of whatever turns out to be the
// last page — never mid-page, never floating over content — keeping `reserve` px of slack
// below it so late reflow cannot tip it over the page edge.
function pinFooterToLastPage(body, spacer, footer, capacity, reserve) {
    spacer.style.height = '0px'
    void spacer.offsetHeight

    const bodyH = body.offsetHeight
    // Space the footer must claim at the bottom of a page to be considered a fit
    const footerBlock = footer.offsetHeight + reserve

    // How much of the current (partly used) page is still free
    const used = bodyH % capacity
    const freeOnPage = capacity - used

    const fill = freeOnPage >= footerBlock
        ? freeOnPage - footerBlock
        // Footer no longer fits — fill this page out and pin it to the bottom of the next
        : freeOnPage + capacity - footerBlock

    const height = Math.max(0, Math.floor(fill))
    spacer.style.height = `${height}px`

    const total = bodyH + height + footer.offsetHeight + reserve
    return Math.max(1, Math.round(total / capacity))
}

// Lays out one A4 document and returns its page count.
// `doc` is the printable-width root; `body` holds the flowing content; `spacer` is an
// empty sibling between body and `footer`.
export function layoutA4({
    doc,
    body,
    spacer,
    footer,
    marginMm = 10,
    marginTopMm = marginMm,
    keepSelector = '.a4-keep',
    bottomReserveMm = FOOTER_BOTTOM_RESERVE_MM,
}) {
    if (!doc || !body || !spacer || !footer) return 1

    const ownerDoc = doc.ownerDocument
    const capacity = Math.round(mmToPx(pageCapacityMm(marginMm, marginTopMm), ownerDoc))
    if (capacity <= 0) return 1

    // Page edges are where the browser really breaks, so keep-together blocks use the full
    // capacity — only the footer gets the reserve.
    avoidBlockSplit(doc, body, capacity, keepSelector)
    return pinFooterToLastPage(body, spacer, footer, capacity, Math.round(mmToPx(bottomReserveMm, ownerDoc)))
}

// Rewrites the page-number strips as plain DOM. Only safe on a detached clone — inside the
// live app those spans are Vue-rendered and re-render themselves from the page count.
function rebuildPageNumbers(doc, capacity, pageCount) {
    if (doc.dataset.a4Pagenumbers !== '1') return
    doc.querySelectorAll('.a4-page-number').forEach((el) => el.remove())

    for (let i = 1; i <= pageCount; i++) {
        const span = doc.ownerDocument.createElement('span')
        span.className = 'a4-page-number'
        span.setAttribute('aria-hidden', 'true')
        span.style.top = `${(i * capacity) - 18}px`
        span.textContent = `Page ${i} of ${pageCount}`
        doc.appendChild(span)
    }
}

// Builds the @page rule from a rendered document's own data-a4-* margins.
//
// Derived from the markup rather than passed in by the caller: the ticket path combines
// several documents by hand and simply forgot to forward the margins, so its pages printed
// with the stale default. Reading them off the clone makes that impossible to get wrong.
// @page is global to a print job, so the first document's margins win — the documents we
// ever combine are identical (one ticket per page).
export function a4PageRuleFor(root) {
    const doc = root?.querySelector?.('.a4-doc')
    if (!doc) return ''

    const marginMm = Number(doc.dataset.a4Margin) || 10
    const marginTopMm = Number(doc.dataset.a4MarginTop) || marginMm
    return `@page { size: A4; margin: ${marginTopMm}mm ${marginMm}mm ${marginMm}mm; }`
}

// Re-runs the layout on every A4 document inside `root` — used by the print window, which
// is a different document with its own font metrics and box rounding. Measuring on the app
// page and baking the spacer height into the clone is what pushed footers onto blank pages:
// a few pixels of drift there is enough to spill past a page edge.
export function relayoutA4Documents(root) {
    const docs = [...(root?.querySelectorAll?.('.a4-doc') ?? [])]

    for (const doc of docs) {
        const marginMm = Number(doc.dataset.a4Margin) || 10
        const marginTopMm = Number(doc.dataset.a4MarginTop) || marginMm
        const pageCount = layoutA4({
            doc,
            body: doc.querySelector('.a4-body'),
            spacer: doc.querySelector('.a4-spacer'),
            footer: doc.querySelector('.a4-footer'),
            marginMm,
            marginTopMm,
            keepSelector: doc.dataset.a4Keep || '.a4-keep',
            bottomReserveMm: doc.dataset.a4Reserve != null
                ? Number(doc.dataset.a4Reserve)
                : FOOTER_BOTTOM_RESERVE_MM,
        })
        const capacity = Math.round(mmToPx(pageCapacityMm(marginMm, marginTopMm), doc.ownerDocument))
        rebuildPageNumbers(doc, capacity, pageCount)
    }

    return docs.length
}
