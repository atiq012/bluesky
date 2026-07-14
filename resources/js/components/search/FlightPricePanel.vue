<script setup>
import { ref, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axiosInstance from '../../axiosInstance'
import { useSearchStore } from '../../stores/searchStore'
import { useBookingStore } from '../../stores/bookingStore'
import { useTpV2Workbench } from '../../composables/useTpV2Workbench'
import { buildSelectionJson } from '../../utils/bookingSelectionJson'
import { completePriceAttempt } from '../../utils/bookingAttemptSession'
import { formatFareAmount } from '../../utils/dynamicRulePricingDisplay'
import AgencyPricingBreakdown from '../common/AgencyPricingBreakdown.vue'

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

const dynamicPricing = computed(() => priceData.value?.dynamic_pricing ?? null)
const hasDynamicPricing = computed(() => !!dynamicPricing.value?.rule_applied)

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
    if (type === 'Infant') return 'infant'
    return 'adult'
}

function paxIcon(type) {
    if (type === 'Child') return 'fa-solid fa-child'
    if (type === 'Infant') return 'fa-solid fa-baby'
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
    return new Date(iso).toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true,
    })
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

const paxCounts = computed(() => ({
    Adult:  Number(props.form?.ADT ?? 1),
    Child:  Number(props.form?.CNN ?? 0),
    Infant: Number(props.form?.INF ?? 0),
}))

function paxCount(type) {
    return paxCounts.value[type] ?? 0
}

const inclusionIcon = (inc) => {
    if (inc === 'Included')    return 'fa-solid fa-check'
    if (inc === 'Chargeable')  return 'fa-solid fa-dollar-sign'
    return 'fa-solid fa-xmark'
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
    Refund:              'fa-solid fa-rotate-left',
    Rebooking:           'fa-solid fa-calendar-check',
    CheckedBag:          'fa-solid fa-suitcase-rolling',
    CarryOn:             'fa-solid fa-suitcase',
    WiFi:                'fa-solid fa-wifi',
    Meals:               'fa-solid fa-utensils',
    SeatAssignment:      'fa-solid fa-chair',
    'Priority CheckIn':  'fa-solid fa-person-walking-arrow-right',
    'In Flight Entertainment': 'fa-solid fa-tv',
    'Lounge Access':     'fa-solid fa-couch',
    'Mileage Accrual':   'fa-solid fa-coins',
    Upgrade:             'fa-solid fa-arrow-up',
}
const classificationIcon = (cls) => CLASSIFICATION_ICON[cls] ?? 'fa-solid fa-circle-question'
const classLabel = (cls) => CLASSIFICATION_LABEL[cls] ?? cls

const INCLUSION_ORDER = { Included: 0, Chargeable: 1, 'Not Offered': 2 }
function sortedBrandAttributes(brand) {
    const attrs = [...(brand?.attributes ?? []), ...(brand?.additional_attributes ?? [])]
    return attrs.sort((a, b) =>
        (INCLUSION_ORDER[a.inclusion] ?? 9) - (INCLUSION_ORDER[b.inclusion] ?? 9)
    )
}

const agencyPricingLineCount = computed(() => dynamicPricing.value?.pricing_breakdown?.length ?? 0)

const agencyTotalPayable = computed(() =>
    Number(dynamicPricing.value?.total_payable ?? priceData.value?.total_price ?? 0)
)

