<script setup>
import { computed, reactive, watch } from 'vue'
import {
    brandGrossFare,
    brandTotalPayable,
    brandHasAgentPricing,
    canShowPayableBreakdown,
    formatFareAmount,
} from '../../utils/dynamicRulePricingDisplay'

const props = defineProps({
    visible: { type: Boolean, default: false },
    flight:  { type: Object,  default: null },
    form:    { type: Object,  default: () => ({}) },
})

const emit = defineEmits(['close', 'select', 'payable-breakdown'])

// Fixed 2-col card grid — panel fits two cards side by side
const CARD_WIDTH = 320
const CARD_GAP = 12
const BODY_PAD = 16
const PANEL_WIDTH = BODY_PAD * 2 + CARD_WIDTH * 2 + CARD_GAP

const dualPricingToggles = reactive({})

watch(() => props.visible, (vis) => {
    if (!vis) {
        Object.keys(dualPricingToggles).forEach((k) => { delete dualPricingToggles[k] })
    }
})

const brandOptions = computed(() => props.flight?.outbound?.brand_options ?? [])

const DEFAULT_AIRLINE_LOGO = '/uploads/airlines/default.svg'

// Header — same layout as FlightPricePanel
const headerAirlineLogo = computed(() =>
    props.flight?.outbound?.first_logo_path
        || props.flight?.inbound?.first_logo_path
        || DEFAULT_AIRLINE_LOGO
)

const headerAirlineName = computed(() =>
    props.flight?.outbound?.first_airline_name
        || props.flight?.outbound?.first_carrier_code
        || 'Airline'
)

const isRoundTrip = computed(() =>
    Number(props.form?.Way) === 2 || !!props.flight?.inbound
)

const headerRouteLabel = computed(() => {
    const origin = props.form?.from || props.flight?.outbound?.origin || ''
    const dest = props.form?.to || props.flight?.outbound?.destination || ''
    if (!origin && !dest) return 'Branded Fares'
    if (isRoundTrip.value && origin && dest) return `${origin} → ${dest} → ${origin}`
    if (origin && dest) return `${origin} → ${dest}`
    return [origin, dest].filter(Boolean).join(' → ') || 'Branded Fares'
})

function formatHeaderDate(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return ''
    const day = String(d.getDate()).padStart(2, '0')
    const mon = d.toLocaleDateString('en-US', { month: 'short' })
    return `${day} ${mon}`
}

const headerTripMeta = computed(() => {
    const trip = isRoundTrip.value ? 'Return' : 'One Way'
    const dep = formatHeaderDate(props.form?.dep_date || props.flight?.outbound?.departure_date)
    const ret = isRoundTrip.value
        ? formatHeaderDate(props.form?.arrival_date || props.flight?.inbound?.departure_date)
        : ''
    const datePart = dep && ret ? `${dep} - ${ret}` : (dep || ret || '')
    const pax = Number(props.form?.ADT ?? 0)
        + Number(props.form?.CNN ?? 0)
        + Number(props.form?.KID ?? 0)
        + Number(props.form?.INF ?? 0)
    const traveler = `${pax || 1} Traveler${(pax || 1) === 1 ? '' : 's'}`
    return [trip, datePart, traveler].filter(Boolean).join(' . ')
})

function onHeaderLogoError(e) {
    if (e?.target) e.target.src = DEFAULT_AIRLINE_LOGO
}

const panelStyle = computed(() => ({
    width: `min(${PANEL_WIDTH}px, 100vw)`,
}))



function isDualPrice(brandIndex) {
    return !!dualPricingToggles[brandIndex]
}

function toggleDualPrice(brandIndex) {
    dualPricingToggles[brandIndex] = !dualPricingToggles[brandIndex]
}

function closePanel() {
    emit('close')
}

function selectBrand(brand) {
    emit('select', brand)
}

