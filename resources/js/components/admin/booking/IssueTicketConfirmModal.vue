<script setup>
import { computed } from 'vue'
import AppButton from '../../common/AppButton.vue'

const props = defineProps({
    visible:       { type: Boolean, default: false },
    pnr:           { type: String,  default: null },
    bookingCode:   { type: String,  default: null },
    sector:        { type: String,  default: null },
    paxCount:      { type: [Number, String], default: null },
    agencyBalance: { type: [Number, String], default: null },
    ticketPrice:   { type: [Number, String], default: null },
    loading:       { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

function toNum(v) {
    if (v === null || v === undefined || v === '') return null
    const n = Number(v)
    return Number.isFinite(n) ? n : null
}

function formatMoney(v) {
    const n = toNum(v)
    if (n === null) return '—'
    return Math.round(n).toLocaleString('en-US', { maximumFractionDigits: 0 })
}

const balanceNum = computed(() => toNum(props.agencyBalance))
const ticketNum = computed(() => toNum(props.ticketPrice))
const remainNum = computed(() => {
    if (balanceNum.value === null || ticketNum.value === null) return null
    return balanceNum.value - ticketNum.value
})

const insufficient = computed(() => remainNum.value !== null && remainNum.value < 0)
const canConfirm = computed(() => !props.loading && !insufficient.value)
</script>

<template>
    <Teleport to="body">
        <Transition name="itc-fade">
            <div
                v-if="visible"
                class="itc-overlay"
                role="dialog"
                aria-modal="true"
                aria-labelledby="itc-title"
                @click.self="!loading && emit('cancel')"
            >
                <div class="itc-card">

                    <header class="itc-header">
                        <div class="itc-header-left">
                            <div class="itc-header-icon" aria-hidden="true">
                                <i class="fa-solid fa-ticket" />
                            </div>
                            <div class="itc-header-text">
                                <h2 id="itc-title" class="itc-title">Issue Ticket?</h2>
                                <p class="itc-subtitle">Fare settles from agency wallet · e-tickets via Travelport</p>
                            </div>
                        </div>
                        <div v-if="pnr" class="itc-pnr-badge">
                            <span class="itc-pnr-label">PNR</span>
                            <span class="itc-pnr-value">{{ pnr }}</span>
                        </div>
                        <button
                            class="itc-close"
                            type="button"
                            aria-label="Close"
                            :disabled="loading"
                            @click="emit('cancel')"
                        >
                            <i class="fa-solid fa-xmark" />
                        </button>
                    </header>

                    <div class="itc-meta">
                        <span v-if="bookingCode" class="itc-chip">
                            <i class="fa-solid fa-barcode" aria-hidden="true" />
                            {{ bookingCode }}
                        </span>
                        <span v-if="sector" class="itc-chip">
                            <i class="fa-solid fa-route" aria-hidden="true" />
                            {{ sector }}
                        </span>
                        <span v-if="paxCount != null && paxCount !== ''" class="itc-chip">
                            <i class="fa-solid fa-users" aria-hidden="true" />
                            Pax {{ paxCount }}
                        </span>
                    </div>

                    <div class="itc-finance" role="group" aria-label="Balance breakdown">
                        <div class="itc-fin-card itc-fin-card--balance">
                            <span class="itc-fin-label">
                                <i class="fa-solid fa-wallet" aria-hidden="true" />
                                Agency Balance
                            </span>
                            <span class="itc-fin-value">৳ {{ formatMoney(balanceNum) }}</span>
                        </div>
                        <div class="itc-fin-op" aria-hidden="true">−</div>
                        <div class="itc-fin-card itc-fin-card--ticket">
                            <span class="itc-fin-label">
                                <i class="fa-solid fa-ticket" aria-hidden="true" />
                                Ticket Price
                            </span>
                            <span class="itc-fin-value">৳ {{ formatMoney(ticketNum) }}</span>
                        </div>
                        <div class="itc-fin-op" aria-hidden="true">=</div>
                        <div
                            class="itc-fin-card itc-fin-card--remain"
                            :class="{ 'itc-fin-card--danger': insufficient }"
                        >
                            <span class="itc-fin-label">
                                <i class="fa-solid fa-scale-balanced" aria-hidden="true" />
                                Remain Amount
                            </span>
                            <span class="itc-fin-value">৳ {{ formatMoney(remainNum) }}</span>
                        </div>
                    </div>

                    <p
                        v-if="insufficient"
                        class="itc-alert"
                        role="alert"
                    >
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
                        Insufficient balance — top up before issuing this ticket.
                    </p>
                    <p v-else class="itc-note">
                        <i class="fa-solid fa-circle-info" aria-hidden="true" />
                        Wallet deducted on issue · void same-day only if airline allows
                    </p>

                    <div class="itc-actions">
                        <div class="itc-action-slot">
                            <AppButton
                                variant="keep"
                                size="md"
                                :disabled="loading"
                                :block="true"
                                @click="emit('cancel')"
                            />
                        </div>
                        <div class="itc-action-slot">
                            <AppButton
                                variant="confirm"
                                size="md"
                                label="Yes, Issue Ticket"
                                loading-text="Issuing…"
                                :loading="loading"
                                :disabled="!canConfirm"
                                :block="true"
                                custom-class="itc-btn-issue"
                                @click="canConfirm && emit('confirm')"
                            />
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.itc-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(8px);
    z-index: 1070;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.itc-card {
    position: relative;
    width: 100%;
    max-width: 640px;
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(139, 92, 246, 0.14);
    border-radius: 1.1rem;
    overflow: hidden;
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.4) inset,
        0 24px 64px rgba(15, 23, 42, 0.22);
    display: flex;
    flex-direction: column;
}

.itc-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.15rem 0.85rem;
    background: linear-gradient(120deg, #4c1d95 0%, #6d28d9 45%, #8b5cf6 100%);
    color: #fff;
}

.itc-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

.itc-header-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.itc-header-text {
    min-width: 0;
}

.itc-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1.25;
    color: #fff;
}

.itc-subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.35;
}

