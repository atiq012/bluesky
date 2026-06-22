<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from "vue";
import { useRouter } from 'vue-router';
import { storeToRefs } from "pinia";
import { useBookingStore } from '../../../stores/bookingStore';
import FlightPricePanel from '../../search/FlightPricePanel.vue';
import Select2 from '../../common/Select2.vue';
import AppDatePicker from '../../common/AppDatePicker.vue';
import DobWithAge from '../../common/DobWithAge.vue';
import ImageUploader from '../../common/ImageUploader.vue';
import { formatDate } from '../../../utils/dateUtils';
import { useTpV2AddTraveler } from '../../../composables/useTpV2AddTraveler';
import { useTpV2Ancillary } from '../../../composables/useTpV2Ancillary';
import { useTpV2PreCommit } from '../../../composables/useTpV2PreCommit';
import { useTpV2BookingReview } from '../../../composables/useTpV2BookingReview';
import BookingReviewConfirm from './BookingReviewConfirm.vue';
import BookingPnrStep from './BookingPnrStep.vue';
import BookingReceiptModal from './BookingReceiptModal.vue';
import { buildReceiptFromCommit } from '../../../utils/buildReceiptFromCommit';

const router = useRouter();
const bookingStore = useBookingStore();
const { submitTravelers, syncTravelerPreferences, isSubmitting: isSubmittingTravelers, error: travelerSubmitError } = useTpV2AddTraveler();
const { shopAncillaries, bookAncillary, isAncillaryBooked, isShoppingAncillaries, isBookingAncillary, shopError, bookError } = useTpV2Ancillary();
const {
    applySsr,
    buildSsrPreviewRows,
    hasSsrToApply,
    isApplyingSsr,
    ssrError,
} = useTpV2PreCommit();
const {
    prepareReview,
    confirmBooking,
    retryCommit,
    isLoading: isReviewLoading,
    isConfirming: isConfirmingBooking,
    error: reviewError,
    snapshot: liveReviewSnapshot,
} = useTpV2BookingReview();
const {
    paxesSubmitted,
    ancillaryShopData,
    contentSource,
    ssrSubmitted,
    ssrSkipped,
} = storeToRefs(bookingStore);

// File state kept local — File objects cannot be serialized to sessionStorage
const travelerFiles = ref([])

// --- Top bar computed ---
const routeFrom = computed(() => bookingStore.form?.from ?? '---')
const routeTo   = computed(() => bookingStore.form?.to   ?? '---')
const tripType  = computed(() => bookingStore.form?.Way === 2 ? 'Round Way' : 'One Way')
const cabin     = computed(() => bookingStore.form?.cabin_class ?? 'Economy')
const depDate   = computed(() => formatDate(bookingStore.form?.dep_date))
const retDate   = computed(() => formatDate(bookingStore.form?.arrival_date))
const totalPax  = computed(() => {
    const f = bookingStore.form
    if (!f) return 0
    return Number(f.ADT ?? 0) + Number(f.CNN ?? 0) + Number(f.INF ?? 0)
})
const isRoundTrip = computed(() => bookingStore.form?.Way === 2)
const routeHeadline = computed(() => {
    const from = routeFrom.value
    const to = routeTo.value
    return isRoundTrip.value ? `${from} ↔ ${to}` : `${from} → ${to}`
})
const paxDetailLabel = computed(() => {
    const f = bookingStore.form
    if (!f) return ''
    const parts = []
    const adt = Number(f.ADT ?? 0)
    const cnn = Number(f.CNN ?? 0)
    const inf = Number(f.INF ?? 0)
    if (adt) parts.push(`${adt} Adult${adt > 1 ? 's' : ''}`)
    if (cnn) parts.push(`${cnn} Child${cnn > 1 ? 'ren' : ''}`)
    if (inf) parts.push(`${inf} Infant${inf > 1 ? 's' : ''}`)
    return parts.join(' · ')
})
const dateRangeLabel = computed(() => {
    if (!depDate.value) return ''
    if (retDate.value && isRoundTrip.value) return `${depDate.value} – ${retDate.value}`
    return depDate.value
})

const WIZARD_STEPS = [
    { id: 'travelers', label: 'Travelers', hint: 'Passenger details', icon: 'fa-user-group' },
    { id: 'addons', label: 'Add-ons', hint: 'Optional extras', icon: 'fa-suitcase-rolling' },
    { id: 'ssr', label: 'SSR', hint: 'Special requests', icon: 'fa-wheelchair' },
    { id: 'review', label: 'Review & confirm', hint: 'Verify and book', icon: 'fa-clipboard-check' },
    { id: 'pnr', label: 'PNR', hint: 'Record locator', icon: 'fa-ticket' },
]

const LEGACY_STEP_MAP = {
    travelerDetails: 'travelers',
    addonesSevice: 'addons',
    requests: 'ssr',
    couponOffers: 'ssr',
    agency: 'ssr',
    reviewConfirm: 'review',
    reviewPayment: 'pnr',
}

function normalizeStepId(step) {
    const mapped = LEGACY_STEP_MAP[step] || step
    return WIZARD_STEPS.some(s => s.id === mapped) ? mapped : 'travelers'
}

const activeStep = computed({
    get: () => normalizeStepId(bookingStore.activeStep),
    set: (value) => { bookingStore.activeStep = value },
})

const currentStepIndex = computed(() => WIZARD_STEPS.findIndex(s => s.id === activeStep.value))

const isReviewStep = computed(() => activeStep.value === 'review' || activeStep.value === 'pnr')

const commitDisplay = computed(() => bookingStore.commitResult ?? {})
const ancillaryCoverageSelection = reactive({})

function coverageOptions(item) {
    const options = item?.coverage_options
    if (Array.isArray(options) && options.length > 0) return options
    return ['both']
}

function selectedCoverage(item) {
    const key = item?.item_key || item?.catalog_offering_id || item?.product_id
    if (!key) return 'both'

    if (!ancillaryCoverageSelection[key]) {
        ancillaryCoverageSelection[key] = item?.default_coverage ?? 'both'
    }

    return ancillaryCoverageSelection[key]
}

function setCoverage(item, value) {
    const key = item?.item_key || item?.catalog_offering_id || item?.product_id
    if (!key) return
    ancillaryCoverageSelection[key] = value
}

function coverageLabel(option) {
    if (option === 'outbound') return 'Outbound only'
    if (option === 'inbound') return 'Inbound only'
    return 'Both'
}

function coverageComponents(item, coverage = 'both') {
    const components = Array.isArray(item?.merged_components) && item.merged_components.length > 0
        ? item.merged_components
        : [item]

    if (coverage === 'both') return components

    const normalizedCoverage = String(coverage || '').trim().toLowerCase()
    const matched = components.filter(component => {
        const direction = String(component?.component_direction || '').trim().toLowerCase()
        return direction === normalizedCoverage
    })

    if (matched.length > 0) return matched

    // Fallback: some responses miss/shift direction tags; keep selector useful for merged round-trip rows.
    if (components.length >= 2) {
        if (normalizedCoverage === 'outbound') return [components[0]]
        if (normalizedCoverage === 'inbound') return [components[1]]
    }

    return components
}

function parseAncillaryPrice(value) {
    const normalized = String(value ?? '').replace(/,/g, '').trim()
    const parsed = Number(normalized)
    if (!Number.isFinite(parsed)) return 0
    return parsed
}

function ancillaryPriceForCoverage(item, coverage = selectedCoverage(item)) {
    const components = coverageComponents(item, coverage)
    if (components.length === 0) return item?.price ?? 0

    const total = components.reduce((sum, component) => sum + parseAncillaryPrice(component?.price), 0)
    if (!Number.isFinite(total)) return item?.price ?? 0

    const hasFraction = Math.abs(total - Math.trunc(total)) > 0
    return hasFraction ? total.toFixed(2) : String(Math.trunc(total))
}

