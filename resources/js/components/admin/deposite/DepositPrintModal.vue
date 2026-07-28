<script setup>
import { ref, computed } from 'vue';
import moment from 'moment';
import { resolveUploadUrl } from '../../../utils/resolveUploadUrl';
import { formatDisplayAmount } from '../../../utils/numberFormat';

const props = defineProps({
    visible: { type: Boolean, default: false },
    deposit: { type: Object, default: null },
    depositHash: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const printRoot = ref(null);
const companyLogo = new URL('../../../../../public/theme/appimages/blueskywings.png', import.meta.url).href;
const footerLogo = new URL('../../../../../public/theme/appimages/bluesky.svg', import.meta.url).href;
const planePlaceholder = new URL('../../../../../public/theme/appimages/Plane_origin.svg', import.meta.url).href;

const hasDeposit = computed(() => !!props.deposit);
const agentLogo = computed(() => {
    const url = resolveUploadUrl(props.deposit?.logo_path);
    return url || planePlaceholder;
});
const referenceUrl = computed(() => resolveUploadUrl(props.deposit?.reference_file));
const printedAt = computed(() => moment().format('DD MMM YYYY, hh:mm A'));

function fmtDate(value) {
    return value ? moment(value).format('DD-MMM-YYYY') : '—';
}

function fmtDateTime(value) {
    return value ? moment(value).format('DD-MMM-YYYY | hh:mm A') : '—';
}

function handleClose() {
    emit('close');
}

function reportPrintResetStyles() {
    return `
@page {
    size: A4 portrait;
    margin: 6mm 7mm;
}
html, body {
    margin: 0;
    padding: 0;
    background: #fff;
    overflow: visible !important;
    height: auto !important;
}
.deposit-print-body,
.deposit-report,
.deposit-report-modal__body {
    overflow: visible !important;
    max-height: none !important;
    border-radius: 0;
}
.deposit-print-body,
.deposit-print-body * {
    visibility: visible !important;
}
.deposit-report {
    font-size: 8.5pt;
    line-height: 1.25;
    page-break-inside: avoid;
    break-inside: avoid;
}
.deposit-report__header { padding: 5px 8px; }
.deposit-report__company-logo { width: 34px; height: 34px; }
.deposit-report__doc-title { font-size: 11pt; }
.deposit-report__doc-sub { font-size: 7pt; }
.deposit-report__meta-top { font-size: 7.5pt; gap: 2px; }
.deposit-report__meta-top span { font-size: 6.5pt; }
.deposit-report__agent-strip { padding: 5px 8px; gap: 8px; }
.deposit-report__agent-logo { width: 32px; height: 32px; padding: 2px; }
.deposit-report__agent-info strong { font-size: 9pt; }
.deposit-report__agent-info div { font-size: 7.5pt; }
.deposit-report__balance-pill { font-size: 7.5pt; padding: 2px 6px; }
.deposit-report__main { gap: 6px; padding: 0 8px 6px; }
.deposit-report__panel { margin: 0; }
.deposit-report__panel-head { padding: 3px 6px; font-size: 6.5pt; }
.deposit-report__kv { gap: 1px 8px; padding: 4px 6px; }
.deposit-report__kv dt { font-size: 7pt; }
.deposit-report__kv dd { font-size: 7.5pt; }
.deposit-report__amount-row th,
.deposit-report__amount-row td { padding: 2px 6px; font-size: 7.5pt; }
.deposit-report__total-row th,
.deposit-report__total-row td { font-size: 8pt; }
.deposit-report__remarks { padding: 4px 6px; font-size: 7.5pt; line-height: 1.3; max-height: 36px; }
.deposit-report__reference-img { max-width: 56px; max-height: 48px; margin: 4px 6px; }
.deposit-report__footer { padding: 4px 8px; font-size: 6.5pt; }
.deposit-report__footer-logo { height: 14px; }
@media print {
    html, body {
        overflow: visible !important;
        height: auto !important;
    }
    * {
        overflow: visible !important;
        max-height: none !important;
    }
    .deposit-report {
        border-width: 1px;
    }
}
`;
}

function stripPrintMedia(css) {
    let result = '';
    let i = 0;
    const lower = css.toLowerCase();

    while (i < css.length) {
        const idx = lower.indexOf('@media print', i);
        if (idx === -1) {
            result += css.slice(i);
            break;
        }

        result += css.slice(i, idx);
        const braceStart = css.indexOf('{', idx);
        if (braceStart === -1) break;

        let depth = 1;
        let j = braceStart + 1;
        while (j < css.length && depth > 0) {
            if (css[j] === '{') depth += 1;
            if (css[j] === '}') depth -= 1;
            j += 1;
        }
        i = j;
    }

    return result;
}

function reportPrintStyles() {
    return [...document.querySelectorAll('style')]
        .filter((el) => el.textContent.includes('deposit-report'))
        .map((el) => stripPrintMedia(el.textContent))
        .join('\n');
}

function waitForImages(doc) {
    const imgs = [...doc.images];
    if (!imgs.length) return Promise.resolve();
    return Promise.all(imgs.map((img) => (
        img.complete
            ? Promise.resolve()
            : new Promise((resolve) => {
                img.onload = resolve;
                img.onerror = resolve;
            })
    )));
}

function openPrintDialog() {
    const docEl = printRoot.value?.querySelector('.deposit-report');
    if (!docEl) return;

    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;border:0;visibility:hidden;pointer-events:none';
    document.body.appendChild(iframe);

    const win = iframe.contentWindow;
    const doc = win.document;
    const ref = props.deposit?.reference_no || props.depositHash || props.deposit?.id || '';
    const title = `Deposit Report ${ref}`;

    doc.open();
    doc.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>${title}</title>
<style>
${reportPrintStyles()}
${reportPrintResetStyles()}
</style>
</head><body class="deposit-print-body">${docEl.outerHTML}</body></html>`);
    doc.close();

    const cleanup = () => iframe.remove();

    const triggerPrint = () => {
        win.onafterprint = cleanup;
        win.focus();
        win.print();
        setTimeout(cleanup, 120_000);
    };

    waitForImages(doc).then(() => {
        requestAnimationFrame(() => {
            requestAnimationFrame(triggerPrint);
        });
    });
}

function handlePrint() {
    openPrintDialog();
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="visible && hasDeposit"
            class="deposit-report-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="deposit-report-title"
        >
            <div class="deposit-report-modal__toolbar no-print">
                <button type="button" class="deposit-report-modal__close" @click="handleClose">
                    <i class="fa-solid fa-xmark" aria-hidden="true" />
                    Close
                </button>
                <button type="button" class="btn btn-primary btn-sm" @click="handlePrint">
                    <i class="fa-solid fa-print me-1" aria-hidden="true" />
                    Print
                </button>
            </div>

            <div ref="printRoot" class="deposit-report-modal__body">
                <div id="deposit-report-title" class="deposit-report">
                    <header class="deposit-report__header">
                        <div class="deposit-report__brand">
                            <img :src="companyLogo" alt="BlueSky" class="deposit-report__company-logo" />
                            <div>
                                <div class="deposit-report__doc-title">Deposit Request Report</div>
                                <div class="deposit-report__doc-sub">Deposit Management</div>
                            </div>
                        </div>
                        <div class="deposit-report__meta-top">
                            <div><span>Status</span><strong>{{ deposit.status || '—' }}</strong></div>
                            <div><span>Ref No</span><strong>{{ deposit.reference_no || '—' }}</strong></div>
                            <div><span>Printed</span><strong>{{ printedAt }}</strong></div>
                        </div>
                    </header>

                    <div class="deposit-report__agent-strip">
                        <img :src="agentLogo" alt="Agent" class="deposit-report__agent-logo" />
                        <div class="deposit-report__agent-info">
                            <strong>{{ deposit.agent_name || '—' }}</strong>
                            <div>{{ deposit.agent_code || '—' }}<span v-if="deposit.iata_number"> · IATA</span></div>
                        </div>
                        <div class="deposit-report__balance-pills">
                            <span class="deposit-report__balance-pill">
                                Credit: <strong>৳ {{ formatDisplayAmount(deposit.credit_balance) }}</strong>
                            </span>
                            <span class="deposit-report__balance-pill">
                                Current: <strong>৳ {{ formatDisplayAmount(deposit.net_balance) }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="deposit-report__main">
                        <section class="deposit-report__panel">
                            <div class="deposit-report__panel-head">Payment Information</div>
                            <dl class="deposit-report__kv deposit-report__kv--3col">
                                <dt>Payment Term</dt>
                                <dd>{{ deposit.type || '—' }}</dd>
                                <dt>Payment Account</dt>
                                <dd>{{ deposit.bank_name || '—' }}</dd>
                                <dt>Account No.</dt>
                                <dd>{{ deposit.acc_no || '—' }}</dd>
                                <dt>Branch</dt>
                                <dd>{{ deposit.branch || '—' }}</dd>
                                <dt>Issued Bank</dt>
                                <dd>{{ deposit.issued_bank || '—' }}</dd>
                                <dt>Reference Date</dt>
                                <dd>{{ fmtDate(deposit.reference_date) }}</dd>
                            </dl>
                        </section>

                        <section class="deposit-report__panel">
                            <div class="deposit-report__panel-head">Amount Summary</div>
                            <table class="deposit-report__amount-table">
                                <tbody>
                                    <tr class="deposit-report__amount-row">
                                        <th>Requested</th>
                                        <td>৳ {{ formatDisplayAmount(deposit.amount) }}</td>
                                    </tr>
                                    <tr class="deposit-report__amount-row">
                                        <th>Charge ({{ deposit.charge_percent ?? '0' }}%)</th>
                                        <td>৳ {{ formatDisplayAmount(deposit.charge) }}</td>
                                    </tr>
                                    <tr class="deposit-report__amount-row">
                                        <th>Credit Adj.</th>
                                        <td>৳ {{ formatDisplayAmount(deposit.credit_balance) }}</td>
                                    </tr>
                                    <tr class="deposit-report__amount-row deposit-report__total-row">
                                        <th>Total</th>
                                        <td>৳ {{ formatDisplayAmount(deposit.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>

                        <section class="deposit-report__panel">
                            <div class="deposit-report__panel-head">Request & Approval</div>
                            <dl class="deposit-report__kv">
                                <dt>Requested By</dt>
                                <dd>{{ deposit.requested_by || '—' }}</dd>
                                <dt>Requested At</dt>
                                <dd>{{ fmtDateTime(deposit.created_at) }}</dd>
                                <dt>Processed By</dt>
                                <dd>{{ deposit.approved_by || '—' }}</dd>
                                <dt>Status</dt>
                                <dd>{{ deposit.status || '—' }}</dd>
                            </dl>
                        </section>
                    </div>

                    <div class="deposit-report__bottom">
                        <section class="deposit-report__panel deposit-report__panel--remarks">
                            <div class="deposit-report__panel-head">Remarks</div>
                            <p class="deposit-report__remarks">{{ deposit.remarks || '—' }}</p>
                        </section>
                        <section v-if="referenceUrl" class="deposit-report__panel deposit-report__panel--ref">
                            <div class="deposit-report__panel-head">Reference</div>
                            <img :src="referenceUrl" alt="Reference" class="deposit-report__reference-img" />
                        </section>
                    </div>

                    <footer class="deposit-report__footer">
                        <img :src="footerLogo" alt="BlueSky" class="deposit-report__footer-logo" />
                        <span>BlueSky Deposit Management · {{ fmtDateTime(deposit.created_at) }}</span>
                    </footer>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.deposit-report-modal {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(15, 23, 42, 0.55);
    overflow: auto;
    padding: 1rem;
}

.deposit-report-modal__toolbar {
    position: sticky;
    top: 0;
    z-index: 2;
    max-width: 820px;
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

.deposit-report-modal__close {
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

.deposit-report-modal__body {
    max-width: 820px;
    margin: 0 auto 2rem;
}

.deposit-report {
    background: #fff;
    border: 2px solid #7239ea;
    border-radius: 4px;
    overflow: hidden;
    color: #1e293b;
    font-size: 0.72rem;
    line-height: 1.3;
}

.deposit-report__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    padding: 0.45rem 0.65rem;
    border-bottom: 1px solid #e9e0ff;
    background: #faf8ff;
}

.deposit-report__brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.deposit-report__company-logo {
    width: 38px;
    height: 38px;
    object-fit: contain;
}

.deposit-report__doc-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1a1a2e;
    line-height: 1.2;
}

.deposit-report__doc-sub {
    font-size: 0.62rem;
    color: #64748b;
}

.deposit-report__meta-top {
    display: flex;
    gap: 0.75rem;
    text-align: right;
    font-size: 0.68rem;
}

.deposit-report__meta-top > div {
    min-width: 0;
}

.deposit-report__meta-top span {
    display: block;
    color: #64748b;
    font-size: 0.58rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.deposit-report__meta-top strong {
    color: #1e293b;
    font-size: 0.7rem;
}

.deposit-report__agent-strip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.65rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}

.deposit-report__agent-logo {
    width: 36px;
    height: 36px;
    object-fit: contain;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 2px;
    flex-shrink: 0;
}

.deposit-report__agent-info {
    flex: 1;
    min-width: 0;
}

.deposit-report__agent-info strong {
    display: block;
    font-size: 0.78rem;
    line-height: 1.2;
}

.deposit-report__agent-info div {
    color: #64748b;
    font-size: 0.65rem;
}

.deposit-report__balance-pills {
    display: flex;
    gap: 0.35rem;
    flex-shrink: 0;
}

.deposit-report__balance-pill {
    font-size: 0.62rem;
    color: #64748b;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 0.15rem 0.4rem;
    white-space: nowrap;
}

.deposit-report__balance-pill strong {
    color: #1e293b;
}

.deposit-report__main {
    display: grid;
    grid-template-columns: 1.4fr 0.9fr 1fr;
    gap: 0.4rem;
    padding: 0.4rem 0.65rem 0;
}

.deposit-report__panel {
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.deposit-report__panel-head {
    padding: 0.2rem 0.45rem;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #475569;
}

.deposit-report__kv {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.1rem 0.5rem;
    padding: 0.3rem 0.45rem;
    margin: 0;
}

.deposit-report__kv--3col {
    grid-template-columns: auto 1fr auto 1fr;
}

.deposit-report__kv dt {
    margin: 0;
    color: #64748b;
    font-weight: 600;
    font-size: 0.62rem;
}

.deposit-report__kv dd {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.66rem;
}

.deposit-report__amount-table {
    width: 100%;
    border-collapse: collapse;
}

.deposit-report__amount-row th,
.deposit-report__amount-row td {
    padding: 0.15rem 0.45rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.66rem;
}

.deposit-report__amount-row th {
    color: #64748b;
    font-weight: 600;
    text-align: left;
}

.deposit-report__amount-row td {
    text-align: right;
    font-weight: 700;
    color: #1e293b;
}

.deposit-report__amount-row:last-child th,
.deposit-report__amount-row:last-child td {
    border-bottom: none;
}

.deposit-report__total-row th,
.deposit-report__total-row td {
    background: #f3eeff;
    color: #5b21b6;
    font-size: 0.72rem;
}

.deposit-report__bottom {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.4rem;
    padding: 0.4rem 0.65rem;
    align-items: start;
}

.deposit-report__panel--ref {
    width: 80px;
}

.deposit-report__remarks {
    margin: 0;
    padding: 0.3rem 0.45rem;
    color: #475569;
    font-size: 0.66rem;
    line-height: 1.35;
    white-space: pre-wrap;
    max-height: 2.8em;
    overflow: hidden;
}

.deposit-report__reference-img {
    display: block;
    max-width: 64px;
    max-height: 52px;
    margin: 0.3rem auto;
    border: 1px solid #e2e8f0;
    border-radius: 3px;
    object-fit: contain;
}

.deposit-report__footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.3rem 0.65rem;
    border-top: 1px solid #e2e8f0;
    font-size: 0.6rem;
    color: #64748b;
    font-weight: 600;
    background: #fafafa;
}

.deposit-report__footer-logo {
    height: 16px;
}

@media (max-width: 768px) {
    .deposit-report__header {
        flex-direction: column;
        align-items: flex-start;
    }

    .deposit-report__meta-top {
        flex-wrap: wrap;
        text-align: left;
    }

    .deposit-report__agent-strip {
        flex-wrap: wrap;
    }

    .deposit-report__main {
        grid-template-columns: 1fr;
    }

    .deposit-report__bottom {
        grid-template-columns: 1fr;
    }

    .deposit-report__panel--ref {
        width: auto;
    }
}

@media print {
    :global(.no-print) {
        display: none !important;
    }
}
</style>
