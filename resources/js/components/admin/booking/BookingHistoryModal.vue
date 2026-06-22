<script setup>
import { computed, watch } from 'vue'
import { useBookingActivityLog } from '../../../composables/useBookingActivityLog'
import { useAuthStore } from '../../../stores/authStore'

const authStore = useAuthStore()
const isDark = computed(() => authStore.isDarkMode)

const props = defineProps({
    visible:   { type: Boolean, default: false },
    attemptId: { type: [Number, String], default: null },
    pnr:       { type: String, default: null },
})

const emit = defineEmits(['close'])

const { logs, loading, error, fetchLogs } = useBookingActivityLog()

watch(() => [props.visible, props.attemptId], ([vis, id]) => {
    if (vis && id) fetchLogs(id)
}, { immediate: true })

const ACTION_CONFIG = {
    proceed_to_booking: {
        label:   'Proceed to Booking',
        icon:    'fa-solid fa-arrow-right-to-bracket',
        color:   '#6366f1',
        bg:      '#eef2ff',
        ring:    '#c7d2fe',
        darkBg:  '#1e1b4b',
        darkRing:'#3730a3',
    },
    traveler_added: {
        label:   'Traveler Added',
        icon:    'fa-solid fa-user-plus',
        color:   '#34d399',
        bg:      '#ecfdf5',
        ring:    '#a7f3d0',
        darkBg:  '#022c22',
        darkRing:'#065f46',
    },
    ssr_added: {
        label:   'SSR Added',
        icon:    'fa-solid fa-utensils',
        color:   '#fbbf24',
        bg:      '#fffbeb',
        ring:    '#fde68a',
        darkBg:  '#1c1400',
        darkRing:'#78350f',
    },
    ancillary_added: {
        label:   'Ancillary Added',
        icon:    'fa-solid fa-bag-shopping',
        color:   '#22d3ee',
        bg:      '#ecfeff',
        ring:    '#a5f3fc',
        darkBg:  '#001f27',
        darkRing:'#164e63',
    },
    booking_confirmed: {
        label:   'Booking Confirmed',
        icon:    'fa-solid fa-circle-check',
        color:   '#4ade80',
        bg:      '#f0fdf4',
        ring:    '#bbf7d0',
        darkBg:  '#021a0e',
        darkRing:'#14532d',
    },
    booking_cancelled: {
        label:   'Booking Cancelled',
        icon:    'fa-solid fa-ban',
        color:   '#dc2626',
        bg:      '#fef2f2',
        ring:    '#fecaca',
        darkBg:  '#1f0000',
        darkRing:'#7f1d1d',
    },
    ticket_issued: {
        label:   'Ticket Issued',
        icon:    'fa-solid fa-ticket',
        color:   '#a78bfa',
        bg:      '#f5f3ff',
        ring:    '#ddd6fe',
        darkBg:  '#150d2e',
        darkRing:'#4c1d95',
    },
    ticket_voided: {
        label:   'Ticket Voided',
        icon:    'fa-solid fa-file-slash',
        color:   '#7c3aed',
        bg:      '#f5f3ff',
        ring:    '#c4b5fd',
        darkBg:  '#2e1065',
        darkRing:'#6d28d9',
    },
}

function getConfig(actionType) {
    return ACTION_CONFIG[actionType] ?? {
        label:   actionType,
        icon:    'fa-solid fa-circle',
        color:   '#64748b',
        bg:      '#f1f5f9',
        ring:    '#e2e8f0',
        darkBg:  '#0f172a',
        darkRing:'#1e293b',
    }
}

function headerStyle(actionType) {
    const c = getConfig(actionType)
    return isDark.value
        ? { background: c.darkBg,  borderBottom: `1px solid ${c.darkRing}` }
        : { background: c.bg,      borderBottom: `1px solid ${c.ring}` }
}

function statusAfterStyle(actionType) {
    const c = getConfig(actionType)
    return isDark.value
        ? { background: c.darkBg, color: c.color, borderColor: c.darkRing }
        : { background: c.bg,     color: c.color, borderColor: c.ring }
}

function metaSummary(log) {
    const m = log.metadata ?? {}
    const parts = []
    if (m.traveler_count)   parts.push(`${m.traveler_count} traveler(s)`)
    if (m.meal_count)       parts.push(`${m.meal_count} meal SSR`)
    if (m.wheelchair_count) parts.push(`${m.wheelchair_count} wheelchair SSR`)
    if (m.ancillary_count)  parts.push(`${m.ancillary_count} ancillary item(s)`)
    if (m.pnr)              parts.push(`PNR: ${m.pnr}`)
    if (m.ticket_numbers?.length) parts.push(m.ticket_numbers.join(', '))
    return parts.join(' · ')
}