function ancillaryCurrencyForCoverage(item, coverage = selectedCoverage(item)) {
    const components = coverageComponents(item, coverage)
    const componentCurrency = components.find(component => component?.currency)?.currency
    return componentCurrency || item?.currency || 'BDT'
}

function ancillaryCoverageTotalAsNumber(item, coverage) {
    const value = ancillaryPriceForCoverage(item, coverage)
    return parseAncillaryPrice(value)
}

function hasDistinctCoveragePrices(item) {
    if (!item?.can_select_coverage) return true

    const outbound = ancillaryCoverageTotalAsNumber(item, 'outbound')
    const inbound = ancillaryCoverageTotalAsNumber(item, 'inbound')

    // Consider tiny float noise as equal.
    return Math.abs(outbound - inbound) > 0.0001
}

// --- Timer (continues from workbench initiation) ---
const remainingSeconds = ref(30 * 60)
let timerInterval = null

onMounted(() => {
    const startedAt = bookingStore.timerStartedAt
    if (startedAt) {
        const elapsed = Math.floor((Date.now() - startedAt) / 1000)
        remainingSeconds.value = Math.max(0, 30 * 60 - elapsed)
    }
    timerInterval = setInterval(() => {
        if (remainingSeconds.value > 0) remainingSeconds.value--
    }, 1000)
})

onUnmounted(() => {
    clearInterval(timerInterval)
    clearInterval(expiredTimer)
    clearReceiptModalTimer()
})

const timerDisplay = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60)
    const s = remainingSeconds.value % 60
    return `${String(m).padStart(2, '0')} : ${String(s).padStart(2, '0')}`
})

const timerCritical = computed(() => remainingSeconds.value < 300)

// --- Timer expiry → force redirect ---
const timerExpiredRedirecting = ref(false)
const timerExpiredCountdown   = ref(5)
let expiredTimer = null

watch(remainingSeconds, (val) => {
    if (val !== 0) return
    const alreadyCommitted = commitDisplay.value?.pnr && !commitDisplay.value?.commit_pending
    if (alreadyCommitted) return
    timerExpiredRedirecting.value = true
    timerExpiredCountdown.value   = 5
    expiredTimer = setInterval(() => {
        timerExpiredCountdown.value--
        if (timerExpiredCountdown.value <= 0) {
            clearInterval(expiredTimer)
            handlePnrNewSearch()
        }
    }, 1000)
})

// --- Flight Details Panel ---
const showFlightDetails = ref(false)
const showReceiptModal = ref(false)
const receiptData = ref(null)
let receiptModalTimer = null

// --- Dynamic travelers ---
const travelers = computed(() => {
    const f = bookingStore.form
    if (!f) return [{ type: 'Adult', label: 'Adult', isPrimary: true, hasAge: false }]
    const list = []
    const adt = Number(f.ADT ?? 1)
    const cnn = Number(f.CNN ?? 0)
    const inf = Number(f.INF ?? 0)
    for (let i = 0; i < adt; i++) list.push({ type: 'Adult',  label: 'Adult',    isPrimary: i === 0, hasAge: false })
    for (let i = 0; i < cnn; i++) list.push({ type: 'Child',  label: 'Children', isPrimary: false,   hasAge: true  })
    for (let i = 0; i < inf; i++) list.push({ type: 'Infant', label: 'Infant',   isPrimary: false,   hasAge: true  })
    return list
})

// --- Select options ---
const titleDefaultOption = { value: null, label: '=List=' }
const adultTitleOptions = [
    titleDefaultOption,
    { value: 'Mr', label: 'Mr.' },
    { value: 'Mrs', label: 'Mrs.' },
    { value: 'Ms', label: 'Ms.' },
]
const childTitleOptions = [
    titleDefaultOption,
    { value: 'Mstr', label: 'Mstr.' },
    { value: 'Ms', label: 'Ms.' },
]
function titleOptionsFor(type) {
    return (type === 'Child' || type === 'Infant') ? childTitleOptions : adultTitleOptions
}
const genderOptions = [
    { value: 'Male', label: 'Male' },
    { value: 'Female', label: 'Female' },
    { value: 'Others', label: 'Others' },
]
const nationalityOptions = [
    { value: 'Bangladeshi', label: 'Bangladeshi' },
    { value: 'American', label: 'American' },
    { value: 'Pakistani', label: 'Pakistani' },
    { value: 'Indian', label: 'Indian' },
]
const mealOptions = [
    { value: 'Veg', label: 'Veg' },
    { value: 'Non Veg', label: 'Non Veg' },
]
const wheelchairOptions = [
    { value: 'Yes', label: 'Yes' },
    { value: 'No', label: 'No' },
]

// --- Per-traveler form state (persisted in bookingStore → sessionStorage) ---
const { travelerForms } = storeToRefs(bookingStore)
watch(travelers, (list) => {
    const prev = bookingStore.travelerForms
    bookingStore.travelerForms = list.map((t, i) => {
        const existing = prev[i] ?? {
            title: null, firstName: '', middleName: '', lastName: '',
            dob: '', gender: '', nationality: '', frequentFlyer: '',
            passportNo: '', expiryDate: '', email: '', phone: '',
            meal: '', wheelchair: '', isPrimaryContact: false,
        }
        if (t.type === 'Adult' && i === 0 && !prev[i]) {
            existing.isPrimaryContact = true
        }
        return existing
    })
    // sync local file state to traveler count
    const prevFiles = travelerFiles.value
    travelerFiles.value = list.map((_, i) => prevFiles[i] ?? { passportFiles: [], visaFiles: [] })
}, { immediate: true })

const ssrPreviewRows = computed(() => buildSsrPreviewRows(travelerForms.value, travelers.value))
const ssrHasRequests = computed(() => hasSsrToApply(travelerForms.value, travelers.value))

// Traveler form validation — drives Continue button state
const MONTHS_MAP = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

function parseDobStr(str) {
    if (!str) return null
    const parts = str.trim().split('-')
    if (parts.length !== 3) return null
    const [d, m, y] = parts
    const mi = MONTHS_MAP.indexOf(m)
    if (mi < 0) return null
    const dt = new Date(parseInt(y, 10), mi, parseInt(d, 10))
    return isNaN(dt.getTime()) ? null : dt
}

function ageInMonthsAtDate(dobStr, travelStr) {
    const dob = parseDobStr(dobStr)
    if (!dob) return null
    const ref = parseDobStr(travelStr) ?? new Date()
    const months = (ref.getFullYear() - dob.getFullYear()) * 12
        + (ref.getMonth() - dob.getMonth())
        + (ref.getDate() < dob.getDate() ? -1 : 0)
    return months < 0 ? null : months
}

function isDobValidForType(dobStr, paxType, travelDate) {
    const dob = parseDobStr(dobStr)
    if (!dob) return false
    if (dob > new Date()) return false
    const months = ageInMonthsAtDate(dobStr, travelDate)
    if (months === null) return false
    const years = Math.floor(months / 12)
    if (paxType === 'Adult')  return years >= 12
    if (paxType === 'Child')  return years >= 2 && years < 12
    if (paxType === 'Infant') return months <= 24
    return true
}

const isTravelerFormValid = computed(() => {
    const travelDate = bookingStore.form?.dep_date ?? ''
    return travelers.value.every((t, i) => {
        const f = travelerForms.value[i]
        if (!f) return false
        const base = f.title && f.firstName?.trim() && f.lastName?.trim()
            && isDobValidForType(f.dob, t.type, travelDate)
            && f.gender && f.nationality
            && f.passportNo?.trim() && f.expiryDate && f.email?.trim() && f.phone?.trim()
        if (!base) return false
        // primary contact (first adult) must have email + phone
        if (t.isPrimaryContact) return f.email?.trim() && f.phone?.trim()
        return true
    })
})

