<script setup>
import { computed } from 'vue'

const props = defineProps({
    visible:       { type: Boolean, default: false },
    pnr:           { type: String,  default: null },
    voidedAt:      { type: String,  default: null },
    voidedTickets: { type: Array,   default: () => [] },
})

const emit = defineEmits(['close'])

const formattedDate = computed(() => {
    if (!props.voidedAt) return null
    try {
        return new Date(props.voidedAt).toLocaleString('en-GB', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        })
    } catch {
        return props.voidedAt
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition name="vr-fade">
            <div v-if="visible" class="vr-overlay" @click.self="emit('close')">
                <div class="vr-card">

                    <!-- LEFT PANEL -->
                    <div class="vr-left">
                        <img :src="'/theme/appimages/worlds.png'" class="vr-worlds-bg" aria-hidden="true" />
                        <div class="vr-brand">
                            <img :src="'/theme/appimages/blueskywings.png'" class="vr-logo-img" alt="BlueSky" />
                            <span class="vr-brand-name">BLUESKY</span>
                        </div>

                        <div class="vr-left-body">
                            <div class="vr-voided-badge">
                                <i class="fa-solid fa-file-slash" />
                                Ticket Voided
                            </div>

                            <div class="vr-field">
                                <div class="vr-field-label">PNR / Locator</div>
                                <div class="vr-field-value vr-pnr">{{ pnr ?? '—' }}</div>
                            </div>

                            <div class="vr-field">
                                <div class="vr-field-label">Voided Via</div>
                                <div class="vr-field-value">Travelport GDS</div>
                            </div>
                        </div>
                    </div>

                    <!-- DIVIDER + NOTCHES -->
                    <div class="vr-divider">
                        <div class="vr-notch vr-notch--top" />
                        <div class="vr-notch vr-notch--bottom" />
                    </div>

                    <!-- RIGHT STUB -->
                    <div class="vr-right">
                        <button class="vr-close-x" @click="emit('close')">
                            <i class="fa-solid fa-xmark" />
                        </button>

                        <div class="vr-stub-label">Void Confirmed</div>

                        <div class="vr-status-box">
                            <i class="fa-solid fa-circle-check vr-status-ico" />
                            <div>
                                <div class="vr-status-title">Successfully Voided</div>
                                <div class="vr-status-sub">Ticket(s) removed from the GDS.</div>
                            </div>
                        </div>

                        <div v-if="voidedTickets.length" class="vr-tickets">
                            <div class="vr-tickets-label">Voided Ticket(s)</div>
                            <div v-for="num in voidedTickets" :key="num" class="vr-ticket-chip">
                                <i class="fa-solid fa-ticket" />
                                {{ num }}
                            </div>
                        </div>

                        <div v-if="formattedDate" class="vr-voided-at">
                            <i class="fa-regular fa-clock" />
                            <span>{{ formattedDate }}</span>
                        </div>

                        <button class="vr-close-btn" @click="emit('close')">Close</button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.vr-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.52);
    z-index: 1060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.vr-card {
    display: flex;
    width: 100%;
    max-width: 560px;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 28px 72px rgba(0, 0, 0, 0.32);
}

/* LEFT */
.vr-left {
    flex: 0 0 58%;
    background: linear-gradient(145deg, #2e1065 0%, #4c1d95 55%, #6d28d9 100%);
    padding: 1.75rem 1.75rem 1.5rem;
    color: #fff;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

.vr-worlds-bg {
    position: absolute;
    right: -10%;
    top: 50%;
    transform: translateY(-50%);
    width: 90%;
    height: auto;
    object-fit: contain;
    opacity: 0.07;
    pointer-events: none;
    z-index: 0;
    mix-blend-mode: screen;
    filter: brightness(0) invert(1);
}

.vr-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.vr-logo-img {
    height: 25px;
    width: auto;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.vr-brand-name {
    font-size: 0.92rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #fff;
}

.vr-left-body {
    flex: 1;
    padding: 1.75rem 0 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    position: relative;
    z-index: 1;
}

.vr-voided-badge {
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
    color: #ddd6fe;
}

.vr-field-label {
    font-size: 0.63rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #c4b5fd;
    margin-bottom: 0.25rem;
}

.vr-field-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
}

.vr-pnr {
    font-size: 1.95rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

/* DIVIDER */
.vr-divider {
    width: 0;
    flex-shrink: 0;
    border-left: 2px dashed rgba(255, 255, 255, 0.22);
    position: relative;
    background: transparent;
}

.vr-notch {
    position: absolute;
    width: 28px;
    height: 28px;
    background: rgba(0, 0, 0, 0.52);
    border-radius: 50%;
    left: -14px;
}
.vr-notch--top    { top: -14px; }
.vr-notch--bottom { bottom: -14px; }

/* RIGHT */
.vr-right {
    flex: 1;
    background: #fff;
    padding: 1.5rem 1.4rem 1.25rem 1.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: relative;
    min-width: 0;
}

.vr-close-x {
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
.vr-close-x:hover { background: #e2e8f0; }

.vr-stub-label {
    font-size: 0.63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #6d28d9;
    margin-top: 0.4rem;
}

.vr-status-box {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.8rem 0.9rem;
    background: #f5f3ff;
    border-radius: 0.6rem;
    border: 1px solid #ddd6fe;
}

.vr-status-ico {
    color: #16a34a;
    font-size: 1.3rem;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.vr-status-title {
    font-size: 0.86rem;
    font-weight: 700;
    color: #3b0764;
}

.vr-status-sub {
    font-size: 0.73rem;
    color: #6d28d9;
    margin-top: 0.15rem;
}

/* Ticket chips */
.vr-tickets {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.vr-tickets-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
}

.vr-ticket-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #ede9fe;
    color: #4c1d95;
    border-radius: 0.4rem;
    padding: 0.3rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.vr-voided-at {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71rem;
    color: #94a3b8;
    margin-top: auto;
}

.vr-close-btn {
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
.vr-close-btn:hover {
    background: #f5f3ff;
    border-color: #7c3aed;
    color: #6d28d9;
}

.vr-fade-enter-active,
.vr-fade-leave-active { transition: opacity 0.2s ease; }
.vr-fade-enter-from,
.vr-fade-leave-to     { opacity: 0; }
</style>