function getInitials(name) {
    if (!name) return '?'
    return name.trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

const isEmpty = computed(() => !loading.value && !error.value && logs.value.length === 0)
</script>

<template>
    <Teleport to="body">
        <Transition name="bh-fade">
            <div v-if="visible" class="bh-overlay" @click.self="emit('close')">
                <div class="bh-panel">

                    <!-- Header -->
                    <div class="bh-header">
                        <div class="bh-header-left">
                            <div class="bh-header-icon">
                                <i class="fa-solid fa-clock-rotate-left" />
                            </div>
                            <div>
                                <div class="bh-header-title">Booking History</div>
                                <div v-if="pnr" class="bh-header-pnr">
                                    <i class="fa-solid fa-hashtag" style="font-size:0.6rem;" />
                                    PNR: {{ pnr }}
                                </div>
                            </div>
                        </div>
                        <button class="bh-close" @click="emit('close')">
                            <i class="fa-solid fa-xmark" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="bh-body">

                        <!-- Loading -->
                        <div v-if="loading" class="bh-state">
                            <div class="bh-spinner" />
                            <span>Loading history…</span>
                        </div>

                        <!-- Error -->
                        <div v-else-if="error" class="bh-state bh-state--error">
                            <i class="fa-solid fa-circle-exclamation" />
                            <span>{{ error }}</span>
                        </div>

                        <!-- Empty -->
                        <div v-else-if="isEmpty" class="bh-state bh-state--empty">
                            <i class="fa-solid fa-inbox" />
                            <span>No activity recorded yet.</span>
                        </div>

                        <!-- Timeline -->
                        <div v-else class="bh-timeline">
                            <div
                                v-for="(log, idx) in logs"
                                :key="log.id"
                                class="bh-entry"
                            >
                                <!-- Left: step indicator + connector -->
                                <div class="bh-connector-col">
                                    <div
                                        class="bh-step"
                                        :style="{
                                            background: getConfig(log.action_type).color,
                                            boxShadow: `0 0 0 4px ${isDark ? getConfig(log.action_type).darkRing : getConfig(log.action_type).ring}`
                                        }"
                                    >
                                        <i :class="getConfig(log.action_type).icon" class="bh-step-icon" />
                                        <span class="bh-step-num">{{ idx + 1 }}</span>
                                    </div>
                                    <div v-if="idx < logs.length - 1" class="bh-line"
                                        :style="{ background: `linear-gradient(to bottom, ${getConfig(log.action_type).color}55, ${getConfig(logs[idx+1].action_type).color}55)` }"
                                    />
                                </div>

                                <!-- Right: card -->
                                <div class="bh-content">
                                    <div class="bh-card">
                                        <!-- Card header strip -->
                                        <div class="bh-card-header" :style="headerStyle(log.action_type)">
                                            <span class="bh-badge"
                                                :style="{ color: getConfig(log.action_type).color }"
                                            >
                                                <i :class="getConfig(log.action_type).icon" />
                                                {{ getConfig(log.action_type).label }}
                                            </span>
                                            <span class="bh-time">
                                                <i class="fa-regular fa-clock" />
                                                {{ log.created_at_fmt }}
                                            </span>
                                        </div>

                                        <!-- Card body -->
                                        <div class="bh-card-body">
                                            <!-- Meta summary -->
                                            <div v-if="metaSummary(log)" class="bh-meta">
                                                {{ metaSummary(log) }}
                                            </div>

                                            <!-- Bottom row: user + status flow -->
                                            <div class="bh-card-bottom">
                                                <div v-if="log.user_name" class="bh-user">
                                                    <div class="bh-avatar"
                                                        :style="{ background: getConfig(log.action_type).color }"
                                                    >
                                                        {{ getInitials(log.user_name) }}
                                                    </div>
                                                    <span class="bh-username">{{ log.user_name }}</span>
                                                </div>

                                                <div v-if="log.status_before || log.status_after" class="bh-status-flow">
                                                    <span v-if="log.status_before" class="bh-status bh-status--before">
                                                        {{ log.status_before }}
                                                    </span>
                                                    <i v-if="log.status_before && log.status_after" class="fa-solid fa-chevron-right bh-status-arrow" />
                                                    <span v-if="log.status_after" class="bh-status bh-status--after"
                                                        :style="statusAfterStyle(log.action_type)"
                                                    >
                                                        {{ log.status_after }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* OVERLAY */
.bh-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 10, 30, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1075;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1rem;
}

/* PANEL */
.bh-panel {
    width: 100%;
    max-width: 460px;
    height: calc(100vh - 2rem);
    background: #f8fafc;
    border-radius: 1.5rem;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 40px 100px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255,255,255,0.08);
}

/* HEADER */
.bh-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.bh-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
}

.bh-header-left {
    display: flex;
    align-items: center;
    gap: 0.9rem;
}

.bh-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1rem;
    backdrop-filter: blur(4px);
}