const devTestData = {
    Adult: [
        { title: 'Mr',   firstName: 'Md',     middleName: 'Shakaouth', lastName: 'Hossain', dob: '01-Jan-1988', gender: 'Male',   passportNo: 'BS456789', expiryDate: '05-Jun-2030', email: 'shakaouth.hossain@galaxybd.com', phone: '01787688855' },
        { title: 'Mr',   firstName: 'Rafiq',  middleName: '',          lastName: 'Islam',   dob: '20-Mar-1985', gender: 'Male',   passportNo: 'BS456790', expiryDate: '05-Jun-2030', email: '', phone: '' },
        { title: 'Mr',   firstName: 'Kamal',  middleName: '',          lastName: 'Ahmed',   dob: '10-Jul-1992', gender: 'Male',   passportNo: 'BS456791', expiryDate: '05-Jun-2030', email: '', phone: '' },
    ],
    Child: [
        { title: 'Mstr', firstName: 'Shohebur', middleName: '', lastName: 'Rahman', dob: '2015-06-01', gender: 'Male', passportNo: 'BS34534', expiryDate: '2030-06-06', email: '', phone: '01714567899' },
        { title: 'Ms', firstName: 'Nadia', middleName: '', lastName: 'Akter', dob: '2017-04-15', gender: 'Female', passportNo: 'BS34535', expiryDate: '2030-06-06', email: '', phone: '' },
    ],
    Infant: [
        { title: 'Ms', firstName: 'Shahira', middleName: '', lastName: 'Sadik', dob: '2025-06-01', gender: 'Female', passportNo: 'SB12345', expiryDate: '2030-06-13', email: '', phone: '' },
        { title: 'Mstr', firstName: 'Rafi', middleName: '', lastName: 'Khan', dob: '2025-08-10', gender: 'Male', passportNo: 'SB12346', expiryDate: '2030-06-13', email: '', phone: '' },
    ],
}

function fillTestData() {
    const counters = { Adult: 0, Child: 0, Infant: 0 }
    travelerForms.value = travelerForms.value.map((f, i) => {
        const type = travelers.value[i]?.type ?? 'Adult'
        const idx = counters[type] ?? 0
        counters[type] = idx + 1
        const data = devTestData[type]?.[idx] ?? devTestData[type]?.[0] ?? {}
        return {
            ...f,
            title:       f.title       || data.title       || 'Mr',
            firstName:   f.firstName   || data.firstName   || 'Guest',
            middleName:  f.middleName  ?? data.middleName  ?? '',
            lastName:    f.lastName    || data.lastName    || 'Traveler',
            dob:         f.dob         || data.dob         || '01-Jan-1990',
            gender:      f.gender      || data.gender      || 'Male',
            nationality: f.nationality || 'Bangladeshi',
            passportNo:  f.passportNo  || data.passportNo  || 'AB123456',
            expiryDate:  f.expiryDate  || data.expiryDate  || '31-Dec-2030',
            email:       f.email       || data.email       || '',
            phone:       f.phone       || data.phone       || '',
            meal:        f.meal        || '',
            wheelchair:  f.wheelchair  || '',
            isPrimaryContact: i === 0,
        }
    })
}

function setPrimaryContact(ti) {
    if (!travelerForms.value[ti]?.isPrimaryContact) return
    travelerForms.value.forEach((f, i) => {
        if (i !== ti && travelers.value[i]?.type === 'Adult') {
            f.isPrimaryContact = false
        }
    })
}

function isStepUnlocked(stepId) {
    if (stepId === 'travelers') return true
    if (stepId === 'pnr') return bookingStore.reviewConfirmed
    return paxesSubmitted.value
}

function isStepCompleted(stepId) {
    const idx = WIZARD_STEPS.findIndex(s => s.id === stepId)
    if (idx < 0) return false
    if (stepId === 'travelers') return paxesSubmitted.value
    if (stepId === 'review') return bookingStore.reviewConfirmed
    if (stepId === 'pnr') return !!commitDisplay.value?.pnr && !commitDisplay.value?.commit_pending
    return currentStepIndex.value > idx
}

function onEnterStep(stepId) {
    if (stepId === 'addons' && !bookingStore.ancillaryShopData) {
        shopAncillaries()
    }
}

function goToStep(stepId, { skipEnter = false } = {}) {
    const normalized = normalizeStepId(stepId)
    if (!WIZARD_STEPS.some(s => s.id === normalized)) return
    if (!isStepUnlocked(normalized)) return
    activeStep.value = normalized
    if (!skipEnter) onEnterStep(normalized)
}

function handleWizardTabClick(stepId) {
    if (stepId === 'review') {
        goToReviewStep()
        return
    }
    if (stepId === 'pnr' && !bookingStore.reviewConfirmed) return
    goToStep(stepId)
}

function goAddonsStep() {
    if (isSubmittingTravelers.value || !paxesSubmitted.value) return
    goToStep('addons')
}

async function handleTravelerContinue() {
    if (isSubmittingTravelers.value) return
    travelerSubmitError.value = null
    const totalPax = (bookingStore.form?.ADT ?? 0) + (bookingStore.form?.CNN ?? 0) + (bookingStore.form?.INF ?? 0)
    if (totalPax > 9) {
        travelerSubmitError.value = 'Maximum 9 travelers allowed per booking.'
        return
    }
    if (paxesSubmitted.value) {
        try {
            await syncTravelerPreferences(travelerForms.value)
            goToStep('addons')
        } catch {
            // travelerSubmitError set in composable
        }
        return
    }
    try {
        await submitTravelers(travelerForms.value, travelerFiles.value, travelers.value)
        goToStep('addons')
    } catch {
        // error surfaced via travelerSubmitError
    }
}

async function handleApplySsr() {
    if (isApplyingSsr.value || ssrSubmitted.value) return
    try {
        await applySsr(travelerForms.value, travelers.value)
    } catch {
        // ssrError set in composable
    }
}

const reviewSnapshotDisplay = computed(() => liveReviewSnapshot.value ?? bookingStore.reviewSnapshot)

const reviewFareLine = computed(() => {
    const price = reviewSnapshotDisplay.value?.price ?? bookingStore.priceData
    if (!price) return null
    const cur = price.currency ?? 'BDT'
    const fmt = (n) => {
        const num = Number(n)
        return Number.isNaN(num) ? '—' : num.toLocaleString(undefined, { maximumFractionDigits: 0 })
    }
    return {
        total: `${cur} ${fmt(price.total_price)}`,
        meta: `Base ${cur} ${fmt(price.base_fare)} + Tax ${cur} ${fmt(price.total_taxes)}`,
    }
})

async function goToReviewStep() {
    if (!paxesSubmitted.value || isReviewLoading.value) return
    try {
        await syncTravelerPreferences(travelerForms.value)
        await prepareReview()
        goToStep('review')
    } catch {
        // reviewError set in composable
    }
}

onMounted(() => {
    const restored = normalizeStepId(bookingStore.activeStep)
    if (!bookingStore.paxesSubmitted && restored !== 'travelers') {
        goToStep('travelers', { skipEnter: true })
        return
    }
    activeStep.value = restored
    onEnterStep(restored)
    if (restored === 'pnr' && !bookingStore.reviewConfirmed) {
        goToStep('review', { skipEnter: true })
        return
    }
    if (restored === 'review' && !bookingStore.reviewSnapshot) {
        goToReviewStep()
    }
})

function clearReceiptModalTimer() {
    if (receiptModalTimer) {
        clearTimeout(receiptModalTimer)
        receiptModalTimer = null
    }
}

