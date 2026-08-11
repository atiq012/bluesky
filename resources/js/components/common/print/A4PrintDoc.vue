<script setup>
// Reusable A4 print shell. Callers supply content through three slots and get the whole
// page model for free:
//   - header  → top of page 1 only (it is simply the first thing in the flow)
//   - default → content, flows across as many pages as it needs
//   - footer  → bottom of the LAST page, always, whether that is page 1 or page 5
// Paper margins come from `@page`, so every page has identical usable height and content
// can never spill outside the printable area.
import { ref, computed, useSlots, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { waitForImages, openVoucherPrintWindow } from '../../../utils/voucherPrint'
import { layoutA4, contentWidthMm, pageCapacityMm, mmToPx } from '../../../utils/a4Print'

const props = defineProps({
    // Paper margin on sides and bottom. 10mm is the safe printable area on consumer printers.
    marginMm: { type: Number, default: 10 },
    // Top gets its own value — a document wants more air above the header than at the edges
    marginTopMm: { type: Number, default: 15 },
    // Blocks that must not be sliced by a page edge — they get pushed to the next page whole
    keepSelector: { type: String, default: '.a4-keep' },
    // Slack left under the footer so late reflow can't tip it onto a blank next page
    bottomReserveMm: { type: Number, default: 8 },
    // Extra selectors html2pdf must not slice through, on top of keepSelector
    avoidSelectors: { type: Array, default: () => [] },
    printTitle: { type: String, default: 'Document' },
    fileName: { type: String, default: 'document.pdf' },
    // "Page 1 of 3" strip at the bottom of every page
    pageNumbers: { type: Boolean, default: false },
    // Screen-only dashed rules showing where each A4 page ends
    showPageEdges: { type: Boolean, default: true },
})

const emit = defineEmits(['error'])

const slots = useSlots()
const sheetEl = ref(null)
const pageCount = ref(1)
const downloading = ref(false)
const pageEdges = ref([])

const docStyle = computed(() => ({ width: `${contentWidthMm(props.marginMm)}mm` }))
const sheetStyle = computed(() => ({
    width: `${210}mm`,
    padding: `${props.marginTopMm}mm ${props.marginMm}mm ${props.marginMm}mm`,
    minHeight: `${297}mm`,
}))

function els() {
    const root = sheetEl.value
    if (!root) return null
    const doc = root.querySelector('.a4-doc')
    const body = root.querySelector('.a4-body')
    const spacer = root.querySelector('.a4-spacer')
    const footer = root.querySelector('.a4-footer')
    if (!doc || !body || !spacer || !footer) return null
    return { doc, body, spacer, footer }
}

// Guards the ResizeObserver: our own spacer/margin writes change heights, and reacting
// to those would loop.
let laying = false

function relayout() {
    const parts = els()
    if (!parts || laying) return
    laying = true
    try {
        pageCount.value = layoutA4({
            ...parts,
            marginMm: props.marginMm,
            marginTopMm: props.marginTopMm,
            keepSelector: props.keepSelector,
            bottomReserveMm: props.bottomReserveMm,
        })
        updatePageEdges()
    } finally {
        laying = false
    }
}

function updatePageEdges() {
    if (!props.showPageEdges || pageCount.value < 2) {
        pageEdges.value = []
        return
    }
    const capacity = mmToPx(pageCapacityMm(props.marginMm, props.marginTopMm))
    pageEdges.value = Array.from({ length: pageCount.value - 1 }, (_, i) => (i + 1) * capacity)
}

// Each strip sits just inside the bottom of its own page
const pageNumberTops = computed(() => {
    if (!props.pageNumbers) return []
    const capacity = mmToPx(pageCapacityMm(props.marginMm, props.marginTopMm))
    return Array.from({ length: pageCount.value }, (_, i) => ((i + 1) * capacity) - 18)
})

let layoutRaf = 0
const layoutTimers = []

function scheduleRelayout() {
    cancelAnimationFrame(layoutRaf)
    layoutTimers.splice(0).forEach(clearTimeout)
    layoutRaf = requestAnimationFrame(() => {
        nextTick(() => {
            relayout()
            // Fonts and images settle late and change heights — re-pin after they land
            layoutTimers.push(setTimeout(relayout, 300))
            layoutTimers.push(setTimeout(relayout, 1000))
        })
    })
}

// Content is slot-driven, so there is no prop to watch — observe the rendered height
// instead. That makes the shell work for any document without the caller wiring anything.
let resizeObserver = null

onMounted(() => {
    scheduleRelayout()
    const parts = els()
    if (!parts || typeof ResizeObserver === 'undefined') return
    resizeObserver = new ResizeObserver(() => {
        if (!laying) scheduleRelayout()
    })
    resizeObserver.observe(parts.body)
})

onBeforeUnmount(() => {
    cancelAnimationFrame(layoutRaf)
    layoutTimers.splice(0).forEach(clearTimeout)
    resizeObserver?.disconnect()
})

async function prepareForPrint() {
    const root = sheetEl.value
    await waitForImages(root)
    relayout()
    await nextTick()
    return root
}

// The whole sheet is cloned, not just .a4-doc — a caller's own class on <A4PrintDoc>
// lands on the sheet (Vue puts it on the child root), and that is what carries the
// document's typography. Print CSS strips the sheet's on-screen padding back to zero.
// The print window reads the margins off the clone's data-a4-* attributes, so single-doc
// and combined-doc jobs get the same @page rule without anyone forwarding it.
async function print() {
    const root = await prepareForPrint()
    if (!root) return
    openVoucherPrintWindow(root.outerHTML, props.printTitle)
}

// Exposed so a caller can combine several documents into ONE print job (e.g. one ticket
// per page) instead of each opening its own print window.
async function getPrintHtml() {
    const root = await prepareForPrint()
    return root?.outerHTML ?? ''
}

async function download() {
    const root = await prepareForPrint()
    // html2pdf slices at raw A4 and applies its own margin, so it gets the printable-width
    // doc, never the padded sheet — otherwise page 2+ would start hard against the edge.
    const docEl = root?.querySelector('.a4-doc')
    if (!docEl || downloading.value) return

    downloading.value = true
    try {
        await new Promise((r) => requestAnimationFrame(r))
        const { default: html2pdf } = await import('html2pdf.js')
        await html2pdf()
            .set({
                // Same margins the print path uses, so both outputs paginate identically
                // (html2pdf takes [top, left, bottom, right])
                margin: [props.marginTopMm, props.marginMm, props.marginMm, props.marginMm],
                filename: props.fileName,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, scrollY: 0, backgroundColor: '#ffffff' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: {
                    mode: ['css', 'legacy'],
                    avoid: [props.keepSelector, ...props.avoidSelectors].filter(Boolean),
                },
            })
            .from(docEl)
            .save()
    } catch (error) {
        console.error(error)
        emit('error', error)
    } finally {
        downloading.value = false
    }
}

defineExpose({ print, download, getPrintHtml, relayout, downloading, pageCount })
</script>

<template>
    <!-- .a4-sheet is the on-screen paper (margins made visible); only .a4-doc is printed -->
    <div ref="sheetEl" class="a4-sheet" :style="sheetStyle">
        <!-- data-a4-* travels with the clone so the print window can re-run the same layout -->
        <div
            class="a4-doc"
            :style="docStyle"
            :data-a4-margin="marginMm"
            :data-a4-margin-top="marginTopMm"
            :data-a4-keep="keepSelector"
            :data-a4-reserve="bottomReserveMm"
            :data-a4-pagenumbers="pageNumbers ? '1' : '0'"
        >
            <div class="a4-body">
                <header v-if="slots.header" class="a4-header a4-keep">
                    <slot name="header" />
                </header>
                <slot />
            </div>

            <!-- Height is set by layoutA4() so the footer lands on the last page bottom -->
            <div class="a4-spacer" aria-hidden="true" />

            <footer class="a4-footer a4-keep">
                <slot name="footer" />
            </footer>

            <div
                v-for="(top, i) in pageEdges"
                :key="`edge-${i}`"
                class="a4-page-edge"
                :style="{ top: `${top}px` }"
                aria-hidden="true"
            />

            <span
                v-for="(top, i) in pageNumberTops"
                :key="`pn-${i}`"
                class="a4-page-number"
                :style="{ top: `${top}px` }"
                aria-hidden="true"
            >Page {{ i + 1 }} of {{ pageCount }}</span>
        </div>
    </div>
</template>

<style scoped>
/* Paper is always white in both themes — it is a print surface, not app chrome */
.a4-sheet {
    box-sizing: border-box;
    background: #fff;
    margin: 0 auto;
}

.a4-doc {
    position: relative;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.a4-body {
    flex: 0 0 auto;
}

.a4-spacer {
    flex: 0 0 auto;
    width: 100%;
    height: 0;
    min-height: 0;
    pointer-events: none;
}

.a4-footer {
    flex: 0 0 auto;
}

.a4-keep {
    break-inside: avoid;
    page-break-inside: avoid;
}

/* Screen-only guide so page breaks are visible before printing */
.a4-page-edge {
    position: absolute;
    left: 0;
    right: 0;
    border-top: 1px dashed rgba(30, 58, 95, 0.25);
    pointer-events: none;
}

.a4-page-number {
    position: absolute;
    right: 0;
    font-size: 9px;
    color: #64748b;
}

@media print {
    .a4-sheet {
        width: auto !important;
        min-height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .a4-doc {
        width: auto !important;
    }

    .a4-page-edge {
        display: none !important;
    }
}
</style>
