<script setup>
import { ref, computed } from 'vue'
import AppModal from '../../common/AppModal.vue'
import BookingReceiptDoc from './BookingReceiptDoc.vue'

const props = defineProps({
    visible: { type: Boolean, default: false },
    receipt: { type: Object, default: null },
    commitError: { type: String, default: null },
    commitPending: { type: Boolean, default: false },
    workbenchExpired: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'retry', 'new-search', 'go-to-list'])

const docRef = ref(null)
const copiedField = ref(null)

const isSuccess = computed(() => !!props.receipt)

const title = computed(() => {
    if (isSuccess.value) return 'Booking Successful!'
    if (props.workbenchExpired) return 'Workbench Session Ended'
    if (props.commitPending) return 'Commit Pending'
    return 'Could Not Complete Booking'
})

const message = computed(() => {
    if (isSuccess.value) return 'Your booking request has been submitted.'
    if (props.workbenchExpired) {
        return 'Travelport workbench closed (expired or too many failed attempts). Retry will not work — start a new search to book again. Local review data is saved.'
    }
    if (props.commitError) return props.commitError
    return 'GDS commit did not complete. Your review is saved — you can retry.'
})

function legacyCopy(text) {
    const el = document.createElement('textarea')
    el.value = text
    el.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0;'
    document.body.appendChild(el)
    el.focus()
    el.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(el)
    return ok
}

async function copyText(text, field, label) {
    if (!text) return
    try {
        let ok = true
        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text)
            } catch {
                ok = legacyCopy(text)
            }
        } else {
            ok = legacyCopy(text)
        }
        if (!ok) throw new Error('copy failed')
        copiedField.value = field
        setTimeout(() => { copiedField.value = null }, 1600)
        Notification?.showToast?.('s', `${label} copied to clipboard.`)
    } catch {
        Notification?.showToast?.('e', `Failed to copy ${label}.`)
    }
}

function handlePrint() {
    docRef.value?.print()
}

function handleDownload() {
    docRef.value?.download()
}
</script>