async function openReceiptModal() {
    const commit = commitDisplay.value
    if (!commit?.pnr || commit?.commit_pending || !commit?.travelport_response) return

    receiptData.value = await buildReceiptFromCommit({
        travelportResponse: commit.travelport_response,
        snapshot: bookingStore.reviewSnapshot,
        priceData: bookingStore.priceData,
        flight: bookingStore.flight,
        form: bookingStore.form,
        travelerForms: bookingStore.travelerForms,
        bookingAttemptId: bookingStore.bookingAttemptId,
    })
    showReceiptModal.value = true
}

function scheduleReceiptModal() {
    clearReceiptModalTimer()
    const commit = commitDisplay.value
    if (!commit?.pnr || commit?.commit_pending) return
    receiptModalTimer = setTimeout(() => openReceiptModal(), 2000)
}

function handleReceiptClose() {
    showReceiptModal.value = false
    clearReceiptModalTimer()
    router.push({ name: 'bookingList' })
}

async function handleConfirmBooking() {
    if (isConfirmingBooking.value) return
    try {
        await confirmBooking()
        goToStep('pnr')
        scheduleReceiptModal()
    } catch {
        // reviewError
    }
}

async function handleRetryCommit() {
    if (isConfirmingBooking.value) return
    try {
        await retryCommit()
        scheduleReceiptModal()
    } catch {
        // reviewError
    }
}

function handlePnrDone() {
    if (commitDisplay.value?.pnr && !commitDisplay.value?.commit_pending) {
        openReceiptModal()
        return
    }
    router.push({ name: 'bookingList' })
}

function handlePnrNewSearch() {
    bookingStore.clearBookingSession()
    router.push({ name: 'searchResult' })
}

