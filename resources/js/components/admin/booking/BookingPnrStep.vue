<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    pnr: { type: String, default: null },
    reservationIdentifier: { type: String, default: null },
    reservationStatus: { type: String, default: null },
    commitPending: { type: Boolean, default: false },
    commitError: { type: String, default: null },
    travelportResponse: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    workbenchExpired: { type: Boolean, default: false },
})

const emit = defineEmits(['back', 'done', 'retry', 'new-search'])

const isWorkbenchDead = computed(() => {
    if (props.workbenchExpired) return true
    const err = (props.commitError || '').toUpperCase()
    return err.includes('SESSION IDENTIFIER IS INVALID') || err.includes('WORKBENCH_EXPIRED')
})

const showRawJson = ref(false)
const copiedField = ref(null)

const statusLabel = computed(() => {
    if (props.commitPending) return 'Commit pending'
    if (props.commitError) return 'Commit failed'
    if (props.reservationStatus) return props.reservationStatus
    return props.pnr ? 'Held booking' : 'Awaiting PNR'
})

const statusVariant = computed(() => {
    if (props.commitPending) return 'pending'
    if (props.commitError) return 'error'
    if (props.pnr) return 'success'
    return 'pending'
})

const responseJson = computed(() => {
    if (!props.travelportResponse) return ''
    try {
        return JSON.stringify(props.travelportResponse, null, 2)
    } catch {
        return String(props.travelportResponse)
    }
})

const metaRows = computed(() => {
    const rows = []
    if (props.reservationIdentifier) {
        rows.push({ key: 'reservation_id', label: 'Reservation ID (Travelport)', value: props.reservationIdentifier })
    }
    if (props.reservationStatus) {
        rows.push({ key: 'status', label: 'Reservation status', value: props.reservationStatus })
    }
    return rows
})

async function copyText(text, field) {
    if (!text) return
    try {
        await navigator.clipboard.writeText(text)
        copiedField.value = field
        setTimeout(() => { copiedField.value = null }, 1600)
    } catch {
        // ignore
    }
}

function copyAll() {
    const parts = []
    if (props.pnr) parts.push(`PNR: ${props.pnr}`)
    if (props.reservationIdentifier) parts.push(`Reservation ID: ${props.reservationIdentifier}`)
    if (responseJson.value) parts.push(responseJson.value)
    copyText(parts.join('\n\n'), 'all')
}
</script>

<template>
    <div class="pnr-step">
        <div v-if="loading" class="pnr-step__state" role="status" aria-live="polite">
            <i class="fa-solid fa-spinner fa-spin" aria-hidden="true" />
            <p>Committing booking to GDS…</p>
        </div>

        <template v-else>
            <div
                v-if="commitError"
                class="alert d-flex align-items-start gap-2 mb-3"
                :class="isWorkbenchDead ? 'alert-warning' : 'alert-danger'"
                role="alert"
            >
                <i class="fa-solid fa-circle-exclamation mt-1" aria-hidden="true" />
                <div>
                    <strong class="d-block">{{ isWorkbenchDead ? 'Workbench session ended' : 'Could not create PNR' }}</strong>
                    <span v-if="isWorkbenchDead">
                        Travelport workbench closed (already committed, expired, or too many failed attempts).
                        Retry will not work for this booking.
                    </span>
                    <span v-else>{{ commitError }}</span>
                    <p class="mb-0 mt-1 small text-muted">
                        <template v-if="isWorkbenchDead">
                            Start a <strong>new search</strong> and complete booking again. Local review data is saved but GDS has no active session.
                        </template>
                        <template v-else>
                            Booking is confirmed locally. You can retry GDS commit while the session timer is still running.
                        </template>
                    </p>
                </div>
            </div>

            <div
                v-else-if="commitPending"
                class="alert alert-warning d-flex align-items-start gap-2 mb-3"
                role="alert"
            >
                <i class="fa-solid fa-clock mt-1" aria-hidden="true" />
                <div>
                    <strong class="d-block">Commit pending</strong>
                    <span>GDS commit did not complete. Your review is saved — try again later.</span>
                </div>
            </div>

            <section
                class="pnr-step__hero"
                :class="{ 'pnr-step__hero--muted': !pnr }"
                aria-labelledby="pnr-locator-heading"
            >
                <p id="pnr-locator-heading" class="pnr-step__hero-label">Record locator (PNR)</p>
                <div class="pnr-step__hero-row">
                    <span class="pnr-step__code" :aria-label="pnr ? `PNR ${pnr}` : 'PNR not available'">
                        {{ pnr || '— — — — — —' }}
                    </span>
                    <button
                        v-if="pnr"
                        type="button"
                        class="btn btn-sm pnr-step__copy-btn"
                        :aria-label="copiedField === 'pnr' ? 'PNR copied' : 'Copy PNR'"
                        @click="copyText(pnr, 'pnr')"
                    >
                        <i
                            class="fa-solid"
                            :class="copiedField === 'pnr' ? 'fa-check' : 'fa-copy'"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <div class="pnr-step__chips">
                    <span class="pnr-step__chip" :class="`pnr-step__chip--${statusVariant}`">{{ statusLabel }}</span>
                    <span v-if="pnr && !commitPending" class="pnr-step__chip pnr-step__chip--brand">GDS committed</span>
                    <span v-if="!pnr && !commitPending && !commitError" class="pnr-step__chip pnr-step__chip--pending">Not ticketed</span>
                </div>
            </section>

            <section v-if="metaRows.length" class="pnr-step__card">
                <header class="pnr-step__card-head">
                    <i class="fa-solid fa-fingerprint" aria-hidden="true" />
                    <span>Reservation identifiers</span>
                </header>
                <dl class="pnr-step__meta">
                    <div v-for="row in metaRows" :key="row.key" class="pnr-step__meta-row">
                        <dt>{{ row.label }}</dt>
                        <dd>
                            <code class="pnr-step__meta-value">{{ row.value }}</code>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                :aria-label="`Copy ${row.label}`"
                                @click="copyText(row.value, row.key)"
                            >
                                <i
                                    class="fa-solid"
                                    :class="copiedField === row.key ? 'fa-check' : 'fa-copy'"
                                    aria-hidden="true"
                                />
                            </button>
                        </dd>
                    </div>
                </dl>
            </section>

            <footer class="pnr-step__actions">
                <button
                    v-if="isWorkbenchDead"
                    type="button"
                    class="btn btn-lg pnr-step__done-btn"
                    @click="emit('new-search')"
                >
                    <i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true" />
                    Start new search
                </button>
                <button
                    v-else-if="commitPending || commitError"
                    type="button"
                    class="btn btn-warning btn-lg"
                    :disabled="loading"
                    @click="emit('retry')"
                >
                    <span v-if="loading"><i class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true" /></span>
                    <i v-else class="fa-solid fa-rotate-right me-1" aria-hidden="true" />
                    Retry GDS commit
                </button>
                <button type="button" class="btn btn-lg pnr-step__done-btn" @click="emit('done')">
                    Done
                </button>
            </footer>
        </template>
    </div>
