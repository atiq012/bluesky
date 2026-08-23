<script setup>
import { ref, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axiosInstance from '../../axiosInstance'
import { useSearchStore } from '../../stores/searchStore'
import { useBookingStore } from '../../stores/bookingStore'
import { useTpV2Workbench } from '../../composables/useTpV2Workbench'
import { buildSelectionJson } from '../../utils/bookingSelectionJson'
import { completePriceAttempt } from '../../utils/bookingAttemptSession'
import {
    brandDynamicPricing,
    formatFareAmount,
} from '../../utils/dynamicRulePricingDisplay'
import { formatTicketingDeadline } from '../../utils/dateUtils'
import LoadingSpinner from '../common/LoadingSpinner.vue'
import SearchWingsBuildLoader from './SearchWingsBuildLoader.vue'
import AgencyPayableBreakdownModal from '../common/AgencyPayableBreakdownModal.vue'

// Scroll-reveal: fade+slide each section in as it enters the panel's scroll viewport (one-shot)
const prefersReducedMotion = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
const vReveal = {
    mounted(el) {
        if (prefersReducedMotion || typeof IntersectionObserver === 'undefined') {
            el.classList.add('fp-reveal-in')
            return
        }
        el.classList.add('fp-reveal')
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fp-reveal-in')
                    observer.unobserve(entry.target)
                }
            })
        }, { root: el.closest('.fp-body'), threshold: 0.12, rootMargin: '0px 0px -6% 0px' })
        observer.observe(el)
        el.__revealObserver = observer
    },
    unmounted(el) {
        el.__revealObserver?.disconnect()
    },
}

const props = defineProps({
    visible:           { type: Boolean, default: false },
    flight:            { type: Object,  default: null },
    selectedBrand:     { type: Object,  default: null },
    form:              { type: Object,  default: () => ({}) },
    catalogIdentifier: { type: String,  default: null },
    searchLogId:       { type: [Number, String], default: null },
    cachedPriceData:   { type: Object,  default: null },
})

const emit = defineEmits(['close'])

const payableBreakdownOpen = ref(false)

const { isInitiating, error: workbenchError, initiateAndNavigate } = useTpV2Workbench()
const searchStore = useSearchStore()
const bookingStore = useBookingStore()
const router = useRouter()
const route = useRoute()
// Already on /flight-booking — proceed CTA would loop same page
const showProceedButton = computed(() => route.name !== 'bookingCreate')
const isAutoRetryingBrand = ref(false)
const currentBrand = ref(null)

function handleProceed() {
    initiateAndNavigate({
        priceLogId:    priceLogId.value,
        offerId:       offerId.value,
        priceData:     priceData.value,
        flight:        props.flight,
        selectedBrand: currentBrand.value,
        form:          props.form,
    })
}

const loading          = ref(false)
const error            = ref(null)
const priceData        = ref(null)
const priceLogId       = ref(null)
const bookingAttemptId = ref(null)
const offerId          = ref(null)
const priceChanged     = ref(false)
const fareRulesLoading = ref(false)
const fareRulesError   = ref(null)
const fareRulesSegments = ref([])
const fareInfoTab = ref('breakdown')

// Prefer fare_pricing (new engine) → fall back to dynamic_pricing (legacy)
const dynamicPricing = computed(() => brandDynamicPricing(priceData.value))

const canOpenPayableBreakdown = computed(() => {
    const breakdown = dynamicPricing.value?.pricing_breakdown
    return Array.isArray(breakdown) && breakdown.length > 0
})

function openPayableBreakdown() {
    if (!canOpenPayableBreakdown.value) return
    payableBreakdownOpen.value = true
}

function closePayableBreakdown() {
    payableBreakdownOpen.value = false
}

watch(
    [() => props.visible, () => props.selectedBrand],
    async ([vis]) => {
        if (!vis) {
            await finishPriceAttempt()
            reset()
            return
        }
        currentBrand.value = props.selectedBrand ?? null
        if (props.cachedPriceData) {
            priceData.value = props.cachedPriceData
            // Booking route: no new price call — restore fare rules from store/DB
            hydrateFareRules()
        } else {
            fetchPrice()
        }
    }
)

function reset() {
    loading.value       = false
    error.value         = null
    priceData.value     = null
    priceLogId.value    = null
    bookingAttemptId.value = null
    offerId.value       = null
    priceChanged.value  = false
    isAutoRetryingBrand.value = false
    fareRulesLoading.value = false
    fareRulesError.value = null
    fareRulesSegments.value = []
    fareInfoTab.value = 'breakdown'
    payableExpanded.value = false
    payableBreakdownOpen.value = false
}

async function finishPriceAttempt() {
    const id = bookingAttemptId.value || searchStore.activeSearchAttemptId
    await completePriceAttempt(id)
    bookingAttemptId.value = null
}

async function closePanel() {
    workbenchError.value = null
    await finishPriceAttempt()
    emit('close')
}

async function fetchPrice() {
    loading.value = true
    error.value   = null

    try {
        const payload = {
            catalog_identifier:   props.catalogIdentifier,
            outbound_offering_id: props.flight?.outbound?._offering_id,
            outbound_product_ref: currentBrand.value?._productRef ?? props.flight?.outbound?._selected_productRef,
            outbound_brand_ref:   currentBrand.value?._brandRef ?? null,
            inbound_offering_id:  props.flight?.inbound?._offering_id  ?? null,
            inbound_product_ref:  (() => {
                const outCodes = currentBrand.value?._combinabilityCode ?? []
                const matched  = props.flight?.inbound?.brand_options?.find(b =>
                    (b._combinabilityCode ?? []).some(c => outCodes.includes(c))
                )
                return matched?._productRef ?? props.flight?.inbound?._selected_productRef ?? null
            })(),
            search_log_id:        props.searchLogId,
            booking_attempt_id:   searchStore.activeSearchAttemptId,
            selection_json:       buildSelectionJson({
                flight: props.flight,
                selectedBrand: currentBrand.value,
                form: props.form,
            }),
            form:                 props.form,
        }

        const res = await axiosInstance.post('v2/price', payload)

        priceData.value  = res.data?.price_data
        priceLogId.value = res.data?.price_log_id
        bookingAttemptId.value = res.data?.booking_attempt_id ?? null
        searchStore.activeSearchAttemptId = bookingAttemptId.value
        offerId.value    = res.data?.offer_identifier

        const confirmedPrice = priceData.value?.gross_payment ?? priceData.value?.total_price ?? 0
        const searchPrice    = currentBrand.value?.gross_payment ?? currentBrand.value?.price ?? 0
        priceChanged.value   = Math.abs(confirmedPrice - searchPrice) > 1

        fetchFareRules()

    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Price confirmation failed. Please try again.'
    } finally {
        loading.value = false
    }
}

function withRouteLabels(segments) {
    const outLabel = props.flight?.outbound
        ? `${props.flight.outbound.origin ?? ''}→${props.flight.outbound.destination ?? ''}`
        : ''
    const inLabel = props.flight?.inbound
        ? `${props.flight.inbound.origin ?? ''}→${props.flight.inbound.destination ?? ''}`
        : ''

    return (segments ?? []).map(seg => ({
        ...seg,
        displayLabel: seg.displayLabel
            || (seg.direction === 'inbound' ? inLabel : outLabel)
            || seg.flightRef
            || 'Segment',
    }))
}

function applyFareRulesSegments(segments) {
    const labeled = withRouteLabels(segments)
    fareRulesSegments.value = labeled
    bookingStore.setFareRulesSegments(labeled)
}

async function hydrateFareRules() {
    fareRulesError.value = null

    // 1) Memory / sessionStorage (same booking flow)
    if (bookingStore.fareRulesSegments?.length) {
        fareRulesSegments.value = withRouteLabels(bookingStore.fareRulesSegments)
        return
    }

    const attemptId = bookingStore.bookingAttemptId
    if (!attemptId) return

    // 2) DB booking_sessions fallback (refresh / reopen)
    fareRulesLoading.value = true
    try {
        const res = await axiosInstance.get('v2/fare-rules/saved', {
            params: { booking_attempt_id: attemptId },
        })
        const segments = res?.data?.data?.fare_rules?.segments
            ?? res?.data?.fare_rules?.segments
            ?? []
        applyFareRulesSegments(segments)
        if (!segments.length) {
            fareRulesError.value = null
        }
    } catch {
        fareRulesError.value = 'Fare rules unavailable right now.'
    } finally {
        fareRulesLoading.value = false
    }
}

function fetchFareRules() {
    if (!bookingAttemptId.value) return

    const attemptId = bookingAttemptId.value
    fareRulesLoading.value = true
    fareRulesError.value = null
    fareRulesSegments.value = []

    const requests = []

    // Outbound + inbound fire in parallel; merge for panel ledger UI
    requests.push(
        axiosInstance.get('v2/fare-rules', { params: {
            catalogProductOfferingsIdentifier: props.catalogIdentifier,
            catalogProductOfferingID:          props.flight?.outbound?._offering_id,
            productIDs:                        currentBrand.value?._productRef ?? props.flight?.outbound?._selected_productRef,
            fareRuleType:                      'Structured',
            direction:                         'outbound',
            booking_attempt_id:                attemptId,
        }}).then(res => ({
            direction: 'outbound',
            label: `${props.flight?.outbound?.origin ?? ''}→${props.flight?.outbound?.destination ?? ''}`,
            segments: res?.data?.fare_rules?.segments ?? [],
        }))
    )

    const inboundOfferingId = props.flight?.inbound?._offering_id
    if (inboundOfferingId) {
        const outCodes = currentBrand.value?._combinabilityCode ?? []
        const matchedInbound = props.flight?.inbound?.brand_options?.find(b =>
            (b._combinabilityCode ?? []).some(c => outCodes.includes(c))
        )
        const inboundProductRef = matchedInbound?._productRef ?? props.flight?.inbound?._selected_productRef
        if (inboundProductRef) {
            requests.push(
                axiosInstance.get('v2/fare-rules', { params: {
                    catalogProductOfferingsIdentifier: props.catalogIdentifier,
                    catalogProductOfferingID:          inboundOfferingId,
                    productIDs:                        inboundProductRef,
                    fareRuleType:                      'Structured',
                    direction:                         'inbound',
                    booking_attempt_id:                attemptId,
                }}).then(res => ({
                    direction: 'inbound',
                    label: `${props.flight?.inbound?.origin ?? ''}→${props.flight?.inbound?.destination ?? ''}`,
                    segments: res?.data?.fare_rules?.segments ?? [],
                }))
            )
        }
    }

    Promise.all(requests)
        .then(results => {
            const merged = results.flatMap(r =>
                (r.segments ?? []).map(seg => ({
                    ...seg,
                    direction: r.direction,
                    displayLabel: r.label,
                }))
            )
            applyFareRulesSegments(merged)
        })
        .catch(() => {
            fareRulesError.value = 'Fare rules unavailable right now.'
        })
        .finally(() => {
            fareRulesLoading.value = false
        })
}