</script>
<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">

        <div class="breadcrumb-title pe-3"> Flight Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        <router-link :to="{ name: 'searchResult' }">Search</router-link>

                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Traveller Info</li>
                </ol>
            </nav>
        </div>
        <!-- <div class="ms-auto">

        </div> -->
    </div>

    <div class="row">
        <div class="col-12 col-md-12 com-sm-12">
            <div class="booking-trip-bar">
                <div class="booking-trip-bar__main">
                    <div class="booking-trip-bar__route">{{ routeHeadline }}</div>
                    <div class="booking-trip-bar__meta">
                        <span v-if="dateRangeLabel" class="booking-trip-bar__chip">
                            <i class="fa-regular fa-calendar" /> {{ dateRangeLabel }}
                        </span>
                        <span class="booking-trip-bar__chip">
                            <i class="fa-solid fa-arrows-rotate" /> {{ tripType }}
                        </span>
                        <span class="booking-trip-bar__chip">
                            <i class="fa-solid fa-chair" /> {{ cabin }}
                        </span>
                        <span class="booking-trip-bar__chip">
                            <i class="fa-solid fa-users" /> {{ paxDetailLabel || `${totalPax} Travellers` }}
                        </span>
                        <span v-if="contentSource" class="booking-trip-bar__chip booking-trip-bar__chip--source">
                            {{ contentSource }}
                        </span>
                    </div>
                </div>
                <div v-if="isReviewStep && reviewFareLine" class="booking-trip-bar__payable">
                    <span class="booking-trip-bar__payable-label">Total payable</span>
                    <span class="booking-trip-bar__payable-amount">{{ reviewFareLine.total }}</span>
                    <span class="booking-trip-bar__payable-meta">{{ reviewFareLine.meta }}</span>
                </div>
                <div class="booking-trip-bar__actions">
                    <div
                        class="booking-trip-bar__timer"
                        :class="{ 'booking-trip-bar__timer--critical': timerCritical }"
                    >
                        <div class="booking-trip-bar__timer-icon">
                            <i class="fa-regular fa-clock" />
                        </div>
                        <div>
                            <span class="booking-trip-bar__timer-label">Time remaining</span>
                            <span class="booking-trip-bar__timer-value">{{ timerDisplay }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="booking-trip-bar__details"
                        @click="showFlightDetails = true"
                    >
                        <i class="fa-solid fa-plane-departure" />
                        <span>Flight Details</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row position-relative mt-4">
        <div class="col-12 col-md-12 com-sm-12">
            <div class="card m-0">
                <div class="row shadow-none rounded rounded-2 p-3 pb-0">
                    <div class="col-12">
                        <div class="row">
                            <div class="col-12">
                                <nav class="booking-wizard" aria-label="Booking progress">
                                    <button
                                        v-for="(step, stepIndex) in WIZARD_STEPS"
                                        :key="step.id"
                                        type="button"
                                        class="booking-wizard__step"
                                        :class="{
                                            'booking-wizard__step--active': activeStep === step.id,
                                            'booking-wizard__step--completed': isStepCompleted(step.id),
                                            'booking-wizard__step--disabled': !isStepUnlocked(step.id),
                                        }"
                                        :disabled="!isStepUnlocked(step.id)"
                                        :aria-current="activeStep === step.id ? 'step' : undefined"
                                        @click="handleWizardTabClick(step.id)"
                                    >
                                        <span class="booking-wizard__index">
                                            <i v-if="isStepCompleted(step.id)" class="fa-solid fa-check" />
                                            <span v-else>{{ stepIndex + 1 }}</span>
                                        </span>
                                        <span class="booking-wizard__text">
                                            <span class="booking-wizard__label">{{ step.label }}</span>
                                            <span class="booking-wizard__hint">{{ step.hint }}</span>
                                        </span>
                                        <i class="fa-solid booking-wizard__icon" :class="step.icon" />
                                    </button>
                                </nav>
                            </div>

                            <div class="col-md-12">
                                <!-- Step 1: Travelers -->
                                <div v-show="activeStep === 'travelers'" class="card fadeIn booking-step-panel">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-end mb-2">
                                            <button type="button" @click="fillTestData" class="btn btn-sm btn-warning">
                                                [DEV] Fill Test Data
                                            </button>
                                        </div>
                                        <div class="accordion" id="accordionTravelers">
                                            <div v-for="(traveler, ti) in travelers" :key="ti" class="accordion-item">
                                                <h2 class="accordion-header" :id="`th-${ti}`">
                                                    <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse"
                                                        :data-bs-target="`#tc-${ti}`"
                                                        aria-expanded="false"
                                                        :aria-controls="`tc-${ti}`">
                                                        <img v-if="traveler.type !== 'Child' && traveler.type !== 'Infant'"
                                                            src="../../../../../public/theme/Booking_Steps/traveller_icon.svg" alt="">
                                                        <i v-else class="fa-solid fa-child-reaching" style="color: #7239ea;"></i>
                                                        <span class="pt-1 ps-1">Traveller {{ ti + 1 }}: {{ traveler.label }}</span>
                                                        <div v-if="travelerForms[ti]?.isPrimaryContact" style="margin-left: 20px;"
                                                            class="badge rounded-pill text-success bg-light-success p-1 px-4">
                                                            Primary Contact</div>
                                                    </button>
                                                </h2>
                                                <div :id="`tc-${ti}`" class="accordion-collapse collapse"
                                                    :aria-labelledby="`th-${ti}`">
                                                    <div class="accordion-body traveller-accordion-body"
                                                        style="background-color: rgba(248, 252, 255, 1);">
                                                        <div class="row">
                                                            <!-- passport notice -->
                                                            <div class="col-md-12">
                                                                <div class="mt-2 mb-0 p-2 passport-notice" style="font-size: 13px !important; background-color: rgba(255, 250, 238, 1); border-radius: 5px;">
                                                                    <span class="bluesky-departure-text mobile-chips-text">
                                                                        <i style="color: rgba(240, 180, 27, 1);" class="fa fa-info-circle"></i>
                                                                        <span class="passport-notice__text" style="font-size: 12px; color: rgba(119, 95, 35, 1);">Please fill-up all the information below as same as given in your passport, to avoid complications at immigration proccess.</span>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <!-- existing traveler search -->
                                                            <div class="col-12 col-lg-12 mt-3">
                                                                <label class="form-label">Existing Traveller</label>
                                                                <input type="text" class="form-control" placeholder="Search with name, phone, email, password">
                                                            </div>
                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="text-center" style="color: rgba(161, 171, 183, 1);font-size: 10px;">Or fill up the information below</div>
                                                            </div>
                                                            <!-- name -->
                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="row bd-highlight mb-3">
                                                                    <div class="col-md-2 bd-highlight pe-3">
                                                                        <label class="form-label">Title <span class="text-danger">*</span></label>
                                                                        <Select2 v-model="travelerForms[ti].title" :options="titleOptionsFor(traveler.type)" :clearable="false" />
                                                                    </div>
                                                                    <div class="col-md-3 pe-3">
                                                                        <label class="form-label">First Name (Given Name) <span class="text-danger">*</span></label>
                                                                        <input v-model="travelerForms[ti].firstName" type="text" class="form-control" placeholder="Enter First Name">
                                                                    </div>
                                                                    <div class="col-md-3 pe-3">
                                                                        <label class="form-label">Middle Name</label>
                                                                        <input v-model="travelerForms[ti].middleName" type="text" class="form-control" placeholder="Enter Middle Name">
                                                                    </div>
                                                                    <div class="col-md-4 pe-3">
                                                                        <label class="form-label">Last Name (Sur Name) <span class="text-danger">*</span></label>
                                                                        <input v-model="travelerForms[ti].lastName" type="text" class="form-control" placeholder="Enter Last Name">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- dob with live age badge + validation -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                                                <DobWithAge
                                                                    v-model="travelerForms[ti].dob"
                                                                    :pax-type="traveler.type"
                                                                    :travel-date="bookingStore.form?.dep_date"
                                                                    placeholder="Date of Birth"
                                                                />
                                                            </div>
                                                            <!-- gender -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                                                <Select2 v-model="travelerForms[ti].gender" :options="genderOptions" placeholder="Select Gender" />
                                                            </div>
                                                            <!-- nationality -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Nationality <span class="text-danger">*</span></label>
                                                                <Select2 v-model="travelerForms[ti].nationality" :options="nationalityOptions" placeholder="Select Nationality" />
                                                            </div>
                                                            <!-- frequent flyer - adults only -->
                                                            <div v-if="traveler.type === 'Adult'" class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Frequent Flyer Number</label>
                                                                <input v-model="travelerForms[ti].frequentFlyer" type="text" class="form-control" placeholder="Enter Flyer Number">
                                                            </div>
                                                            <!-- passport -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Passport Number <span class="text-danger">*</span></label>
                                                                <input v-model="travelerForms[ti].passportNo" type="text" class="form-control" placeholder="Enter Passport Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Expiry Date <span class="text-danger">*</span></label>
                                                                <AppDatePicker v-model="travelerForms[ti].expiryDate" placeholder="Expiry Date" />
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Passport Image</label>
                                                                <ImageUploader
                                                                    v-model="travelerFiles[ti].passportFiles"
                                                                    :max-files="1"
                                                                />
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Visa Image</label>
                                                                <ImageUploader
                                                                    v-model="travelerFiles[ti].visaFiles"
                                                                    :max-files="1"
                                                                />
                                                            </div>
                                                            <!-- contact -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                                                <input v-model="travelerForms[ti].email" type="text" class="form-control" placeholder="Enter Email">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                                                <input v-model="travelerForms[ti].phone" type="text" class="form-control" placeholder="Enter Phone">
                                                            </div>
                                                            <!-- meal & wheelchair -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Meal Type</label>
                                                                <Select2 v-model="travelerForms[ti].meal" :options="mealOptions" placeholder="Choose One..." />
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label class="form-label">Wheel Chair Needed ?</label>
                                                                <Select2 v-model="travelerForms[ti].wheelchair" :options="wheelchairOptions" placeholder="Choose One..." />
                                                            </div>
                                                            <!-- primary contact checkbox - adults only -->
                                                            <div v-if="traveler.type === 'Adult'" class="col-6 col-sm-6 col-md-6 mt-3">
                                                                <div class="form-check">
                                                                    <input v-model="travelerForms[ti].isPrimaryContact" class="form-check-input" type="checkbox" :id="`primary-${ti}`" @change="setPrimaryContact(ti)">
                                                                    <label class="form-check-label" :for="`primary-${ti}`">Select as Primary Contact</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-block">
                                            <!-- <button @click="addAddOnServices()" class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button> -->
                                            <div v-if="travelerSubmitError" class="text-danger small mb-2 w-100">{{ travelerSubmitError }}</div>
                                            <button type="button" @click="handleTravelerContinue"
                                                :disabled="isSubmittingTravelers || !isTravelerFormValid"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">
                                                <template v-if="isSubmittingTravelers">
                                                    <i class="fa-solid fa-spinner fa-spin me-2"></i>
                                                    Processing...
                                                </template>
                                                <template v-else>Continue</template>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end of traveller details -->


                                <!-- Step 2: Add-ons -->
                                <div v-show="activeStep === 'addons'" class="card fadeIn booking-step-panel">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="ancillary-section">
                                                    <!-- Section Header -->
                                                    <div class="ancillary-header d-flex align-items-center justify-content-between mb-3">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="ancillary-header-icon">
                                                                <i class="fa-solid fa-bag-shopping"></i>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-0 fw-bold">Ancillary Services</h6>
                                                                <small class="text-muted" style="font-size:10px; letter-spacing:0.5px;">OPTIONAL ADD-ONS</small>
                                                            </div>
                                                        </div>
                                                        <span v-if="contentSource" class="badge ancillary-source-badge">{{ contentSource }}</span>
                                                    </div>

                                                    <!-- Loading -->
                                                    <div v-if="isShoppingAncillaries" class="ancillary-loading">
                                                        <div class="ancillary-loading-spinner">
                                                            <i class="fa-solid fa-spinner fa-spin"></i>
                                                        </div>
                                                        <span class="text-muted small ms-2">Loading ancillary options...</span>
                                                    </div>

                                                    <!-- Empty state -->
                                                    <div v-else-if="ancillaryShopData && (!ancillaryShopData.items || ancillaryShopData.items.length === 0)" class="ancillary-empty">
                                                        <i class="fa-regular fa-folder-open fa-2x mb-2" style="color:#7239ea; opacity:0.4;"></i>
                                                        <p class="text-muted small mb-0">{{ ancillaryShopData.message || 'No ancillary options found.' }}</p>
                                                    </div>

                                                    <!-- Dynamic list -->
                                                    <div v-else-if="ancillaryShopData && ancillaryShopData.items" class="ancillary-grid">
                                                        <div v-for="item in ancillaryShopData.items" :key="item.item_key || item.catalog_offering_id || item.product_id" class="ancillary-card"
                                                            :class="{ 'ancillary-card--added': isAncillaryBooked(item, selectedCoverage(item)) }">
                                                            <!-- Icon -->
                                                            <i class="fa-solid ancillary-card-icon"
                                                                :class="item.ancillary_type === 'AncillaryAirBaggage' ? 'fa-suitcase-rolling' : item.ancillary_type === 'AncillaryMeal' ? 'fa-utensils' : item.ancillary_type === 'AncillarySeat' ? 'fa-chair' : 'fa-concierge-bell'"
                                                                :style="{ color: item.ancillary_type === 'AncillaryAirBaggage' ? '#f97316' : item.ancillary_type === 'AncillaryMeal' ? '#10b981' : item.ancillary_type === 'AncillarySeat' ? '#3b82f6' : '#7239ea' }"></i>

                                                            <!-- Content -->
                                                            <div class="ancillary-card-content">
                                                                <div class="ancillary-card-title">{{ item.name || item.ancillary_type || 'Ancillary' }}</div>
                                                                <div class="ancillary-card-sub">
                                                                    {{ item.subtitle || (item.ancillary_type === 'AncillaryAirBaggage' ? 'Extra baggage' : 'Optional service') }}
                                                                    <span v-if="item.ssr_code" class="ancillary-code-badge ms-1">{{ item.ssr_code }}</span>
                                                                    <span v-else-if="item.code" class="ancillary-code-badge ms-1">{{ item.code }}</span>
                                                                </div>
                                                                <div v-if="item.scope_label" class="ancillary-card-scope">{{ item.scope_label }}</div>
                                                                <div v-if="item.can_select_coverage" class="ancillary-coverage mt-1">
                                                                    <label class="ancillary-coverage-label me-1">Coverage:</label>
                                                                    <select
                                                                        class="ancillary-coverage-select"
                                                                        :value="selectedCoverage(item)"
                                                                        @change="setCoverage(item, $event.target.value)"
                                                                    >
                                                                        <option
                                                                            v-for="opt in coverageOptions(item)"
                                                                            :key="opt"
                                                                            :value="opt"
                                                                        >
                                                                            {{ coverageLabel(opt) }}
                                                                        </option>
                                                                    </select>
                                                                    <div v-if="!hasDistinctCoveragePrices(item)" class="ancillary-coverage-note mt-1">
                                                                        Outbound and inbound prices are equal for this option.
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Price + Action -->
                                                            <div class="ancillary-card-action">
                                                                <div class="ancillary-price">
                                                                    <span class="ancillary-price-currency">{{ ancillaryCurrencyForCoverage(item, selectedCoverage(item)) }}</span>
                                                                    <span class="ancillary-price-amount">{{ ancillaryPriceForCoverage(item, selectedCoverage(item)) }}</span>
                                                                </div>
                                                                <button
                                                                    v-if="isAncillaryBooked(item, selectedCoverage(item))"
                                                                    class="ancillary-btn ancillary-btn--added"
                                                                    disabled>
                                                                    <i class="fa-solid fa-check me-1"></i> Added
                                                                </button>
                                                                <button
                                                                    v-else
                                                                    @click="bookAncillary(item, selectedCoverage(item))"
                                                                    :disabled="isBookingAncillary"
                                                                    class="ancillary-btn ancillary-btn--add">
                                                                    <i v-if="isBookingAncillary" class="fa-solid fa-spinner fa-spin"></i>
                                                                    <template v-else><i class="fa-solid fa-plus me-1"></i> Add</template>
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div v-if="bookError" class="ancillary-error mt-2">
                                                            <i class="fa-solid fa-circle-exclamation me-1"></i>{{ bookError }}
                                                        </div>
                                                    </div>

                                                    <!-- Not yet loaded -->
                                                    <div v-else class="ancillary-pending">
                                                        <i class="fa-regular fa-clock me-1" style="color:#7239ea;"></i>
                                                        <span class="text-muted small">Ancillary options will load automatically.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer booking-step-footer">
                                        <button type="button" @click="goToStep('travelers')" class="w3-button w3-dark-gray w3-round w3-medium">Back</button>
                                        <button type="button" @click="goToStep('ssr')" class="w3-button w3-blue-sky-purple w3-round w3-medium">Continue</button>
                                    </div>
                                </div>

                                <!-- Step 3: SSR -->
                                <div v-show="activeStep === 'ssr'" class="card fadeIn booking-step-panel">
                                    <div class="card-body">
                                        <div class="booking-step-section">
                                            <div class="booking-step-section__head">
                                                <i class="fa fa-wheelchair" style="color: #7239ea;"></i>
                                                <span>Special Service Requests</span>
                                            </div>
                                            <p class="text-muted small mb-2">From traveler details. Veg meal and wheelchair are sent to the airline.</p>
                                            <div v-if="ssrPreviewRows.length" class="table-responsive">
                                                <table class="table table-sm table-borderless mb-2" style="font-size: 13px;">
                                                    <thead>
                                                        <tr class="text-muted">
                                                            <th>Traveler</th>
                                                            <th>Meal</th>
                                                            <th>Wheelchair</th>
                                                            <th>SSR</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="(row, ri) in ssrPreviewRows" :key="ri">
                                                            <td>{{ row.name }}</td>
                                                            <td>{{ row.meal }}</td>
                                                            <td>{{ row.wheelchair }}</td>
                                                            <td>
                                                                <span v-if="row.willApply" class="text-success">Will apply</span>
                                                                <span v-else class="text-muted">—</span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div v-else class="text-muted small py-1">No travelers loaded.</div>
                                            <div v-if="!ssrHasRequests" class="text-muted small">No SSR to apply (set Veg meal or wheelchair Yes on traveler step).</div>
                                            <div class="d-flex justify-content-end mt-2">
                                                <button
                                                    type="button"
                                                    v-if="ssrSubmitted"
                                                    class="w3-button w3-tiny w3-blue-sky-purple w3-round"
                                                    disabled>Applied</button>
                                                <button
                                                    type="button"
                                                    v-else
                                                    @click="handleApplySsr"
                                                    :disabled="isApplyingSsr || !ssrHasRequests"
                                                    class="w3-button w3-tiny w3-blue-sky-purple w3-round">
                                                    <i v-if="isApplyingSsr" class="fa-solid fa-spinner fa-spin"></i>
                                                    <span v-else>Apply SSR to booking</span>
                                                </button>
                                            </div>
                                            <div v-if="ssrError" class="text-danger small mt-2">{{ ssrError }}</div>
                                            <div v-if="ssrSubmitted && ssrSkipped" class="text-muted small mt-1">Skipped — nothing to send.</div>
                                        </div>
                                    </div>
                                    <div class="card-footer booking-step-footer">
                                        <button type="button" @click="goToStep('addons')" class="w3-button w3-dark-gray w3-round w3-medium">Back</button>
                                        <button type="button" @click="goToReviewStep"
                                            :disabled="isReviewLoading"
                                            class="w3-button w3-blue-sky-purple w3-round w3-medium">
                                            <span v-if="isReviewLoading"><i class="fa-solid fa-spinner fa-spin"></i></span>
                                            <span v-else>Continue</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 4: Review & confirm -->
                                <div v-show="activeStep === 'review'" class="card fadeIn border-0 shadow-sm booking-step-panel">
                                    <div class="card-body p-3 p-md-4">
                                        <BookingReviewConfirm
                                            :snapshot="reviewSnapshotDisplay"
                                            :loading="isReviewLoading"
                                            :confirming="isConfirmingBooking"
                                            :error="reviewError"
                                            @back="goToStep('ssr')"
                                            @confirm="handleConfirmBooking"
                                        />
                                    </div>
                                </div>

                                <!-- Step 5: PNR -->
                                <div v-show="activeStep === 'pnr'" class="card fadeIn border-0 shadow-sm booking-step-panel">
                                    <div class="card-body p-3 p-md-4">
                                        <BookingPnrStep
                                            :pnr="commitDisplay.pnr"
                                            :reservation-identifier="commitDisplay.reservation_identifier"
                                            :reservation-status="commitDisplay.reservation_status"
                                            :commit-pending="commitDisplay.commit_pending"
                                            :commit-error="commitDisplay.commit_error"
                                            :travelport-response="commitDisplay.travelport_response"
                                            :loading="isConfirmingBooking"
                                            :workbench-expired="!!commitDisplay.workbench_expired"
                                            @back="goToStep('review')"
                                            @done="handlePnrDone"
                                            @retry="handleRetryCommit"
                                            @new-search="handlePnrNewSearch"
                                        />
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <FlightPricePanel
        :visible="showFlightDetails"
        :cached-price-data="bookingStore.priceData"
        :flight="bookingStore.flight"
        :selected-brand="bookingStore.selectedBrand"
        :form="bookingStore.form"
        @close="showFlightDetails = false"
    />

    <BookingReceiptModal
        :visible="showReceiptModal"
        :receipt="receiptData"
        @close="handleReceiptClose"
    />

    <!-- Timer-expired redirect overlay -->
    <Teleport to="body">
        <Transition name="teo-fade">
            <div v-if="timerExpiredRedirecting" class="teo-backdrop" role="alertdialog" aria-modal="true" aria-live="assertive">
                <div class="teo-box">
                    <div class="teo-icon">
                        <i class="fa-solid fa-hourglass-end" aria-hidden="true" />
                    </div>
                    <h5 class="teo-title">Session Expired</h5>
                    <p class="teo-msg">Your booking session has timed out. Redirecting to search in <strong>{{ timerExpiredCountdown }}s</strong>…</p>
                    <div class="teo-progress-track">
                        <div class="teo-progress-bar" :style="{ width: `${(timerExpiredCountdown / 5) * 100}%` }" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

