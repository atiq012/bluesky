<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    visible: { type: Boolean, default: false },
    receipt: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const printRoot = ref(null)
const downloading = ref(false)
const defaultLogo = new URL('../../../../../public/theme/appimages/blueskywings.png', import.meta.url).href
const footerLogo = new URL('../../../../../public/theme/appimages/bluesky.svg', import.meta.url).href

const hasReceipt = computed(() => !!props.receipt)
const agencyLogo = computed(() => props.receipt?.agency?.logo || defaultLogo)

function fmtMoney(amount) {
    const n = Number(amount)
    if (Number.isNaN(n)) return '—'
    const cur = props.receipt?.fare?.currency ?? 'BDT'
    return `${cur}${n.toLocaleString(undefined, { maximumFractionDigits: 0 })}`
}

function classLabel(seg) {
    const cabin = seg.cabin_class || 'Economy'
    const code = seg.booking_code || ''
    return code ? `${cabin}(${code})` : cabin
}

function durationLabel(seg) {
    const d = seg.duration || '—'
    return d.startsWith('Duration') ? d : `Duration ${d}`
}

function handleClose() {
    emit('close')
}

function receiptPrintResetStyles() {
    return `
html, body {
    margin: 0;
    padding: 0;
    background: #fff;
    overflow: visible !important;
    height: auto !important;
}
.receipt-print-body,
.receipt-doc,
.receipt-modal__body,
.table-responsive {
    overflow: visible !important;
    max-height: none !important;
    border-radius: 0;
}
.receipt-print-body,
.receipt-print-body * {
    visibility: visible !important;
}
@media print {
    html, body {
        overflow: visible !important;
        height: auto !important;
    }
    * {
        overflow: visible !important;
        max-height: none !important;
    }
}
`
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
        if (braceStart === -1) {
            break
        }

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

function receiptPrintStyles() {
    return [...document.querySelectorAll('style')]
        .filter((el) => el.textContent.includes('receipt-'))
        .map((el) => stripPrintMedia(el.textContent))
        .join('\n')
}

function waitForImages(doc) {
    const imgs = [...doc.images]
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

function openReceiptPrintDialog() {
    const docEl = printRoot.value?.querySelector('.receipt-doc')
    if (!docEl) return

    const iframe = document.createElement('iframe')
    iframe.setAttribute('aria-hidden', 'true')
    iframe.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;border:0;visibility:hidden;pointer-events:none'
    document.body.appendChild(iframe)

    const win = iframe.contentWindow
    const doc = win.document
    const title = `Receipt ${props.receipt?.bookingId ?? ''}`

    doc.open()
    doc.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>${title}</title>
<style>
${receiptPrintStyles()}
${receiptPrintResetStyles()}
</style>
</head><body class="receipt-print-body">${docEl.outerHTML}</body></html>`)
    doc.close()

    const cleanup = () => iframe.remove()

    const triggerPrint = () => {
        win.onafterprint = cleanup
        win.focus()
        win.print()
        setTimeout(cleanup, 120_000)
    }

    waitForImages(doc).then(() => {
        requestAnimationFrame(() => {
            requestAnimationFrame(triggerPrint)
        })
    })
}

function handlePrint() {
    openReceiptPrintDialog()
}

async function handleDownload() {
    const element = printRoot.value?.querySelector('.receipt-doc')
    if (!element || downloading.value) return

    downloading.value = true
    try {
        const { default: html2pdf } = await import('html2pdf.js')
        const filename = `Receipt-${props.receipt?.bookingId ?? 'booking'}.pdf`
        await html2pdf()
            .set({
                margin: [0.2, 0.2, 0.2, 0.2],
                filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
            })
            .from(element)
            .save()
    } catch (error) {
        console.error(error)
        Notification?.showToast?.('e', 'Failed to download receipt PDF.')
    } finally {
        downloading.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="visible && hasReceipt"
            class="receipt-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="receipt-modal-title"
        >
            <div class="receipt-modal__toolbar no-print">
                <button type="button" class="receipt-modal__close" @click="handleClose">
                    <i class="fa-solid fa-xmark" aria-hidden="true" />
                    Close
                </button>
                <div class="receipt-modal__toolbar-actions">
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        :disabled="downloading"
                        @click="handleDownload"
                    >
                        <i class="fa-solid fa-download me-1" aria-hidden="true" />
                        {{ downloading ? 'Downloading…' : 'Download this Receipt' }}
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" @click="handlePrint">
                        <i class="fa-solid fa-print me-1" aria-hidden="true" />
                        Print
                    </button>
                </div>
            </div>

            <div ref="printRoot" class="receipt-modal__body">
                <div id="receipt-modal-title" class="receipt-doc">
                    <header class="receipt-doc__header">
                        <div class="receipt-doc__brand">
                            <img
                                :src="agencyLogo"
                                alt="Travel"
                                class="receipt-doc__logo"
                            />
                        </div>
                        <div class="receipt-doc__agency">
                            <strong>{{ receipt.agency.name }}</strong>
                            <div>{{ receipt.agency.address }}</div>
                            <div>{{ receipt.agency.email }}</div>
                            <div>{{ receipt.agency.phone }}</div>
                        </div>
                    </header>

                    <div class="receipt-doc__route-bar">
                        <span>
                            <i class="fa-solid fa-plane me-2" aria-hidden="true" />
                            {{ receipt.route.label }}
                        </span>
                        <span>Total Flight Time: {{ receipt.totalFlightTime }}</span>
                    </div>

                    <div class="receipt-doc__meta-grid">
                        <div><span>Booking ID</span><strong>{{ receipt.bookingId }}</strong></div>
                        <div><span>GDS PNR</span><strong>{{ receipt.gdsPnr }}</strong></div>
                        <div><span>Airline PNR</span><strong>{{ receipt.airlinePnr }}</strong></div>
                        <div><span>Refund Status</span><strong>{{ receipt.refundStatus }}</strong></div>
                        <div><span>Status</span><strong>{{ receipt.status }}</strong></div>
                    </div>

                    <section class="receipt-panel">
                        <div class="receipt-panel__head receipt-panel__head--cols">
                            <span>Flights</span>
                            <span>Departure</span>
                            <span>Arrival</span>
                        </div>

                        <template v-for="(seg, idx) in receipt.segments" :key="idx">
                            <article class="receipt-seg">
                                <div class="receipt-seg__grid">
                                    <div class="receipt-seg__flight">
                                        <div class="receipt-seg__logo">
                                            <i class="fa-solid fa-plane" aria-hidden="true" />
                                        </div>
                                        <div>
                                            <div class="receipt-seg__airline">{{ seg.airline_name }}</div>
                                            <div class="receipt-seg__meta">Flight No: {{ seg.flight_number }}</div>
                                            <div class="receipt-seg__meta">{{ seg.equipment }}</div>
                                            <div class="receipt-seg__meta">Class: {{ classLabel(seg) }}</div>
                                        </div>
                                    </div>

                                    <div class="receipt-seg__point">
                                        <div class="receipt-seg__city">{{ seg.departure_city }}</div>
                                        <div class="receipt-seg__airport">({{ seg.departure_airport }})</div>
                                        <div class="receipt-seg__time">{{ seg.datetime_departure }}</div>
                                        <div class="receipt-seg__detail">Terminal: <strong>{{ seg.origin_terminal }}</strong></div>
                                        <div class="receipt-seg__detail">Baggage: <strong>{{ seg.baggage }}</strong></div>
                                    </div>

                                    <div class="receipt-seg__mid">
                                        <span class="receipt-seg__duration">{{ durationLabel(seg) }}</span>
                                    </div>

                                    <div class="receipt-seg__point">
                                        <div class="receipt-seg__city">{{ seg.arrival_city }}</div>
                                        <div class="receipt-seg__airport">({{ seg.arrival_airport }})</div>
                                        <div class="receipt-seg__time">{{ seg.datetime_arrival }}</div>
                                        <div class="receipt-seg__detail">Terminal: <strong>{{ seg.destination_terminal }}</strong></div>
                                    </div>
                                </div>
                            </article>

                            <div v-if="!seg.lastitem && seg.layover_time" class="receipt-layover">
                                Layover at {{ seg.arrival_city }} - {{ seg.layover_time }} | {{ seg.arrival_airport }}
                            </div>
                            <div v-else-if="seg.lastitem" class="receipt-dest">
                                Reached Destination at {{ seg.arrival_city }} | {{ seg.arrival_airport }}
                            </div>
                        </template>
                    </section>

                    <section class="receipt-panel">
                        <div class="receipt-panel__head">Passenger Details</div>
                        <div class="table-responsive">
                            <table class="receipt-table">
                                <thead>
                                    <tr>
                                        <th>Traveler</th>
                                        <th>Gender</th>
                                        <th>Date of Birth</th>
                                        <th>Passport No.</th>
                                        <th>Contact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(p, pi) in receipt.passengers" :key="pi">
                                        <td>{{ p.name }}</td>
                                        <td>{{ p.gender }}</td>
                                        <td>{{ p.dob }}</td>
                                        <td>{{ p.passport }}</td>
                                        <td>{{ p.contact }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="receipt-panel">
                        <div class="receipt-panel__head receipt-panel__head--fare">Fare Summary</div>
                        <div class="receipt-fare">
                            <div><span>Gross Fare</span><strong>{{ fmtMoney(receipt.fare.grossFare) }}</strong></div>
                            <div><span>TAX</span><strong>{{ fmtMoney(receipt.fare.tax) }}</strong></div>
                            <div><span>AIT</span><strong>{{ fmtMoney(receipt.fare.ait) }}</strong></div>
                            <div><span>Service Charge</span><strong>{{ fmtMoney(receipt.fare.serviceCharge) }}</strong></div>
                            <div><span>Discount</span><strong>{{ fmtMoney(receipt.fare.discount) }}</strong></div>
                            <div class="receipt-fare__total">
                                <span>Total Payable</span>
                                <strong>{{ fmtMoney(receipt.fare.totalPayable) }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="receipt-panel">
                        <div class="receipt-panel__head">Terms &amp; Conditions</div>
                        <div class="receipt-terms">
                            <p v-for="(line, ti) in receipt.terms" :key="ti">{{ line }}</p>
                            <p v-if="receipt.paymentDeadline && receipt.paymentDeadline !== '—'" class="receipt-terms__deadline">
                                Last ticketing time: {{ receipt.paymentDeadline }}
                            </p>
                        </div>
                    </section>

                    <footer class="receipt-doc__footer">
                        <span>Authorized By:</span>
                        <img :src="footerLogo" alt="BLUESKY" class="receipt-doc__footer-logo" />
                    </footer>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.receipt-modal {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(15, 23, 42, 0.55);
    overflow: auto;
    padding: 1rem;
}

.receipt-modal__toolbar {
    position: sticky;
    top: 0;
    z-index: 2;
    max-width: 980px;
    margin: 0 auto 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 0.85rem;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.14);
}

.receipt-modal__close {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: none;
    background: #fff;
    color: #dc2626;
    font-weight: 600;
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.receipt-modal__toolbar-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.receipt-modal__body {
    max-width: 980px;
    margin: 0 auto 2rem;
}

.receipt-doc {
    background: #fff;
    border: 3px solid #0880e1;
    border-radius: 4px;
    overflow: hidden;
    color: #1e293b;
    font-size: 0.82rem;
}

.receipt-doc__header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem 0.75rem;
    border-bottom: 1px solid #dbeafe;
}

.receipt-doc__logo {
    width: 72px;
    height: 72px;
    object-fit: contain;
}

.receipt-doc__agency {
    text-align: right;
    font-size: 0.78rem;
    line-height: 1.45;
}

.receipt-doc__agency strong {
    display: block;
    font-size: 0.95rem;
    margin-bottom: 0.15rem;
}

.receipt-doc__route-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.55rem 1.25rem;
    background: #e8f4fc;
    color: #066bb8;
    font-weight: 700;
    font-size: 0.85rem;
}

.receipt-doc__meta-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    border-bottom: 1px solid #dbeafe;
}

.receipt-doc__meta-grid > div {
    padding: 0.65rem 0.5rem;
    text-align: center;
    border-right: 1px solid #e2e8f0;
}

.receipt-doc__meta-grid > div:last-child {
    border-right: none;
}

.receipt-doc__meta-grid span {
    display: block;
    font-size: 0.68rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.2rem;
}

.receipt-doc__meta-grid strong {
    font-size: 0.82rem;
    color: #0f172a;
}

.receipt-panel {
    border-bottom: 1px solid #dbeafe;
}

.receipt-panel__head {
    background: linear-gradient(135deg, #0880e1, #3b9eff);
    color: #fff;
    text-align: center;
    font-weight: 700;
    padding: 0.45rem 0.75rem;
    font-size: 0.85rem;
}

.receipt-panel__head--cols {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr;
    text-align: left;
    padding-left: 1rem;
    padding-right: 1rem;
}

.receipt-panel__head--fare {
    text-align: left;
    padding-left: 1rem;
}

.receipt-seg {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #eef2f7;
}

.receipt-seg__grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr 120px 1fr;
    gap: 0.75rem;
    align-items: start;
}

.receipt-seg__flight {
    display: flex;
    gap: 0.65rem;
}

.receipt-seg__logo {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0880e1;
    flex-shrink: 0;
}

.receipt-seg__airline {
    font-weight: 700;
    margin-bottom: 0.15rem;
}

.receipt-seg__meta {
    font-size: 0.76rem;
    color: #475569;
    line-height: 1.35;
}

.receipt-seg__city {
    font-weight: 800;
    color: #0fb3a6;
    text-transform: uppercase;
    font-size: 0.82rem;
}

.receipt-seg__airport {
    font-size: 0.74rem;
    color: #475569;
    margin: 0.1rem 0 0.25rem;
}

.receipt-seg__time {
    font-weight: 600;
    font-size: 0.78rem;
    margin-bottom: 0.25rem;
}

.receipt-seg__detail {
    font-size: 0.74rem;
    color: #64748b;
}

.receipt-seg__mid {
    display: flex;
    align-items: center;
    justify-content: center;
    padding-top: 1.5rem;
}

.receipt-seg__duration {
    background: #0880e1;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    white-space: nowrap;
}

.receipt-layover,
.receipt-dest {
    padding: 0.45rem 1rem;
    font-size: 0.78rem;
    font-weight: 600;
    border-bottom: 1px solid #eef2f7;
}

.receipt-layover {
    background: #e8f4fc;
    color: #066bb8;
}

.receipt-dest {
    background: #f4f0ff;
    color: #5b21b6;
}

.receipt-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}

.receipt-table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

.receipt-table tbody td {
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
}

.receipt-fare {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
}

.receipt-fare > div {
    padding: 0.75rem 0.5rem;
    text-align: center;
    border-right: 1px solid #e2e8f0;
}

.receipt-fare > div:last-child {
    border-right: none;
}

.receipt-fare span {
    display: block;
    font-weight: 700;
    font-size: 0.76rem;
    margin-bottom: 0.25rem;
}

.receipt-fare strong {
    font-size: 0.82rem;
    color: #334155;
}

.receipt-fare__total {
    background: #e8f4fc;
}

.receipt-fare__total strong {
    color: #0880e1;
    font-size: 1rem;
}

.receipt-terms {
    padding: 0.85rem 1rem 1rem;
    font-size: 0.72rem;
    line-height: 1.5;
    color: #475569;
}

.receipt-terms p {
    margin: 0 0 0.35rem;
}

.receipt-terms__deadline {
    margin-top: 0.5rem !important;
    font-weight: 700;
    color: #066bb8;
}

.receipt-doc__footer {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem 1rem;
    font-weight: 600;
}

.receipt-doc__footer-logo {
    height: 28px;
}

@media (max-width: 768px) {
    .receipt-doc__meta-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .receipt-seg__grid {
        grid-template-columns: 1fr;
    }

    .receipt-fare {
        grid-template-columns: repeat(2, 1fr);
    }

    .receipt-panel__head--cols {
        display: none;
    }
}

@media print {
    :global(html),
    :global(body) {
        overflow: visible !important;
        height: auto !important;
    }

    :global(.no-print) {
        display: none !important;
    }

    :global(body *) {
        visibility: hidden;
    }

    :global(.receipt-modal),
    :global(.receipt-modal *) {
        visibility: visible;
    }

    :global(.receipt-modal) {
        position: absolute;
        inset: 0;
        background: #fff !important;
        padding: 0;
        overflow: visible !important;
    }

    :global(.receipt-modal__body),
    :global(.receipt-doc),
    :global(.table-responsive) {
        max-width: none;
        margin: 0;
        overflow: visible !important;
        max-height: none !important;
    }
}
</style>
