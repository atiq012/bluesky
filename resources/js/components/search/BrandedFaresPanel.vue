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

// Fixed 2-col grid — body scrolls → scrollbar stays at panel far-right
const CARD_WIDTH = 320
const CARD_GAP = 12
const BODY_PAD = 16
const RIGHT_GAP = 16
const PANEL_WIDTH = BODY_PAD * 2 + CARD_WIDTH * 2 + CARD_GAP + RIGHT_GAP

const payableRevealed = reactive({})
const featuresExpanded = reactive({})

watch(() => props.visible, (vis) => {
    if (!vis) {
        Object.keys(payableRevealed).forEach((k) => { delete payableRevealed[k] })
        Object.keys(featuresExpanded).forEach((k) => { delete featuresExpanded[k] })
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

function isPayableRevealed(brandIndex) {
    return !!payableRevealed[brandIndex]
}

// Price click → reveal Payable mini next to fare basis (agent pricing only)
function onPriceClick(brand, brandIndex) {
    if (!brandHasAgentPricing(brand)) return
    payableRevealed[brandIndex] = !payableRevealed[brandIndex]
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

// Providers send classification as either spaced ("Priority CheckIn") or
// concatenated ("PriorityCheckIn") strings — normalize before lookup so both match.
const normClass = (c) => String(c || '').replace(/[^a-z]/gi, '').toLowerCase()

const CLASSIFICATION_LABEL = {
    refund:                'Refund',
    rebooking:             'Rebooking',
    checkedbag:            'Checked Baggage',
    carryon:               'Carry-on',
    wifi:                  'Wi-Fi',
    meals:                 'Meals',
    seatassignment:        'Seat Selection',
    mileageaccrual:        'Mileage Accrual',
    upgrade:               'Upgrade',
    upgrades:              'Upgrades',
    loungeaccess:          'Lounge Access',
    premiumseat:           'Premium Seat',
    inflightentertainment: 'In-Flight Entertainment',
    prioritycheckin:       'Priority Check-in',
    priorityboarding:      'Priority Boarding',
    prioritybaggage:       'Priority Baggage',
}
const CLASSIFICATION_ICON = {
    refund:                'fa-solid fa-rotate-left',
    rebooking:             'fa-solid fa-calendar-check',
    checkedbag:            'fa-solid fa-suitcase-rolling',
    carryon:               'fa-solid fa-suitcase',
    wifi:                  'fa-solid fa-wifi',
    meals:                 'fa-solid fa-utensils',
    seatassignment:        'fa-solid fa-chair',
    mileageaccrual:        'fa-solid fa-coins',
    upgrade:               'fa-solid fa-arrow-up',
    upgrades:              'fa-solid fa-arrow-up',
    loungeaccess:          'fa-solid fa-couch',
    premiumseat:           'fa-solid fa-star',
    inflightentertainment: 'fa-solid fa-tv',
    prioritycheckin:       'fa-solid fa-person-walking-arrow-right',
    priorityboarding:      'fa-solid fa-door-open',
    prioritybaggage:       'fa-solid fa-box',
}
const classLabel = (c) => CLASSIFICATION_LABEL[normClass(c)] ?? c
const classIcon = (c) => CLASSIFICATION_ICON[normClass(c)] ?? 'fa-solid fa-circle-question'

// Icon color is per feature type (not inclusion status) so each amenity stays recognizable at a glance
const CLASSIFICATION_COLOR_SLUG = {
    refund:                'refund',
    rebooking:             'rebooking',
    checkedbag:            'checked-bag',
    carryon:               'carry-on',
    wifi:                  'wifi',
    meals:                 'meals',
    seatassignment:        'seat',
    mileageaccrual:        'mileage',
    upgrade:               'upgrade',
    upgrades:              'upgrade',
    loungeaccess:          'lounge',
    premiumseat:           'premium-seat',
    inflightentertainment: 'entertainment',
    prioritycheckin:       'priority',
    priorityboarding:      'priority-boarding',
    prioritybaggage:       'priority-baggage',
}
const classColorSlug = (c) => CLASSIFICATION_COLOR_SLUG[normClass(c)] ?? 'default'

const INCLUSION_ORDER = { Included: 0, Chargeable: 1, 'Not Offered': 2 }
const FEATURE_PREVIEW = 7

function sortedAttributes(attrs) {
    if (!attrs?.length) return []
    return [...attrs].sort((a, b) =>
        (INCLUSION_ORDER[a.inclusion] ?? 9) - (INCLUSION_ORDER[b.inclusion] ?? 9)
    )
}

// Collapse long amenity lists — first 7 visible, rest behind Show more
function visibleAttributes(attrs, bIdx) {
    const sorted = sortedAttributes(attrs)
    if (featuresExpanded[bIdx]) return sorted
    return sorted.slice(0, FEATURE_PREVIEW)
}

function hiddenFeatureCount(attrs) {
    return Math.max(0, sortedAttributes(attrs).length - FEATURE_PREVIEW)
}

function isFeaturesExpanded(bIdx) {
    return !!featuresExpanded[bIdx]
}

function toggleFeatures(bIdx) {
    featuresExpanded[bIdx] = !featuresExpanded[bIdx]
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
                                <div class="fare-card__header">
                                    <div class="fare-card__header-top">
                                        <span class="fare-card__title" :title="brand.label">{{ brand.label }}</span>
                                        <button
                                            type="button"
                                            class="fare-card__price"
                                            :class="{ 'fare-card__price--clickable': brandHasAgentPricing(brand) }"
                                            :title="brandHasAgentPricing(brand) ? (isPayableRevealed(bIdx) ? 'Hide payable' : 'Show payable') : undefined"
                                            @click="onPriceClick(brand, bIdx)"
                                        >
                                            <span class="fare-card__currency">{{ brand.currency }}</span>
                                            <span class="fare-card__amount">{{ formatFareAmount(brandGrossFare(brand)) }}</span>
                                        </button>
                                    </div>
                                    <div class="fare-card__header-meta">
                                        <span class="fare-card__meta-inline">
                                            <span>Class {{ brand.class_of_service }}</span>
                                            <span v-if="brand.fare_basis_code">{{ brand.fare_basis_code }}</span>
                                            <span v-if="brand.is_default_brand" class="fare-card__meta-tag">Default</span>
                                        </span>
                                        <button
                                            v-if="isPayableRevealed(bIdx) && brandHasAgentPricing(brand)"
                                            type="button"
                                            class="fare-card__payable-mini"
                                            :class="{ 'fare-card__payable-mini--interactive': canShowPayableBreakdown(brand) }"
                                            :title="canShowPayableBreakdown(brand) ? 'View payable breakdown' : undefined"
                                            @click="onPayableClick(brand)"
                                        >
                                            <span class="fare-card__payable-mini-value">
                                                {{ brand.currency }} {{ formatFareAmount(brandTotalPayable(brand)) }}
                                            </span>
                                            <i
                                                v-if="canShowPayableBreakdown(brand)"
                                                class="fa-solid fa-circle-info"
                                                aria-hidden="true"
                                            ></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="fare-card__divider"></div>
                                <div class="fare-card__features">
                                    <div
                                        class="fare-card__feature-list"
                                        :class="{ 'fare-card__feature-list--scroll': hiddenFeatureCount(brand.attributes) > 0 }"
                                    >
                                    <div
                                        v-for="(attr, aIdx) in visibleAttributes(brand.attributes, bIdx)"
                                        :key="aIdx"
                                        class="fare-card__feature"
                                    >
                                        <span class="fare-card__feature-part">
                                            <span
                                                class="fare-card__cat-icon"
                                                :class="'fare-card__cat-icon--' + classColorSlug(attr.classification)"
                                            >
                                                <i :class="classIcon(attr.classification)"></i>
                                            </span>
                                            <span class="fare-card__feature-text">{{ classLabel(attr.classification) }}</span>
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
                                    <button
                                        v-if="hiddenFeatureCount(brand.attributes) > 0"
                                        class="fare-card__more"
                                        type="button"
                                        @click="toggleFeatures(bIdx)"
                                    >
                                        <template v-if="isFeaturesExpanded(bIdx)">
                                            Show less <i class="fa-solid fa-chevron-up ms-1"></i>
                                        </template>
                                        <template v-else>
                                            Show more {{ hiddenFeatureCount(brand.attributes) }}
                                            <i class="fa-solid fa-chevron-down ms-1"></i>
                                        </template>
                                    </button>
                                </div>
                                <button class="fare-card__footer" type="button" @click="selectBrand(brand)">
                                    <span
                                        class="fare-card__source"
                                        :class="brand.content_source === 'NDC' ? 'fare-card__source--ndc' : 'fare-card__source--gds'"
                                    >{{ brand.content_source || 'GDS' }}</span>
                                    <span class="fare-card__footer-cta">
                                        Select Fare <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </span>
                                </button>
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
    /* Bluesky logo hues, very light tint — BLUE (firoza) → SKY (purple) */
    background: linear-gradient(90deg, #d2f4f2 0%, #d6eef9 35%, #e2e0f8 70%, #ebe4fc 100%);
    color: #0f172a;
    border-bottom: 1px solid rgba(124, 58, 237, 0.12);
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
    background: linear-gradient(90deg, #1a2f35 0%, #1a2838 35%, #24204a 70%, #2a1f3c 100%);
    border-bottom-color: rgba(124, 58, 237, 0.22);
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
    overflow-x: hidden;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    background: var(--bs-tertiary-bg, #f1f5f9);
    scrollbar-width: thin;
    scrollbar-color: #c7d7f5 transparent;
}
.bfp-body::-webkit-scrollbar { width: 5px; }
.bfp-body::-webkit-scrollbar-track { background: transparent; }
.bfp-body::-webkit-scrollbar-thumb { background: #c7d7f5; border-radius: 10px; }
html[data-bs-theme="dark"] .bfp-body {
    scrollbar-color: #374151 transparent;
}
html[data-bs-theme="dark"] .bfp-body::-webkit-scrollbar-thumb { background: #374151; }

/* Cards only as wide as grid — leftover body width = gap before far-right scrollbar */
.bfp-cards {
    display: grid;
    grid-template-columns: repeat(2, 320px);
    gap: 12px;
    align-content: start;
    width: max-content;
    max-width: 100%;
    padding-bottom: 4px;
}
/* Card height follows content (7 rows collapsed / expand via Show more) */
.bfp-card-item {
    width: 320px;
    height: auto;
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
    height: auto;
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
    padding: 12px 12px 10px;
    background: var(--bs-tertiary-bg, #f9fafb);
    border-bottom: 1px solid var(--bs-border-color, #eef0f6);
    flex-shrink: 0;
}
.fare-card__header-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.fare-card__title {
    min-width: 0;
    flex: 1;
    font-size: 14px;
    font-weight: 700;
    color: var(--bs-body-color, #1a2436);
    line-height: 1.25;
    letter-spacing: -0.15px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.fare-card__price {
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
    flex-shrink: 0;
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
    font-variant-numeric: tabular-nums;
    cursor: default;
    text-align: right;
    line-height: 1;
}
.fare-card__price--clickable {
    cursor: pointer;
    border-radius: 6px;
    padding: 2px 4px;
    margin: -2px -4px;
    transition: background 0.15s ease;
}
.fare-card__price--clickable:hover,
.fare-card__price--clickable:focus-visible {
    background: rgba(59, 121, 242, 0.08);
    outline: none;
}
.fare-card__currency {
    font-size: 11px;
    font-weight: 600;
    color: var(--bs-secondary-color, #7b879f);
}
.fare-card__amount {
    font-size: 17px;
    font-weight: 800;
    color: var(--bs-body-color, #1a2436);
    letter-spacing: -0.4px;
}
.fare-card__header-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 18px;
}
.fare-card__meta-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0;
    min-width: 0;
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
.fare-card__payable-mini {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    margin: 0;
    padding: 2px 6px;
    border: none;
    border-radius: 4px;
    background: rgba(21, 101, 192, 0.08);
    color: #1565c0;
    font-size: 10px;
    font-weight: 600;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
    cursor: default;
    white-space: nowrap;
}
.fare-card__payable-mini--interactive {
    cursor: pointer;
    transition: background 0.15s ease;
}
.fare-card__payable-mini--interactive:hover,
.fare-card__payable-mini--interactive:focus-visible {
    background: rgba(21, 101, 192, 0.14);
    outline: none;
}
.fare-card__payable-mini-value {
    font-weight: 700;
}
.fare-card__payable-mini i {
    font-size: 9px;
    opacity: 0.75;
}
html[data-bs-theme="dark"] .fare-card__payable-mini {
    background: rgba(147, 197, 253, 0.12);
    color: #93c5fd;
}
html[data-bs-theme="dark"] .fare-card__payable-mini--interactive:hover,
html[data-bs-theme="dark"] .fare-card__payable-mini--interactive:focus-visible {
    background: rgba(147, 197, 253, 0.2);
}
html[data-bs-theme="dark"] .fare-card__price--clickable:hover,
html[data-bs-theme="dark"] .fare-card__price--clickable:focus-visible {
    background: rgba(147, 197, 253, 0.12);
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

.fare-card__divider {
    height: 1px;
    background: var(--bs-border-color, #eef0f6);
    flex-shrink: 0;
}

.fare-card__features {
    flex: 0 0 auto;
    padding: 6px 12px 8px;
}

.fare-card__feature-list--scroll {
    /* Lock to 7-row height so Show more scrolls instead of growing the card */
    height: 182px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #c7d7f5 transparent;
}
.fare-card__feature-list--scroll::-webkit-scrollbar { width: 5px; }
.fare-card__feature-list--scroll::-webkit-scrollbar-track { background: transparent; }
.fare-card__feature-list--scroll::-webkit-scrollbar-thumb { background: #c7d7f5; border-radius: 10px; }
html[data-bs-theme="dark"] .fare-card__feature-list--scroll {
    scrollbar-color: #374151 transparent;
}
html[data-bs-theme="dark"] .fare-card__feature-list--scroll::-webkit-scrollbar-thumb { background: #374151; }

.fare-card__feature {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
}

.fare-card__more {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    margin-top: 4px;
    padding: 6px 8px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #3B79F2;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.fare-card__more:hover {
    background: rgba(59, 121, 242, 0.08);
    color: #2563eb;
}
html[data-bs-theme="dark"] .fare-card__more { color: #93c5fd; }
html[data-bs-theme="dark"] .fare-card__more:hover {
    background: rgba(147, 197, 253, 0.12);
    color: #bfdbfe;
}

.fare-card__feature-part {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    line-height: 1;
}
.fare-card__feature-part:first-child {
    flex: 1;
}
/* Lock status icon column so check/$ line up despite Included vs Chargeable width */
.fare-card__feature-part--status {
    display: grid;
    grid-template-columns: 16px 5.6em;
    column-gap: 6px;
    align-items: center;
    flex-shrink: 0;
    min-width: 0;
    margin-left: auto;
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
/* One color per feature type, independent of inclusion status */
.fare-card__cat-icon--refund      { color: #7c3aed; }
.fare-card__cat-icon--rebooking   { color: #0891b2; }
.fare-card__cat-icon--checked-bag { color: #92400e; }
.fare-card__cat-icon--carry-on    { color: #2563eb; }
.fare-card__cat-icon--wifi        { color: #0d9488; }
.fare-card__cat-icon--meals       { color: #ea580c; }
.fare-card__cat-icon--seat        { color: #16a34a; }
.fare-card__cat-icon--mileage     { color: #ca8a04; }
.fare-card__cat-icon--upgrade     { color: #db2777; }
.fare-card__cat-icon--lounge      { color: #7e22ce; }
.fare-card__cat-icon--premium-seat { color: #4f46e5; }
.fare-card__cat-icon--entertainment { color: #e11d48; }
.fare-card__cat-icon--priority    { color: #65a30d; }
.fare-card__cat-icon--priority-boarding { color: #a21caf; }
.fare-card__cat-icon--priority-baggage  { color: #b45309; }
.fare-card__cat-icon--default     { color: #64748b; }
html[data-bs-theme="dark"] .fare-card__cat-icon--refund      { color: #c4b5fd; }
html[data-bs-theme="dark"] .fare-card__cat-icon--rebooking   { color: #67e8f9; }
html[data-bs-theme="dark"] .fare-card__cat-icon--checked-bag { color: #fbbf24; }
html[data-bs-theme="dark"] .fare-card__cat-icon--carry-on    { color: #93c5fd; }
html[data-bs-theme="dark"] .fare-card__cat-icon--wifi        { color: #5eead4; }
html[data-bs-theme="dark"] .fare-card__cat-icon--meals       { color: #fdba74; }
html[data-bs-theme="dark"] .fare-card__cat-icon--seat        { color: #86efac; }
html[data-bs-theme="dark"] .fare-card__cat-icon--mileage     { color: #fde047; }
html[data-bs-theme="dark"] .fare-card__cat-icon--upgrade     { color: #f9a8d4; }
html[data-bs-theme="dark"] .fare-card__cat-icon--lounge      { color: #d8b4fe; }
html[data-bs-theme="dark"] .fare-card__cat-icon--premium-seat { color: #a5b4fc; }
html[data-bs-theme="dark"] .fare-card__cat-icon--entertainment { color: #fda4af; }
html[data-bs-theme="dark"] .fare-card__cat-icon--priority    { color: #bef264; }
html[data-bs-theme="dark"] .fare-card__cat-icon--priority-boarding { color: #e879f9; }
html[data-bs-theme="dark"] .fare-card__cat-icon--priority-baggage  { color: #fcd34d; }
html[data-bs-theme="dark"] .fare-card__cat-icon--default     { color: #94a3b8; }

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
.fare-card__feature-text--status { font-weight: 700; text-align: left; }
.fare-card__feature-text--ok  { color: #0d9b6e; }
.fare-card__feature-text--fee { color: #d97706; }
.fare-card__feature-text--no  { color: #9aa3b5; }
html[data-bs-theme="dark"] .fare-card__feature-text--ok  { color: #6ee7b7; }
html[data-bs-theme="dark"] .fare-card__feature-text--fee { color: #fbbf24; }
html[data-bs-theme="dark"] .fare-card__feature-text--no  { color: #6b7280; }

/* Full-bleed footer — matches card bottom curve; NDC left, Select Fare right */
.fare-card__footer {
    flex-shrink: 0;
    margin-top: auto;
    width: 100%;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border: none;
    outline: none;
    border-radius: 0 0 11px 11px;
    cursor: pointer;
    transition: background 0.18s, filter 0.18s;
    text-align: left;
}
.fare-card__footer .fare-card__source {
    font-size: 10px;
    padding: 3px 8px;
    /* Keep badge readable on colored footer */
    background: rgba(255, 255, 255, 0.92);
}
.fare-card__footer-cta {
    display: inline-flex;
    align-items: center;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.2px;
    flex-shrink: 0;
}
.fare-card--eco  .fare-card__footer {
    background: linear-gradient(135deg, #e8faf7 0%, #d5f4ee 55%, #c3ede5 100%);
}
.fare-card--eco  .fare-card__footer:hover {
    background: linear-gradient(135deg, #dcf7f1 0%, #c8eee6 55%, #b5e6dc 100%);
}
.fare-card--eco  .fare-card__footer-cta { color: #0f766e; }
.fare-card--flex .fare-card__footer {
    background: linear-gradient(135deg, #eef4fe 0%, #dde9fc 55%, #cce0fa 100%);
}
.fare-card--flex .fare-card__footer:hover {
    background: linear-gradient(135deg, #e4eefd 0%, #d2e3fb 55%, #c0d7f8 100%);
}
.fare-card--flex .fare-card__footer-cta { color: #1d4ed8; }
.fare-card--first .fare-card__footer {
    background: linear-gradient(135deg, #f3eefc 0%, #e8dff8 55%, #ddd0f3 100%);
}
.fare-card--first .fare-card__footer:hover {
    background: linear-gradient(135deg, #ede6fa 0%, #e1d6f5 55%, #d4c5f0 100%);
}
.fare-card--first .fare-card__footer-cta { color: #6d28d9; }

@media (max-width: 700px) {
    .bfp-panel { width: 100vw !important; }
    .bfp-body { align-items: stretch; }
    .bfp-cards {
        grid-template-columns: 1fr;
        width: 100%;
        max-width: 100%;
    }
    .bfp-card-item {
        width: 100%;
        max-width: 100%;
        height: auto;
    }
}
</style>
