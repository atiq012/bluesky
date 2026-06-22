<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
    visible:       { type: Boolean, default: false },
    pnr:           { type: String,  default: null },
    bookingCode:   { type: String,  default: null },
    ticketNumbers: { type: Array,   default: () => [] },
    ticketPaxMap:  { type: Object,  default: () => ({}) },
    loading:       { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

const selected = ref([])

watch(() => props.visible, (val) => {
    if (val) {
        selected.value = props.ticketNumbers.length === 1
            ? [...props.ticketNumbers]
            : []
    }
})

const canConfirm = computed(() => selected.value.length > 0 && !props.loading)

function toggleTicket(num) {
    if (props.loading) return
    const idx = selected.value.indexOf(num)
    if (idx === -1) selected.value.push(num)
    else selected.value.splice(idx, 1)
}

function selectAll() {
    selected.value = [...props.ticketNumbers]
}

function handleConfirm() {
    if (!canConfirm.value) return
    emit('confirm', [...selected.value])
}
</script>

<template>
    <Teleport to="body">
        <Transition name="vc-fade">
            <div v-if="visible" class="vc-overlay" @click.self="!loading && emit('cancel')">
                <div class="vc-card">

                    <!-- Header -->
                    <div class="vc-header">
                        <div class="vc-header-left">
                            <div class="vc-header-icon">
                                <i class="fa-solid fa-circle-xmark" />
                            </div>
                            <span class="vc-title">Void Ticket Confirmation</span>
                        </div>
                        <div v-if="pnr" class="vc-pnr-group">
                            <span class="vc-pnr-label">PNR</span>
                            <span class="vc-pnr-badge">{{ pnr }}</span>
                        </div>
                    </div>

                    <div class="vc-divider" />

                    <!-- Body: two columns -->
                    <div class="vc-body">

                        <!-- Left: ticket list -->
                        <div class="vc-left">
                            <div class="vc-tickets-header">
                                <span class="vc-tickets-label">Select Tickets for Voiding</span>
                                <button
                                    v-if="ticketNumbers.length > 1"
                                    class="vc-select-all"
                                    :disabled="loading"
                                    @click="selectAll"
                                >Select All</button>
                            </div>
                            <div class="vc-tickets">
                                <label
                                    v-for="num in ticketNumbers"
                                    :key="num"
                                    class="vc-ticket-row"
                                    :class="{ 'vc-ticket-row--checked': selected.includes(num), 'vc-ticket-row--disabled': loading }"
                                >
                                    <input
                                        type="checkbox"
                                        class="vc-checkbox"
                                        :checked="selected.includes(num)"
                                        :disabled="loading"
                                        @change="toggleTicket(num)"
                                    />
                                    <span class="vc-ticket-info">
                                        <span class="vc-ticket-num">{{ num }}</span>
                                        <span v-if="ticketPaxMap[num]" class="vc-ticket-pax">{{ ticketPaxMap[num] }}</span>
                                    </span>
                                    <span class="vc-ticket-badge">Ticket</span>
                                </label>
                            </div>
                        </div>

                        <!-- Right: notes -->
                        <div class="vc-right">
                            <div class="vc-notes">
                                <div class="vc-notes-title">
                                    <i class="fa-solid fa-triangle-exclamation" />
                                    Critical Notes
                                </div>
                                <ul class="vc-notes-list">
                                    <li>
                                        <span class="vc-dot vc-dot--red" />
                                        Same-day issue voids only.
                                    </li>
                                    <li>
                                        <span class="vc-dot vc-dot--red" />
                                        Action cannot be restored.
                                    </li>
                                    <li>
                                        <span class="vc-dot vc-dot--blue" />
                                        Check airline partial void rules.
                                    </li>
                                </ul>
                            </div>
                            <p class="vc-hint">Select the tickets you intend to void from this PNR.</p>
                        </div>

                    </div>

                    <div class="vc-divider" />

                    <!-- Footer -->
                    <div class="vc-footer">
                        <span class="vc-op-label">Operation: Void Request</span>
                        <div class="vc-actions">
                            <button class="vc-btn vc-btn--keep" :disabled="loading" @click="emit('cancel')">
                                No, Keep It
                            </button>
                            <button
                                class="vc-btn vc-btn--void"
                                :disabled="!canConfirm"
                                @click="handleConfirm"
                            >
                                <i v-if="loading" class="fa-solid fa-spinner fa-spin" />
                                {{ loading ? 'Voiding…' : 'Yes, Void Ticket' }}
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── OVERLAY ── */
.vc-overlay {
    position: fixed;
    inset: 0;
    background: rgba(5, 8, 18, 0.72);
    backdrop-filter: blur(4px);
    z-index: 1070;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

/* ── CARD ── */
.vc-card {
    position: relative;
    width: 100%;
    max-width: 660px;
    background: #fff;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.08), 0 24px 64px rgba(0, 0, 0, 0.18);
    display: flex;
    flex-direction: column;
    font-family: Roboto, sans-serif;
}

/* ── HEADER ── */
.vc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    gap: 1rem;
}

.vc-header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.vc-header-icon {
    width: 32px;
    height: 32px;
    background: rgba(220, 38, 38, 0.12);
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    font-size: 1rem;
    flex-shrink: 0;
}

.vc-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.vc-pnr-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.vc-pnr-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.vc-pnr-badge {
    background: #6d28d9;
    color: #fff;
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    padding: 0.25rem 0.75rem;
    border-radius: 0.4rem;
}

/* ── DIVIDER ── */
.vc-divider {
    height: 1px;
    background: #e2e8f0;
    flex-shrink: 0;
}

/* ── BODY ── */
.vc-body {
    display: flex;
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    min-height: 0;
}

/* ── LEFT COLUMN ── */
.vc-left {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.vc-tickets-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.vc-tickets-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: #64748b;
}

.vc-select-all {
    border: none;
    background: none;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6d28d9;
    cursor: pointer;
    padding: 0;
}
.vc-select-all:hover { text-decoration: underline; }
.vc-select-all:disabled { opacity: 0.45; cursor: not-allowed; }

.vc-tickets {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.vc-ticket-row {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.6rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.6rem;
    background: #f8fafc;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
    user-select: none;
}
.vc-ticket-row:hover:not(.vc-ticket-row--disabled) {
    background: #f1f5f9;
    border-color: #c4b5fd;
}
.vc-ticket-row--checked {
    background: #f5f3ff;
    border-color: #a78bfa;
}
.vc-ticket-row--disabled { cursor: not-allowed; opacity: 0.6; }

.vc-checkbox {
    accent-color: #7c3aed;
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    cursor: pointer;
}

.vc-ticket-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    gap: 0.1rem;
}

.vc-ticket-num {
    font-size: 0.84rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.vc-ticket-pax {
    font-size: 0.73rem;
    font-weight: 500;
    color: #64748b;
    line-height: 1.2;
}

.vc-ticket-badge {
    flex-shrink: 0;
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 0.35rem;
    padding: 0.15rem 0.5rem;
}

/* ── RIGHT COLUMN ── */
.vc-right {
    width: 220px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.vc-notes {
    border: 1px solid rgba(220, 38, 38, 0.3);
    border-radius: 0.6rem;
    padding: 0.7rem 0.8rem;
    background: rgba(220, 38, 38, 0.04);
}

.vc-notes-title {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: #dc2626;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.55rem;
}

.vc-notes-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.vc-notes-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: #475569;
    line-height: 1.45;
}

.vc-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 0.22rem;
}
.vc-dot--red  { background: #ef4444; }
.vc-dot--blue { background: #6d28d9; }

.vc-hint {
    font-size: 0.73rem;
    color: #94a3b8;
    font-style: italic;
    line-height: 1.5;
    margin: 0;
}

/* ── FOOTER ── */
.vc-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.25rem;
    gap: 1rem;
}

.vc-op-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #94a3b8;
}

.vc-actions {
    display: flex;
    gap: 0.6rem;
}

.vc-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 1.1rem;
    border-radius: 0.5rem;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    white-space: nowrap;
}

