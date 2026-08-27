<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import BookingReceiptDoc from './BookingReceiptDoc.vue'
import { openVoucherPrintWindow } from '../../../utils/voucherPrint'

const props = defineProps({
    visible:       { type: Boolean, default: false },
    ticketNumbers: { type: Array,   default: () => [] },
    ticketedAt:    { type: String,  default: null },
    pnr:           { type: String,  default: null },
    // Full booking receipt (same shape BookingReceiptDoc/"Booking Confirmed" uses) — powers the
    // two print buttons. Left null, printing is unavailable but the ticket numbers still show.
    receipt:       { type: Object,  default: null },
})

const emit = defineEmits(['close'])

const NUMBERS_PREVIEW_LIMIT = 4

const hasTickets = computed(() => props.ticketNumbers.length > 0)
const hasReceipt = computed(() => !!props.receipt)
// 2x2 grid once there's more than one ticket — a lone ticket gets a content-sized chip instead
const isNumbersGrid = computed(() => props.ticketNumbers.length > 1)

const showAllNumbers = ref(false)
const visibleNumbers = computed(() => (
    showAllNumbers.value ? props.ticketNumbers : props.ticketNumbers.slice(0, NUMBERS_PREVIEW_LIMIT)
))
const hiddenNumbersCount = computed(() => Math.max(0, props.ticketNumbers.length - NUMBERS_PREVIEW_LIMIT))

watch(() => props.visible, (v) => {
    if (!v) showAllNumbers.value = false
})

// Which tickets "Print Single Page" includes — defaults to all, user can uncheck ones they
// don't need. Resets whenever a fresh ticket list comes in (new array each time modal opens).
const selectedIndexes = ref(new Set())
watch(() => props.ticketNumbers, (nums) => {
    selectedIndexes.value = new Set(nums.map((_, idx) => idx))
}, { immediate: true })

function isSelected(idx) {
    return selectedIndexes.value.has(idx)
}

function toggleSelected(idx) {
    const next = new Set(selectedIndexes.value)
    if (next.has(idx)) next.delete(idx)
    else next.add(idx)
    selectedIndexes.value = next
}

const selectedCount = computed(() => selectedIndexes.value.size)

const formattedDate = computed(() => {
    if (!props.ticketedAt) return null
    try {
        return new Date(props.ticketedAt).toLocaleString('en-GB', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: true,
        })
    } catch {
        return props.ticketedAt
    }
})

function handleClose() {
    emit('close')
}

function toggleShowAllNumbers() {
    showAllNumbers.value = !showAllNumbers.value
}

// Off-screen BookingReceiptDoc instances — one prints every ticket number on a single
// voucher, the rest each print exactly one ticket number, combined into one print job below.
const printAllRef = ref(null)
const singleDocRefs = ref([])
function bindSingleDocRef(idx) {
    return (el) => { singleDocRefs.value[idx] = el }
}

const printingAll = ref(false)
const printingSingle = ref(false)

async function onPrintAll() {
    if (!hasReceipt.value || !hasTickets.value || printingAll.value) return
    printingAll.value = true
    try {
        await nextTick()
        await printAllRef.value?.print()
    } finally {
        printingAll.value = false
    }
}