function onPayableClick(brand) {
    if (!canShowPayableBreakdown(brand)) return
    emit('payable-breakdown', brand)
}

const CLASSIFICATION_LABEL = {
    Refund:         'Refund',
    Rebooking:      'Rebooking',
    CheckedBag:     'Checked Baggage',
    CarryOn:        'Carry-on',
    WiFi:           'Wi-Fi',
    Meals:          'Meals',
    SeatAssignment: 'Seat Selection',
}
const CLASSIFICATION_ICON = {
    Refund:            'fa-solid fa-rotate-left',
    Rebooking:         'fa-solid fa-calendar-check',
    CheckedBag:        'fa-solid fa-suitcase-rolling',
    CarryOn:           'fa-solid fa-suitcase',
    WiFi:              'fa-solid fa-wifi',
    Meals:             'fa-solid fa-utensils',
    SeatAssignment:    'fa-solid fa-chair',
    'Mileage Accrual': 'fa-solid fa-coins',
    Upgrade:           'fa-solid fa-arrow-up',
    'Lounge Access':   'fa-solid fa-couch',
}
const classLabel = (c) => CLASSIFICATION_LABEL[c] ?? c
const classIcon = (c) => CLASSIFICATION_ICON[c] ?? 'fa-solid fa-circle-question'

const INCLUSION_ORDER = { Included: 0, Chargeable: 1, 'Not Offered': 2 }
function sortedAttributes(attrs) {
    if (!attrs?.length) return []
    return [...attrs].sort((a, b) =>
        (INCLUSION_ORDER[a.inclusion] ?? 9) - (INCLUSION_ORDER[b.inclusion] ?? 9)
    )
}

const TIER_CLASS = ['fare-card--eco', 'fare-card--flex', 'fare-card--first']
function tierClass(bIdx) {
    return TIER_CLASS[bIdx % TIER_CLASS.length]
}
</script>