</template>

<style scoped>
.pnr-step {
    --pnr-primary: #7239ea;
    --pnr-accent: #0ea5e9;
    --pnr-border: #e2e8f0;
    --pnr-muted: #64748b;
    --pnr-radius: 12px;
}

.pnr-step__state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 3rem 1rem;
    color: var(--pnr-muted);
}

.pnr-step__hero {
    text-align: center;
    padding: 2rem 1.5rem;
    margin-bottom: 1.25rem;
    border-radius: var(--pnr-radius);
    border: 2px solid rgba(114, 57, 234, 0.2);
    background: linear-gradient(165deg, #fff 0%, #f0f9ff 48%, rgba(114, 57, 234, 0.06) 100%);
    box-shadow: 0 8px 28px rgba(114, 57, 234, 0.1);
}

.pnr-step__hero--muted .pnr-step__code {
    color: #94a3b8;
    letter-spacing: 0.15em;
}

.pnr-step__hero-label {
    margin: 0 0 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--pnr-muted);
}

.pnr-step__hero-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.pnr-step__code {
    font-family: 'Fira Code', ui-monospace, monospace;
    font-size: clamp(2rem, 6vw, 3.25rem);
    font-weight: 700;
    letter-spacing: 0.22em;
    color: var(--pnr-primary);
    line-height: 1.1;
}

.pnr-step__copy-btn {
    border-color: rgba(114, 57, 234, 0.35);
    color: var(--pnr-primary);
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;
}

.pnr-step__copy-btn:hover {
    background: rgba(114, 57, 234, 0.08);
    color: var(--pnr-primary);
}

.pnr-step__chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.pnr-step__chip {
    display: inline-flex;
    align-items: center;
    padding: 0.28rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 999px;
    border: 1px solid transparent;
}

.pnr-step__chip--success {
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
    border-color: rgba(34, 197, 94, 0.3);
}

.pnr-step__chip--pending {
    background: #f1f5f9;
    color: #475569;
    border-color: #e2e8f0;
}

.pnr-step__chip--error {
    background: rgba(239, 68, 68, 0.1);
    color: #b91c1c;
    border-color: rgba(239, 68, 68, 0.25);
}

.pnr-step__chip--brand {
    background: rgba(114, 57, 234, 0.1);
    color: var(--pnr-primary);
    border-color: rgba(114, 57, 234, 0.25);
}

.pnr-step__card {
    margin-bottom: 1rem;
    border: 1px solid var(--pnr-border);
    border-radius: var(--pnr-radius);
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}