</template>

<style>
.booking-trip-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem 1.25rem;
    padding: 1rem 1.25rem;
    margin: 0;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #faf8ff 100%);
    border: 1px solid rgba(114, 57, 234, 0.12);
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
}

.booking-trip-bar__main {
    flex: 1 1 280px;
    min-width: 0;
}

.booking-trip-bar__route {
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: #1f2937;
    line-height: 1.2;
    margin-bottom: 0.5rem;
}

.booking-trip-bar__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.booking-trip-bar__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 500;
    color: #4b5563;
    background: #f3f4f6;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
}

.booking-trip-bar__chip i {
    font-size: 0.7rem;
    color: #7239ea;
}

.booking-trip-bar__chip--source {
    background: #f4f0ff;
    border-color: rgba(114, 57, 234, 0.25);
    color: #7239ea;
    font-weight: 600;
}

.booking-trip-bar__payable {
    flex: 0 1 auto;
    text-align: center;
    padding: 0.35rem 1rem;
    border-left: 1px solid rgba(114, 57, 234, 0.12);
    border-right: 1px solid rgba(114, 57, 234, 0.12);
}

.booking-trip-bar__payable-label {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
}

.booking-trip-bar__payable-amount {
    display: block;
    font-size: 1.25rem;
    font-weight: 800;
    color: #7239ea;
    line-height: 1.2;
}

