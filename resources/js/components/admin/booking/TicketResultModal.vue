<script setup>
import { computed } from 'vue'

const props = defineProps({
    visible:       { type: Boolean, default: false },
    ticketNumbers: { type: Array,   default: () => [] },
    ticketedAt:    { type: String,  default: null },
    pnr:           { type: String,  default: null },
})

const emit = defineEmits(['close'])

const hasTickets = computed(() => props.ticketNumbers.length > 0)

const formattedDate = computed(() => {
    if (!props.ticketedAt) return null
    try {
        return new Date(props.ticketedAt).toLocaleString('en-GB', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        })
    } catch {
        return props.ticketedAt
    }
})

function handleClose() {
    emit('close')
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

                        <div v-if="hasTickets" class="bp-numbers">
                            <div
                                v-for="(num, idx) in ticketNumbers"
                                :key="idx"
                                class="bp-number-row"
                            >
                                <i class="fa-solid fa-barcode bp-barcode-ico" />
                                <span class="bp-number">{{ num }}</span>
                            </div>
                        </div>
                        <div v-else class="bp-no-number">
                            Ticket issued but numbers not returned in response.
                        </div>

                        <div v-if="formattedDate" class="bp-issued-at">
                            <i class="fa-regular fa-clock" />
                            <span>{{ formattedDate }}</span>
                        </div>

                        <button class="bp-close-btn" @click="handleClose">Close</button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.tm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.52);
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
    max-width: 580px;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 28px 72px rgba(0, 0, 0, 0.32);
}

/* ── LEFT PANEL ── */
.bp-left {
    flex: 0 0 58%;
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
    background: rgba(0, 0, 0, 0.52); /* matches overlay */
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
    gap: 0.85rem;
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
    gap: 0.45rem;
}

.bp-number-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.7rem;
    background: #f5f3ff;
    border-radius: 0.5rem;
    border: 1px solid #ddd6fe;
    min-width: 0;
}

.bp-barcode-ico {
    color: #7c3aed;
    font-size: 1rem;
    flex-shrink: 0;
}

.bp-number {
    font-size: 0.84rem;
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

.bp-issued-at {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71rem;
    color: #94a3b8;
    margin-top: auto;
}

.bp-close-btn {
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 0.5rem;
    font-size: 0.84rem;
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