<template>
    <Teleport to="body">
        <Transition name="bfp-fade">
            <div v-if="visible" class="bfp-backdrop" @click="closePanel"></div>
        </Transition>

        <Transition name="bfp-slide">
            <div
                v-if="visible"
                class="bfp-panel"
                :style="panelStyle"
                role="dialog"
                aria-modal="true"
                aria-label="Branded Fares"
            >
                <div class="bfp-header">
                    <div class="bfp-header-brand">
                        <img
                            :src="headerAirlineLogo"
                            :alt="headerAirlineName"
                            class="bfp-header-logo"
                            @error="onHeaderLogoError"
                        />
                    </div>
                    <div class="bfp-header-title">
                        <div class="bfp-header-main">{{ headerRouteLabel }}</div>
                        <div class="bfp-header-sub">{{ headerTripMeta }}</div>
                    </div>
                    <button class="bfp-close-btn" type="button" title="Back" @click="closePanel">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>


                <div class="bfp-body">
                    <div v-if="brandOptions.length" class="bfp-cards">
                        <div
                            v-for="(brand, bIdx) in brandOptions"
                            :key="bIdx"
                            class="bfp-card-item"
                        >
                            <div
                                class="fare-card"
                                :class="tierClass(bIdx)"
                            >
                                <div class="fare-card__header fare-card__header--slim">
                                    <div class="fare-card__header-row">
                                        <div class="fare-card__title-block">
                                            <span class="fare-card__title-row">
                                                <span class="fare-card__title">{{ brand.label }}</span>
                                                <span
                                                    class="fare-card__source"
                                                    :class="brand.content_source === 'NDC' ? 'fare-card__source--ndc' : 'fare-card__source--gds'"
                                                >{{ brand.content_source || 'GDS' }}</span>
                                            </span>
                                            <span class="fare-card__meta-inline">
                                                <span>Class {{ brand.class_of_service }}</span>
                                                <span v-if="brand.fare_basis_code">{{ brand.fare_basis_code }}</span>
                                                <span v-if="brand.is_default_brand" class="fare-card__meta-tag">Default</span>
                                            </span>
                                        </div>
                                        <label
                                            v-if="brandHasAgentPricing(brand)"
                                            class="fare-card__price-toggle fare-card__price-toggle--sm"
                                            :title="isDualPrice(bIdx) ? 'Show selling price only' : 'Show selling and payable'"
                                        >
                                            <input
                                                type="checkbox"
                                                class="fare-card__price-toggle-input"
                                                :checked="isDualPrice(bIdx)"
                                                @change="toggleDualPrice(bIdx)"
                                            />
                                            <span class="fare-card__price-toggle-ui" aria-hidden="true"></span>
                                        </label>
                                    </div>
                                    <div class="fare-card__header-row fare-card__header-row--price">
                                        <template v-if="!isDualPrice(bIdx)">
                                            <div class="fare-card__price-single">
                                                <span class="fare-card__currency">{{ brand.currency }}</span>
                                                <span class="fare-card__amount">{{ formatFareAmount(brandGrossFare(brand)) }}</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="fare-card__price-stack">
                                                <div class="fare-card__price-line">
                                                    <span class="fare-card__price-label">Selling</span>
                                                    <span class="fare-card__price-value">
                                                        <span class="fare-card__price-currency">{{ brand.currency }}</span>
                                                        {{ formatFareAmount(brandGrossFare(brand)) }}
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="fare-card__price-line fare-card__price-line--payable"
                                                    :class="{ 'fare-card__price-line--interactive': canShowPayableBreakdown(brand) }"
                                                    :title="canShowPayableBreakdown(brand) ? 'View payable breakdown' : undefined"
                                                    @click.stop="onPayableClick(brand)"
                                                >
                                                    <span class="fare-card__price-label">
                                                        Payable
                                                        <i
                                                            v-if="canShowPayableBreakdown(brand)"
                                                            class="fa-solid fa-circle-info fare-card__price-hint"
                                                            aria-hidden="true"
                                                        ></i>
                                                    </span>
                                                    <span class="fare-card__price-value">
                                                        <span class="fare-card__price-currency">{{ brand.currency }}</span>
                                                        {{ formatFareAmount(brandTotalPayable(brand)) }}
                                                    </span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="fare-card__divider"></div>
                                <div class="fare-card__features">
                                    <div
                                        v-for="(attr, aIdx) in sortedAttributes(brand.attributes)"
                                        :key="aIdx"
                                        class="fare-card__feature"
                                    >
                                        <span class="fare-card__feature-part">
                                            <span
                                                class="fare-card__cat-icon"
                                                :class="{
                                                    'fare-card__cat-icon--ok':  attr.inclusion === 'Included',
                                                    'fare-card__cat-icon--fee': attr.inclusion === 'Chargeable',
                                                    'fare-card__cat-icon--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >
                                                <i :class="classIcon(attr.classification)"></i>
                                            </span>
                                            <span
                                                class="fare-card__feature-text"
                                                :class="{
                                                    'fare-card__feature-text--ok':  attr.inclusion === 'Included',
                                                    'fare-card__feature-text--fee': attr.inclusion === 'Chargeable',
                                                    'fare-card__feature-text--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >{{ classLabel(attr.classification) }}</span>
                                        </span>
                                        <span class="fare-card__feature-part fare-card__feature-part--status">
                                            <span
                                                class="fare-card__status-dot fare-card__status-dot--outline"
                                                :class="{
                                                    'fare-card__status-dot--ok':  attr.inclusion === 'Included',
                                                    'fare-card__status-dot--fee': attr.inclusion === 'Chargeable',
                                                    'fare-card__status-dot--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >
                                                <i :class="{
                                                    'fa-solid fa-check':       attr.inclusion === 'Included',
                                                    'fa-solid fa-dollar-sign': attr.inclusion === 'Chargeable',
                                                    'fa-solid fa-xmark':       attr.inclusion === 'Not Offered',
                                                }"></i>
                                            </span>
                                            <span
                                                class="fare-card__feature-text fare-card__feature-text--status"
                                                :class="{
                                                    'fare-card__feature-text--ok':  attr.inclusion === 'Included',
                                                    'fare-card__feature-text--fee': attr.inclusion === 'Chargeable',
                                                    'fare-card__feature-text--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >{{ attr.inclusion }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="fare-card__footer">
                                    <button class="fare-card__book-btn" type="button" @click="selectBrand(brand)">
                                        Select fare <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="bfp-empty">No brand options available.</div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Transitions (same motion as FlightPricePanel) ─ */