async function onPrintSingle() {
    if (!hasReceipt.value || !hasTickets.value || printingSingle.value || selectedCount.value === 0) return
    printingSingle.value = true
    try {
        await nextTick()
        const targetIndexes = [...selectedIndexes.value].sort((a, b) => a - b)
        const htmls = await Promise.all(
            targetIndexes.map((idx) => singleDocRefs.value[idx]?.getPrintHtml())
        )
        const pages = htmls
            .filter(Boolean)
            .map((html) => `<div class="ticket-print-page">${html}</div>`)
            .join('')
        if (!pages) return
        openVoucherPrintWindow(
            pages,
            `Tickets ${props.pnr ?? ''}`,
            // Every page carries the same @page margin now, so each ticket only needs to
            // start on a fresh sheet — no per-document margin juggling.
            `.ticket-print-page { page-break-after: always; }
             .ticket-print-page:last-child { page-break-after: auto; }`,
        )
    } finally {
        printingSingle.value = false
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="tm-fade">
            <div v-if="visible" class="tm-overlay" @click.self="handleClose">
                <div class="bp-card">

                    <!-- LEFT PANEL -->
                    <div class="bp-left">
                        <img :src="'/theme/appimages/worlds.png'" class="bp-worlds-bg" aria-hidden="true" />
                        <div class="bp-brand">
                            <img :src="'/theme/appimages/blueskywings.png'" class="bp-logo-img" alt="BlueSky" />
                            <span class="bp-brand-name">BLUESKY</span>
                        </div>

                        <div class="bp-left-body">
                            <div class="bp-issued-badge">
                                <i class="fa-solid fa-circle-check" />
                                e-Ticket Issued
                            </div>

                            <div class="bp-field">
                                <div class="bp-field-label">PNR / Locator</div>
                                <div class="bp-field-value bp-pnr">{{ pnr ?? '—' }}</div>
                            </div>

                            <div class="bp-field">
                                <div class="bp-field-label">Issued Via</div>
                                <div class="bp-field-value">Travelport GDS</div>
                            </div>
                        </div>

                    </div>

                    <!-- DIVIDER + NOTCHES -->
                    <div class="bp-divider">
                        <div class="bp-notch bp-notch--top" />
                        <div class="bp-notch bp-notch--bottom" />
                    </div>

                    <!-- RIGHT STUB -->
                    <div class="bp-right">
                        <button class="bp-close-x" @click="handleClose">
                            <i class="fa-solid fa-xmark" />
                        </button>

                        <div class="bp-stub-label">E-Ticket Numbers</div>

                        <div
                            v-if="hasTickets"
                            class="bp-numbers"
                            :class="isNumbersGrid ? 'bp-numbers--grid' : 'bp-numbers--single'"
                        >
                            <label
                                v-for="(num, idx) in visibleNumbers"
                                :key="idx"
                                class="bp-number-row"
                            >
                                <input
                                    type="checkbox"
                                    class="bp-number-checkbox"
                                    :checked="isSelected(idx)"
                                    @change="toggleSelected(idx)"
                                />
                                <span class="bp-number">{{ num }}</span>
                            </label>
                            <button
                                v-if="hiddenNumbersCount > 0"
                                type="button"
                                class="bp-numbers-toggle"
                                :class="{ 'bp-numbers-toggle--grid': isNumbersGrid }"
                                @click="toggleShowAllNumbers"
                            >
                                {{ showAllNumbers ? 'Show less' : `+${hiddenNumbersCount} more` }}
                            </button>
                        </div>
                        <div v-else class="bp-no-number">
                            Ticket issued but numbers not returned in response.
                        </div>

                        <div v-if="formattedDate" class="bp-issued-at">
                            <i class="fa-regular fa-clock" />
                            <span>{{ formattedDate }}</span>
                        </div>

                        <div v-if="hasReceipt && hasTickets" class="bp-print-actions">
                            <button
                                type="button"
                                class="bp-print-btn"
                                :disabled="printingAll"
                                @click="onPrintAll"
                            >
                                <i class="fa-solid fa-print" />
                                {{ printingAll ? 'Preparing…' : 'Print All Ticket' }}
                            </button>
                            <button
                                type="button"
                                class="bp-print-btn"
                                :disabled="printingSingle || selectedCount === 0"
                                @click="onPrintSingle"
                            >
                                <i class="fa-solid fa-copy" />
                                {{ printingSingle
                                    ? 'Preparing…'
                                    : `Print Single Page${selectedCount < ticketNumbers.length ? ` (${selectedCount})` : ''}` }}
                            </button>
                        </div>

                        <button class="bp-close-btn" @click="handleClose">Close</button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Off-screen — rendered so BookingReceiptDoc's own A4 layout engine can run, never shown -->
    <Teleport to="body">
        <div v-if="visible && hasReceipt" class="ticket-print-offstage" aria-hidden="true">
            <BookingReceiptDoc ref="printAllRef" :receipt="receipt" :ticket-numbers="ticketNumbers" />
            <BookingReceiptDoc
                v-for="(num, idx) in ticketNumbers"
                :key="num + idx"
                :ref="bindSingleDocRef(idx)"
                :receipt="receipt"
                :ticket-numbers="[num]"
                :passenger-index="idx"
            />
        </div>
    </Teleport>
</template>

<style scoped>
.tm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.72);
    z-index: 1060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

/* ── BOARDING PASS CARD ── */
.bp-card {
    display: flex;
    width: 100%;
    max-width: 700px;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 28px 72px rgba(0, 0, 0, 0.32);
}

/* ── LEFT PANEL ── */
.bp-left {
    flex: 0 0 44%;
    background: linear-gradient(145deg, #1e1b4b 0%, #312e81 55%, #4338ca 100%);
    padding: 1.75rem 1.75rem 1.5rem;
    color: #fff;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

.bp-worlds-bg {
    position: absolute;
    right: -10%;
    top: 50%;
    transform: translateY(-50%);
    width: 90%;
    height: auto;
    object-fit: contain;
    opacity: 0.08;
    pointer-events: none;
    z-index: 0;
    mix-blend-mode: screen;
    filter: brightness(0) invert(1);
}

.bp-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.bp-logo-img {
    height: 25px;
    width: auto;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.bp-brand-name {
    font-size: 0.92rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #fff;
}

.bp-left-body {
    flex: 1;
    padding: 1.75rem 0 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    position: relative;
    z-index: 1;
}

.bp-issued-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 2rem;
    padding: 0.3rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 600;
    width: fit-content;
    color: #c7d2fe;
}

.bp-field-label {
    font-size: 0.63rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #a5b4fc;
    margin-bottom: 0.25rem;
}

.bp-field-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
}

.bp-pnr {
    font-size: 1.95rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}


/* ── TEAR DIVIDER ── */
.bp-divider {
    width: 0;
    flex-shrink: 0;
    border-left: 2px dashed rgba(255, 255, 255, 0.22);
    position: relative;
    background: transparent;
}

.bp-notch {
    position: absolute;
    width: 28px;
    height: 28px;
    background: rgba(0, 0, 0, 0.72); /* matches overlay */
    border-radius: 50%;
    left: -14px;
}

.bp-notch--top    { top: -14px; }
.bp-notch--bottom { bottom: -14px; }

/* ── RIGHT STUB ── */
.bp-right {
    flex: 1;
    background: #fff;
    padding: 1.5rem 1.4rem 1.25rem 1.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    position: relative;
    min-width: 0;
}

.bp-close-x {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    width: 28px;
    height: 28px;
    border: none;
    background: #f1f5f9;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 0.85rem;
    transition: background 0.15s;
}

.bp-close-x:hover { background: #e2e8f0; }

.bp-stub-label {
    font-size: 0.63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #7c3aed;
    margin-top: 0.4rem;
}

.bp-numbers {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.bp-numbers--grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
}

.bp-numbers--single {
    align-items: flex-start;
}

.bp-numbers--single .bp-number-row {
    width: auto;
}

.bp-number-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.2rem 0.55rem;
    background: #f5f3ff;
    border-radius: 0.4rem;
    border: 1px solid #ddd6fe;
    min-width: 0;
    cursor: pointer;
}

.bp-number-checkbox {
    flex-shrink: 0;
    width: 0.85rem;
    height: 0.85rem;
    accent-color: #7c3aed;
    cursor: pointer;
}

.bp-number {
    font-size: 0.76rem;
    font-weight: 700;
    color: #312e81;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.04em;
    font-family: 'Courier New', monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bp-no-number {
    font-size: 0.78rem;
    color: #94a3b8;
    font-style: italic;
    padding: 0.25rem 0;
}

.bp-numbers-toggle {
    align-self: flex-start;
    border: none;
    background: none;
    padding: 0.15rem 0.1rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #7c3aed;
    cursor: pointer;
}

.bp-numbers-toggle:hover { text-decoration: underline; }

.bp-numbers-toggle--grid {
    grid-column: 1 / -1;
    align-self: center;
}

.bp-issued-at {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: auto;
}

.bp-print-actions {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.bp-print-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.35rem;
    border: 1px solid #7c3aed;
    background: #7c3aed;
    color: #fff;
    border-radius: 0.4rem;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}

.bp-print-btn:hover:not(:disabled) { background: #6d28d9; }

.bp-print-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Rendered off-canvas, never shown — BookingReceiptDoc's A4 layout math still needs real DOM */
.ticket-print-offstage {
    position: fixed;
    left: -99999px;
    top: 0;
}

.bp-close-btn {
    padding: 0.35rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 0.4rem;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
}

.bp-close-btn:hover {
    background: #f5f3ff;
    border-color: #7c3aed;
    color: #7c3aed;
}

/* ── FADE TRANSITION ── */
.tm-fade-enter-active,
.tm-fade-leave-active { transition: opacity 0.2s ease; }

.tm-fade-enter-from,
.tm-fade-leave-to     { opacity: 0; }
</style>