<template>
    <AppModal
        :is-open="visible"
        :show-header="false"
        size="md"
        max-width="440px"
        :close-on-backdrop="false"
        :backdrop-opacity="0.8"
        @close="emit('close')"
    >
        <div class="bcm-shell">
            <button type="button" class="bcm-close" aria-label="Close" @click="emit('close')">
                <i class="fa-solid fa-xmark" aria-hidden="true" />
            </button>

            <div v-if="isSuccess" class="bcm-header">
                <svg class="bcm-header-img" viewBox="0 0 1026 650" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <g clip-path="url(#bcmWingClip)">
                        <path d="M954.96 335.875L143.945 606.202C331.363 632.13 733.613 668.429 843.271 606.202C952.93 543.976 963.422 400.057 954.96 335.875Z" fill="url(#bcmWingPaint0)" />
                        <path d="M875.864 163.292L145.037 607.204C333.611 590.439 734.035 535.671 827.133 450.724C920.231 365.776 898.411 223.708 875.864 163.292Z" fill="url(#bcmWingPaint1)" />
                        <path d="M717.049 1.06781L126.119 619.103C303.918 554.073 676.497 397.473 744.419 291.313C812.341 185.153 754.473 53.5829 717.049 1.06781Z" fill="url(#bcmWingPaint2)" />
                    </g>
                    <defs>
                        <linearGradient id="bcmWingPaint0" x1="942.091" y1="439.296" x2="188.524" y2="599.922" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#964EF1" />
                            <stop offset="1" stop-color="#AE1AC6" stop-opacity="0" />
                        </linearGradient>
                        <linearGradient id="bcmWingPaint1" x1="884.731" y1="266.736" x2="150.274" y2="602.971" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#3B79F2" />
                            <stop offset="1" stop-color="#80A3FF" stop-opacity="0" />
                        </linearGradient>
                        <linearGradient id="bcmWingPaint2" x1="747.607" y1="90.1656" x2="125.853" y2="621.755" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#5AFFEB" />
                            <stop offset="1" stop-color="#72CCFF" stop-opacity="0" />
                        </linearGradient>
                        <clipPath id="bcmWingClip">
                            <rect width="1026" height="650" fill="white" />
                        </clipPath>
                    </defs>
                </svg>
            </div>

            <div class="bcm-body">
                <div v-if="!isSuccess" class="bcm-icon" :class="workbenchExpired ? 'bcm-icon--error' : 'bcm-icon--pending'">
                    <i :class="workbenchExpired ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-clock'" aria-hidden="true" />
                </div>

                <h2 class="bcm-title" :class="{ 'bcm-title--success': isSuccess }">{{ title }}</h2>
                <p v-if="message" class="bcm-message">{{ message }}</p>

                <div v-if="isSuccess" class="bcm-refs">
                    <button type="button" class="bcm-pill bcm-pill--ref" @click="copyText(receipt.bookingId, 'bookingId', 'Booking Ref')">
                        <span>Booking Ref : <strong>{{ receipt.bookingId }}</strong></span>
                        <i class="fa-solid" :class="copiedField === 'bookingId' ? 'fa-check' : 'fa-copy'" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="bcm-pill bcm-pill--pnr"
                        @click="copyText(receipt.gdsPnr !== '—' ? receipt.gdsPnr : receipt.airlinePnr, 'pnr', 'PNR')"
                    >
                        <span>PNR : <strong>{{ receipt.gdsPnr !== '—' ? receipt.gdsPnr : receipt.airlinePnr }}</strong></span>
                        <i class="fa-solid" :class="copiedField === 'pnr' ? 'fa-check' : 'fa-copy'" aria-hidden="true" />
                    </button>
                </div>

                <div v-if="isSuccess" class="bcm-actions">
                    <button type="button" class="bcm-action-btn bcm-action-btn--light" @click="handlePrint">
                        <i class="fa-solid fa-print me-1" aria-hidden="true" />
                        Print Voucher
                    </button>
                    <button type="button" class="bcm-action-btn bcm-action-btn--primary" @click="handleDownload">
                        <i class="fa-solid fa-download me-1" aria-hidden="true" />
                        Download Voucher
                    </button>
                </div>

                <div v-else class="bcm-actions">
                    <button
                        v-if="workbenchExpired"
                        type="button"
                        class="bcm-action-btn bcm-action-btn--primary bcm-action-btn--full"
                        @click="emit('new-search')"
                    >
                        <i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true" />
                        Start New Search
                    </button>
                    <button
                        v-else
                        type="button"
                        class="bcm-action-btn bcm-action-btn--warning bcm-action-btn--full"
                        :disabled="loading"
                        @click="emit('retry')"
                    >
                        <i v-if="loading" class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true" />
                        <i v-else class="fa-solid fa-rotate-right me-1" aria-hidden="true" />
                        Retry
                    </button>
                </div>

                <span class="bcm-divider">Or</span>

                <button type="button" class="bcm-list-link" @click="emit('go-to-list')">
                    Go to Booking List <i class="fa-solid fa-arrow-right" aria-hidden="true" />
                </button>
            </div>

            <BookingReceiptDoc
                v-if="isSuccess"
                ref="docRef"
                :key="receipt?.gdsPnr || receipt?.bookingId || 'receipt'"
                :receipt="receipt"
                class="bcm-hidden-doc"
            />
        </div>
    </AppModal>
</template>

<style scoped>
.bcm-shell {
    position: relative;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    overflow-x: hidden;
}

.bcm-close {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.85);
    color: #334155;
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
    transition: background 0.15s ease;
    z-index: 2;
}
.bcm-close:hover {
    background: #fff;
}

.bcm-header {
    position: relative;
    width: 100%;
    height: 60px;
    background: linear-gradient(135deg, #adfff5 0%, #9dbcf9 55%, #caa9f8 100%);
    overflow: hidden;
    animation: bcm-header-in 0.4s ease both;
}
.bcm-header-img {
    position: absolute;
    inset: 0;
    display: block;
    width: 100%;
    height: 100%;
    opacity: 0.65;
}

@keyframes bcm-header-in {
    0% { opacity: 0; transform: scale(1.08); }
    100% { opacity: 1; transform: scale(1); }
}

.bcm-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.5rem 1.5rem;
}