function formatRuleTiming(timing) {
    if (!timing) return '—'
    return String(timing)
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/_/g, ' ')
}

function formatRuleAmount(amount) {
    if (!amount) return '—'
    const code = amount.code ?? ''
    const value = Number(amount.value ?? 0)
    return `${code} ${value.toLocaleString()}`.trim()
}

function paxTone(type) {
    if (type === 'Child') return 'child'
    if (type === 'Kids') return 'kids'
    if (String(type).startsWith('Infant')) return 'infant'
    return 'adult'
}

function paxIcon(type) {
    if (type === 'Child') return 'fa-solid fa-child'
    if (type === 'Kids') return 'fa-solid fa-child-reaching'
    if (String(type).startsWith('Infant')) return 'fa-solid fa-baby'
    return 'fa-solid fa-person'
}

const alternativeBrands = computed(() => {
    const list = props.flight?.outbound?.brand_options ?? []
    const currentProductRef = currentBrand.value?._productRef
    return list.filter(b => b?._productRef && b._productRef !== currentProductRef)
})

const shouldSuggestAlternateFlow = computed(() => {
    const msg = String(workbenchError.value ?? '').toLowerCase()
    return msg.includes('temporarily unavailable')
        || msg.includes('communication error')
        || msg.includes('failed to add offer to workbench')
        || msg.includes('retry')
})

async function autoRetryWithAnotherBrand() {
    if (isAutoRetryingBrand.value) return
    const nextBrand = alternativeBrands.value[0]
    if (!nextBrand) return

    isAutoRetryingBrand.value = true
    workbenchError.value = null

    try {
        currentBrand.value = nextBrand
        await fetchPrice()

        if (!error.value && priceLogId.value && offerId.value) {
            handleProceed()
        }
    } finally {
        isAutoRetryingBrand.value = false
    }
}

async function goBackToSearch() {
    await closePanel()
    router.push({ name: 'searchResult' })
}



function formatTime(date, time) {
    if (!time) return ''
    const ts = new Date(`${date}T${time}`)
    return ts.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })
}

function formatDeadline(iso) {
    if (!iso) return null
    // Pinned to agency time — the browser's own timezone must not change the deadline shown
    return formatTicketingDeadline(iso, 'MMM D, YYYY, hh:mm A') || null
}

function formatDuration(iso) {
    if (!iso) return ''
    const m = iso.match(/PT(?:(\d+)H)?(?:(\d+)M)?/)
    if (!m) return iso
    const h = parseInt(m[1] ?? 0)
    const min = String(parseInt(m[2] ?? 0)).padStart(2, '0')
    return `${h}h ${min}m`
}

function routePoint(product, side) {
    const dep = product?.flight?.departure ?? {}
    const arr = product?.flight?.arrival ?? {}
    const inbound = product?.direction === 'inbound'

    if (side === 'left') {
        return inbound ? arr : dep
    }

    return inbound ? dep : arr
}

const inclusionIcon = (inc) => {
    if (inc === 'Included')    return 'fa-solid fa-check'
    if (inc === 'Chargeable')  return 'fa-solid fa-dollar-sign'
    return 'fa-solid fa-xmark'
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
const classificationIcon = (cls) => CLASSIFICATION_ICON[normClass(cls)] ?? 'fa-solid fa-circle-question'
const classLabel = (cls) => CLASSIFICATION_LABEL[normClass(cls)] ?? cls

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
const classColorSlug = (cls) => CLASSIFICATION_COLOR_SLUG[normClass(cls)] ?? 'default'

const INCLUSION_ORDER = { Included: 0, Chargeable: 1, 'Not Offered': 2 }
function sortedBrandAttributes(brand) {
    const attrs = [...(brand?.attributes ?? []), ...(brand?.additional_attributes ?? [])]
    return attrs.sort((a, b) =>
        (INCLUSION_ORDER[a.inclusion] ?? 9) - (INCLUSION_ORDER[b.inclusion] ?? 9)
    )
}

const agencyTotalPayable = computed(() =>
    Number(dynamicPricing.value?.total_payable ?? priceData.value?.total_price ?? 0)
)

const footerGrossFare = computed(() =>
    Number(priceData.value?.gross_fare ?? priceData.value?.gross_payment ?? priceData.value?.total_price ?? 0)
)

const payableExpanded = ref(false)

const DEFAULT_AIRLINE_LOGO = '/uploads/airlines/default.svg'

// Header route + trip meta — match search-summary chip in screenshot
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
    if (!origin && !dest) return currentBrand.value?.label || 'Fare Confirmation'
    if (isRoundTrip.value && origin && dest) return `${origin} → ${dest} → ${origin}`
    if (origin && dest) return `${origin} → ${dest}`
    return [origin, dest].filter(Boolean).join(' → ') || 'Fare Confirmation'
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
        + Number(props.form?.INS ?? 0)
    const traveler = `${pax || 1} Traveler${(pax || 1) === 1 ? '' : 's'}`
    return [trip, datePart, traveler].filter(Boolean).join(' . ')
})