.booking-trip-bar__payable-meta {
    display: block;
    font-size: 0.72rem;
    color: #9ca3af;
}

.booking-trip-bar__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.65rem;
    flex-shrink: 0;
}

.booking-trip-bar__timer {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 1rem;
    min-width: 150px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f4f0ff 0%, #ede9fe 100%);
    border: 1px solid rgba(114, 57, 234, 0.15);
}

.booking-trip-bar__timer--critical {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-color: rgba(220, 38, 38, 0.25);
}

.booking-trip-bar__timer-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(114, 57, 234, 0.12);
    color: #7239ea;
    font-size: 1rem;
}

.booking-trip-bar__timer--critical .booking-trip-bar__timer-icon {
    background: rgba(220, 38, 38, 0.12);
    color: #dc2626;
}

.booking-trip-bar__timer-label {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
    line-height: 1.2;
}

.booking-trip-bar__timer-value {
    display: block;
    font-size: 1.1rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: #7239ea;
    line-height: 1.2;
}

.booking-trip-bar__timer--critical .booking-trip-bar__timer-value {
    color: #dc2626;
}

.booking-trip-bar__details {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.55rem 1.1rem;
    border: none;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.booking-trip-bar__details:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
    color: #fff;
}

.booking-trip-bar__details i {
    font-size: 0.95rem;
}

@media (max-width: 767px) {
    .booking-trip-bar__payable {
        width: 100%;
        border-left: none;
        border-right: none;
        border-top: 1px solid rgba(114, 57, 234, 0.12);
        padding-top: 0.75rem;
    }
    .booking-trip-bar__actions {
        width: 100%;
    }
    .booking-trip-bar__timer,
    .booking-trip-bar__details {
        flex: 1 1 auto;
    }
}

.booking-wizard {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 16px;
    padding: 4px;
    background: linear-gradient(180deg, rgba(114, 57, 234, 0.06), rgba(114, 57, 234, 0.02));
    border: 1px solid rgba(114, 57, 234, 0.12);
    border-radius: 12px;
}

