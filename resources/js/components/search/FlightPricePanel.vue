<script setup>
import { ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import axiosInstance from '../../axiosInstance'
import { useSearchStore } from '../../stores/searchStore'
import { useTpV2Workbench } from '../../composables/useTpV2Workbench'
import { buildSelectionJson } from '../../utils/bookingSelectionJson'
import { completePriceAttempt } from '../../utils/bookingAttemptSession'

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
const router = useRouter()
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
const isDownloading    = ref(false)
const priceChanged     = ref(false)

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

        const confirmedPrice = priceData.value?.total_price ?? 0
        const searchPrice    = currentBrand.value?.price ?? 0
        priceChanged.value   = Math.abs(confirmedPrice - searchPrice) > 1

        fetchFareRules()

    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Price confirmation failed. Please try again.'
    } finally {
        loading.value = false
    }
}

function fetchFareRules() {
    if (!bookingAttemptId.value) return

    const attemptId = bookingAttemptId.value

    // outbound — fire independently
    axiosInstance.get('v2/fare-rules', { params: {
        catalogProductOfferingsIdentifier: props.catalogIdentifier,
        catalogProductOfferingID:          props.flight?.outbound?._offering_id,
        productIDs:                        currentBrand.value?._productRef ?? props.flight?.outbound?._selected_productRef,
        fareRuleType:                      'Structured',
        direction:                         'outbound',
        booking_attempt_id:                attemptId,
    }}).catch(() => {})

    // inbound — fire independently if round trip
    const inboundOfferingId = props.flight?.inbound?._offering_id
    if (inboundOfferingId) {
        const outCodes = currentBrand.value?._combinabilityCode ?? []
        const matchedInbound = props.flight?.inbound?.brand_options?.find(b =>
            (b._combinabilityCode ?? []).some(c => outCodes.includes(c))
        )
        const inboundProductRef = matchedInbound?._productRef ?? props.flight?.inbound?._selected_productRef
        if (inboundProductRef) {
            axiosInstance.get('v2/fare-rules', { params: {
                catalogProductOfferingsIdentifier: props.catalogIdentifier,
                catalogProductOfferingID:          inboundOfferingId,
                productIDs:                        inboundProductRef,
                fareRuleType:                      'Structured',
                direction:                         'inbound',
                booking_attempt_id:                attemptId,
            }}).catch(() => {})
        }
    }
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

async function downloadFiles() {
    if (!priceLogId.value || isDownloading.value) return
    isDownloading.value = true
    try {
        const res  = await axiosInstance.post('flight-price-log/view', { id: priceLogId.value })
        const data = res?.data?.data
        if (!data) return

        const triggerDownload = (content, filename) => {
            const blob = new Blob([JSON.stringify(content, null, 2)], { type: 'application/json' })
            const url  = URL.createObjectURL(blob)
            const a    = document.createElement('a')
            a.href     = url
            a.download = filename
            a.click()
            URL.revokeObjectURL(url)
        }

        const productRef = currentBrand.value?._productRef ?? 'p'
        const brandRef   = currentBrand.value?._brandRef   ?? 'b'
        const prefix     = `tp-price-${productRef}-${brandRef}-${priceLogId.value}`

        triggerDownload(data.price_payload, `${prefix}-payload.json`)
        await new Promise(r => setTimeout(r, 300))
        triggerDownload(data.response_json, `${prefix}-response.json`)
    } catch (e) {
        console.error(e)
    } finally {
        isDownloading.value = false
    }
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
const inclusionClass = (inc) => {
    if (inc === 'Included')    return 'attr-ok'
    if (inc === 'Chargeable')  return 'attr-fee'
    return 'attr-no'
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
const attrLabel = (attr) => `${classLabel(attr.classification)} (${attr.inclusion})`
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

                <!-- Header -->
                <div class="fp-header">
                    <button class="fp-close-btn" @click="closePanel" title="Close">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <div class="fp-header-title">
                        <div class="fp-header-main">Fare Confirmation</div>
                        <div v-if="currentBrand?.label" class="fp-header-sub">
                            {{ currentBrand.label }}
                        </div>
                    </div>
                    <button
                        v-if="priceLogId"
                        class="fp-dl-btn"
                        @click="downloadFiles"
                        :disabled="isDownloading"
                        title="Download payload & response"
                    >
                        <i :class="isDownloading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-download'"></i>
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

                        <!-- Price Breakdown -->
                        <div class="fp-section">
                            <div class="fp-section-label">
                                <i class="fa-solid fa-receipt me-2"></i>Price Breakdown
                            </div>

                            <div v-for="bd in priceData.price_breakdown" :key="bd.passenger_type_code" class="fp-price-pax-block">
                                <div class="fp-pax-header">
                                    <i :class="{
                                        'fa-solid fa-person text-primary': bd.type === 'Adult',
                                        'fa-solid fa-child text-success': bd.type === 'Child',
                                        'fa-solid fa-baby text-warning': bd.type === 'Infant',
                                    }" style="font-size:13px;"></i>
                                    <span class="fp-pax-type">{{ bd.type }}</span>
                                    <span class="fp-pax-qty">× {{ paxCount(bd.type) }}</span>
                                    <span class="fp-pax-total ms-auto">
                                        {{ priceData.currency }} {{ (paxCount(bd.type) * bd.total_price).toLocaleString() }}
                                    </span>
                                </div>

                                <div class="fp-pax-rows">
                                    <div class="fp-pax-row">
                                        <span>Base Fare</span>
                                        <span>{{ priceData.currency }} {{ bd.base_fare.toLocaleString() }}</span>
                                    </div>
                                    <div class="fp-pax-row">
                                        <span>Total Taxes</span>
                                        <span>{{ priceData.currency }} {{ bd.total_taxes.toLocaleString() }}</span>
                                    </div>
                                </div>

                                <!-- Tax detail accordion -->
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

                                <!-- <div v-if="bd.fare_calculation" class="fp-fare-calc">
                                    <span class="fp-fare-calc-label">Fare Calc:</span>
                                    {{ bd.fare_calculation }}
                                    <span v-if="bd.filed_amount?.value" class="fp-filed-amt">
                                        (Filed: {{ bd.filed_amount.currency }} {{ bd.filed_amount.value }})
                                    </span>
                                </div> -->
                            </div>

                            <!-- Gross Total -->
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
                                    <span>{{ priceData.currency }} {{ priceData.total_price.toLocaleString() }}</span>
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
                                        v-for="(attr, aIdx) in [...(priceData.brand.attributes ?? []), ...(priceData.brand.additional_attributes ?? [])]"
                                        :key="aIdx"
                                        class="fp-attr-row"
                                    >
                                        <span :class="['fp-attr-dot', inclusionClass(attr.inclusion)]">
                                            <i :class="inclusionIcon(attr.inclusion)"></i>
                                        </span>
                                        <i :class="['fp-attr-cat', classificationIcon(attr.classification)]"></i>
                                        <span :class="['fp-attr-text', inclusionClass(attr.inclusion) + '-text']">
                                            {{ attrLabel(attr) }}
                                        </span>
                                    </div>
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

                    </template>
                </div>

                <!-- Footer CTA -->
                <div v-if="priceData && !loading" class="fp-footer">
                    <div class="fp-footer-price">
                        <span class="fp-footer-currency">{{ priceData.currency }}</span>
                        <span class="fp-footer-amount">{{ priceData.total_price.toLocaleString() }}</span>
                    </div>
                    <div class="fp-footer-right">
                        <div v-if="workbenchError" class="fp-wb-error">{{ workbenchError }}</div>
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
                            class="fp-book-btn"
                            @click="handleProceed"
                            :disabled="isInitiating"
                        >
                            <template v-if="isInitiating">
                                <i class="fa-solid fa-spinner fa-spin me-2"></i>
                                Processing...
                            </template>
                            <template v-else>
                                Proceed to Booking
                                <i class="fa-solid fa-arrow-right ms-2"></i>
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
    padding: 14px 18px;
    background: linear-gradient(135deg, #7944eb 0%, #4a6ef5 100%);
    color: #fff;
    flex-shrink: 0;
}
.fp-close-btn {
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
}
.fp-close-btn:hover { background: rgba(255,255,255,0.3); }
.fp-header-title { flex: 1; min-width: 0; }
.fp-header-main { font-size: 15px; font-weight: 700; }
.fp-header-sub  { font-size: 11px; opacity: 0.85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fp-dl-btn {
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}
.fp-dl-btn:hover:not(:disabled) { background: rgba(255,255,255,0.3); }
.fp-dl-btn:disabled { opacity: 0.5; cursor: default; }

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

/* ── Price breakdown ─────────────────────── */
.fp-price-pax-block {
    background: var(--bs-tertiary-bg, #f8f9fa);
    border: 1px solid var(--bs-border-color, #e2e8f0);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
}
.fp-pax-header {
    display: flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 600; margin-bottom: 6px;
}
.fp-pax-type  { color: var(--bs-body-color); }
.fp-pax-qty   { color: var(--bs-secondary-color, #6b7280); font-size: 12px; }
.fp-pax-total { font-weight: 700; color: #7944eb; }
.fp-pax-rows  { display: flex; flex-direction: column; gap: 3px; margin-bottom: 6px; }
.fp-pax-row {
    display: flex; justify-content: space-between;
    font-size: 11px; color: var(--bs-secondary-color, #6b7280);
}

/* Tax details */
.fp-tax-details { margin-top: 6px; }
.fp-tax-summary {
    font-size: 11px; color: #7944eb; cursor: pointer;
    list-style: none; user-select: none;
}
.fp-tax-summary:hover { text-decoration: underline; }
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
.fp-attrs { display: flex; flex-direction: column; gap: 5px; }
.fp-attr-row { display: flex; align-items: center; gap: 8px; font-size: 12px; }
.fp-attr-dot {
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; flex-shrink: 0;
}
.attr-ok  { background: #d1fae5; color: #065f46; }
.attr-fee { background: #fef3c7; color: #92400e; }
.attr-no  { background: #fee2e2; color: #991b1b; }
.fp-attr-cat { font-size: 12px; color: var(--bs-secondary-color, #6b7280); width: 16px; flex-shrink: 0; }
.fp-attr-text { color: var(--bs-body-color); }
.attr-fee-text { color: #92400e; }
.attr-no-text  { color: #991b1b; text-decoration: line-through; opacity: 0.75; }
[data-bs-theme="dark"] .attr-ok  { background: #064e3b; color: #6ee7b7; }
[data-bs-theme="dark"] .attr-fee { background: #451a03; color: #fcd34d; }
[data-bs-theme="dark"] .attr-no  { background: #450a0a; color: #fca5a5; }

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
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 18px;
    border-top: 1px solid var(--bs-border-color, #e2e8f0);
    background: var(--bs-body-bg, #fff);
}
.fp-footer-price { display: flex; align-items: baseline; gap: 4px; }
.fp-footer-currency { font-size: 12px; color: var(--bs-secondary-color, #6b7280); font-weight: 600; }
.fp-footer-amount { font-size: 22px; font-weight: 800; color: #7944eb; }
.fp-book-btn {
    background: linear-gradient(135deg, #7944eb, #4a6ef5);
    color: #fff !important;
    text-decoration: none;
    border: none;
    border-radius: 8px;
    padding: 11px 22px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: opacity 0.15s, transform 0.1s;
    white-space: nowrap;
}
.fp-book-btn:hover { opacity: 0.92; transform: translateY(-1px); }

/* ── Footer right ────────────────────────── */
.fp-footer-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.fp-wb-error {
    font-size: 11px;
    color: var(--bs-danger, #dc3545);
    max-width: 220px;
    text-align: right;
}
.fp-wb-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    width: 100%;
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
    background: rgba(121, 68, 235, 0.14);
    color: #6d28d9;
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
}
</style>