function onHeaderLogoError(e) {
    if (e?.target) e.target.src = DEFAULT_AIRLINE_LOGO
}
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition name="fp-fade">
            <div v-if="visible" class="fp-backdrop" @click="closePanel"></div>
        </Transition>

        <!-- Panel -->
        <Transition name="fp-slide">
            <div v-if="visible" class="fp-panel" :class="{ 'fp-panel--loading': loading }" role="dialog" aria-modal="true">

                <!-- Header: airline logo + route / trip meta -->
                <div class="fp-header">
                    <div class="fp-header-brand">
                        <img
                            :src="headerAirlineLogo"
                            :alt="headerAirlineName"
                            class="fp-header-logo"
                            @error="onHeaderLogoError"
                        />
                    </div>
                    <div class="fp-header-title">
                        <div class="fp-header-main">{{ headerRouteLabel }}</div>
                        <div class="fp-header-sub">{{ headerTripMeta }}</div>
                    </div>
                    <button class="fp-close-btn" @click="closePanel" title="Back">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="fp-body">

                    <!-- Loading: same wings-build animation as the search page -->
                    <template v-if="loading">
                        <div class="fp-loading-wrap">
                            <SearchWingsBuildLoader :size="220" />
                        </div>
                    </template>

                    <!-- Error -->
                    <template v-else-if="error">
                        <div class="fp-error-box">
                            <i class="fa-solid fa-triangle-exclamation fa-2x mb-2"></i>
                            <p>{{ error }}</p>
                            <button class="fp-retry-btn" @click="fetchPrice">
                                <i class="fa-solid fa-rotate-right me-1"></i> Retry
                            </button>
                        </div>
                    </template>

                    <!-- Content -->
                    <template v-else-if="priceData">

                        <!-- Price Status Banner -->
                        <div v-reveal :class="['fp-status-banner', priceChanged ? 'fp-status-banner--warn' : 'fp-status-banner--ok']">
                            <i :class="priceChanged ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check'"></i>
                            <span v-if="priceChanged">
                                Price updated by Travelport. Please review before proceeding.
                            </span>
                            <span v-else>Price confirmed — same as displayed fare.</span>
                        </div>

                        <!-- S1: Flight Segments -->
                        <div class="fp-group">
                        <div
                            v-for="(product, pi) in priceData.products"
                            :key="pi"
                            v-reveal
                            class="fp-section"
                            :class="product.direction === 'inbound' ? 'fp-section--inbound' : 'fp-section--outbound'"
                        >
                            <div class="fp-section-label">
                                <i
                                    class="fa-solid fa-plane me-2 fp-section-label__ico"
                                    :class="product.direction === 'inbound' ? 'fp-section-label__ico--inbound' : 'fp-section-label__ico--outbound'"
                                ></i>
                                {{ product.direction === 'inbound' ? 'Return' : 'Outbound' }} Flight
                            </div>
                            <div class="fp-segment-card">
                                <div class="fp-seg-route">
                                    <div class="fp-seg-point">
                                        <div class="fp-seg-code">{{ routePoint(product, 'left').location }}</div>
                                        <div class="fp-seg-time">{{ formatTime(routePoint(product, 'left').date, routePoint(product, 'left').time) }}</div>
                                        <div class="fp-seg-term" v-if="routePoint(product, 'left').terminal">T{{ routePoint(product, 'left').terminal }}</div>
                                    </div>
                                    <div class="fp-seg-mid">
                                        <div class="fp-seg-duration">{{ formatDuration(product.total_duration) }}</div>
                                        <div
                                            class="fp-seg-line"
                                            :class="product.direction === 'inbound' ? 'fp-seg-line--inbound' : ''"
                                        >
                                            <span class="fp-seg-dot fp-seg-dot--dep"></span>
                                            <span class="fp-seg-track">
                                                <i
                                                    class="fa-solid fa-plane fp-seg-plane"
                                                    :class="product.direction === 'inbound' ? 'fp-seg-plane--inbound' : 'fp-seg-plane--outbound'"
                                                ></i>
                                            </span>
                                            <span class="fp-seg-dot fp-seg-dot--arr"></span>
                                        </div>
                                        <div class="fp-seg-flight-no">
                                            {{ product.flight.flight_numbers || `${product.flight.carrier}${product.flight.number}` }}
                                            <span v-if="product.flight.stops" class="fp-seg-equip">
                                                · {{ product.flight.stops }} stop{{ product.flight.stops > 1 ? 's' : '' }}
                                            </span>
                                            <span v-else class="fp-seg-equip">· {{ product.flight.equipment }}</span>
                                        </div>
                                    </div>
                                    <div class="fp-seg-point fp-seg-point--right">
                                        <div class="fp-seg-code">{{ routePoint(product, 'right').location }}</div>
                                        <div class="fp-seg-time">{{ formatTime(routePoint(product, 'right').date, routePoint(product, 'right').time) }}</div>
                                        <div class="fp-seg-term" v-if="routePoint(product, 'right').terminal">T{{ routePoint(product, 'right').terminal }}</div>
                                    </div>
                                </div>
                                <div class="fp-seg-meta">
                                    <span class="fp-badge fp-badge--cabin">{{ product.cabin }}</span>
                                    <span class="fp-badge fp-badge--cos">Class {{ product.class_of_service }}</span>
                                    <span class="fp-badge fp-badge--fare">{{ product.fare_basis_code }}</span>
                                    <span class="fp-badge fp-badge--type">{{ product.fare_type }}</span>
                                </div>

                                <!-- Baggage per product -->
                                <div v-if="product.baggage?.length" class="fp-baggage-row">
                                    <div v-for="bag in product.baggage" :key="bag.type" class="fp-bag-chip">
                                        <i :class="bag.type === 'carry_on' ? 'bx bx-briefcase-alt-2' : 'bx bxs-briefcase'"></i>
                                        <span>{{ bag.label }}</span>
                                        <span v-if="bag.weight" class="fp-bag-weight">{{ bag.weight }}</span>
                                        <span v-if="!bag.included" class="fp-bag-fee">Chargeable</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <!-- S2: Fare Brand -->
                        <div v-if="priceData.brand" v-reveal class="fp-section fp-group">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-tags me-2"></i>Fare Brand
                                <span v-if="priceData.brand.tier" class="fp-brand-header-tier">| Tier {{ priceData.brand.tier }}</span>
                                <span class="fp-brand-header-name ms-auto">{{ priceData.brand.name }}</span>
                            </div>
                            <hr class="fp-section-divider">
                            <div class="fp-brand-card">
                                <div class="fp-attrs">
                                    <div
                                        v-for="(attr, aIdx) in sortedBrandAttributes(priceData.brand)"
                                        :key="aIdx"
                                        class="fp-attr-row"
                                    >
                                        <span class="fp-attr-part">
                                            <span
                                                class="fp-attr-cat"
                                                :class="'fp-attr-cat--' + classColorSlug(attr.classification)"
                                            >
                                                <i :class="classificationIcon(attr.classification)"></i>
                                            </span>
                                            <span class="fp-attr-text">{{ classLabel(attr.classification) }}</span>
                                        </span>
                                        <span class="fp-attr-part fp-attr-part--status">
                                            <span
                                                class="fp-attr-dot fp-attr-dot--outline"
                                                :class="{
                                                    'fp-attr-dot--ok':  attr.inclusion === 'Included',
                                                    'fp-attr-dot--fee': attr.inclusion === 'Chargeable',
                                                    'fp-attr-dot--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >
                                                <i :class="inclusionIcon(attr.inclusion)"></i>
                                            </span>
                                            <span
                                                class="fp-attr-text fp-attr-text--status"
                                                :class="{
                                                    'fp-attr-text--ok':  attr.inclusion === 'Included',
                                                    'fp-attr-text--fee': attr.inclusion === 'Chargeable',
                                                    'fp-attr-text--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >{{ attr.inclusion }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- S3: Fare Breakdown / Fare Rules — shadcn in-cell tab style -->
                        <div v-reveal class="fp-section fp-fare-tabs-section fp-group">
                            <div class="fp-fare-tabs-scroll">
                                <div class="fp-fare-tabs" role="tablist">
                                    <button
                                        type="button"
                                        role="tab"
                                        class="fp-fare-tab"
                                        :class="{ 'fp-fare-tab--active': fareInfoTab === 'breakdown' }"
                                        :aria-selected="fareInfoTab === 'breakdown'"
                                        @click="fareInfoTab = 'breakdown'"
                                    >
                                        <i class="fa-solid fa-receipt fp-fare-tab__ico" aria-hidden="true"></i>
                                        <span>Fare Breakdown</span>
                                    </button>
                                    <button
                                        type="button"
                                        role="tab"
                                        class="fp-fare-tab"
                                        :class="{ 'fp-fare-tab--active': fareInfoTab === 'rules' }"
                                        :aria-selected="fareInfoTab === 'rules'"
                                        @click="fareInfoTab = 'rules'"
                                    >
                                        <i class="fa-solid fa-file-contract fp-fare-tab__ico" aria-hidden="true"></i>
                                        <span>Fare Rules</span>
                                        <span
                                            v-if="fareRulesLoading"
                                            class="fp-fare-tab__pulse"
                                            aria-hidden="true"
                                        ></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Tab: Fare Breakdown -->
                            <div v-show="fareInfoTab === 'breakdown'" class="fp-fare-tab-panel" role="tabpanel">
                                <div
                                    v-for="(bd, bdIndex) in priceData.price_breakdown"
                                    :key="`${bd.passenger_type_code}-${bdIndex}`"
                                    class="fp-price-pax-block"
                                    :class="`fp-price-pax-block--${paxTone(bd.type)}`"
                                >
                                    <div class="fp-pax-header">
                                        <span class="fp-pax-ico" :class="`fp-pax-ico--${paxTone(bd.type)}`">
                                            <i :class="paxIcon(bd.type)"></i>
                                        </span>
                                        <span class="fp-pax-type">{{ bd.type }}</span>
                                        <span class="fp-pax-qty">× {{ bd.quantity }}</span>
                                        <span class="fp-pax-total ms-auto">
                                            {{ priceData.currency }} {{ (bd.quantity * bd.total_price).toLocaleString() }}
                                        </span>
                                    </div>

                                    <div class="fp-pax-rows">
                                        <div class="fp-pax-row fp-pax-row--base">
                                            <span class="fp-pax-row__label">
                                                <i class="fa-solid fa-ticket"></i> Base Fare
                                            </span>
                                            <span>{{ priceData.currency }} {{ bd.base_fare.toLocaleString() }}</span>
                                        </div>
                                        <div class="fp-pax-row fp-pax-row--tax">
                                            <span class="fp-pax-row__label">
                                                <i class="fa-solid fa-landmark"></i> Total Taxes
                                            </span>
                                            <span>{{ priceData.currency }} {{ bd.total_taxes.toLocaleString() }}</span>
                                        </div>
                                    </div>

                                    <details class="fp-tax-details" v-if="bd.taxes?.length">
                                        <summary class="fp-tax-summary">View tax breakdown ({{ bd.taxes.length }} items) <i class="fa-solid fa-chevron-down fp-tax-summary__chevron"></i></summary>
                                        <div class="fp-tax-table">
                                            <div v-for="tax in bd.taxes" :key="tax.code" class="fp-tax-row">
                                                <span class="fp-tax-code">{{ tax.code }}</span>
                                                <span class="fp-tax-desc">{{ tax.description || '—' }}</span>
                                                <span class="fp-tax-amt">{{ priceData.currency }} {{ tax.amount.toLocaleString() }}</span>
                                            </div>
                                        </div>
                                    </details>
                                </div>

                                <div class="fp-gross-total">
                                    <div class="fp-gross-row">
                                        <span><i class="fa-solid fa-ticket fp-gross-row__ico"></i>Base Fare</span>
                                        <span>{{ priceData.currency }} {{ priceData.base_fare.toLocaleString() }}</span>
                                    </div>
                                    <div class="fp-gross-row">
                                        <span><i class="fa-solid fa-landmark fp-gross-row__ico"></i>Total Taxes</span>
                                        <span>{{ priceData.currency }} {{ priceData.total_taxes.toLocaleString() }}</span>
                                    </div>
                                    <div class="fp-gross-row fp-gross-row--total">
                                        <span><i class="fa-solid fa-sack-dollar fp-gross-row__ico"></i>Gross Fare</span>
                                        <span>{{ priceData.currency }} {{ formatFareAmount(priceData.gross_fare ?? priceData.total_price) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Fare Rules -->
                            <div v-show="fareInfoTab === 'rules'" class="fp-fare-tab-panel fp-fare-rules-panel" role="tabpanel">
                                <div v-if="fareRulesLoading" class="fp-rules-state">
                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                    <span>Loading fare rules…</span>
                                </div>
                                <div v-else-if="fareRulesError" class="fp-rules-state fp-rules-state--error">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <span>{{ fareRulesError }}</span>
                                </div>
                                <div v-else-if="!fareRulesSegments.length" class="fp-rules-state">
                                    <i class="fa-regular fa-folder-open"></i>
                                    <span>No structured fare rules for this offer.</span>
                                </div>
                                <div v-else class="fp-rules-stack">
                                    <article
                                        v-for="(seg, sIdx) in fareRulesSegments"
                                        :key="`${seg.direction}-${seg.flightRef}-${sIdx}`"
                                        class="fp-rule-card"
                                        :class="seg.direction === 'inbound' ? 'fp-rule-card--in' : 'fp-rule-card--out'"
                                    >
                                        <header class="fp-rule-card__head">
                                            <div class="fp-rule-card__route">
                                                <i class="fa-solid fa-plane"></i>
                                                <span>{{ seg.displayLabel || seg.flightRef || 'Segment' }}</span>
                                            </div>
                                            <span class="fp-rule-dir">{{ seg.direction === 'inbound' ? 'Return' : 'Outbound' }}</span>
                                        </header>

                                        <div class="fp-rule-tables">
                                            <div class="fp-rule-block fp-rule-block--cancel">
                                                <div class="fp-rule-block__title">
                                                    <i class="fa-solid fa-ban"></i>
                                                    Cancellation
                                                    <span class="fp-rule-count">{{ seg.cancellation?.length || 0 }}</span>
                                                </div>
                                                <div v-if="!seg.cancellation?.length" class="fp-rule-empty">No data</div>
                                                <table v-else class="fp-rule-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Condition</th>
                                                            <th>Status</th>
                                                            <th>Charge</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="(c, ci) in seg.cancellation" :key="ci">
                                                            <td>{{ formatRuleTiming(c.timing) }}</td>
                                                            <td>
                                                                <span
                                                                    class="fp-rule-chip"
                                                                    :class="c.permitted ? 'fp-rule-chip--ok' : 'fp-rule-chip--no'"
                                                                >
                                                                    {{ c.permitted ? 'Permitted' : 'Not Permitted' }}
                                                                </span>
                                                            </td>
                                                            <td class="fp-rule-amt">{{ formatRuleAmount(c.amount) }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <p
                                                    v-if="seg.cancellation?.some(c => c.taxes_refundable === false)"
                                                    class="fp-rule-note"
                                                >
                                                    * Taxes non-refundable
                                                </p>
                                            </div>

                                            <div class="fp-rule-block fp-rule-block--change">
                                                <div class="fp-rule-block__title">
                                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                                    Changes
                                                    <span class="fp-rule-count">{{ seg.changes?.length || 0 }}</span>
                                                </div>
                                                <div v-if="!seg.changes?.length" class="fp-rule-empty">No data</div>
                                                <table v-else class="fp-rule-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Condition</th>
                                                            <th>Status</th>
                                                            <th>Charge</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="(c, ci) in seg.changes" :key="ci">
                                                            <td>{{ formatRuleTiming(c.timing) }}</td>
                                                            <td>
                                                                <span
                                                                    class="fp-rule-chip"
                                                                    :class="c.permitted ? 'fp-rule-chip--ok' : 'fp-rule-chip--no'"
                                                                >
                                                                    {{ c.permitted ? 'Permitted' : 'Not Permitted' }}
                                                                </span>
                                                            </td>
                                                            <td class="fp-rule-amt">{{ formatRuleAmount(c.amount) }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="fp-rule-meta">
                                            <div class="fp-rule-meta__item">
                                                <span class="fp-rule-meta__label">Min Stay</span>
                                                <span class="fp-rule-meta__val">{{ seg.min_stay ?? 'No restriction' }}</span>
                                            </div>
                                            <div class="fp-rule-meta__item">
                                                <span class="fp-rule-meta__label">Max Stay</span>
                                                <span class="fp-rule-meta__val">{{ seg.max_stay ?? 'No restriction' }}</span>
                                            </div>
                                            <div class="fp-rule-meta__item">
                                                <span class="fp-rule-meta__label">Advance</span>
                                                <span class="fp-rule-meta__val">
                                                    {{ seg.advance_booking?.book_by
                                                        || seg.advance_booking?.pay_after_booking
                                                        || seg.advance_booking?.pay_before_departure
                                                        || 'No restriction' }}
                                                </span>
                                            </div>
                                            <div class="fp-rule-meta__item">
                                                <span class="fp-rule-meta__label">Stopover</span>
                                                <span class="fp-rule-meta__val">
                                                    {{ seg.stopover == null ? '—' : (seg.stopover ? 'Allowed' : 'Not allowed') }}
                                                </span>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>

                        <!-- S4: Penalties + Fare Validity -->
                        <div class="fp-group">
                        <div v-if="priceData.penalties?.change || priceData.penalties?.cancel" v-reveal class="fp-section">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-scale-balanced me-2"></i>Penalties
                            </div>
                            <div class="fp-penalties">
                                <div v-if="priceData.penalties.change" class="fp-penalty-row fp-penalty--change">
                                    <span class="fp-tile-ico fp-tile-ico--change" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span>
                                    <div>
                                        <div class="fp-penalty-title">Change Fee</div>
                                        <div class="fp-penalty-meta">{{ priceData.penalties.change.applies_to?.replace('Per','Per ') }}</div>
                                    </div>
                                    <span class="fp-penalty-amount ms-auto">
                                        {{ priceData.penalties.change.currency }}
                                        {{ priceData.penalties.change.amount.toLocaleString() }}
                                    </span>
                                </div>
                                <div v-if="priceData.penalties.cancel" class="fp-penalty-row fp-penalty--cancel">
                                    <span class="fp-tile-ico fp-tile-ico--cancel" aria-hidden="true"><i class="fa-solid fa-ban"></i></span>
                                    <div>
                                        <div class="fp-penalty-title">Cancellation Fee</div>
                                        <div class="fp-penalty-meta">{{ priceData.penalties.cancel.applies_to?.replace('Per','Per ') }}</div>
                                    </div>
                                    <span class="fp-penalty-amount ms-auto">
                                        {{ priceData.penalties.cancel.currency }}
                                        {{ priceData.penalties.cancel.amount.toLocaleString() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Restrictions -->
                        <div v-if="priceData.restrictions?.length" v-reveal class="fp-section">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-circle-info me-2"></i>Fare Restrictions
                            </div>
                            <ul class="fp-restrictions">
                                <li v-for="r in priceData.restrictions" :key="r">{{ r }}</li>
                            </ul>
                        </div>

                        <!-- Deadlines -->
                        <div v-reveal class="fp-section">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-calendar-day me-2"></i>Fare Validity
                                <span v-if="priceData.validating_airline" class="fp-badge fp-badge--airline ms-auto">
                                    <i class="fa-solid fa-plane-departure"></i> {{ priceData.validating_airline }}
                                </span>
                            </div>
                            <div class="fp-deadlines">
                                <div v-if="priceData.payment_time_limit" class="fp-deadline-row fp-deadline--payment">
                                    <span class="fp-tile-ico fp-tile-ico--payment" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
                                    <div>
                                        <div class="fp-deadline-label">Payment Deadline</div>
                                        <div class="fp-deadline-val fw-bold">
                                            {{ formatDeadline(priceData.payment_time_limit) }}
                                        </div>
                                    </div>
                                </div>
                                <div v-if="priceData.expiry_date" class="fp-deadline-row fp-deadline--expiry">
                                    <span class="fp-tile-ico fp-tile-ico--expiry" aria-hidden="true"><i class="fa-regular fa-hourglass"></i></span>
                                    <div>
                                        <div class="fp-deadline-label">Fare Expires</div>
                                        <div class="fp-deadline-val fw-bold">
                                            {{ formatDeadline(priceData.expiry_date) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                    </template>
                </div>

                <!-- Footer CTA — tall sticky bar: payable + gross + book -->
                <div v-if="priceData && !loading" class="fp-footer">
                    <div class="fp-footer-prices">
                        <button
                            type="button"
                            class="fp-footer-price-row fp-footer-price-row--payable fp-footer-price-row--toggle"
                            :aria-expanded="payableExpanded"
                            aria-label="Toggle total payable"
                            @click="payableExpanded = !payableExpanded"
                        >
                            <div class="fp-footer-price-meta">
                                <span class="fp-footer-ico fp-footer-ico--payable" aria-hidden="true">
                                    <i class="fa-solid fa-receipt"></i>
                                </span>
                                <span class="fp-footer-price-label">Gross Fare</span>
                            </div>
                            <span class="fp-footer-currency">{{ priceData.currency }}</span>
                            <span class="fp-footer-amount">{{ formatFareAmount(footerGrossFare) }}</span>
                            <i
                                class="fa-solid fa-chevron-down fp-footer-row-chevron"
                                :class="{ 'fp-footer-row-chevron--open': payableExpanded }"
                                aria-hidden="true"
                            ></i>
                        </button>
                        <div class="fp-footer-payable-collapse" :class="{ 'fp-footer-payable-collapse--open': payableExpanded }">
                            <div class="fp-footer-payable-inner">
                                <button
                                    type="button"
                                    class="fp-footer-price-row fp-footer-price-row--gross fp-footer-price-row--as-btn"
                                    :class="{ 'fp-footer-price-row--payable-click': canOpenPayableBreakdown }"
                                    :title="canOpenPayableBreakdown ? 'View payable breakdown' : undefined"
                                    :disabled="!canOpenPayableBreakdown"
                                    @click="openPayableBreakdown"
                                >
                                    <div class="fp-footer-price-meta">
                                        <span class="fp-footer-ico fp-footer-ico--gross" aria-hidden="true">
                                            <i class="fa-solid fa-wallet"></i>
                                        </span>
                                        <span class="fp-footer-price-label">Total Payable</span>
                                    </div>
                                    <span class="fp-footer-currency">{{ priceData.currency }}</span>
                                    <span class="fp-footer-amount-gross">{{ formatFareAmount(agencyTotalPayable) }}</span>
                                    <i
                                        v-if="canOpenPayableBreakdown"
                                        class="fa-solid fa-circle-info fp-footer-payable-info"
                                        aria-hidden="true"
                                    ></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="fp-footer-right">
                        <div
                            v-if="workbenchError && shouldSuggestAlternateFlow"
                            class="fp-wb-actions"
                        >
                            <button
                                class="fp-alt-btn"
                                :disabled="isAutoRetryingBrand || isInitiating || alternativeBrands.length === 0"
                                @click="autoRetryWithAnotherBrand"
                            >
                                <i
                                    :class="isAutoRetryingBrand ? 'fa-solid fa-spinner fa-spin me-1' : 'fa-solid fa-shuffle me-1'"
                                ></i>
                                {{ alternativeBrands.length ? 'Try another brand automatically' : 'No alternate brand found' }}
                            </button>
                            <button
                                class="fp-search-btn"
                                :disabled="isInitiating || isAutoRetryingBrand"
                                @click="goBackToSearch"
                            >
                                <i class="fa-solid fa-arrow-left me-1"></i> Back to search
                            </button>
                        </div>
                        <button
                            v-if="showProceedButton"
                            class="fp-book-btn"
                            @click="handleProceed"
                            :disabled="isInitiating"
                        >
                            <template v-if="isInitiating">
                                <LoadingSpinner :inline="true" size="sm" class="text-white" />
                                <span class="fp-book-btn__text">Processing...</span>
                            </template>
                            <template v-else>
                                <i class="fa-solid fa-plane-departure fp-book-btn__lead"></i>
                                <span class="fp-book-btn__text">Proceed Booking</span>
                                <i class="fa-solid fa-arrow-right fp-book-btn__arrow"></i>
                            </template>
                        </button>
                    </div>
                </div>

            </div>
        </Transition>
    </Teleport>

    <AgencyPayableBreakdownModal
        :is-open="payableBreakdownOpen"
        :pricing="dynamicPricing"
        :currency="priceData?.currency ?? 'BDT'"
        :gross-payment="footerGrossFare"
        @close="closePayableBreakdown"
    />
</template>

<style scoped>
/* ── Transitions ─────────────────────────── */
.fp-fade-enter-active, .fp-fade-leave-active { transition: opacity 0.25s ease; }
.fp-fade-enter-from, .fp-fade-leave-to { opacity: 0; }

.fp-slide-enter-active, .fp-slide-leave-active { transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease; }
.fp-slide-enter-from, .fp-slide-leave-to { transform: translateX(100%); opacity: 0; }

/* ── Backdrop ────────────────────────────── */
.fp-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1040;
    backdrop-filter: blur(2px);
}

/* ── Panel shell ─────────────────────────── */
.fp-panel {
    position: fixed;
    top: 0; right: 0; bottom: 0;
    width: min(620px, 100vw);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    background: var(--bs-body-bg, #fff);
    box-shadow: -6px 0 40px rgba(0,0,0,0.18);
    overflow: hidden;
}

/* while pricing loads, let the dimmed search page behind show through instead of a flat page */
.fp-panel--loading {
    background: rgba(255, 255, 255, 0.435);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

html[data-bs-theme="dark"] .fp-panel--loading {
    background: rgba(15, 23, 42, 0.55);
}

/* ── Header ──────────────────────────────── */
.fp-header {
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
.fp-close-btn {
    background: rgba(255, 255, 255, 0.75);
    border: none;
    color: #1a9eb5;
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
}
.fp-close-btn:hover { background: rgba(255, 255, 255, 0.95); }
.fp-header-brand {
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
.fp-header-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.fp-header-title { flex: 1; min-width: 0; }
.fp-header-main {
    font-size: 16px;
    font-weight: 700;
    line-height: 1.25;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fp-header-sub {
    margin-top: 2px;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.3;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

html[data-bs-theme="dark"] .fp-header {
    background: linear-gradient(90deg, #1a2f35 0%, #1a2838 35%, #24204a 70%, #2a1f3c 100%);
    border-bottom-color: rgba(124, 58, 237, 0.22);
    color: #e2e8f0;
}
html[data-bs-theme="dark"] .fp-header-main { color: #f1f5f9; }
html[data-bs-theme="dark"] .fp-header-sub { color: #94a3b8; }
html[data-bs-theme="dark"] .fp-header-brand {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.12);
}
html[data-bs-theme="dark"] .fp-close-btn {
    background: rgba(255, 255, 255, 0.1);
    color: #7dd3fc;
}
html[data-bs-theme="dark"] .fp-close-btn:hover {
    background: rgba(255, 255, 255, 0.18);
}

/* ── Body ────────────────────────────────── */
.fp-body {
    position: relative;
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── Loading ─────────────────────────────── */
.fp-loading-wrap {
    position: absolute;
    inset: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Error ───────────────────────────────── */
.fp-error-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    padding: 40px 20px;
    color: var(--bs-danger, #dc3545);
    text-align: center;
}
.fp-retry-btn {
    background: var(--bs-danger, #dc3545);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 8px 20px;
    cursor: pointer;
    font-size: 13px;
    margin-top: 10px;
}

/* ── Scroll reveal (v-reveal directive) ──── */
.fp-reveal {
    opacity: 0;
    transform: translateY(16px);
}
.fp-reveal-in {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 0.5s cubic-bezier(0.16, 1, 0.3, 1), transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
@media (prefers-reduced-motion: reduce) {
    .fp-reveal, .fp-reveal-in { opacity: 1; transform: none; transition: none; }
}

/* ── Status banner ───────────────────────── */
.fp-status-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 14px;
}
.fp-status-banner--ok   { background: #d1fae5; color: #065f46; }
.fp-status-banner--warn { background: #fef3c7; color: #92400e; }
[data-bs-theme="dark"] .fp-status-banner--ok   { background: #064e3b; color: #6ee7b7; }
[data-bs-theme="dark"] .fp-status-banner--warn { background: #451a03; color: #fcd34d; }

/* ── Section ─────────────────────────────── */
.fp-section { margin-bottom: 14px; }

/* ── Section groups (S1 Flight, S2 Fare Brand, S3 Fare Breakdown/Rules,
   S4 Penalties+Validity) — a distinct card per group so the panel reads
   as clearly separated blocks instead of one continuous flow ── */
.fp-group {
    background: var(--bs-tertiary-bg, #f8fafc);
    border: 1px solid var(--bs-border-color, #e2e8f0);
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 28px;
}
.fp-group > .fp-section:last-child { margin-bottom: 0; }
html[data-bs-theme="dark"] .fp-group {
    background: rgba(255, 255, 255, 0.03);
    border-color: var(--bs-border-color, #334155);
}
.fp-section-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
}
.fp-section-divider {
    border: none;
    border-top: 1px solid var(--bs-border-color, #e2e8f0);
    margin: 0 0 12px;
    opacity: 0.7;
}
.fp-section--outbound .fp-section-label { color: #027de2; }
.fp-section--inbound .fp-section-label { color: #00ab55; }
.fp-section-label__ico--outbound { color: #027de2; }
.fp-section-label__ico--inbound {
    color: #00ab55;
    transform: rotate(180deg);
}

/* ── Segment card ────────────────────────── */
.fp-segment-card {
    background: var(--bs-tertiary-bg, #f8f9fa);
    border: 1px solid var(--bs-border-color, #e2e8f0);
    border-radius: 10px;
    padding: 14px;
}
.fp-section--outbound .fp-segment-card {
    background: #f5faff;
    border-color: #bfdbfe;
}
.fp-section--inbound .fp-segment-card {
    background: #f4fdf8;
    border-color: #bbf7d0;
}
.fp-seg-route {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.fp-seg-point { text-align: center; min-width: 52px; }
.fp-seg-point--right { text-align: center; }
.fp-seg-code  { font-size: 20px; font-weight: 900; line-height: 1; }
.fp-section--outbound .fp-seg-code { color: #027de2; }
.fp-section--inbound .fp-seg-code { color: #00ab55; }
.fp-seg-time  { font-size: 12px; font-weight: 600; color: var(--bs-body-color); }
.fp-seg-term  { font-size: 10px; color: var(--bs-secondary-color, #6b7280); }
.fp-seg-mid   { flex: 1; text-align: center; }
.fp-seg-duration { font-size: 11px; color: var(--bs-secondary-color, #6b7280); margin-bottom: 4px; }
.fp-seg-line  { display: flex; align-items: center; gap: 4px; }
.fp-seg-line--inbound { flex-direction: row-reverse; }
.fp-seg-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.fp-section--outbound .fp-seg-dot { background: #027de2; }
.fp-section--inbound .fp-seg-dot { background: #00ab55; }
.fp-section--outbound .fp-seg-dot--dep { animation: fp-dot-pulse-out 6s ease-in-out infinite; }
.fp-section--inbound .fp-seg-dot--dep { animation: fp-dot-pulse-in 6s ease-in-out infinite; }
.fp-seg-track {
    flex: 1;
    height: 2px;
    position: relative;
    overflow: visible;
}
.fp-section--outbound .fp-seg-track {
    background: linear-gradient(to right, #93c5fd 0%, #dbeafe 50%, #93c5fd 100%);
}
.fp-section--inbound .fp-seg-track {
    background: linear-gradient(to right, #86efac 0%, #d1fae5 50%, #86efac 100%);
}
.fp-seg-plane {
    font-size: 13px;
    position: absolute;
    top: 50%;
    left: 0;
    transform: translateY(-50%);
}
.fp-section--outbound .fp-seg-plane {
    color: #027de2;
    filter: drop-shadow(0 0 4px rgba(2, 125, 226, 0.55));
}
.fp-section--inbound .fp-seg-plane {
    color: #00ab55;
    filter: drop-shadow(0 0 4px rgba(0, 171, 85, 0.55));
}
.fp-seg-plane--outbound {
    animation: fp-plane-fwd 6s linear infinite;
}
.fp-seg-plane--inbound {
    animation: fp-plane-rev 6s linear infinite;
}
@keyframes fp-plane-fwd {
    0%   { left: 0%;   opacity: 0; }
    10%  { opacity: 1; }
    88%  { opacity: 1; }
    100% { left: calc(100% - 13px); opacity: 0; }
}
@keyframes fp-plane-rev {
    0%   { left: calc(100% - 13px); transform: translateY(-50%) rotate(180deg); opacity: 0; }
    10%  { opacity: 1; }
    88%  { opacity: 1; }
    100% { left: 0%; transform: translateY(-50%) rotate(180deg); opacity: 0; }
}
@keyframes fp-dot-pulse-out {
    0%, 100% { box-shadow: 0 0 0 0 rgba(2, 125, 226, 0.5); }
    50%       { box-shadow: 0 0 0 4px rgba(2, 125, 226, 0); }
}
@keyframes fp-dot-pulse-in {
    0%, 100% { box-shadow: 0 0 0 0 rgba(0, 171, 85, 0.5); }
    50%       { box-shadow: 0 0 0 4px rgba(0, 171, 85, 0); }
}
.fp-seg-flight-no { font-size: 11px; font-weight: 600; color: var(--bs-body-color); margin-top: 4px; }
.fp-seg-equip { font-weight: 400; color: var(--bs-secondary-color, #6b7280); }
.fp-seg-meta  { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px; }

/* ── Badges ──────────────────────────────── */
.fp-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    display: inline-block;
}
.fp-badge--cabin  { background: #e4e3f6; color: #7944eb; }
.fp-badge--cos    { background: #def1ec; color: #12ce69; }
.fp-badge--fare   { background: #fff3cd; color: #856404; }
.fp-badge--type   { background: #e0f2fe; color: #0369a1; }
.fp-badge--airline { background: rgba(8, 145, 178, 0.14); color: #0e7490; display: inline-flex; align-items: center; gap: 4px; font-size: 11px; }
[data-bs-theme="dark"] .fp-badge--fare  { background: #44370a; color: #fbbf24; }
[data-bs-theme="dark"] .fp-badge--cabin { background: #2d1e5a; color: #a78bfa; }
[data-bs-theme="dark"] .fp-badge--cos   { background: rgba(18, 206, 105, 0.12); color: #6ee7b7; }
[data-bs-theme="dark"] .fp-badge--type  { background: rgba(3, 105, 161, 0.2); color: #7dd3fc; }
[data-bs-theme="dark"] .fp-badge--airline { background: rgba(34, 211, 238, 0.2); color: #67e8f9; }

/* ── Segment card dark overrides ─────────── */
[data-bs-theme="dark"] .fp-section--outbound .fp-segment-card {
    background: rgba(2, 125, 226, 0.07);
    border-color: rgba(2, 125, 226, 0.25);
}
[data-bs-theme="dark"] .fp-section--inbound .fp-segment-card {
    background: rgba(0, 171, 85, 0.07);
    border-color: rgba(0, 171, 85, 0.25);
}
[data-bs-theme="dark"] .fp-section--outbound .fp-seg-track {
    background: linear-gradient(to right, rgba(147, 197, 253, 0.3) 0%, rgba(219, 234, 254, 0.15) 50%, rgba(147, 197, 253, 0.3) 100%);
}
[data-bs-theme="dark"] .fp-section--inbound .fp-seg-track {
    background: linear-gradient(to right, rgba(134, 239, 172, 0.3) 0%, rgba(209, 250, 229, 0.15) 50%, rgba(134, 239, 172, 0.3) 100%);
}

/* ── Baggage ─────────────────────────────── */
.fp-baggage-row  { display: flex; flex-wrap: wrap; gap: 6px; border-top: 1px solid var(--bs-border-color, #e2e8f0); padding-top: 8px; margin-top: 4px; }
.fp-bag-chip {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 500;
    background: var(--bs-body-bg, #fff);
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 20px;
    padding: 3px 10px;
    color: var(--bs-body-color);
}
.fp-bag-weight { color: #7944eb; font-weight: 700; }
.fp-bag-fee    { color: #e65100; font-size: 10px; }

/* ── Fare tabs — shadcn "tabs-in-cell" look ─ */
.fp-fare-tabs-section { padding-top: 2px; }
/* Compact button-group, hugs its own content and sits at the right —
   not a full-width bar; the selected tab gets its own background (no
   separate sliding indicator, since the two labels differ in width) */
.fp-fare-tabs-scroll {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 12px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.fp-fare-tabs-scroll::-webkit-scrollbar { height: 4px; }
.fp-fare-tabs-scroll::-webkit-scrollbar-thumb {
    background: var(--bs-border-color, #cbd5e1);
    border-radius: 999px;
}
.fp-fare-tabs {
    display: inline-flex;
    flex-shrink: 0;
    padding: 3px;
    gap: 2px;
    background: var(--bs-tertiary-bg, #f1f5f9);
    border-radius: 9px;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05);
}
.fp-fare-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px 11px;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: var(--bs-secondary-color, #64748b);
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.01em;
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}
.fp-fare-tab__ico {
    font-size: 11px;
    opacity: 0.6;
    transition: opacity 0.2s ease, color 0.2s ease;
}
.fp-fare-tab--active {
    background: var(--bs-body-bg, #fff);
    color: #7944eb;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(121, 68, 235, 0.12);
}
.fp-fare-tab--active .fp-fare-tab__ico {
    opacity: 1;
    color: #7944eb;
}
.fp-fare-tab:hover:not(.fp-fare-tab--active) {
    color: var(--bs-body-color, #1e293b);
}
.fp-fare-tab:focus-visible {
    outline: 2px solid #7944eb;
    outline-offset: 2px;
}
.fp-fare-tab__pulse {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #7944eb;
    animation: fp-pulse 1s ease-in-out infinite;
}
@keyframes fp-pulse {
    0%, 100% { opacity: 0.35; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1); }
}
html[data-bs-theme="dark"] .fp-fare-tabs {
    background: rgba(255, 255, 255, 0.04);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.3);
}
html[data-bs-theme="dark"] .fp-fare-tab {
    color: #94a3b8;
}
html[data-bs-theme="dark"] .fp-fare-tab:hover:not(.fp-fare-tab--active) {
    color: #e2e8f0;
}
html[data-bs-theme="dark"] .fp-fare-tab--active {
    background: rgba(255, 255, 255, 0.08);
    color: #c4b5fd;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(196, 181, 253, 0.18);
}
html[data-bs-theme="dark"] .fp-fare-tab--active .fp-fare-tab__ico {
    color: #c4b5fd;
}
.fp-fare-tab-panel { animation: fp-tab-in 0.2s ease; }
@keyframes fp-tab-in {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: none; }
}

/* ── Price breakdown ─────────────────────── */
.fp-price-pax-block {
    background: rgba(121, 68, 235, 0.08);
    border: 1px solid rgba(121, 68, 235, 0.2);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
}
html[data-bs-theme="dark"] .fp-price-pax-block {
    background: rgba(196, 181, 253, 0.12);
    border-color: rgba(196, 181, 253, 0.25);
}
.fp-pax-header {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; margin-bottom: 8px;
}
.fp-pax-ico {
    width: 26px; height: 26px; border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px; flex-shrink: 0;
}
.fp-pax-ico--adult { background: rgba(121, 68, 235, 0.14); color: #7944eb; }
.fp-pax-ico--child { background: rgba(5, 150, 105, 0.14); color: #059669; }
.fp-pax-ico--kids { background: rgba(37, 99, 235, 0.14); color: #2563eb; }
.fp-pax-ico--infant { background: rgba(217, 119, 6, 0.14); color: #d97706; }
.fp-pax-type  { color: var(--bs-body-color); }
.fp-pax-qty   { color: var(--bs-secondary-color, #6b7280); font-size: 12px; }
.fp-pax-total { font-weight: 700; color: #7944eb; }
.fp-pax-rows  { display: flex; flex-direction: column; gap: 4px; margin-bottom: 6px; }
.fp-pax-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 11px; color: var(--bs-secondary-color, #6b7280);
    padding: 5px 8px; border-radius: 6px;
}
.fp-pax-row--base { background: rgba(13, 148, 136, 0.08); color: #0f766e; }
.fp-pax-row--tax { background: rgba(234, 88, 12, 0.08); color: #c2410c; }
.fp-pax-row__label { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
html[data-bs-theme="dark"] .fp-pax-row--base { background: rgba(45, 212, 191, 0.12); color: #5eead4; }
html[data-bs-theme="dark"] .fp-pax-row--tax { background: rgba(251, 146, 60, 0.14); color: #fdba74; }
html[data-bs-theme="dark"] .fp-pax-ico--adult { background: rgba(121, 68, 235, 0.28); color: #c4b5fd; }
html[data-bs-theme="dark"] .fp-pax-ico--child { background: rgba(16, 185, 129, 0.22); color: #6ee7b7; }
html[data-bs-theme="dark"] .fp-pax-ico--kids { background: rgba(59, 130, 246, 0.24); color: #93c5fd; }
html[data-bs-theme="dark"] .fp-pax-ico--infant { background: rgba(245, 158, 11, 0.22); color: #fcd34d; }

/* ── Fare Rules dossier ──────────────────── */
.fp-rules-state {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    min-height: 88px; padding: 18px 12px;
    border-radius: 10px;
    border: 1px dashed var(--bs-border-color, #cbd5e1);
    color: var(--bs-secondary-color, #64748b);
    font-size: 12px; font-weight: 600;
}
.fp-rules-state--error { color: #dc3545; border-color: rgba(220, 53, 69, 0.35); }
.fp-rules-stack { display: flex; flex-direction: column; gap: 12px; }
.fp-rule-card {
    border-radius: 12px;
    border: 1px solid var(--bs-border-color, #e2e8f0);
    overflow: hidden;
    background: var(--bs-body-bg, #fff);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
}
.fp-rule-card--out { border-top: 3px solid #3b82f6; }
.fp-rule-card--in { border-top: 3px solid #10b981; }
.fp-rule-card__head {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 12px;
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.08), transparent 70%);
}
.fp-rule-card--in .fp-rule-card__head {
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.1), transparent 70%);
}
.fp-rule-card__route {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 800; color: var(--bs-body-color);
}
.fp-rule-dir {
    font-size: 10px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
    padding: 3px 8px; border-radius: 999px;
    background: rgba(59, 130, 246, 0.12); color: #2563eb;
}
.fp-rule-card--in .fp-rule-dir {
    background: rgba(16, 185, 129, 0.14); color: #059669;
}
.fp-rule-tables { display: flex; flex-direction: column; gap: 8px; padding: 0 10px 10px; }
.fp-rule-block {
    border-radius: 9px;
    border: 1px solid var(--bs-border-color, #e2e8f0);
    overflow: hidden;
}
.fp-rule-block--cancel { border-color: rgba(220, 53, 69, 0.28); }
.fp-rule-block--change { border-color: rgba(37, 99, 235, 0.28); }
.fp-rule-block__title {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    font-size: 11px; font-weight: 800; letter-spacing: 0.03em; text-transform: uppercase;
}
.fp-rule-block--cancel .fp-rule-block__title {
    background: rgba(220, 53, 69, 0.08); color: #b91c1c;
}
.fp-rule-block--change .fp-rule-block__title {
    background: rgba(37, 99, 235, 0.08); color: #1d4ed8;
}
.fp-rule-count {
    margin-left: auto;
    min-width: 20px; height: 20px; padding: 0 6px;
    border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; background: rgba(15, 23, 42, 0.08); color: inherit;
}
.fp-rule-empty {
    padding: 10px; font-size: 11px; color: var(--bs-secondary-color, #94a3b8);
}
.fp-rule-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}
.fp-rule-table th,
.fp-rule-table td {
    padding: 7px 10px;
    border-top: 1px solid var(--bs-border-color, #eef2f7);
    vertical-align: middle;
}
.fp-rule-table th {
    font-size: 10px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
    color: var(--bs-secondary-color, #64748b);
    background: var(--bs-tertiary-bg, #f8fafc);
}
.fp-rule-table th:last-child,
.fp-rule-table td:last-child { text-align: right; }
.fp-rule-table th:nth-child(2),
.fp-rule-table td:nth-child(2) { text-align: center; }
.fp-rule-chip {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 2px 8px; border-radius: 999px;
    font-size: 10px; font-weight: 700; white-space: nowrap;
}
.fp-rule-chip--ok { background: rgba(16, 185, 129, 0.14); color: #047857; }
.fp-rule-chip--no { background: rgba(220, 53, 69, 0.12); color: #b91c1c; }
.fp-rule-amt { font-weight: 700; font-variant-numeric: tabular-nums; }
.fp-rule-note {
    margin: 0; padding: 6px 10px;
    font-size: 10px; color: #b91c1c;
    background: rgba(220, 53, 69, 0.06);
}
.fp-rule-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    padding: 0 10px 10px;
}
.fp-rule-meta__item {
    border-radius: 8px;
    border: 1px solid var(--bs-border-color, #e2e8f0);
    background: var(--bs-tertiary-bg, #f8fafc);
    padding: 8px;
    text-align: center;
}
.fp-rule-meta__label {
    display: block;
    font-size: 9px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--bs-secondary-color, #64748b); margin-bottom: 3px;
}
.fp-rule-meta__val {
    display: block;
    font-size: 11px; font-weight: 700; color: var(--bs-body-color);
    line-height: 1.3;
}
html[data-bs-theme="dark"] .fp-rule-card {
    background: rgba(15, 23, 42, 0.35);
    box-shadow: none;
}
html[data-bs-theme="dark"] .fp-rule-block--cancel .fp-rule-block__title { color: #fca5a5; }
html[data-bs-theme="dark"] .fp-rule-block--change .fp-rule-block__title { color: #93c5fd; }
html[data-bs-theme="dark"] .fp-rule-chip--ok { background: rgba(16, 185, 129, 0.22); color: #6ee7b7; }
html[data-bs-theme="dark"] .fp-rule-chip--no { background: rgba(248, 113, 113, 0.2); color: #fca5a5; }
html[data-bs-theme="dark"] .fp-rule-dir { color: #93c5fd; }
html[data-bs-theme="dark"] .fp-rule-card--in .fp-rule-dir { color: #6ee7b7; }

/* Tax details */
.fp-tax-details { margin-top: 6px; }
.fp-tax-summary {
    font-size: 11px; font-weight: 600; color: #7944eb; cursor: pointer;
    list-style: none; user-select: none;
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 4px; margin: -2px -4px; border-radius: 4px;
    transition: background 0.15s ease;
}
.fp-tax-summary::-webkit-details-marker { display: none; }
.fp-tax-summary__chevron { font-size: 9px; transition: transform 0.15s ease; }
details[open] > .fp-tax-summary .fp-tax-summary__chevron { transform: rotate(180deg); }
.fp-tax-summary:hover { background: rgba(121, 68, 235, 0.1); }
.fp-tax-summary:focus-visible { outline: 2px solid #7944eb; outline-offset: 2px; }
html[data-bs-theme="dark"] .fp-tax-summary:hover { background: rgba(196, 181, 253, 0.14); }
.fp-tax-table  { margin-top: 6px; display: flex; flex-direction: column; gap: 2px; }
.fp-tax-row    { display: flex; align-items: center; gap: 6px; font-size: 10px; }
.fp-tax-code   { font-weight: 700; color: #7944eb; width: 28px; flex-shrink: 0; }
.fp-tax-desc   { flex: 1; color: var(--bs-secondary-color, #6b7280); }
.fp-tax-amt    { font-weight: 600; color: var(--bs-body-color); flex-shrink: 0; }

.fp-fare-calc {
    margin-top: 6px;
    font-size: 9px;
    color: var(--bs-secondary-color, #94a3b8);
    word-break: break-all;
    line-height: 1.4;
}
.fp-fare-calc-label { font-weight: 700; color: #7944eb; }
.fp-filed-amt       { color: #0891b2; }

/* Gross total */
.fp-gross-total {
    background: linear-gradient(135deg, #7944eb14, #4a6ef514);
    border: 1.5px solid #7944eb33;
    border-radius: 12px;
    padding: 12px 16px;
    margin-top: 4px;
    box-shadow: 0 2px 8px rgba(121, 68, 235, 0.08);
}
html[data-bs-theme="dark"] .fp-gross-total { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); }
.fp-gross-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 4px 0; color: var(--bs-body-color); }
.fp-gross-row__ico { width: 16px; margin-right: 8px; font-size: 10px; color: var(--bs-secondary-color, #7b879f); text-align: center; }
.fp-gross-row--total .fp-gross-row__ico { color: #7944eb; font-size: 12px; }
.fp-gross-row--total {
    border-top: 1.5px solid #7944eb44;
    margin-top: 4px;
    padding-top: 8px;
    font-size: 15px;
    font-weight: 700;
    color: #7944eb;
}

.fp-gross-row--subtotal {
    font-weight: 600;
}
.fp-gross-row--deduction span:last-child {
    color: #c62828;
}
.fp-gross-row--customer {
    margin-top: 8px;
    font-weight: 700;
}
.fp-gross-row--payable {
    font-weight: 800;
    color: #1565c0;
    border-top: 1px dashed var(--bs-border-color, #cbd5e1);
    margin-top: 6px;
    padding-top: 8px;
    font-size: 14px;
}
.fp-rule-ref {
    margin-top: 8px;
    font-size: 11px;
    color: var(--bs-secondary-color, #64748b);
}
html[data-bs-theme="dark"] .fp-gross-row--payable {
    color: #64b5f6;
}
html[data-bs-theme="dark"] .fp-gross-row--deduction span:last-child {
    color: #ef9a9a;
}

/* ── Brand card ──────────────────────────── */
.fp-brand-card {
    background: var(--bs-tertiary-bg, #f8f9fa);
    border-radius: 10px;
    padding: 2px;
}
/* Brand name + tier moved into the section header — normal case, not the
   label's uppercase/letter-spaced treatment */
.fp-brand-header-tier {
    margin-left: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--bs-secondary-color, #7b879f);
}
.fp-brand-header-name {
    font-size: 13px;
    font-weight: 700;
    text-transform: none;
    letter-spacing: normal;
    color: var(--bs-body-color, #1a2436);
}
.fp-attrs { display: flex; flex-direction: column; }
.fp-attr-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 5px 0;
}
.fp-attr-part {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    line-height: 1;
}
.fp-attr-part--status { flex-shrink: 0; }
.fp-attr-cat {
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    flex-shrink: 0;
    line-height: 1;
    color: var(--bs-secondary-color, #8b97ad);
}
/* One color per feature type, independent of inclusion status */
.fp-attr-cat--refund      { color: #7c3aed; }
.fp-attr-cat--rebooking   { color: #0891b2; }
.fp-attr-cat--checked-bag { color: #92400e; }
.fp-attr-cat--carry-on    { color: #2563eb; }
.fp-attr-cat--wifi        { color: #0d9488; }
.fp-attr-cat--meals       { color: #ea580c; }
.fp-attr-cat--seat        { color: #16a34a; }
.fp-attr-cat--mileage     { color: #ca8a04; }
.fp-attr-cat--upgrade     { color: #db2777; }
.fp-attr-cat--lounge      { color: #7e22ce; }
.fp-attr-cat--premium-seat { color: #4f46e5; }
.fp-attr-cat--entertainment { color: #e11d48; }
.fp-attr-cat--priority    { color: #65a30d; }
.fp-attr-cat--priority-boarding { color: #a21caf; }
.fp-attr-cat--priority-baggage  { color: #b45309; }
.fp-attr-cat--default     { color: #64748b; }
html[data-bs-theme="dark"] .fp-attr-cat--refund      { color: #c4b5fd; }
html[data-bs-theme="dark"] .fp-attr-cat--rebooking   { color: #67e8f9; }
html[data-bs-theme="dark"] .fp-attr-cat--checked-bag { color: #fbbf24; }
html[data-bs-theme="dark"] .fp-attr-cat--carry-on    { color: #93c5fd; }
html[data-bs-theme="dark"] .fp-attr-cat--wifi        { color: #5eead4; }
html[data-bs-theme="dark"] .fp-attr-cat--meals       { color: #fdba74; }
html[data-bs-theme="dark"] .fp-attr-cat--seat        { color: #86efac; }
html[data-bs-theme="dark"] .fp-attr-cat--mileage     { color: #fde047; }
html[data-bs-theme="dark"] .fp-attr-cat--upgrade     { color: #f9a8d4; }
html[data-bs-theme="dark"] .fp-attr-cat--lounge      { color: #d8b4fe; }
html[data-bs-theme="dark"] .fp-attr-cat--premium-seat { color: #a5b4fc; }
html[data-bs-theme="dark"] .fp-attr-cat--entertainment { color: #fda4af; }
html[data-bs-theme="dark"] .fp-attr-cat--priority    { color: #bef264; }
html[data-bs-theme="dark"] .fp-attr-cat--priority-boarding { color: #e879f9; }
html[data-bs-theme="dark"] .fp-attr-cat--priority-baggage  { color: #fcd34d; }
html[data-bs-theme="dark"] .fp-attr-cat--default     { color: #94a3b8; }
.fp-attr-dot {
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
.fp-attr-dot i {
    display: block;
    line-height: 1;
    width: 1em;
    text-align: center;
}
.fp-attr-dot--ok  { background: #e6f7f4; color: #0d9b6e; }
.fp-attr-dot--fee { background: #fff5e6; color: #d97706; }
.fp-attr-dot--no  { background: #e8eaef; color: #9aa3b5; }
.fp-attr-dot--outline {
    background: transparent;
    border: 1.5px solid currentColor;
}
.fp-attr-dot--outline.fp-attr-dot--ok  { color: #0d9b6e; }
.fp-attr-dot--outline.fp-attr-dot--fee { color: #d97706; border-color: #d97706; }
.fp-attr-dot--outline.fp-attr-dot--no  { color: #9aa3b5; border-color: #c5cad6; }
html[data-bs-theme="dark"] .fp-attr-dot--ok  { background: #064e3b; color: #6ee7b7; }
html[data-bs-theme="dark"] .fp-attr-dot--fee { background: #451a03; color: #fbbf24; }
html[data-bs-theme="dark"] .fp-attr-dot--no  { background: #374151; color: #9ca3af; }
html[data-bs-theme="dark"] .fp-attr-dot--outline.fp-attr-dot--ok  { color: #6ee7b7; background: transparent; }
html[data-bs-theme="dark"] .fp-attr-dot--outline.fp-attr-dot--fee { color: #fbbf24; border-color: #fbbf24; background: transparent; }
html[data-bs-theme="dark"] .fp-attr-dot--outline.fp-attr-dot--no  { color: #6b7280; border-color: #6b7280; background: transparent; }
.fp-attr-text {
    font-size: 11px;
    font-weight: 600;
    color: var(--bs-body-color, #1a2436);
    line-height: 16px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fp-attr-text--status { font-weight: 700; }
.fp-attr-text--ok  { color: #0d9b6e; }
.fp-attr-text--fee { color: #d97706; }
.fp-attr-text--no  { color: #9aa3b5; }
html[data-bs-theme="dark"] .fp-attr-text--ok  { color: #6ee7b7; }
html[data-bs-theme="dark"] .fp-attr-text--fee { color: #fbbf24; }
html[data-bs-theme="dark"] .fp-attr-text--no  { color: #6b7280; }

/* ── Icon badge (shared by penalty & deadline tiles) ── */
.fp-tile-ico {
    width: 26px; height: 26px; border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; flex-shrink: 0;
}
.fp-tile-ico--change  { background: rgba(37, 99, 235, 0.14); color: #1e40af; }
.fp-tile-ico--cancel  { background: rgba(220, 38, 38, 0.14); color: #991b1b; }
.fp-tile-ico--payment { background: rgba(220, 38, 38, 0.14); color: #b91c1c; }
.fp-tile-ico--expiry  { background: rgba(217, 119, 6, 0.16); color: #b45309; }
html[data-bs-theme="dark"] .fp-tile-ico--change  { background: rgba(96, 165, 250, 0.2); color: #93c5fd; }
html[data-bs-theme="dark"] .fp-tile-ico--cancel  { background: rgba(248, 113, 113, 0.2); color: #fca5a5; }
html[data-bs-theme="dark"] .fp-tile-ico--payment { background: rgba(248, 113, 113, 0.2); color: #fca5a5; }
html[data-bs-theme="dark"] .fp-tile-ico--expiry  { background: rgba(251, 191, 36, 0.2); color: #fcd34d; }

/* ── Penalties ───────────────────────────── */
.fp-penalties {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
}
.fp-penalty-row {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border-radius: 10px;
    font-size: 12px; min-width: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.fp-penalty-row:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}
.fp-penalty-row > div { min-width: 0; }
.fp-penalty--change { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
.fp-penalty--cancel { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
[data-bs-theme="dark"] .fp-penalty--change { background: #1e3a5f; border-color: #3b82f6; color: #93c5fd; }
[data-bs-theme="dark"] .fp-penalty--cancel { background: #450a0a; border-color: #ef4444; color: #fca5a5; }
.fp-penalty-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fp-penalty-meta  { font-size: 10px; opacity: 0.75; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fp-penalty-amount { font-size: 13px; font-weight: 700; white-space: nowrap; }

/* ── Restrictions ────────────────────────── */
.fp-restrictions {
    margin: 0; padding: 0 0 0 16px;
    font-size: 12px;
    color: var(--bs-secondary-color, #6b7280);
    display: flex; flex-direction: column; gap: 3px;
}

/* ── Deadlines ───────────────────────────── */
.fp-deadlines {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 8px;
}
.fp-deadline-row {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px;
    border-radius: 10px;
    font-size: 12px;
    min-width: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.fp-deadline-row:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}
.fp-deadline-row > div { min-width: 0; }
.fp-deadline-label { font-size: 10px; color: var(--bs-secondary-color, #6b7280); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fp-deadline-val   { font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.fp-deadline--payment { background: #fef2f2; border: 1px solid #fecaca; }
.fp-deadline--payment .fp-deadline-val { color: #b91c1c; }
.fp-deadline--expiry { background: #fffbeb; border: 1px solid #fde68a; }
.fp-deadline--expiry .fp-deadline-val { color: #b45309; }

html[data-bs-theme="dark"] .fp-deadline--payment { background: #450a0a; border-color: #ef4444; }
html[data-bs-theme="dark"] .fp-deadline--payment .fp-deadline-val { color: #fca5a5; }
html[data-bs-theme="dark"] .fp-deadline--expiry { background: #451a03; border-color: #f59e0b; }
html[data-bs-theme="dark"] .fp-deadline--expiry .fp-deadline-val { color: #fcd34d; }
html[data-bs-theme="dark"] .fp-penalty-row,
html[data-bs-theme="dark"] .fp-deadline-row { box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3); }
html[data-bs-theme="dark"] .fp-penalty-row:hover,
html[data-bs-theme="dark"] .fp-deadline-row:hover { box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4); }
@media (prefers-reduced-motion: reduce) {
    .fp-penalty-row, .fp-deadline-row { transition: none; }
    .fp-penalty-row:hover, .fp-deadline-row:hover { transform: none; }
}

/* ── Footer ──────────────────────────────── */
.fp-footer {
    flex-shrink: 0;
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 0;
    min-height: 88px;
    padding: 0;
    border-top: 1px solid var(--bs-border-color, #e2e8f0);
    background: linear-gradient(180deg, rgba(121, 68, 235, 0.04) 0%, var(--bs-body-bg, #fff) 42%);
    box-shadow: 0 -6px 18px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
html[data-bs-theme="dark"] .fp-footer {
    background: linear-gradient(180deg, rgba(121, 68, 235, 0.12) 0%, var(--bs-body-bg, #1a1d24) 42%);
    box-shadow: 0 -6px 18px rgba(0, 0, 0, 0.2);
}
.fp-footer-prices {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    min-width: 0;
    flex: 1;
    padding: 14px 16px;
}
/* Single-level grid, trailing columns fixed-width — meta's 1fr absorbs any
   outer width variance (button padding etc.) so currency/amount/chevron
   pin to identical x on every row regardless of label length or font-size */
.fp-footer-price-row {
    display: grid;
    grid-template-columns: 1fr 28px 78px 16px;
    align-items: center;
    column-gap: 6px;
}
.fp-footer-price-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.fp-footer-ico {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 12px;
}
.fp-footer-ico--payable {
    background: rgba(121, 68, 235, 0.14);
    color: #7944eb;
}
.fp-footer-ico--gross {
    background: rgba(100, 116, 139, 0.12);
    color: #64748b;
}
html[data-bs-theme="dark"] .fp-footer-ico--payable {
    background: rgba(121, 68, 235, 0.28);
    color: #c4b5fd;
}
html[data-bs-theme="dark"] .fp-footer-ico--gross {
    background: rgba(148, 163, 184, 0.16);
    color: #94a3b8;
}
.fp-footer-price-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--bs-secondary-color, #6b7280);
    white-space: nowrap;
}
.fp-footer-currency {
    font-size: 11px;
    color: var(--bs-secondary-color, #6b7280);
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}
.fp-footer-amount {
    font-size: 22px;
    font-weight: 800;
    color: #7944eb;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
    text-align: right;
    white-space: nowrap;
}
.fp-footer-amount-gross {
    font-size: 15px;
    font-weight: 700;
    color: var(--bs-body-color, #1a2436);
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
    text-align: right;
    white-space: nowrap;
}
.fp-footer-price-row--payable .fp-footer-price-label { color: #7944eb; }
html[data-bs-theme="dark"] .fp-footer-amount { color: #c4b5fd; }
html[data-bs-theme="dark"] .fp-footer-price-row--payable .fp-footer-price-label { color: #c4b5fd; }

/* Gross Fare row doubles as the disclosure trigger for Total Payable */
/* Zero padding/margin/border (box-sizing safe) so this <button>'s grid
   track math is pixel-identical to the plain <div> row below it — the
   hover highlight is drawn on a ::before overlay instead of real padding */
.fp-footer-price-row--toggle {
    position: relative;
    width: 100%;
    box-sizing: border-box;
    border: none;
    margin: 0;
    padding: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
    font-family: inherit;
}
.fp-footer-price-row--toggle::before {
    content: '';
    position: absolute;
    inset: -4px -6px;
    border-radius: 8px;
    background: transparent;
    transition: background 0.15s ease;
    pointer-events: none;
}
.fp-footer-price-row--toggle:hover::before { background: rgba(121, 68, 235, 0.06); }
.fp-footer-price-row--toggle:focus-visible { outline: 2px solid #7944eb; outline-offset: 2px; }
.fp-footer-row-chevron {
    justify-self: center;
    font-size: 11px;
    color: #7944eb;
    opacity: 0.55;
    transition: transform 0.25s ease, opacity 0.2s ease;
    animation: fp-chevron-pulse 1.8s ease-in-out infinite;
}
.fp-footer-row-chevron--open {
    transform: rotate(180deg);
    opacity: 0.9;
    animation: none;
}
@keyframes fp-chevron-pulse {
    0%, 100% { opacity: 0.4; transform: translateY(0); }
    50% { opacity: 1; transform: translateY(2px); }
}
html[data-bs-theme="dark"] .fp-footer-price-row--toggle:hover::before { background: rgba(196, 181, 253, 0.1); }
html[data-bs-theme="dark"] .fp-footer-row-chevron { color: #c4b5fd; }

/* CSS-grid collapse — no JS height measurement needed */
.fp-footer-payable-collapse {
    display: grid;
    grid-template-rows: 0fr;
    width: 100%;
    transition: grid-template-rows 0.3s ease;
}
.fp-footer-payable-collapse--open { grid-template-rows: 1fr; }
.fp-footer-payable-inner { width: 100%; overflow: hidden; min-height: 0; }

@media (prefers-reduced-motion: reduce) {
    .fp-footer-row-chevron { animation: none; }
    .fp-footer-payable-collapse { transition: none; }
}
html[data-bs-theme="dark"] .fp-footer-amount-gross { color: var(--bs-body-color, #dee2e6); }

/* Total Payable → AgencyPayableBreakdownModal (same pattern as branded blue mini) */
.fp-footer-price-row--as-btn {
    width: 100%;
    box-sizing: border-box;
    border: none;
    margin: 0;
    padding: 0;
    background: transparent;
    text-align: left;
    font-family: inherit;
    color: inherit;
    cursor: default;
}
.fp-footer-price-row--payable-click {
    cursor: pointer;
    border-radius: 8px;
}
.fp-footer-price-row--payable-click:hover {
    background: rgba(21, 101, 192, 0.06);
}
.fp-footer-price-row--payable-click:focus-visible {
    outline: 2px solid #1565c0;
    outline-offset: 2px;
}
.fp-footer-payable-info {
    justify-self: center;
    font-size: 11px;
    color: #1565c0;
    opacity: 0.8;
}
.fp-footer-price-row--payable-click .fp-footer-amount-gross {
    color: #1565c0;
}
html[data-bs-theme="dark"] .fp-footer-price-row--payable-click:hover {
    background: rgba(147, 197, 253, 0.1);
}
html[data-bs-theme="dark"] .fp-footer-payable-info,
html[data-bs-theme="dark"] .fp-footer-price-row--payable-click .fp-footer-amount-gross {
    color: #93c5fd;
}

/* Full-height narrow CTA — flush right edge (red-box layout) */
.fp-book-btn {
    background: linear-gradient(180deg, #7944eb 0%, #5b6ff0 55%, #4a6ef5 100%);
    color: #fff !important;
    text-decoration: none;
    border: none;
    border-radius: 0;
    width: 168px;
    min-width: 168px;
    max-width: 168px;
    align-self: stretch;
    min-height: 100%;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.01em;
    line-height: 1.25;
    cursor: pointer;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: filter 0.15s, opacity 0.15s;
    box-shadow: none;
}
.fp-book-btn__text {
    text-align: center;
    white-space: nowrap;
}
.fp-book-btn__lead {
    font-size: 15px;
    opacity: 0.95;
}
.fp-book-btn__arrow {
    font-size: 12px;
    transition: transform 0.15s ease;
}
.fp-book-btn:hover:not(:disabled) {
    filter: brightness(1.06);
    opacity: 1;
    transform: none;
    box-shadow: none;
}
.fp-book-btn:hover:not(:disabled) .fp-book-btn__arrow {
    transform: translateX(2px);
}
.fp-book-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    filter: none;
}

/* ── Footer right ────────────────────────── */
.fp-footer-right {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    justify-content: flex-end;
    gap: 0;
    flex-shrink: 0;
    position: relative;
}
.fp-wb-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    padding: 8px 10px;
    justify-content: center;
}
.fp-alt-btn,
.fp-search-btn {
    border: none;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.fp-alt-btn {
    background: rgba(220, 53, 69, 0.12);
    color: #dc3545;
}
html[data-bs-theme="dark"] .fp-alt-btn {
    background: rgba(220, 53, 69, 0.22);
    color: #f87171;
}
.fp-search-btn {
    background: rgba(71, 85, 105, 0.14);
    color: #334155;
}
.fp-alt-btn:disabled,
.fp-search-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* ── Scrollbar ───────────────────────────── */
.fp-body::-webkit-scrollbar { width: 5px; }
.fp-body::-webkit-scrollbar-track { background: transparent; }
.fp-body::-webkit-scrollbar-thumb { background: #c7d7f5; border-radius: 10px; }
[data-bs-theme="dark"] .fp-body::-webkit-scrollbar-thumb { background: #374151; }

/* ── Mobile ──────────────────────────────── */
@media (max-width: 576px) {
    .fp-panel { width: 100vw; }
    .fp-footer {
        flex-direction: column;
        align-items: stretch;
        min-height: 0;
        gap: 0;
    }
    .fp-footer-prices { padding: 14px 16px 10px; }
    .fp-footer-right {
        flex-direction: column;
        align-items: stretch;
    }
    .fp-wb-actions {
        align-items: stretch;
        padding: 0 16px 10px;
    }
    .fp-book-btn {
        width: 100%;
        min-width: 0;
        max-width: none;
        min-height: 52px;
        flex-direction: row;
        gap: 10px;
        padding: 12px 16px;
        font-size: 13px;
        border-radius: 0;
    }
    .fp-book-btn__text {
        max-width: none;
        /* white-space: nowrap; */
    }
}
</style>
