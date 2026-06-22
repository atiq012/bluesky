<script setup>
import { computed } from 'vue'

const props = defineProps({
    visible:     { type: Boolean, default: false },
    pnr:         { type: String,  default: null },
    cancelledAt: { type: String,  default: null },
})

const emit = defineEmits(['close'])

const formattedDate = computed(() => {
    if (!props.cancelledAt) return null
    try {
        return new Date(props.cancelledAt).toLocaleString('en-GB', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        })
    } catch {
        return props.cancelledAt
    }
})

function handleClose() {
    emit('close')
}
</script>

<template>
    <Teleport to="body">
        <Transition name="cm-fade">
            <div v-if="visible" class="cm-overlay" @click.self="handleClose">
                <div class="cm-card">

                    <!-- LEFT PANEL -->
                    <div class="cm-left">
                        <img :src="'/theme/appimages/worlds.png'" class="cm-worlds-bg" aria-hidden="true" />
                        <div class="cm-brand">
                            <img :src="'/theme/appimages/blueskywings.png'" class="cm-logo-img" alt="BlueSky" />
                            <span class="cm-brand-name">BLUESKY</span>
                        </div>

                        <div class="cm-left-body">
                            <div class="cm-cancelled-badge">
                                <i class="fa-solid fa-ban" />
                                Booking Cancelled
                            </div>

                            <div class="cm-field">
                                <div class="cm-field-label">PNR / Locator</div>
                                <div class="cm-field-value cm-pnr">{{ pnr ?? '—' }}</div>
                            </div>

                            <div class="cm-field">
                                <div class="cm-field-label">Cancelled Via</div>
                                <div class="cm-field-value">Travelport GDS</div>
                            </div>
                        </div>
                    </div>

                    <!-- DIVIDER + NOTCHES -->
                    <div class="cm-divider">
                        <div class="cm-notch cm-notch--top" />
                        <div class="cm-notch cm-notch--bottom" />
                    </div>

                    <!-- RIGHT STUB -->
                    <div class="cm-right">
                        <button class="cm-close-x" @click="handleClose">
                            <i class="fa-solid fa-xmark" />
                        </button>

                        <div class="cm-stub-label">Cancellation Confirmed</div>

                        <div class="cm-status-box">
                            <i class="fa-solid fa-circle-check cm-status-ico" />
                            <div>
                                <div class="cm-status-title">Successfully Cancelled</div>
                                <div class="cm-status-sub">The held booking has been voided in the GDS.</div>
                            </div>
                        </div>

                        <div v-if="formattedDate" class="cm-cancelled-at">
                            <i class="fa-regular fa-clock" />
                            <span>{{ formattedDate }}</span>
                        </div>

                        <button class="cm-close-btn" @click="handleClose">Close</button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.52);
    z-index: 1060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.cm-card {
    display: flex;
    width: 100%;
    max-width: 560px;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 28px 72px rgba(0, 0, 0, 0.32);
}

/* LEFT */
.cm-left {
    flex: 0 0 58%;
    background: linear-gradient(145deg, #7f1d1d 0%, #991b1b 55%, #b91c1c 100%);
    padding: 1.75rem 1.75rem 1.5rem;
    color: #fff;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

.cm-worlds-bg {
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

.cm-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.cm-logo-img {
    height: 25px;
    width: auto;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.cm-brand-name {
    font-size: 0.92rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #fff;
}

.cm-left-body {
    flex: 1;
    padding: 1.75rem 0 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    position: relative;
    z-index: 1;
}

.cm-cancelled-badge {
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
    color: #fca5a5;
}

.cm-field-label {
    font-size: 0.63rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #fca5a5;
    margin-bottom: 0.25rem;
}

.cm-field-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
}

.cm-pnr {
    font-size: 1.95rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

/* DIVIDER */
.cm-divider {
    width: 0;
    flex-shrink: 0;
    border-left: 2px dashed rgba(255, 255, 255, 0.22);
    position: relative;
    background: transparent;
}

.cm-notch {
    position: absolute;
    width: 28px;
    height: 28px;
    background: rgba(0, 0, 0, 0.52);
    border-radius: 50%;
    left: -14px;
}

.cm-notch--top    { top: -14px; }
.cm-notch--bottom { bottom: -14px; }

/* RIGHT */
.cm-right {
    flex: 1;
    background: #fff;
    padding: 1.5rem 1.4rem 1.25rem 1.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    position: relative;
    min-width: 0;
}

.cm-close-x {
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

.cm-close-x:hover { background: #e2e8f0; }

.cm-stub-label {
    font-size: 0.63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #dc2626;
    margin-top: 0.4rem;
}

.cm-status-box {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: #fef2f2;
    border-radius: 0.6rem;
    border: 1px solid #fecaca;
}

.cm-status-ico {
    color: #16a34a;
    font-size: 1.4rem;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.cm-status-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #991b1b;
}

.cm-status-sub {
    font-size: 0.75rem;
    color: #b91c1c;
    margin-top: 0.2rem;
}

.cm-cancelled-at {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71rem;
    color: #94a3b8;
    margin-top: auto;
}

.cm-close-btn {
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

.cm-close-btn:hover {
    background: #fef2f2;
    border-color: #dc2626;
    color: #dc2626;
}

.cm-fade-enter-active,
.cm-fade-leave-active { transition: opacity 0.2s ease; }

.cm-fade-enter-from,
.cm-fade-leave-to     { opacity: 0; }
</style>