.bfp-fade-enter-active, .bfp-fade-leave-active { transition: opacity 0.25s ease; }
.bfp-fade-enter-from, .bfp-fade-leave-to { opacity: 0; }

.bfp-slide-enter-active, .bfp-slide-leave-active {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
}
.bfp-slide-enter-from, .bfp-slide-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

/* ── Backdrop ────────────────────────────── */
.bfp-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1040;
    backdrop-filter: blur(2px);
}

/* ── Panel shell ─────────────────────────── */
.bfp-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    background: var(--bs-body-bg, #fff);
    box-shadow: -6px 0 40px rgba(0, 0, 0, 0.18);
    overflow: hidden;
}

.bfp-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    /* Bluesky logo hues, very light tint */
    background: linear-gradient(90deg, #e6fafa 0%, #e8f4fb 40%, #eaf3fc 70%, #e8f2fb 100%);
    color: #0f172a;
    border-bottom: 1px solid rgba(26, 158, 181, 0.14);
    flex-shrink: 0;
}
.bfp-close-btn {
    background: rgba(255, 255, 255, 0.75);
    border: none;
    color: #1a9eb5;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
}
.bfp-close-btn:hover { background: rgba(255, 255, 255, 0.95); }
.bfp-header-brand {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(26, 158, 181, 0.14);
    display: flex;
    align-items: center;
    justify-content: center;
}
.bfp-header-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.bfp-header-title { flex: 1; min-width: 0; }
.bfp-header-main {
    font-size: 16px;
    font-weight: 700;
    line-height: 1.25;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bfp-header-sub {
    margin-top: 2px;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.3;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

html[data-bs-theme="dark"] .bfp-header {
    background: linear-gradient(90deg, #1a2f35 0%, #1a2838 40%, #1b2a3a 70%, #1a2c3c 100%);
    border-bottom-color: rgba(26, 158, 181, 0.2);
    color: #e2e8f0;
}
html[data-bs-theme="dark"] .bfp-header-main { color: #f1f5f9; }
html[data-bs-theme="dark"] .bfp-header-sub { color: #94a3b8; }
html[data-bs-theme="dark"] .bfp-header-brand {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.12);
}
html[data-bs-theme="dark"] .bfp-close-btn {
    background: rgba(255, 255, 255, 0.1);
    color: #7dd3fc;
}
html[data-bs-theme="dark"] .bfp-close-btn:hover {
    background: rgba(255, 255, 255, 0.18);
}


.bfp-body {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    padding: 16px;
    display: flex;
    flex-direction: column;
    background: var(--bs-tertiary-bg, #f1f5f9);
}

/* 2×2 grid — vertical scroll when >2 cards (extra rows) */
.bfp-cards {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: repeat(2, 320px);
    gap: 12px;
    align-content: start;
    justify-content: start;
    overflow-x: hidden;
    overflow-y: auto;
    padding-bottom: 4px;
    scrollbar-width: thin;
    scrollbar-color: #c7d7f5 transparent;
}
.bfp-cards::-webkit-scrollbar { width: 5px; }
.bfp-cards::-webkit-scrollbar-track { background: transparent; }
.bfp-cards::-webkit-scrollbar-thumb { background: #c7d7f5; border-radius: 10px; }
html[data-bs-theme="dark"] .bfp-cards {
    scrollbar-color: #374151 transparent;
}
html[data-bs-theme="dark"] .bfp-cards::-webkit-scrollbar-thumb { background: #374151; }

/* Fixed card box — not full-width stretch */
.bfp-card-item {
    width: 320px;
    height: min(550px, calc((100dvh - 90px) / 2 - 2px));
    min-height: 450px;
    max-height: 600px;
    display: flex;
}

.bfp-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color, #6b7280);
    font-size: 13px;
    padding: 40px 20px;
    text-align: center;
}

/* ── Fare cards ──────────────────────────── */
.fare-card {
    width: 100%;
    height: 100%;
    min-height: 0;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--bs-border-color, #e4e9f2);
    background: var(--bs-body-bg, #fff);
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.2s, transform 0.2s;
}
.fare-card:hover {
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.09);
    transform: translateY(-2px);
}
.fare-card--eco  { border-top: 4px solid #16B4A1; }
.fare-card--flex { border-top: 4px solid #3B79F2; }
.fare-card--first { border-top: 4px solid #875ae9; }

.fare-card__header {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px 8px;
    background: var(--bs-tertiary-bg, #f9fafb);
    border-bottom: 1px solid var(--bs-border-color, #eef0f6);
    flex-shrink: 0;
}
.fare-card__header--slim { gap: 4px; }
.fare-card__header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 0;
}
.fare-card__header-row--price {
    justify-content: flex-end;
    padding-top: 1px;
}
.fare-card__title-block {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.fare-card__title-row {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}
.fare-card__title {
    font-size: 14px;
    font-weight: 700;
    color: var(--bs-body-color, #1a2436);
    line-height: 1.2;
    letter-spacing: -0.15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fare-card__source {
    flex-shrink: 0;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 20px;
    line-height: 1.2;
}
.fare-card__source--gds {
    background: rgba(94, 114, 228, 0.12);
    color: #4c63d2;
    border: 1px solid rgba(94, 114, 228, 0.28);
}
.fare-card__source--ndc {
    background: rgba(245, 158, 11, 0.14);
    color: #b45309;
    border: 1px solid rgba(245, 158, 11, 0.35);
}
[data-bs-theme="dark"] .fare-card__source--gds {
    background: rgba(129, 140, 248, 0.18);
    color: #a5b4fc;
    border-color: rgba(129, 140, 248, 0.35);
}
[data-bs-theme="dark"] .fare-card__source--ndc {
    background: rgba(251, 191, 36, 0.18);
    color: #fbbf24;
    border-color: rgba(251, 191, 36, 0.35);
}
.fare-card__meta-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0;
    font-size: 10px;
    font-weight: 500;
    color: var(--bs-secondary-color, #7b879f);
    line-height: 1.2;
}
.fare-card__meta-inline > span:not(:last-child)::after {
    content: '·';
    margin: 0 5px;
    color: #b8c0d0;
    font-weight: 700;
}
.fare-card__meta-tag {
    color: var(--bs-secondary-color, #5c6778);
    font-weight: 600;
}

.fare-card__price-toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    margin: 0;
    flex-shrink: 0;
}
.fare-card__price-toggle--sm {
    min-height: 20px;
    min-width: 32px;
}
.fare-card__price-toggle-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.fare-card__price-toggle-ui {
    width: 28px;
    height: 16px;
    border-radius: 999px;
    background: #c5cae9;
    position: relative;
    transition: background 0.2s ease;
}
.fare-card__price-toggle-ui::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18);
    transition: transform 0.2s ease;
}
.fare-card__price-toggle-input:checked + .fare-card__price-toggle-ui {
    background: #3d5afe;
}
.fare-card__price-toggle-input:checked + .fare-card__price-toggle-ui::after {
    transform: translateX(12px);
}
.fare-card__price-toggle-input:focus-visible + .fare-card__price-toggle-ui {
    outline: 2px solid #3d5afe;
    outline-offset: 1px;
}

.fare-card__price-single {
    display: flex;
    align-items: baseline;
    gap: 5px;
    font-variant-numeric: tabular-nums;
}
.fare-card__price-stack {
    display: flex;
    flex-direction: column;
    gap: 3px;
    width: 100%;
    max-width: 100%;
}
.fare-card__price-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 3px 0;
    font-variant-numeric: tabular-nums;
    border-bottom: 1px solid var(--bs-border-color, #eef0f5);
}
.fare-card__price-line:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.fare-card__price-line--payable { padding-top: 2px; }
.fare-card__price-line--payable .fare-card__price-label,
.fare-card__price-line--payable .fare-card__price-value {
    color: #1565c0;
}
html[data-bs-theme="dark"] .fare-card__price-line--payable .fare-card__price-label,
html[data-bs-theme="dark"] .fare-card__price-line--payable .fare-card__price-value {
    color: #93c5fd;
}
.fare-card__price-line--interactive {
    width: 100%;
    border: none;
    background: transparent;
    text-align: inherit;
    cursor: pointer;
    transition: background 0.15s ease;
}
.fare-card__price-line--interactive:hover,
.fare-card__price-line--interactive:focus-visible {
    background: rgba(21, 101, 192, 0.08);
    outline: none;
}
.fare-card__price-hint {
    margin-left: 4px;
    font-size: 9px;
    opacity: 0.75;
}
.fare-card__price-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--bs-secondary-color, #8b97ad);
    white-space: nowrap;
}
.fare-card__price-value {
    font-size: 13px;
    font-weight: 800;
    color: var(--bs-body-color, #1a2436);
    letter-spacing: -0.25px;
    text-align: right;
}
.fare-card__price-line--payable .fare-card__price-value { font-size: 13px; }
.fare-card__price-currency {
    font-size: 10px;
    font-weight: 600;
    margin-right: 2px;
}
.fare-card__currency {
    font-size: 11px;
    font-weight: 600;
    color: var(--bs-secondary-color, #7b879f);
}
.fare-card__amount {
    font-size: 18px;
    font-weight: 800;
    color: var(--bs-body-color, #1a2436);
    letter-spacing: -0.4px;
}

.fare-card__divider {
    height: 1px;
    background: var(--bs-border-color, #eef0f6);
    flex-shrink: 0;
}

.fare-card__features {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 6px 12px;
    scrollbar-width: thin;
    scrollbar-color: #c5cae9 transparent;
}
.fare-card__features::-webkit-scrollbar { width: 4px; }
.fare-card__features::-webkit-scrollbar-thumb {
    background: #c5cae9;
    border-radius: 2px;
}
html[data-bs-theme="dark"] .fare-card__features {
    scrollbar-color: #374151 transparent;
}
html[data-bs-theme="dark"] .fare-card__features::-webkit-scrollbar-thumb {
    background: #374151;
}

.fare-card__feature {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid var(--bs-border-color, #f4f5fa);
}
.fare-card__feature:last-child { border-bottom: none; }

.fare-card__feature-part {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    line-height: 1;
}
.fare-card__feature-part--status {
    flex-shrink: 0;
}

.fare-card__cat-icon {
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color, #8b97ad);
    font-size: 11px;
    flex-shrink: 0;
    line-height: 1;
}
.fare-card__cat-icon--ok  { color: #0d9b6e; }
.fare-card__cat-icon--fee { color: #d97706; }
.fare-card__cat-icon--no  { color: #9aa3b5; }
html[data-bs-theme="dark"] .fare-card__cat-icon--ok  { color: #6ee7b7; }
html[data-bs-theme="dark"] .fare-card__cat-icon--fee { color: #fbbf24; }
html[data-bs-theme="dark"] .fare-card__cat-icon--no  { color: #6b7280; }

.fare-card__status-dot {
    box-sizing: border-box;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    flex-shrink: 0;
    line-height: 0;
}
.fare-card__status-dot i {
    display: block;
    line-height: 1;
    width: 1em;
    text-align: center;
}
.fare-card__status-dot--ok  { background: #e6f7f4; color: #0d9b6e; }
.fare-card__status-dot--fee { background: #fff5e6; color: #d97706; }
.fare-card__status-dot--no  { background: #e8eaef; color: #9aa3b5; }
.fare-card__status-dot--outline {
    background: transparent;
    border: 1.5px solid currentColor;
}
.fare-card__status-dot--outline.fare-card__status-dot--ok  { color: #0d9b6e; }
.fare-card__status-dot--outline.fare-card__status-dot--fee { color: #d97706; border-color: #d97706; }
.fare-card__status-dot--outline.fare-card__status-dot--no  { color: #9aa3b5; border-color: #c5cad6; }
html[data-bs-theme="dark"] .fare-card__status-dot--ok  { background: #064e3b; color: #6ee7b7; }
html[data-bs-theme="dark"] .fare-card__status-dot--fee { background: #451a03; color: #fbbf24; }
html[data-bs-theme="dark"] .fare-card__status-dot--no  { background: #374151; color: #9ca3af; }
html[data-bs-theme="dark"] .fare-card__status-dot--outline.fare-card__status-dot--ok  { color: #6ee7b7; background: transparent; }
html[data-bs-theme="dark"] .fare-card__status-dot--outline.fare-card__status-dot--fee { color: #fbbf24; border-color: #fbbf24; background: transparent; }
html[data-bs-theme="dark"] .fare-card__status-dot--outline.fare-card__status-dot--no  { color: #6b7280; border-color: #6b7280; background: transparent; }

.fare-card__feature-text {
    font-size: 11px;
    font-weight: 600;
    color: var(--bs-body-color, #1a2436);
    line-height: 16px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fare-card__feature-text--status { font-weight: 700; }
.fare-card__feature-text--ok  { color: #0d9b6e; }
.fare-card__feature-text--fee { color: #d97706; }
.fare-card__feature-text--no  { color: #9aa3b5; }
html[data-bs-theme="dark"] .fare-card__feature-text--ok  { color: #6ee7b7; }
html[data-bs-theme="dark"] .fare-card__feature-text--fee { color: #fbbf24; }
html[data-bs-theme="dark"] .fare-card__feature-text--no  { color: #6b7280; }

.fare-card__footer {
    flex-shrink: 0;
    padding: 12px 14px 14px;
    display: flex;
    justify-content: flex-end;
}
.fare-card__book-btn {
    display: inline-flex;
    align-items: center;
    padding: 9px 20px;
    text-align: center;
    border: none;
    outline: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.18s, box-shadow 0.18s;
    letter-spacing: 0.2px;
}
.fare-card--eco  .fare-card__book-btn { background: #16B4A1; color: #fff; }
.fare-card--eco  .fare-card__book-btn:hover { background: #0e9b8b; box-shadow: 0 4px 12px rgba(22, 180, 161, 0.35); }
.fare-card--flex .fare-card__book-btn { background: #3B79F2; color: #fff; }
.fare-card--flex .fare-card__book-btn:hover { background: #2963d8; box-shadow: 0 4px 12px rgba(59, 121, 242, 0.35); }
.fare-card--first .fare-card__book-btn { background: #875ae9; color: #fff; }
.fare-card--first .fare-card__book-btn:hover { background: #6e42cc; box-shadow: 0 4px 12px rgba(135, 90, 233, 0.35); }

@media (max-width: 700px) {
    .bfp-panel { width: 100vw !important; }
    .bfp-cards {
        grid-template-columns: 1fr;
        justify-content: stretch;
    }
    .bfp-card-item {
        width: 100%;
        max-width: 100%;
        height: min(560px, 80dvh);
    }
}
</style>