.pnr-step__card-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    font-weight: 700;
    font-size: 0.9rem;
    color: #1e293b;
    border-bottom: 1px solid var(--pnr-border);
    background: #f8fafc;
}

.pnr-step__card-head i {
    color: var(--pnr-accent);
}

.pnr-step__meta {
    margin: 0;
    padding: 0.75rem 1rem 1rem;
}

.pnr-step__meta-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.5rem 0.75rem;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.pnr-step__meta-row:last-child {
    border-bottom: none;
}

.pnr-step__meta-row dt {
    grid-column: 1 / -1;
    margin: 0;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--pnr-muted);
}

.pnr-step__meta-row dd {
    display: contents;
    margin: 0;
}

.pnr-step__meta-value {
    font-size: 0.8rem;
    word-break: break-all;
    padding: 0.35rem 0.5rem;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid var(--pnr-border);
}

.pnr-step__accordion-trigger {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1rem;
    border: none;
    background: #f8fafc;
    font-weight: 600;
    font-size: 0.9rem;
    color: #1e293b;
    cursor: pointer;
    transition: background 0.2s ease;
}

.pnr-step__accordion-trigger:hover {
    background: #f1f5f9;
}

.pnr-step__accordion-trigger span {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.pnr-step__accordion-trigger i.fa-code {
    color: var(--pnr-accent);
}

.pnr-step__chevron {
    transition: transform 0.2s ease;
}

.pnr-step__chevron--open {
    transform: rotate(180deg);
}

.pnr-step__json-wrap {
    max-height: 420px;
    overflow: auto;
    border-top: 1px solid var(--pnr-border);
}

.pnr-step__json {
    margin: 0;
    padding: 1rem;
    font-size: 0.72rem;
    line-height: 1.45;
    background: #0f172a;
    color: #e2e8f0;
}

.pnr-step__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--pnr-border);
}

.pnr-step__done-btn {
    background: linear-gradient(135deg, #7239ea, #a855f7);
    border: none;
    color: #fff;
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.15s ease;
}

.pnr-step__done-btn:hover {
    color: #fff;
    opacity: 0.95;
    transform: translateY(-1px);
}

@media (max-width: 575px) {
    .pnr-step__code {
        letter-spacing: 0.12em;
    }

    .pnr-step__actions .btn {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pnr-step__chevron,
    .pnr-step__copy-btn,
    .pnr-step__done-btn {
        transition: none;
    }
}

/* ── Dark mode ──────────────────────────────────────────────── */
[data-bs-theme="dark"] .pnr-step {
    --pnr-border: var(--bs-border-color);
    --pnr-muted:  var(--bs-secondary-color);
}

[data-bs-theme="dark"] .pnr-step__hero {
    background: linear-gradient(165deg,
        var(--bs-card-bg) 0%,
        rgba(14, 165, 233, 0.05) 48%,
        rgba(114, 57, 234, 0.08) 100%) !important;
    border-color: rgba(114, 57, 234, 0.3);
    box-shadow: 0 8px 28px rgba(114, 57, 234, 0.15);
}

[data-bs-theme="dark"] .pnr-step__hero--muted .pnr-step__code { color: #475569; }

[data-bs-theme="dark"] .pnr-step__chip--pending {
    background: rgba(255, 255, 255, 0.07);
    color: var(--bs-secondary-color);
    border-color: var(--bs-border-color);
}
[data-bs-theme="dark"] .pnr-step__chip--success {
    background: rgba(34, 197, 94, 0.12);
    color: #4ade80;
    border-color: rgba(34, 197, 94, 0.3);
}
[data-bs-theme="dark"] .pnr-step__chip--error {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.3);
}
[data-bs-theme="dark"] .pnr-step__chip--brand {
    background: rgba(114, 57, 234, 0.15);
    color: #c084fc;
    border-color: rgba(114, 57, 234, 0.35);
}

[data-bs-theme="dark"] .pnr-step__card {
    background: var(--bs-card-bg);
    border-color: var(--bs-border-color);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
[data-bs-theme="dark"] .pnr-step__card-head {
    background: rgba(255, 255, 255, 0.04);
    color: var(--bs-body-color);
    border-bottom-color: var(--bs-border-color);
}
[data-bs-theme="dark"] .pnr-step__meta-row {
    border-bottom-color: rgba(255, 255, 255, 0.06);
}
[data-bs-theme="dark"] .pnr-step__meta-value {
    background: rgba(255, 255, 255, 0.05);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .pnr-step__accordion-trigger {
    background: rgba(255, 255, 255, 0.03);
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .pnr-step__accordion-trigger:hover {
    background: rgba(255, 255, 255, 0.06);
}
[data-bs-theme="dark"] .pnr-step__actions {
    border-top-color: var(--bs-border-color);
}
</style>
