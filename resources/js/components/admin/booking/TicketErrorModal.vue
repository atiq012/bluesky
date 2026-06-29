<script setup>
const props = defineProps({
    visible: { type: Boolean, default: false },
    pnr:     { type: String,  default: null },
    message: { type: String,  default: null },
})

const emit = defineEmits(['close'])

const isFareInvalidated = computed(() => {
    return props.message?.toLowerCase().includes('filed fare has been invalidated')
        || props.message?.toLowerCase().includes('fare has been invalidated')
})

import { computed } from 'vue'
</script>

<template>
    <Teleport to="body">
        <Transition name="te-fade">
            <div v-if="visible" class="te-overlay" @click.self="emit('close')">
                <div class="te-card">

                    <!-- Close -->
                    <button class="te-close" @click="emit('close')">
                        <i class="fa-solid fa-xmark" />
                    </button>

                    <!-- Icon zone -->
                    <div class="te-icon-zone">
                        <div class="te-pulse-ring te-pulse-ring--lg" />
                        <div class="te-pulse-ring te-pulse-ring--md" />
                        <div class="te-icon-circle">
                            <i class="fa-solid fa-triangle-exclamation te-ico-main" />
                        </div>
                        <div class="te-deco-dot te-deco-dot--tl" />
                        <div class="te-deco-dot te-deco-dot--tr" />
                        <div class="te-deco-dot te-deco-dot--bl" />
                    </div>

                    <!-- Body -->
                    <div class="te-body">
                        <h2 class="te-title">
                            {{ isFareInvalidated ? 'Fare No Longer Available' : 'Ticketing Failed' }}
                        </h2>

                        <div v-if="pnr" class="te-pnr-badge">
                            <i class="fa-solid fa-ticket te-pnr-ico" />
                            <span class="te-pnr-label">PNR</span>
                            <span class="te-pnr-value">{{ pnr }}</span>
                        </div>

                        <p class="te-desc">
                            <template v-if="isFareInvalidated">
                                The fare for this booking has been <strong>invalidated</strong> by the GDS.
                                The ticket was not issued and no balance was deducted.
                            </template>
                            <template v-else>
                                {{ message || 'Ticketing failed. Please try again.' }}
                            </template>
                        </p>

                        <template v-if="isFareInvalidated">
                            <div class="te-steps-label">What to do next:</div>
                            <ul class="te-list">
                                <li>
                                    <span class="te-step-num">1</span>
                                    <strong>Cancel</strong> this booking using the Cancel button
                                </li>
                                <li>
                                    <span class="te-step-num">2</span>
                                    <strong>Search</strong> for the same flight again
                                </li>
                                <li>
                                    <span class="te-step-num">3</span>
                                    <strong>Book → Ticket</strong> with the new fare
                                </li>
                            </ul>
                        </template>
                    </div>

                    <!-- Actions -->
                    <div class="te-actions">
                        <button class="te-btn-close" @click="emit('close')">
                            <i class="fa-solid fa-check" />
                            Got it
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.te-overlay {
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

.te-card {
    position: relative;
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 1.5rem;
    overflow: hidden;
    box-shadow:
        0 0 0 1px rgba(217, 119, 6, 0.10),
        0 32px 80px rgba(0, 0, 0, 0.28);
    display: flex;
    flex-direction: column;
}

html[data-bs-theme="dark"] .te-card {
    background: #1e1e2e;
}

.te-close {
    position: absolute;
    top: 0.9rem;
    right: 0.9rem;
    z-index: 2;
    width: 30px;
    height: 30px;
    border: none;
    background: rgba(255, 255, 255, 0.22);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.80);
    font-size: 0.9rem;
    transition: background 0.15s;
}

.te-close:hover {
    background: rgba(255, 255, 255, 0.38);
    color: #fff;
}

/* Icon zone */
.te-icon-zone {
    position: relative;
    background: linear-gradient(145deg, #92400e 0%, #b45309 55%, #f59e0b 100%);
    padding: 2.75rem 2rem 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.te-pulse-ring {
    position: absolute;
    border-radius: 50%;
    border: 1.5px solid rgba(255, 255, 255, 0.18);
    animation: te-pulse 2.4s ease-out infinite;
    pointer-events: none;
}

.te-pulse-ring--lg { width: 200px; height: 200px; animation-delay: 0s; }
.te-pulse-ring--md { width: 150px; height: 150px; animation-delay: 0.6s; }

@keyframes te-pulse {
    0%   { transform: scale(0.85); opacity: 0.5; }
    70%  { transform: scale(1.15); opacity: 0; }
    100% { transform: scale(1.15); opacity: 0; }
}

.te-icon-circle {
    position: relative;
    z-index: 1;
    width: 90px;
    height: 90px;
    background: rgba(255, 255, 255, 0.12);
    border: 2px solid rgba(255, 255, 255, 0.22);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.te-ico-main {
    font-size: 2.4rem;
    color: #fff;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
}

.te-deco-dot {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
}
.te-deco-dot--tl { top: 1.25rem; left: 1.5rem; }
.te-deco-dot--tr { top: 2rem; right: 2.5rem; width: 5px; height: 5px; }
.te-deco-dot--bl { bottom: 1.5rem; left: 2.5rem; width: 6px; height: 6px; }

/* Body */
.te-body {
    padding: 1.5rem 1.75rem 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.85rem;
    text-align: center;
}

.te-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1e1e2e;
    margin: 0;
}

html[data-bs-theme="dark"] .te-title { color: #f1f5f9; }

.te-pnr-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #fffbeb;
    border: 1.5px solid #fcd34d;
    border-radius: 999px;
    padding: 0.35rem 1rem;
}

html[data-bs-theme="dark"] .te-pnr-badge {
    background: #2d2413;
    border-color: #854d0e;
}

.te-pnr-ico   { color: #d97706; font-size: 0.85rem; }
.te-pnr-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #d97706; }
.te-pnr-value { font-size: 1rem; font-weight: 800; color: #92400e; letter-spacing: 0.1em; }

html[data-bs-theme="dark"] .te-pnr-value { color: #fbbf24; }

.te-desc {
    font-size: 0.84rem;
    color: #475569;
    margin: 0;
    line-height: 1.6;
}

html[data-bs-theme="dark"] .te-desc { color: #94a3b8; }

.te-steps-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #d97706;
    align-self: flex-start;
    margin-bottom: -0.25rem;
}

.te-list {
    list-style: none;
    margin: 0;
    padding: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    text-align: left;
}

.te-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    font-size: 0.8rem;
    color: #475569;
    line-height: 1.5;
}

html[data-bs-theme="dark"] .te-list li { color: #94a3b8; }

.te-step-num {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fef3c7;
    border: 1.5px solid #fcd34d;
    color: #92400e;
    font-size: 0.7rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 0.05rem;
}

html[data-bs-theme="dark"] .te-step-num {
    background: #2d2413;
    border-color: #854d0e;
    color: #fbbf24;
}

/* Actions */
.te-actions {
    padding: 1.25rem 1.75rem 1.75rem;
    display: flex;
    justify-content: center;
}

.te-btn-close {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 2rem;
    background: linear-gradient(135deg, #b45309, #d97706);
    color: #fff;
    border: none;
    border-radius: 999px;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.15s;
}

.te-btn-close:hover { opacity: 0.88; }

/* Transition */
.te-fade-enter-active { transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.34,1.56,0.64,1); }
.te-fade-leave-active { transition: opacity 0.15s ease; }
.te-fade-enter-from  { opacity: 0; transform: scale(0.92) translateY(12px); }
.te-fade-leave-to    { opacity: 0; }
</style>