.vc-btn--keep {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.vc-btn--keep:hover:not(:disabled) { background: #e2e8f0; }
.vc-btn--keep:disabled { opacity: 0.5; cursor: not-allowed; }

.vc-btn--void {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: #fff;
    box-shadow: 0 2px 10px rgba(220, 38, 38, 0.35);
}
.vc-btn--void:hover:not(:disabled) { background: linear-gradient(135deg, #b91c1c, #dc2626); }
.vc-btn--void:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }

/* ── TRANSITION ── */
.vc-fade-enter-active { transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.34,1.56,0.64,1); }
.vc-fade-leave-active { transition: opacity 0.15s ease; }
.vc-fade-enter-from { opacity: 0; transform: scale(0.94) translateY(10px); }
.vc-fade-leave-to   { opacity: 0; }

/* ── DARK MODE ── */
:global([data-bs-theme="dark"] .vc-card) {
    background: #0f1117;
    box-shadow: 0 0 0 1px rgba(255,255,255,0.06), 0 24px 64px rgba(0,0,0,0.6);
}

:global([data-bs-theme="dark"] .vc-title) {
    color: #f1f5f9;
}

:global([data-bs-theme="dark"] .vc-header-icon) {
    background: rgba(220, 38, 38, 0.18);
}

:global([data-bs-theme="dark"] .vc-divider) {
    background: rgba(255,255,255,0.07);
}

:global([data-bs-theme="dark"] .vc-tickets-label) {
    color: #94a3b8;
}

:global([data-bs-theme="dark"] .vc-ticket-row) {
    background: #1a1f2e;
    border-color: rgba(255,255,255,0.08);
}

:global([data-bs-theme="dark"] .vc-ticket-row:hover:not(.vc-ticket-row--disabled)) {
    background: #1e2540;
    border-color: #6d28d9;
}

:global([data-bs-theme="dark"] .vc-ticket-row--checked) {
    background: #1e1b4b;
    border-color: #7c3aed;
}

:global([data-bs-theme="dark"] .vc-ticket-num) {
    color: #f1f5f9;
}

:global([data-bs-theme="dark"] .vc-ticket-pax) {
    color: #94a3b8;
}

:global([data-bs-theme="dark"] .vc-ticket-badge) {
    background: #1e293b;
    border-color: rgba(255,255,255,0.1);
    color: #64748b;
}

:global([data-bs-theme="dark"] .vc-notes) {
    background: rgba(220, 38, 38, 0.08);
    border-color: rgba(220, 38, 38, 0.25);
}

:global([data-bs-theme="dark"] .vc-notes-list li) {
    color: #94a3b8;
}

:global([data-bs-theme="dark"] .vc-hint) {
    color: #64748b;
}

:global([data-bs-theme="dark"] .vc-op-label) {
    color: #475569;
}

:global([data-bs-theme="dark"] .vc-btn--keep) {
    background: #1e293b;
    color: #94a3b8;
    border-color: rgba(255,255,255,0.1);
}

:global([data-bs-theme="dark"] .vc-btn--keep:hover:not(:disabled)) {
    background: #334155;
}
</style>