.itc-pnr-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    padding: 0.3rem 0.7rem;
    flex-shrink: 0;
}

.itc-pnr-label {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.7);
}

.itc-pnr-value {
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    font-variant-numeric: tabular-nums;
}

.itc-close {
    width: 36px;
    height: 36px;
    min-width: 36px;
    min-height: 36px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.85);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.2s ease, color 0.2s ease;
}

.itc-close:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.28);
    color: #fff;
}

.itc-close:focus-visible {
    outline: 2px solid #fbbf24;
    outline-offset: 2px;
}

.itc-close:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.itc-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.85rem 1.15rem 0.25rem;
}

.itc-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 650;
    color: #334155;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 0.28rem 0.65rem;
    max-width: 100%;
    word-break: break-word;
}

.itc-chip i {
    color: #8b5cf6;
    font-size: 0.7rem;
}

.itc-finance {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto 1fr;
    align-items: stretch;
    gap: 0.45rem;
    padding: 0.75rem 1.15rem 0.35rem;
}

.itc-fin-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 0.65rem 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.itc-fin-card--ticket {
    background: #f5f3ff;
    border-color: #ddd6fe;
}

.itc-fin-card--remain {
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.itc-fin-card--danger {
    background: #fef2f2;
    border-color: #fecaca;
}

.itc-fin-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.itc-fin-label i {
    font-size: 0.7rem;
    color: #94a3b8;
}

.itc-fin-card--ticket .itc-fin-label i { color: #8b5cf6; }
.itc-fin-card--remain .itc-fin-label i { color: #059669; }
.itc-fin-card--danger .itc-fin-label i { color: #dc2626; }

.itc-fin-value {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
    line-height: 1.2;
    word-break: break-all;
}

.itc-fin-card--ticket .itc-fin-value { color: #5b21b6; }
.itc-fin-card--remain .itc-fin-value { color: #047857; }
.itc-fin-card--danger .itc-fin-value { color: #b91c1c; }

.itc-fin-op {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
    color: #94a3b8;
    padding-top: 0.85rem;
}

.itc-note,
.itc-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    margin: 0.35rem 1.15rem 0;
    font-size: 0.75rem;
    line-height: 1.45;
}

.itc-note {
    color: #64748b;
}

.itc-note i {
    color: #0284c7;
    margin-top: 0.1rem;
}

.itc-alert {
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 0.65rem;
    padding: 0.5rem 0.7rem;
}

.itc-alert i {
    margin-top: 0.1rem;
}

.itc-actions {
    display: flex;
    gap: 0.65rem;
    padding: 0.9rem 1.15rem 1.1rem;
}

.itc-action-slot {
    flex: 1;
    min-width: 0;
}

:deep(.itc-btn-issue) {
    background: #8b5cf6 !important;
    border-color: #8b5cf6 !important;
    color: #fff !important;
    transition: background 0.2s ease, border-color 0.2s ease;
}

:deep(.itc-btn-issue:hover:not(:disabled)) {
    background: #7c3aed !important;
    border-color: #7c3aed !important;
}

:deep(.itc-btn-issue:disabled) {
    opacity: 0.55;
    cursor: not-allowed;
}

:deep(.itc-btn-issue:focus-visible) {
    outline: 2px solid #fbbf24;
    outline-offset: 2px;
}

.itc-fade-enter-active {
    transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.itc-fade-leave-active {
    transition: opacity 0.15s ease;
}
.itc-fade-enter-from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
}
.itc-fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .itc-fade-enter-active,
    .itc-fade-leave-active,
    .itc-close,
    :deep(.itc-btn-issue) {
        transition: none !important;
    }
}

html[data-bs-theme="dark"] .itc-card {
    background: rgba(30, 41, 59, 0.96);
    border-color: rgba(167, 139, 250, 0.22);
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
}

html[data-bs-theme="dark"] .itc-chip {
    background: rgba(15, 23, 42, 0.55);
    border-color: rgba(148, 163, 184, 0.22);
    color: #e2e8f0;
}

html[data-bs-theme="dark"] .itc-fin-card {
    background: rgba(15, 23, 42, 0.55);
    border-color: rgba(148, 163, 184, 0.2);
}

html[data-bs-theme="dark"] .itc-fin-card--ticket {
    background: rgba(124, 58, 237, 0.16);
    border-color: rgba(167, 139, 250, 0.3);
}

html[data-bs-theme="dark"] .itc-fin-card--remain {
    background: rgba(5, 150, 105, 0.14);
    border-color: rgba(52, 211, 153, 0.28);
}

html[data-bs-theme="dark"] .itc-fin-card--danger {
    background: rgba(220, 38, 38, 0.14);
    border-color: rgba(248, 113, 113, 0.3);
}

html[data-bs-theme="dark"] .itc-fin-value {
    color: #f1f5f9;
}

html[data-bs-theme="dark"] .itc-fin-card--ticket .itc-fin-value { color: #c4b5fd; }
html[data-bs-theme="dark"] .itc-fin-card--remain .itc-fin-value { color: #6ee7b7; }
html[data-bs-theme="dark"] .itc-fin-card--danger .itc-fin-value { color: #fca5a5; }

html[data-bs-theme="dark"] .itc-fin-label {
    color: #94a3b8;
}

html[data-bs-theme="dark"] .itc-note {
    color: #94a3b8;
}

html[data-bs-theme="dark"] .itc-alert {
    background: rgba(220, 38, 38, 0.14);
    border-color: rgba(248, 113, 113, 0.3);
    color: #fca5a5;
}

@media (max-width: 640px) {
    .itc-header {
        flex-wrap: wrap;
        padding-right: 3rem;
    }

    .itc-close {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
    }

    .itc-pnr-badge {
        order: 3;
        width: 100%;
        justify-content: center;
    }

    .itc-finance {
        grid-template-columns: 1fr;
        gap: 0.4rem;
    }

    .itc-fin-op {
        display: none;
    }

    .itc-actions {
        flex-direction: column-reverse;
    }
}
</style>