.bcm-icon {
    width: 92px;
    height: 92px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

.bcm-icon--pending,
.bcm-icon--error {
    border-radius: 50%;
    font-size: 2.1rem;
    animation: bcm-pulse 1.6s ease-in-out infinite;
}
.bcm-icon--pending {
    background: rgba(245, 158, 11, 0.12);
    color: #b45309;
}
.bcm-icon--error {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
}

@keyframes bcm-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.06); opacity: 0.85; }
}

.bcm-title {
    margin: 0 0 0.4rem;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}
.bcm-title--success {
    margin-top: 0.5rem;
    margin-bottom: 0.8rem;
    font-size: 1.5rem;
    color: #16a34a;
}

.bcm-message {
    margin: 0 0 1rem;
    font-size: 0.82rem;
    line-height: 1.5;
    color: #64748b;
}

.bcm-refs {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
}

.bcm-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    border-radius: 999px;
    padding: 0.45rem 1rem;
    font-size: 0.82rem;
    cursor: pointer;
    transition: filter 0.15s ease;
}
.bcm-pill:hover {
    filter: brightness(0.97);
}
.bcm-pill strong {
    font-weight: 700;
    letter-spacing: 0.02em;
}
.bcm-pill i {
    font-size: 0.75rem;
    opacity: 0.8;
}

.bcm-pill--ref {
    background: #e5f6fb;
    color: #0e7490;
}
.bcm-pill--pnr {
    background: #f2ecfe;
    color: #7239ea;
}

.bcm-actions {
    width: 100%;
    display: flex;
    gap: 0.6rem;
    margin-bottom: 0.85rem;
}

.bcm-action-btn {
    flex: 1;
    border: none;
    border-radius: 10px;
    padding: 0.55rem 0.75rem;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: filter 0.15s ease;
}
.bcm-action-btn:hover:not(:disabled) {
    filter: brightness(0.95);
}
.bcm-action-btn:disabled {
    opacity: 0.7;
    cursor: default;
}

.bcm-action-btn--light {
    background: #eef1f4;
    color: #334155;
}
.bcm-action-btn--primary {
    background: #2563eb;
    color: #fff;
}
.bcm-action-btn--warning {
    background: #f59e0b;
    color: #fff;
}

.bcm-action-btn--full {
    flex: 1 0 100%;
}

.bcm-divider {
    display: block;
    font-size: 0.72rem;
    color: #94a3b8;
    margin-bottom: 0.6rem;
}

.bcm-list-link {
    border: none;
    background: none;
    color: #0f172a;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0.35rem 0.5rem;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.bcm-list-link:hover {
    opacity: 0.75;
}
.bcm-list-link i {
    font-size: 0.72rem;
    margin-left: 0.15rem;
}

.bcm-hidden-doc {
    position: fixed;
    top: 0;
    left: -99999px;
    width: 900px;
    pointer-events: none;
}

@media (max-width: 480px) {
    .bcm-actions {
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bcm-icon--pending,
    .bcm-icon--error,
    .bcm-header {
        animation: none;
    }
}

/* ── Dark mode ──────────────────────────────────────────────── */
[data-bs-theme="dark"] .bcm-title {
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .bcm-title--success {
    color: #4ade80;
}
[data-bs-theme="dark"] .bcm-message {
    color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .bcm-pill--ref {
    background: rgba(14, 116, 144, 0.22);
    color: #67e8f9;
}
[data-bs-theme="dark"] .bcm-pill--pnr {
    background: rgba(114, 57, 234, 0.22);
    color: #c084fc;
}
[data-bs-theme="dark"] .bcm-action-btn--light {
    background: rgba(255, 255, 255, 0.08);
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .bcm-divider {
    color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .bcm-icon--pending {
    background: rgba(245, 158, 11, 0.16);
    color: #fbbf24;
}
[data-bs-theme="dark"] .bcm-icon--error {
    background: rgba(239, 68, 68, 0.16);
    color: #f87171;
}
[data-bs-theme="dark"] .bcm-list-link {
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .bcm-list-link:hover {
    opacity: 0.75;
}
</style>