const footerGrossFare = computed(() =>
    Number(priceData.value?.gross_fare ?? priceData.value?.gross_payment ?? priceData.value?.total_price ?? 0)
)

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
            <div v-if="visible" class="fp-panel" role="dialog" aria-modal="true">

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

                    <!-- Skeleton -->
                    <template v-if="loading">
                        <div class="fp-skeleton-wrap">
                            <div class="fp-sk-block" style="height:80px;border-radius:10px;"></div>
                            <div class="fp-sk-block mt-3" style="height:160px;border-radius:10px;"></div>
                            <div class="fp-sk-block mt-3" style="height:130px;border-radius:10px;"></div>
                            <div class="fp-sk-block mt-3" style="height:110px;border-radius:10px;"></div>
                            <div class="fp-sk-block mt-3" style="height:90px;border-radius:10px;"></div>
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
                        <div :class="['fp-status-banner', priceChanged ? 'fp-status-banner--warn' : 'fp-status-banner--ok']">
                            <i :class="priceChanged ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check'"></i>
                            <span v-if="priceChanged">
                                Price updated by Travelport. Please review before proceeding.
                            </span>
                            <span v-else>Price confirmed — same as displayed fare.</span>
                        </div>

                        <!-- Flight Segments -->
                        <div
                            v-for="(product, pi) in priceData.products"
                            :key="pi"
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

                        <!-- Brand & Attributes -->
                        <div v-if="priceData.brand" class="fp-section">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-tags me-2"></i>Fare Brand
                            </div>
                            <div class="fp-brand-card">
                                <div class="fp-brand-top">
                                    <img v-if="priceData.brand.image_url" :src="priceData.brand.image_url" class="fp-brand-img" alt="">
                                    <div>
                                        <div class="fp-brand-name">{{ priceData.brand.name }}</div>
                                        <div class="fp-brand-meta">
                                            <span class="fp-badge fp-badge--tier">Tier {{ priceData.brand.tier }}</span>
                                            <span class="fp-badge fp-badge--code">{{ priceData.brand.code }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="fp-attrs">
                                    <div
                                        v-for="(attr, aIdx) in sortedBrandAttributes(priceData.brand)"
                                        :key="aIdx"
                                        class="fp-attr-row"
                                    >
                                        <span class="fp-attr-part">
                                            <span
                                                class="fp-attr-cat"
                                                :class="{
                                                    'fp-attr-cat--ok':  attr.inclusion === 'Included',
                                                    'fp-attr-cat--fee': attr.inclusion === 'Chargeable',
                                                    'fp-attr-cat--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >
                                                <i :class="classificationIcon(attr.classification)"></i>
                                            </span>
                                            <span
                                                class="fp-attr-text"
                                                :class="{
                                                    'fp-attr-text--ok':  attr.inclusion === 'Included',
                                                    'fp-attr-text--fee': attr.inclusion === 'Chargeable',
                                                    'fp-attr-text--no':  attr.inclusion === 'Not Offered',
                                                }"
                                            >{{ classLabel(attr.classification) }}</span>
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

                        <!-- Fare Breakdown / Fare Rules — shadcn in-cell tab style -->
                        <div class="fp-section fp-fare-tabs-section">
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
                                    v-for="bd in priceData.price_breakdown"
                                    :key="bd.passenger_type_code"
                                    class="fp-price-pax-block"
                                    :class="`fp-price-pax-block--${paxTone(bd.type)}`"
                                >
                                    <div class="fp-pax-header">
                                        <span class="fp-pax-ico" :class="`fp-pax-ico--${paxTone(bd.type)}`">
                                            <i :class="paxIcon(bd.type)"></i>
                                        </span>
                                        <span class="fp-pax-type">{{ bd.type }}</span>
                                        <span class="fp-pax-qty">× {{ paxCount(bd.type) }}</span>
                                        <span class="fp-pax-total ms-auto">
                                            {{ priceData.currency }} {{ (paxCount(bd.type) * bd.total_price).toLocaleString() }}
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
                                        <summary class="fp-tax-summary">View tax breakdown ({{ bd.taxes.length }} items)</summary>
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
                                        <span>Base Fare</span>
                                        <span>{{ priceData.currency }} {{ priceData.base_fare.toLocaleString() }}</span>
                                    </div>
                                    <div class="fp-gross-row">
                                        <span>Total Taxes</span>
                                        <span>{{ priceData.currency }} {{ priceData.total_taxes.toLocaleString() }}</span>
                                    </div>
                                    <div class="fp-gross-row fp-gross-row--total">
                                        <span>Gross Fare</span>
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

                        <!-- Penalties -->
                        <div v-if="priceData.penalties?.change || priceData.penalties?.cancel" class="fp-section">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-scale-balanced me-2"></i>Penalties
                            </div>
                            <div class="fp-penalties">
                                <div v-if="priceData.penalties.change" class="fp-penalty-row fp-penalty--change">
                                    <i class="fa-solid fa-calendar-check"></i>
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
                                    <i class="fa-solid fa-ban"></i>
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
                        <div v-if="priceData.restrictions?.length" class="fp-section">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-circle-info me-2"></i>Fare Restrictions
                            </div>
                            <ul class="fp-restrictions">
                                <li v-for="r in priceData.restrictions" :key="r">{{ r }}</li>
                            </ul>
                        </div>

                        <!-- Deadlines -->
                        <div class="fp-section">
                            <div class="fp-deadlines">
                                <div v-if="priceData.payment_time_limit" class="fp-deadline-row">
                                    <i class="fa-regular fa-clock text-danger"></i>
                                    <div>
                                        <div class="fp-deadline-label">Payment Deadline</div>
                                        <div class="fp-deadline-val text-danger fw-bold">
                                            {{ formatDeadline(priceData.payment_time_limit) }}
                                        </div>
                                    </div>
                                </div>
                                <div v-if="priceData.expiry_date" class="fp-deadline-row">
                                    <i class="fa-regular fa-hourglass text-warning"></i>
                                    <div>
                                        <div class="fp-deadline-label">Fare Expires</div>
                                        <div class="fp-deadline-val text-warning fw-bold">
                                            {{ formatDeadline(priceData.expiry_date) }}
                                        </div>
                                    </div>
                                </div>
                                <div v-if="priceData.validating_airline" class="fp-deadline-row">
                                    <i class="fa-solid fa-plane-departure text-info"></i>
                                    <div>
                                        <div class="fp-deadline-label">Validating Airline</div>
                                        <div class="fp-deadline-val fw-bold">{{ priceData.validating_airline }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="hasDynamicPricing" class="fp-section fp-dynamic-pricing">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-calculator me-2"></i>Agency Pricing
                            </div>
                            <div class="fp-gross-total">
                                <div class="fp-gross-row fp-gross-row--totals">
                                    <span>Total Payable</span>
                                    <span>{{ priceData.currency }} {{ formatFareAmount(agencyTotalPayable) }}</span>
                                </div>
                                <details v-if="agencyPricingLineCount" class="fp-tax-details">
                                    <summary class="fp-tax-summary">
                                        View agency pricing breakdown ({{ agencyPricingLineCount }} items)
                                    </summary>
                                    <AgencyPricingBreakdown
                                        :pricing="dynamicPricing"
                                        :currency="priceData.currency"
                                        :gross-payment="priceData.gross_payment ?? priceData.total_price"
                                    />
                                </details>
                            </div>
                        </div>

                    </template>
                </div>

                <!-- Footer CTA — tall sticky bar: payable + gross + book -->
                <div v-if="priceData && !loading" class="fp-footer">
                    <div class="fp-footer-prices">
                        <div class="fp-footer-price-row fp-footer-price-row--payable">
                            <div class="fp-footer-price-meta">
                                <span class="fp-footer-ico fp-footer-ico--payable" aria-hidden="true">
                                    <i class="fa-solid fa-receipt"></i>
                                </span>
                                <span class="fp-footer-price-label">Gross Fare</span>
                            </div>
                            <div class="fp-footer-price-value">
                                <span class="fp-footer-currency">{{ priceData.currency }}</span>
                                <span class="fp-footer-amount">{{ formatFareAmount(footerGrossFare) }}</span>
                            </div>
                        </div>
                        <div class="fp-footer-price-row fp-footer-price-row--gross">
                            <div class="fp-footer-price-meta">
                                <span class="fp-footer-ico fp-footer-ico--gross" aria-hidden="true">
                                    <i class="fa-solid fa-wallet"></i>
                                </span>
                                <span class="fp-footer-price-label">Total Payable</span>
                            </div>
                            <div class="fp-footer-price-value">
                                <span class="fp-footer-currency">{{ priceData.currency }}</span>
                                <span class="fp-footer-amount-gross">{{ formatFareAmount(agencyTotalPayable) }}</span>
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
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                <span class="fp-book-btn__text">Processing...</span>
                            </template>
                            <template v-else>
                                <i class="fa-solid fa-plane-departure fp-book-btn__lead"></i>
                                <span class="fp-book-btn__text">Proceed to Booking</span>
                                <i class="fa-solid fa-arrow-right fp-book-btn__arrow"></i>
                            </template>
                        </button>
                    </div>
                </div>

            </div>
        </Transition>
    </Teleport>
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

/* ── Header ──────────────────────────────── */
.fp-header {
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
    background: linear-gradient(90deg, #1a2f35 0%, #1a2838 40%, #1b2a3a 70%, #1a2c3c 100%);
    border-bottom-color: rgba(26, 158, 181, 0.2);
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
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── Skeleton ────────────────────────────── */
.fp-skeleton-wrap { display: flex; flex-direction: column; }
.fp-sk-block {
    background: linear-gradient(90deg, var(--bs-secondary-bg, #e8e8e8) 25%, var(--bs-tertiary-bg, #f5f5f5) 50%, var(--bs-secondary-bg, #e8e8e8) 75%);
    background-size: 200% 100%;
    animation: fp-shimmer 1.4s infinite;
}
@keyframes fp-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

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
.fp-section-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
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
.fp-badge--tier   { background: #f3e8ff; color: #7e22ce; }
.fp-badge--code   { background: #f1f5f9; color: #475569; }
[data-bs-theme="dark"] .fp-badge--fare  { background: #44370a; color: #fbbf24; }
[data-bs-theme="dark"] .fp-badge--cabin { background: #2d1e5a; color: #a78bfa; }
[data-bs-theme="dark"] .fp-badge--cos   { background: rgba(18, 206, 105, 0.12); color: #6ee7b7; }
[data-bs-theme="dark"] .fp-badge--type  { background: rgba(3, 105, 161, 0.2); color: #7dd3fc; }
[data-bs-theme="dark"] .fp-badge--tier  { background: rgba(126, 34, 206, 0.2); color: #d8b4fe; }
[data-bs-theme="dark"] .fp-badge--code  { background: rgba(71, 85, 105, 0.25); color: #94a3b8; }

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
.fp-fare-tabs-scroll {
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
    width: 100%;
    min-width: max-content;
    height: auto;
    padding: 0;
    margin: 0;
    gap: 0;
    background: var(--bs-body-bg, #fff);
    border-radius: 0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.fp-fare-tab {
    position: relative;
    flex: 1 1 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 40px;
    padding: 10px 14px;
    margin: 0 0 0 -1px;
    border: 1px solid var(--bs-border-color, #e2e8f0);
    border-radius: 0;
    background: var(--bs-body-bg, #fff);
    color: var(--bs-secondary-color, #64748b);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.01em;
    white-space: nowrap;
    cursor: pointer;
    overflow: hidden;
    transition: background 0.15s, color 0.15s;
}
.fp-fare-tab:first-child {
    margin-left: 0;
    border-radius: 6px 0 0 6px;
}
.fp-fare-tab:last-child {
    border-radius: 0 6px 6px 0;
}
/* Active bottom primary bar (shadcn after:h-0.5) */
.fp-fare-tab::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 2px;
    background: transparent;
    pointer-events: none;
}
.fp-fare-tab__ico {
    font-size: 13px;
    opacity: 0.6;
}
.fp-fare-tab--active {
    background: var(--bs-tertiary-bg, #f1f5f9);
    color: var(--bs-body-color, #0f172a);
    z-index: 1;
}
.fp-fare-tab--active::after {
    background: #7944eb;
}
.fp-fare-tab--active .fp-fare-tab__ico {
    opacity: 0.85;
    color: #7944eb;
}
.fp-fare-tab:hover:not(.fp-fare-tab--active) {
    background: rgba(148, 163, 184, 0.08);
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
    background: transparent;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
}
html[data-bs-theme="dark"] .fp-fare-tab {
    background: var(--bs-body-bg, #1a1d24);
    border-color: var(--bs-border-color, #334155);
    color: #94a3b8;
}
html[data-bs-theme="dark"] .fp-fare-tab--active {
    background: rgba(148, 163, 184, 0.12);
    color: #e2e8f0;
}
html[data-bs-theme="dark"] .fp-fare-tab--active::after {
    background: #a78bfa;
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
    background: var(--bs-tertiary-bg, #f8f9fa);
    border: 1px solid var(--bs-border-color, #e2e8f0);
    border-left: 3px solid #7944eb;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
}
.fp-price-pax-block--child { border-left-color: #059669; }
.fp-price-pax-block--infant { border-left-color: #d97706; }
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
    font-size: 11px; color: #7944eb; cursor: pointer;
    list-style: none; user-select: none;
}
.fp-tax-summary::-webkit-details-marker { display: none; }
.fp-tax-summary:hover { text-decoration: underline; }
.fp-dynamic-pricing .fp-tax-details { margin-top: 8px; }
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
    border-radius: 8px;
    padding: 10px 14px;
    margin-top: 4px;
}
.fp-gross-row { display: flex; justify-content: space-between; font-size: 12px; padding: 3px 0; color: var(--bs-body-color); }
.fp-gross-row--total {
    border-top: 1.5px solid #7944eb44;
    margin-top: 4px;
    padding-top: 6px;
    font-size: 15px;
    font-weight: 700;
    color: #7944eb;
}

.fp-gross-row--totals {
    margin-top: 4px;
    padding-top: 6px;
    font-size: 15px;
    font-weight: 700;
    color: #7944eb;
}

.fp-dynamic-pricing {
    margin-top: 12px;
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
    border: 1px solid var(--bs-border-color, #e2e8f0);
    border-radius: 10px;
    padding: 14px;
}
.fp-brand-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.fp-brand-img { height: 36px; object-fit: contain; border-radius: 4px; }
.fp-brand-name { font-size: 14px; font-weight: 700; color: var(--bs-body-color); }
.fp-brand-meta { display: flex; gap: 5px; margin-top: 3px; }
.fp-attrs { display: flex; flex-direction: column; }
.fp-attr-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid var(--bs-border-color, #f4f5fa);
}
.fp-attr-row:last-child { border-bottom: none; }
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
.fp-attr-cat--ok  { color: #0d9b6e; }
.fp-attr-cat--fee { color: #d97706; }
.fp-attr-cat--no  { color: #9aa3b5; }
html[data-bs-theme="dark"] .fp-attr-cat--ok  { color: #6ee7b7; }
html[data-bs-theme="dark"] .fp-attr-cat--fee { color: #fbbf24; }
html[data-bs-theme="dark"] .fp-attr-cat--no  { color: #6b7280; }
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

/* ── Penalties ───────────────────────────── */
.fp-penalties { display: flex; flex-direction: column; gap: 8px; }
.fp-penalty-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; border-radius: 8px;
    font-size: 12px;
}
.fp-penalty--change { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
.fp-penalty--cancel { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
[data-bs-theme="dark"] .fp-penalty--change { background: #1e3a5f; border-color: #3b82f6; color: #93c5fd; }
[data-bs-theme="dark"] .fp-penalty--cancel { background: #450a0a; border-color: #ef4444; color: #fca5a5; }
.fp-penalty-title { font-weight: 600; }
.fp-penalty-meta  { font-size: 10px; opacity: 0.75; }
.fp-penalty-amount { font-size: 14px; font-weight: 700; }

/* ── Restrictions ────────────────────────── */
.fp-restrictions {
    margin: 0; padding: 0 0 0 16px;
    font-size: 12px;
    color: var(--bs-secondary-color, #6b7280);
    display: flex; flex-direction: column; gap: 3px;
}

/* ── Deadlines ───────────────────────────── */
.fp-deadlines { display: flex; flex-direction: column; gap: 8px; }
.fp-deadline-row {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 8px 12px;
    background: var(--bs-tertiary-bg, #f8f9fa);
    border: 1px solid var(--bs-border-color, #e2e8f0);
    border-radius: 8px;
    font-size: 12px;
}
.fp-deadline-label { font-size: 10px; color: var(--bs-secondary-color, #6b7280); }
.fp-deadline-val   { font-size: 13px; }

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
.fp-footer-price-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
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
.fp-footer-price-value {
    display: flex;
    align-items: baseline;
    gap: 5px;
    min-width: 0;
}
.fp-footer-currency {
    font-size: 11px;
    color: var(--bs-secondary-color, #6b7280);
    font-weight: 700;
}
.fp-footer-amount {
    font-size: 22px;
    font-weight: 800;
    color: #7944eb;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.fp-footer-amount-gross {
    font-size: 15px;
    font-weight: 700;
    color: var(--bs-body-color, #1a2436);
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.fp-footer-price-row--payable .fp-footer-price-label { color: #7944eb; }
html[data-bs-theme="dark"] .fp-footer-amount { color: #c4b5fd; }
html[data-bs-theme="dark"] .fp-footer-price-row--payable .fp-footer-price-label { color: #c4b5fd; }
html[data-bs-theme="dark"] .fp-footer-amount-gross { color: var(--bs-body-color, #dee2e6); }

/* Full-height narrow CTA — flush right edge (red-box layout) */
.fp-book-btn {
    background: linear-gradient(180deg, #7944eb 0%, #5b6ff0 55%, #4a6ef5 100%);
    color: #fff !important;
    text-decoration: none;
    border: none;
    border-radius: 0;
    width: 125px;
    min-width: 125px;
    max-width: 125px;
    align-self: stretch;
    min-height: 100%;
    padding: 10px 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    line-height: 1.25;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: filter 0.15s, opacity 0.15s;
    box-shadow: none;
}
.fp-book-btn__text {
    text-align: center;
    white-space: normal;
    max-width: 4.6em;
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
        white-space: nowrap;
    }
}
</style>
