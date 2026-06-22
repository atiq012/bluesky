<script setup>
import AppButton from '../../common/AppButton.vue'

const props = defineProps({
    visible:     { type: Boolean, default: false },
    pnr:         { type: String,  default: null },
    bookingCode: { type: String,  default: null },
    loading:     { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
    <Teleport to="body">
        <Transition name="cc-fade">
            <div v-if="visible" class="cc-overlay" @click.self="!loading && emit('cancel')">
                <div class="cc-card">

                    <!-- Icon zone -->
                    <div class="cc-icon-zone">
                        <div class="cc-pulse-ring cc-pulse-ring--lg" />
                        <div class="cc-pulse-ring cc-pulse-ring--md" />
                        <div class="cc-icon-circle">
                            <span class="cc-icon-fa-stack">
                                <i class="fa-solid fa-plane cc-ico-plane" />
                                <i class="fa-solid fa-ban cc-ico-ban" />
                            </span>
                        </div>
                        <div class="cc-deco-dot cc-deco-dot--tl" />
                        <div class="cc-deco-dot cc-deco-dot--tr" />
                        <div class="cc-deco-dot cc-deco-dot--bl" />
                    </div>

                    <!-- Close -->
                    <button class="cc-close" :disabled="loading" @click="emit('cancel')">
                        <i class="fa-solid fa-xmark" />
                    </button>

                    <!-- Body -->
                    <div class="cc-body">
                        <h2 class="cc-title">Cancel This Booking?</h2>

                        <div v-if="pnr" class="cc-pnr-badge">
                            <i class="fa-solid fa-ticket cc-pnr-ico" />
                            <span class="cc-pnr-label">PNR</span>
                            <span class="cc-pnr-value">{{ pnr }}</span>
                        </div>

                        <p class="cc-desc">
                            You are about to void this held booking directly in the Travelport GDS.
                        </p>

                        <ul class="cc-list">
                            <li>
                                <i class="fa-solid fa-triangle-exclamation cc-li-ico cc-li-ico--warn" />
                                Booking will be permanently cancelled — cannot be restored
                            </li>
                            <li>
                                <i class="fa-solid fa-circle-xmark cc-li-ico cc-li-ico--danger" />
                                GDS inventory released immediately
                            </li>
                            <li>
                                <i class="fa-solid fa-clock cc-li-ico cc-li-ico--info" />
                                Only applicable before ticketing (held bookings only)
                            </li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="cc-actions">
                        <div class="cc-action-slot">
                            <AppButton
                                variant="keep"
                                size="md"
                                :disabled="loading"
                                :block="true"
                                @click="emit('cancel')"
                            />
                        </div>
                        <div class="cc-action-slot">
                            <AppButton
                                variant="void"
                                size="md"
                                loading-text="Yes, Cancel"
                                :loading="loading"
                                :block="true"
                                @click="emit('confirm')"
                            />
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── OVERLAY ── */
.cc-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 10, 20, 0.60);
    backdrop-filter: blur(3px);
    z-index: 1070;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

/* ── CARD ── */
.cc-card {
    position: relative;
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 1.5rem;
    overflow: hidden;
    box-shadow:
        0 0 0 1px rgba(220, 38, 38, 0.08),
        0 32px 80px rgba(0, 0, 0, 0.28);
    display: flex;
    flex-direction: column;
}

/* ── CLOSE ── */
.cc-close {
    position: absolute;
    top: 0.9rem;
    right: 0.9rem;
    z-index: 2;
    width: 30px;
    height: 30px;
    border: none;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.9rem;
    transition: background 0.15s, color 0.15s;
}

.cc-close:hover {
    background: rgba(255, 255, 255, 0.4);
    color: #fff;
}

/* ── ICON ZONE ── */
.cc-icon-zone {
    position: relative;
    background: linear-gradient(145deg, #7f1d1d 0%, #b91c1c 60%, #ef4444 100%);
    padding: 3rem 2rem 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* Animated pulse rings */
.cc-pulse-ring {
    position: absolute;
    border-radius: 50%;
    border: 1.5px solid rgba(255, 255, 255, 0.18);
    animation: cc-pulse 2.4s ease-out infinite;
    pointer-events: none;
}

.cc-pulse-ring--lg {
    width: 200px;
    height: 200px;
    animation-delay: 0s;
}

.cc-pulse-ring--md {
    width: 150px;
    height: 150px;
    animation-delay: 0.6s;
}

@keyframes cc-pulse {
    0%   { transform: scale(0.85); opacity: 0.5; }
    70%  { transform: scale(1.15); opacity: 0; }
    100% { transform: scale(1.15); opacity: 0; }
}

/* Icon circle */
.cc-icon-circle {
    position: relative;
    z-index: 1;
    width: 96px;
    height: 96px;
    background: rgba(255, 255, 255, 0.12);
    border: 2px solid rgba(255, 255, 255, 0.22);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.cc-icon-fa-stack {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
}

.cc-ico-plane {
    position: absolute;
    font-size: 1.75rem;
    color: rgba(255, 255, 255, 0.55);
    transform: rotate(-45deg) translate(-2px, -2px);
}

.cc-ico-ban {
    position: absolute;
    font-size: 3rem;
    color: #fff;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.25));
}

/* Decorative dots */
.cc-deco-dot {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
}

.cc-deco-dot--tl { top: 1.25rem; left: 1.5rem; }
.cc-deco-dot--tr { top: 2rem; right: 2.5rem; width: 5px; height: 5px; }
.cc-deco-dot--bl { bottom: 1.5rem; left: 2.5rem; width: 6px; height: 6px; }

/* ── BODY ── */
.cc-body {
    padding: 1.75rem 1.75rem 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    text-align: center;
}

.cc-title {
    font-size: 1.2rem;
    font-weight: 800;
    color: #1e1e2e;
    margin: 0;
    letter-spacing: -0.01em;
}

.cc-pnr-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #fef2f2;
    border: 1.5px solid #fecaca;
    border-radius: 999px;
    padding: 0.35rem 1rem;
}

.cc-pnr-ico {
    color: #dc2626;
    font-size: 0.85rem;
}

.cc-pnr-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #ef4444;
}

.cc-pnr-value {
    font-size: 1rem;
    font-weight: 800;
    color: #991b1b;
    letter-spacing: 0.12em;
    font-variant-numeric: tabular-nums;
}

.cc-desc {
    font-size: 0.84rem;
    color: #475569;
    margin: 0;
    line-height: 1.6;
}

/* List */
.cc-list {
    list-style: none;
    margin: 0;
    padding: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    text-align: left;
}

.cc-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.45;
}

.cc-li-ico {
    flex-shrink: 0;
    margin-top: 0.1rem;
    font-size: 0.78rem;
}

.cc-li-ico--warn   { color: #d97706; }
.cc-li-ico--danger { color: #dc2626; }
.cc-li-ico--info   { color: #0284c7; }

/* ── ACTIONS ── */
.cc-actions {
    display: flex;
    gap: 0.75rem;
    padding: 1.25rem 1.75rem 1.75rem;
}

.cc-action-slot {
    flex: 1;
    min-width: 0;
}

/* ── TRANSITION ── */
.cc-fade-enter-active { transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.34,1.56,0.64,1); }
.cc-fade-leave-active { transition: opacity 0.15s ease; }

.cc-fade-enter-from { opacity: 0; transform: scale(0.92) translateY(12px); }
.cc-fade-leave-to   { opacity: 0; }
</style>