.bh-header-title {
    font-size: 1rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.02em;
}

.bh-header-pnr {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.55);
    font-weight: 600;
    letter-spacing: 0.06em;
    margin-top: 0.15rem;
}

.bh-close {
    width: 34px;
    height: 34px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.75);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    transition: all 0.15s;
    flex-shrink: 0;
}
.bh-close:hover {
    background: rgba(255, 255, 255, 0.22);
    color: #fff;
    border-color: rgba(255,255,255,0.3);
}

/* BODY */
.bh-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.75rem 1.5rem 1.75rem 1.25rem;
    scrollbar-width: thin;
    scrollbar-color: #e2e8f0 transparent;
}

/* STATE */
.bh-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    min-height: 200px;
    font-size: 0.85rem;
    color: #94a3b8;
}
.bh-state--error { color: #dc2626; }
.bh-state--error i { font-size: 2rem; }
.bh-state--empty i { font-size: 2.5rem; color: #cbd5e1; }

/* SPINNER */
.bh-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid #e2e8f0;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: bh-spin 0.7s linear infinite;
}
@keyframes bh-spin { to { transform: rotate(360deg); } }

/* TIMELINE */
.bh-timeline {
    display: flex;
    flex-direction: column;
}

.bh-entry {
    display: flex;
    gap: 0;
    position: relative;
}

/* Left column */
.bh-connector-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    width: 52px;
    padding-top: 0.1rem;
}

.bh-step {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    z-index: 1;
}


.bh-step-icon {
    color: #fff;
    font-size: 0.9rem;
}

.bh-step-num {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    font-size: 0.6rem;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    line-height: 1;
}

.bh-line {
    width: 2px;
    flex: 1;
    min-height: 20px;
    margin: 0.4rem 0;
    border-radius: 1px;
}

/* Card */
.bh-content {
    flex: 1;
    min-width: 0;
    padding-bottom: 1.25rem;
    padding-left: 0.25rem;
}

.bh-card {
    background: #fff;
    border: 1px solid #e8ecf0;
    border-radius: 0.875rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
}


.bh-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.6rem 0.85rem;
    flex-wrap: wrap;
}

.bh-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.38rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.bh-time {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.67rem;
    color: #94a3b8;
    white-space: nowrap;
}

.bh-card-body {
    padding: 0.65rem 0.85rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.bh-meta {
    font-size: 0.74rem;
    color: #64748b;
    line-height: 1.5;
}

/* Card bottom */
.bh-card-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* User */
.bh-user {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.bh-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.58rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: 0;
    flex-shrink: 0;
}

.bh-username {
    font-size: 0.72rem;
    color: #475569;
    font-weight: 600;
}

/* Status flow */
.bh-status-flow {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.bh-status {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    border: 1px solid;
}

.bh-status--before {
    background: #f1f5f9;
    color: #64748b;
    border-color: #e2e8f0;
}

.bh-status--after {
    border-width: 1px;
    border-style: solid;
}

.bh-status-arrow {
    font-size: 0.55rem;
    color: #cbd5e1;
}

/* TRANSITION */
.bh-fade-enter-active { transition: opacity 0.22s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
.bh-fade-leave-active { transition: opacity 0.15s ease; }
.bh-fade-enter-from  { opacity: 0; transform: translateX(28px); }
.bh-fade-leave-to    { opacity: 0; }

/* ─── DARK MODE ─── */
:global([data-bs-theme="dark"] .bh-panel) {
    background: #0f1117;
    box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255,255,255,0.06);
}

:global([data-bs-theme="dark"] .bh-body) {
    scrollbar-color: #2d3748 transparent;
}

:global([data-bs-theme="dark"] .bh-state) {
    color: #64748b;
}

:global([data-bs-theme="dark"] .bh-state--error) {
    color: #f87171;
}

:global([data-bs-theme="dark"] .bh-state--empty i) {
    color: #374151;
}

:global([data-bs-theme="dark"] .bh-spinner) {
    border-color: #1e293b;
    border-top-color: #818cf8;
}

:global([data-bs-theme="dark"] .bh-step-num) {
    background: #1e293b;
    color: #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.5);
}

:global([data-bs-theme="dark"] .bh-card) {
    background: #1a1f2e;
    border-color: #2d3748;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3), 0 1px 2px rgba(0,0,0,0.2);
}

:global([data-bs-theme="dark"] .bh-time) {
    color: #64748b;
}

:global([data-bs-theme="dark"] .bh-meta) {
    color: #94a3b8;
}

:global([data-bs-theme="dark"] .bh-username) {
    color: #94a3b8;
}

:global([data-bs-theme="dark"] .bh-status--before) {
    background: #1e293b;
    color: #94a3b8;
    border-color: #334155;
}

:global([data-bs-theme="dark"] .bh-status-arrow) {
    color: #334155;
}
</style>