.booking-wizard__step {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    border: 1px solid transparent;
    border-radius: 10px;
    background: transparent;
    padding: 10px 12px;
    text-align: left;
    transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.booking-wizard__step:hover:not(:disabled) {
    background: rgba(114, 57, 234, 0.06);
    border-color: rgba(114, 57, 234, 0.18);
}

.booking-wizard__step--active {
    background: #fff;
    border-color: rgba(114, 57, 234, 0.35);
    box-shadow: 0 4px 14px rgba(114, 57, 234, 0.12);
}

.booking-wizard__step--completed:not(.booking-wizard__step--active) .booking-wizard__index {
    background: rgba(34, 197, 94, 0.14);
    color: #16a34a;
    border-color: rgba(34, 197, 94, 0.35);
}

.booking-wizard__step--disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.booking-wizard__index {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 1px solid rgba(114, 57, 234, 0.25);
    color: #7239ea;
    background: rgba(114, 57, 234, 0.08);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}

.booking-wizard__step--active .booking-wizard__index {
    background: linear-gradient(135deg, #7239ea, #a855f7);
    color: #fff;
    border-color: transparent;
}

.booking-wizard__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.booking-wizard__label {
    font-size: 13px;
    font-weight: 700;
    color: var(--bs-body-color);
    line-height: 1.2;
}

.booking-wizard__hint {
    font-size: 10px;
    color: var(--bs-secondary-color, #6c757d);
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.booking-wizard__step--active .booking-wizard__label {
    color: #5b21b6;
}

.booking-wizard__icon {
    color: rgba(114, 57, 234, 0.45);
    font-size: 14px;
    flex-shrink: 0;
}

.booking-wizard__step--active .booking-wizard__icon {
    color: #7239ea;
}

.booking-step-panel {
    border: 1px solid rgba(114, 57, 234, 0.1);
}

.booking-step-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.booking-step-section {
    background: rgba(248, 252, 255, 1);
    border: 1px solid rgba(114, 57, 234, 0.1);
    border-radius: 12px;
    padding: 16px;
}

.booking-step-section__head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--bs-body-color);
}

@media (max-width: 1199px) {
    .booking-wizard {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 1200px) and (max-width: 1399px) {
    .booking-wizard__hint {
        display: none;
    }
}

@media (max-width: 991px) {
    .booking-wizard {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .booking-wizard__icon {
        display: none;
    }
}

@media (max-width: 575px) {
    .booking-wizard {
        grid-template-columns: 1fr;
    }
}

.w3-blue-sky-purple {
    color: #fff !important;
    background-color: #7239ea;
}

/* ── Ancillary Services ───────────────────────────────────────── */
.ancillary-section {
    background: var(--bs-body-bg, #fff);
    border: 1px solid var(--bs-border-color, #e9ecef);
    border-radius: 12px;
    padding: 16px;
}

.ancillary-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #7239ea, #a855f7);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 15px;
    flex-shrink: 0;
}

.ancillary-source-badge {
    background: rgba(114, 57, 234, 0.12);
    color: #7239ea;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.8px;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid rgba(114, 57, 234, 0.25);
}

[data-bs-theme=dark] .ancillary-source-badge {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
    border-color: rgba(168, 85, 247, 0.3);
}

.ancillary-loading,
.ancillary-empty,
.ancillary-pending {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 24px 0;
    text-align: center;
}

.ancillary-loading {
    flex-direction: row;
}

.ancillary-loading-spinner {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(114, 57, 234, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #7239ea;
    font-size: 13px;
}

.ancillary-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 4px;
}

.ancillary-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--bs-tertiary-bg, #f8f9fa);
    border: 1px solid var(--bs-border-color, #e9ecef);
    border-radius: 10px;
    padding: 12px 14px;
    transition: box-shadow 0.2s, border-color 0.2s;
}

.ancillary-card:hover {
    border-color: rgba(114, 57, 234, 0.4);
    box-shadow: 0 2px 12px rgba(114, 57, 234, 0.08);
}

.ancillary-card--added {
    border-color: rgba(16, 185, 129, 0.4);
    background: rgba(16, 185, 129, 0.04);
}

[data-bs-theme=dark] .ancillary-card--added {
    background: rgba(16, 185, 129, 0.07);
    border-color: rgba(16, 185, 129, 0.35);
}

.ancillary-card-icon {
    font-size: 26px;
    flex-shrink: 0;
    width: 32px;
    text-align: center;
}

.ancillary-card-content {
    flex: 1;
    min-width: 0;
}

.ancillary-card-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--bs-body-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}

.ancillary-card-sub {
    font-size: 11px;
    color: var(--bs-secondary-color, #6c757d);
    margin-top: 3px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.ancillary-card-scope {
    font-size: 10px;
    margin-top: 3px;
    color: #7239ea;
    font-weight: 600;
}

.ancillary-coverage {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.ancillary-coverage-label {
    font-size: 10px;
    color: var(--bs-secondary-color, #6c757d);
    font-weight: 600;
}

.ancillary-coverage-select {
    font-size: 10px;
    border: 1px solid #d1c4f7;
    border-radius: 6px;
    padding: 1px 6px;
    color: #5b21b6;
    background: #f6f2ff;
    outline: none;
}

.ancillary-coverage-note {
    width: 100%;
    font-size: 10px;
    color: var(--bs-secondary-color, #6c757d);
    line-height: 1.2;
}

.ancillary-code-badge {
    background: rgba(114, 57, 234, 0.1);
    color: #7239ea;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 20px;
    letter-spacing: 0.4px;
}

[data-bs-theme=dark] .ancillary-code-badge {
    background: rgba(168, 85, 247, 0.18);
    color: #c084fc;
}

.ancillary-card-action {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

.ancillary-price {
    display: flex;
    align-items: baseline;
    gap: 3px;
    line-height: 1;
}

.ancillary-price-currency {
    font-size: 10px;
    font-weight: 600;
    color: var(--bs-secondary-color, #6c757d);
    letter-spacing: 0.5px;
}

.ancillary-price-amount {
    font-size: 15px;
    font-weight: 700;
    color: var(--bs-body-color);
}

.ancillary-btn {
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    white-space: nowrap;
    transition: opacity 0.2s, transform 0.15s;
}

.ancillary-btn:hover:not(:disabled) {
    opacity: 0.88;
    transform: translateY(-1px);
}

.ancillary-btn:disabled {
    cursor: default;
}

.ancillary-btn--add {
    background: linear-gradient(135deg, #7239ea, #a855f7);
    color: #fff;
    box-shadow: 0 3px 10px rgba(114, 57, 234, 0.3);
}

.ancillary-btn--added {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.35);
}

[data-bs-theme=dark] .ancillary-btn--added {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border-color: rgba(52, 211, 153, 0.3);
}

.ancillary-error {
    font-size: 12px;
    color: #dc3545;
    display: flex;
    align-items: center;
}

/* ── Dark mode overrides ──────────────────────────────────────── */
[data-bs-theme="dark"] .booking-trip-bar {
    background: var(--bs-card-bg) !important;
    border-color: rgba(114, 57, 234, 0.2);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}
[data-bs-theme="dark"] .booking-trip-bar__route {
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .booking-trip-bar__chip {
    background: rgba(255, 255, 255, 0.06);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}
[data-bs-theme="dark"] .booking-trip-bar__chip--source {
    background: rgba(114, 57, 234, 0.18);
    border-color: rgba(114, 57, 234, 0.35);
    color: #c084fc;
}
[data-bs-theme="dark"] .booking-trip-bar__payable {
    border-color: rgba(114, 57, 234, 0.15);
}
[data-bs-theme="dark"] .booking-trip-bar__payable-label,
[data-bs-theme="dark"] .booking-trip-bar__payable-meta,
[data-bs-theme="dark"] .booking-trip-bar__timer-label {
    color: var(--bs-secondary-color);
}
[data-bs-theme="dark"] .booking-trip-bar__timer {
    background: rgba(114, 57, 234, 0.12);
    border-color: rgba(114, 57, 234, 0.25);
}
[data-bs-theme="dark"] .booking-trip-bar__timer--critical {
    background: rgba(220, 38, 38, 0.12);
    border-color: rgba(220, 38, 38, 0.25);
}
[data-bs-theme="dark"] .booking-wizard {
    background: rgba(114, 57, 234, 0.05);
    border-color: rgba(114, 57, 234, 0.15);
}
[data-bs-theme="dark"] .booking-wizard__step--active {
    background: rgba(114, 57, 234, 0.14);
    border-color: rgba(114, 57, 234, 0.4);
    box-shadow: 0 4px 14px rgba(114, 57, 234, 0.18);
}
[data-bs-theme="dark"] .booking-wizard__step--active .booking-wizard__label {
    color: #c084fc;
}
[data-bs-theme="dark"] .booking-step-section {
    background: var(--bs-card-bg) !important;
    border-color: rgba(114, 57, 234, 0.15);
}
[data-bs-theme="dark"] .traveller-accordion-body {
    background-color: var(--bs-card-bg) !important;
}
[data-bs-theme="dark"] .passport-notice {
    background-color: rgba(240, 180, 27, 0.1) !important;
}
[data-bs-theme="dark"] .passport-notice__text {
    color: #fcd34d !important;
}
[data-bs-theme="dark"] .ancillary-coverage-select {
    background: rgba(114, 57, 234, 0.12);
    color: #c084fc;
    border-color: rgba(114, 57, 234, 0.3);
}

/* ── Timer-expired overlay ─────────────────────────────────── */
.teo-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
}

.teo-box {
    background: #fff;
    border-radius: 16px;
    padding: 2.25rem 2rem;
    text-align: center;
    max-width: 360px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
    animation: teo-pop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

[data-bs-theme="dark"] .teo-box {
    background: #1e1e2d;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.teo-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #ef4444;
}

.teo-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.5rem;
}
[data-bs-theme="dark"] .teo-title { color: #f1f5f9; }

.teo-msg {
    font-size: 0.88rem;
    color: #64748b;
    margin-bottom: 1.25rem;
    line-height: 1.55;
}
[data-bs-theme="dark"] .teo-msg { color: #94a3b8; }

.teo-progress-track {
    height: 5px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
}
[data-bs-theme="dark"] .teo-progress-track { background: rgba(255, 255, 255, 0.1); }

.teo-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #ef4444, #f97316);
    border-radius: 999px;
    transition: width 1s linear;
}

@keyframes teo-pop {
    from { transform: scale(0.85); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
}

.teo-fade-enter-active { transition: opacity 0.2s ease; }
.teo-fade-leave-active { transition: opacity 0.15s ease; }
.teo-fade-enter-from,
.teo-fade-leave-to     { opacity: 0; }
</style>
