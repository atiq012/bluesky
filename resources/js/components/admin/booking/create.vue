<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { ref, reactive, computed, watch, onMounted, onUnmounted } from "vue";
import { useRouter } from 'vue-router';
import { storeToRefs } from "pinia";
import { useBookingStore } from '../../../stores/bookingStore';
import FlightPricePanel from '../../search/FlightPricePanel.vue';
import Select2 from '../../common/Select2.vue';
import AppDatePicker from '../../common/AppDatePicker.vue';
import DobWithAge from '../../common/DobWithAge.vue';
import ImageUploader from '../../common/ImageUploader.vue';
import SearchInput from '../../common/SearchInput.vue';
import LoadingSpinner from '../../common/LoadingSpinner.vue';
import { useTpV2AddTraveler } from '../../../composables/useTpV2AddTraveler';
import { useTpV2Ancillary } from '../../../composables/useTpV2Ancillary';
import { useTpV2PreCommit } from '../../../composables/useTpV2PreCommit';
import { useTpV2BookingReview } from '../../../composables/useTpV2BookingReview';
import BookingReviewConfirm from './BookingReviewConfirm.vue';
import BookingFareSidebar from './BookingFareSidebar.vue';
import BookingTripSidebar from './BookingTripSidebar.vue';
import BookingConfirmModal from './BookingConfirmModal.vue';
import { buildReceiptFromCommit } from '../../../utils/buildReceiptFromCommit';
import { useAuthStore } from '../../../stores/authStore';
import { useSearchStore } from '../../../stores/searchStore';

const router = useRouter();
const bookingStore = useBookingStore();
const searchStore = useSearchStore();
const authStore = useAuthStore();
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
const existingTravelerQuery = reactive({})

const WIZARD_STEPS = [
    { id: 'travelers', label: 'Travelers', hint: 'Passenger details', icon: 'fa-user-group' },
    { id: 'addons', label: 'Add-ons', hint: 'Optional extras', icon: 'fa-suitcase-rolling' },
    { id: 'ssr', label: 'SSR', hint: 'Special requests', icon: 'fa-wheelchair' },
    { id: 'review', label: 'Review & confirm', hint: 'Verify and book', icon: 'fa-clipboard-check' },
]

const LEGACY_STEP_MAP = {
    travelerDetails: 'travelers',
    addonesSevice: 'addons',
    requests: 'ssr',
    couponOffers: 'ssr',
    agency: 'ssr',
    reviewConfirm: 'review',
    reviewPayment: 'review',
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

const visibleWizardSteps = computed(() => WIZARD_STEPS)

const commitDisplay = computed(() => bookingStore.commitResult ?? {})
const bookingFullyCommitted = computed(() => !!commitDisplay.value?.pnr && !commitDisplay.value?.commit_pending)
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
})

const timerDigits = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60)
    const s = remainingSeconds.value % 60
    const mm = String(m).padStart(2, '0')
    const ss = String(s).padStart(2, '0')
    return [mm[0], mm[1], ss[0], ss[1]]
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
            handleStartNewSearch()
        }
    }, 1000)
})

// --- Flight Details Panel ---
const showFlightDetails = ref(false)
const showConfirmModal = ref(false)
const confirmReceipt = ref(null)

// --- Dynamic travelers ---
const travelers = computed(() => {
    const f = bookingStore.form
    if (!f) return [{ type: 'Adult', label: 'Adult', isPrimary: true, hasAge: false }]
    const list = []
    const adt = Number(f.ADT ?? 1)
    const cnn = Number(f.CNN ?? 0)
    const kid = Number(f.KID ?? 0)
    const inf = Number(f.INF ?? 0)
    const ins = Number(f.INS ?? 0)
    for (let i = 0; i < adt; i++) list.push({ type: 'Adult',  label: 'Adult',        isPrimary: i === 0, hasAge: false })
    for (let i = 0; i < cnn; i++) list.push({ type: 'Child',  label: 'Children',     isPrimary: false,   hasAge: true  })
    for (let i = 0; i < kid; i++) list.push({ type: 'Child',  label: 'Kids',         isPrimary: false,   hasAge: true, ageBand: 'kids' })
    for (let i = 0; i < inf; i++) list.push({ type: 'Infant', label: 'Infant',       isPrimary: false,   hasAge: true  })
    for (let i = 0; i < ins; i++) list.push({ type: 'Infant', label: 'Infant (seat)', isPrimary: false,  hasAge: true, seat: true })
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
    { value: 'Miss', label: 'Miss' },
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
    const forms = travelerForms.value
    // Exactly one primary contact must be selected
    const primaryCount = forms.filter((f) => f?.isPrimaryContact).length
    if (primaryCount !== 1) return false

    return travelers.value.every((t, i) => {
        const f = forms[i]
        if (!f) return false
        const baseOk = f.title && f.firstName?.trim() && f.lastName?.trim()
            && isDobValidForType(f.dob, t.type, travelDate)
            && f.gender && f.nationality
            && f.passportNo?.trim() && f.expiryDate
        if (!baseOk) return false
        // Email + phone mandatory only for selected Primary Contact
        if (f.isPrimaryContact) {
            return Boolean(f.email?.trim() && f.phone?.trim())
        }
        return true
    })
})

const devTestData = {
    Adult: [
        { title: 'Mr',   firstName: 'Md Shakaouth', middleName: '', lastName: 'Hossain', dob: '01-Jan-1988', gender: 'Male',   passportNo: 'BS456789', expiryDate: '05-Jun-2030', email: 'shakaouth.hossain@galaxybd.com', phone: '01787688855' },
        { title: 'Mr',   firstName: 'Rafiq',  middleName: '',          lastName: 'Islam',   dob: '20-Mar-1985', gender: 'Male',   passportNo: 'BS456790', expiryDate: '05-Jun-2030', email: 'rafiq.islam@galaxybd.com', phone: '01787688856' },
        { title: 'Mr',   firstName: 'Kamal',  middleName: '',          lastName: 'Ahmed',   dob: '10-Jul-1992', gender: 'Male',   passportNo: 'BS456791', expiryDate: '05-Jun-2030', email: 'kamal.ahmed@galaxybd.com', phone: '01787688857' },
    ],
    Child: [
        { title: 'Mstr', firstName: 'Shohebur', middleName: '', lastName: 'Rahman', dob: '01-Jun-2015', gender: 'Male', passportNo: 'BS34534', expiryDate: '06-Jun-2030', email: 'shohebur.rahman@galaxybd.com', phone: '01714567899' },
        { title: 'Miss', firstName: 'Nadia', middleName: '', lastName: 'Akter', dob: '15-Apr-2017', gender: 'Female', passportNo: 'BS34535', expiryDate: '06-Jun-2030', email: 'nadia.akter@galaxybd.com', phone: '01714567900' },
        { title: 'Mstr', firstName: 'Arif', middleName: '', lastName: 'Karim', dob: '01-Jun-2023', gender: 'Male', passportNo: 'BS34536', expiryDate: '06-Jun-2030', email: 'arif.karim@galaxybd.com', phone: '01714567901' },
        { title: 'Miss', firstName: 'Maya', middleName: '', lastName: 'Chowdhury', dob: '10-Sep-2022', gender: 'Female', passportNo: 'BS34537', expiryDate: '06-Jun-2030', email: 'maya.chowdhury@galaxybd.com', phone: '01714567902' },
    ],
    Infant: [
        { title: 'Miss', firstName: 'Shahira', middleName: '', lastName: 'Sadik', dob: '01-Jun-2025', gender: 'Female', passportNo: 'SB12345', expiryDate: '13-Jun-2030', email: 'shahira.sadik@galaxybd.com', phone: '01714567903' },
        { title: 'Mstr', firstName: 'Rafi', middleName: '', lastName: 'Khan', dob: '10-Aug-2025', gender: 'Male', passportNo: 'SB12346', expiryDate: '13-Jun-2030', email: 'rafi.khan@galaxybd.com', phone: '01714567904' },
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
            middleName:  '',
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
    return paxesSubmitted.value
}

function isStepCompleted(stepId) {
    const idx = WIZARD_STEPS.findIndex(s => s.id === stepId)
    if (idx < 0) return false
    if (stepId === 'travelers') return paxesSubmitted.value
    if (stepId === 'review') return bookingStore.reviewConfirmed
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

// Sidebar back button — mirrors each step's previous footer back handler
function handleSidebarBack() {
    if (activeStep.value === 'travelers') {
        router.push({ name: 'searchResult' })
        return
    }
    if (activeStep.value === 'addons') {
        goToStep('travelers')
        return
    }
    if (activeStep.value === 'ssr') {
        goToStep('addons')
        return
    }
    if (activeStep.value === 'review') {
        goToStep('ssr')
    }
}

function handleWizardTabClick(stepId) {
    if (stepId === 'review') {
        goToReviewStep()
        return
    }
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
    if (restored === 'review' && !bookingStore.reviewSnapshot) {
        goToReviewStep()
    }
})

async function openConfirmModal() {
    const commit = commitDisplay.value
    confirmReceipt.value = (commit?.pnr && !commit?.commit_pending && commit?.travelport_response)
        ? await buildReceiptFromCommit({
            travelportResponse: commit.travelport_response,
            snapshot: bookingStore.reviewSnapshot,
            priceData: bookingStore.priceData,
            flight: bookingStore.flight,
            form: bookingStore.form,
            travelerForms: bookingStore.travelerForms,
            bookingAttemptId: commit.attempt_ref ?? bookingStore.bookingAttemptId,
            bookedBy: authStore.name || null,
            bookedOn: commit.created_at ?? commit.committed_at ?? null,
        })
        : null
    showConfirmModal.value = true
}

// Booked fare no longer valid — wipe search results + session timer immediately
function stopLocalBookingTimer() {
    if (timerInterval) {
        clearInterval(timerInterval)
        timerInterval = null
    }
}

function clearSearchAfterBookingSuccess() {
    searchStore.clearSearch()
    bookingStore.timerStartedAt = null
    stopLocalBookingTimer()
}

async function handleConfirmBooking() {
    if (isConfirmingBooking.value) return
    try {
        await confirmBooking()
        await openConfirmModal()
        if (bookingFullyCommitted.value) {
            clearSearchAfterBookingSuccess()
        }
    } catch {
        // reviewError
    }
}

async function handleRetryCommit() {
    if (isConfirmingBooking.value) return
    try {
        await retryCommit()
        await openConfirmModal()
        if (bookingFullyCommitted.value) {
            clearSearchAfterBookingSuccess()
        }
    } catch {
        // reviewError
    }
}

function handleConfirmModalGoToList() {
    showConfirmModal.value = false
    if (bookingFullyCommitted.value) {
        clearSearchAfterBookingSuccess()
        bookingStore.clearBookingSession()
    }
    router.push({ name: 'bookingList' })
}

function handleStartNewSearch() {
    clearSearchAfterBookingSuccess()
    bookingStore.clearBookingSession()
    router.push({ name: 'searchResult' })
}

// Sidebar must stick below the fixed topbar (60px) AND the sticky breadcrumb
// header — measured live since the header's height varies with content/wrapping.
const stickyHeaderRef = ref(null)
const sidebarStickyTop = ref(90)
function updateSidebarStickyTop() {
    if (stickyHeaderRef.value) {
        sidebarStickyTop.value = 60 + stickyHeaderRef.value.offsetHeight + 12
    }
}
onMounted(() => {
    updateSidebarStickyTop()
    window.addEventListener('resize', updateSidebarStickyTop)
})
onUnmounted(() => {
    window.removeEventListener('resize', updateSidebarStickyTop)
})

</script>
<template>
    <div class="booking-sticky-header" ref="stickyHeaderRef">
        <AppBreadcrumbs
            title="Flight Management"
            :back-to="{ name: 'searchResult' }"
            :breadcrumbs="[
                { label: 'Dashboard', to: { name: 'Home' } },
                { label: 'Search', to: { name: 'searchResult' } },
                { label: 'Booking' },
            ]"
        >
            <template #actions>
                <div class="booking-header-actions">
                    <button
                        type="button"
                        class="booking-flight-details-btn"
                        title="Flight Details"
                        @click="showFlightDetails = true"
                    >
                        <i class="fa-solid fa-plane-departure" />
                    </button>
                    <div
                        class="compact-timer-block"
                        :class="{ 'compact-timer-block--critical': timerCritical }"
                    >
                        <i class="bx bx-time-five compact-timer-icon"></i>
                        <div class="compact-timer-digits">
                            <div class="digit-slot">
                                <Transition name="digit-slip">
                                    <span :key="timerDigits[0]" class="digit-val">{{ timerDigits[0] }}</span>
                                </Transition>
                            </div>
                            <div class="digit-slot">
                                <Transition name="digit-slip">
                                    <span :key="timerDigits[1]" class="digit-val">{{ timerDigits[1] }}</span>
                                </Transition>
                            </div>
                            <span class="digit-colon">:</span>
                            <div class="digit-slot">
                                <Transition name="digit-slip">
                                    <span :key="timerDigits[2]" class="digit-val">{{ timerDigits[2] }}</span>
                                </Transition>
                            </div>
                            <div class="digit-slot">
                                <Transition name="digit-slip">
                                    <span :key="timerDigits[3]" class="digit-val">{{ timerDigits[3] }}</span>
                                </Transition>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </AppBreadcrumbs>
    </div>

    <div class="row position-relative mt-4">
        <div class="col-12 col-md-12 com-sm-12">
            <div class="booking-page-layout">
            <div class="booking-page-main">
            <div class="card m-0">
                <div class="row shadow-none rounded rounded-2 p-3">
                    <div class="col-12">
                        <div class="row">
                            <div class="col-12">
                                <nav class="booking-wizard" aria-label="Booking progress">
                                    <button
                                        v-for="step in visibleWizardSteps"
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
                                        <i class="fa-solid booking-wizard__icon" :class="step.icon" />
                                        <span class="booking-wizard__label">{{ step.label }}</span>
                                    </button>
                                </nav>
                            </div>

                            <div class="col-md-12">
                                <!-- Step 1: Travelers -->
                                <div v-show="activeStep === 'travelers'" class="fadeIn">
                                    <div>
                                        <div class="accordion" id="accordionTravelers">
                                            <div v-for="(traveler, ti) in travelers" :key="ti" class="accordion-item">
                                                <h2 class="accordion-header" :id="`th-${ti}`">
                                                    <button class="accordion-button collapsed traveler-header-btn" type="button"
                                                        data-bs-toggle="collapse"
                                                        :data-bs-target="`#tc-${ti}`"
                                                        aria-expanded="false"
                                                        :aria-controls="`tc-${ti}`">
                                                        <span class="traveler-avatar" :class="`traveler-avatar--${traveler.type.toLowerCase()}`">
                                                            <img v-if="traveler.type !== 'Child' && traveler.type !== 'Infant'"
                                                                src="../../../../../public/theme/Booking_Steps/traveller_icon.svg" alt="">
                                                            <i v-else class="fa-solid fa-child-reaching"></i>
                                                        </span>
                                                        <span class="traveler-header-text">
                                                            <span class="traveler-header-index">Traveller {{ ti + 1 }}</span>
                                                            <span class="traveler-header-type">{{ traveler.label }}</span>
                                                        </span>
                                                        <span v-if="travelerForms[ti]?.isPrimaryContact" class="traveler-primary-badge">
                                                            <i class="fa-solid fa-circle-check"></i> Primary Contact
                                                        </span>
                                                    </button>
                                                </h2>
                                                <div :id="`tc-${ti}`" class="accordion-collapse collapse"
                                                    :aria-labelledby="`th-${ti}`">
                                                    <div class="accordion-body traveller-accordion-body"
                                                        style="background-color: rgba(248, 252, 255, 1);">
                                                        <div class="row">
                                                            <!-- passport notice -->
                                                            <!-- <div class="col-md-12">
                                                                <div class="mt-2 mb-0 p-2 passport-notice" style="font-size: 13px !important; background-color: rgba(255, 250, 238, 1); border-radius: 5px;">
                                                                    <span class="bluesky-departure-text mobile-chips-text">
                                                                        <i style="color: rgba(240, 180, 27, 1);" class="fa fa-info-circle"></i>
                                                                        <span class="passport-notice__text" style="font-size: 12px; color: rgba(119, 95, 35, 1);">Please fill-up all the information below as same as given in your passport, to avoid complications at immigration proccess.</span>
                                                                    </span>
                                                                </div>
                                                            </div> -->
                                                            <!-- existing traveler search -->
                                                            <div class="col-8 offset-2 mt-3 text-center">
                                                                <label class="form-label d-block">Existing Traveller</label>
                                                                <SearchInput v-model="existingTravelerQuery[ti]" placeholder="Search by Name, Phone, Email, Passport No" />
                                                            </div>
                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="text-center" style="color: rgba(161, 171, 183, 1);font-size: 10px;">Or fill up the information below</div>
                                                            </div>
                                                            <!-- Personal Details -->
                                                            <div class="col-12 mt-4">
                                                                <div class="traveler-form-section-title">
                                                                    <i class="fa-solid fa-id-card"></i>
                                                                    <span>Personal Details</span>
                                                                    <button v-if="ti === 0" type="button" @click="fillTestData" class="btn btn-sm btn-warning ms-auto auto-fill-btn">
                                                                        Auto Fill
                                                                    </button>
                                                                </div>
                                                                <div class="mt-2 p-2 passport-notice">
                                                                    <i class="fa fa-info-circle passport-notice__icon"></i>
                                                                    <span class="passport-notice__text">
                                                                        Enter the name exactly as printed on the passport. Given Name = passport "Given Name(s)" (including middle name, if any), Surname = passport "Surname". Any mismatch may block check-in or immigration.
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-md-6 mt-2">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <label class="form-label mb-0 text-nowrap personal-detail-label">Title <span class="text-danger">*</span></label>
                                                                    <div class="flex-grow-1">
                                                                        <Select2 v-model="travelerForms[ti].title" :options="titleOptionsFor(traveler.type)" />
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                                    <label class="form-label mb-0 text-nowrap personal-detail-label">Given Name <span class="text-danger">*</span></label>
                                                                    <div class="flex-grow-1">
                                                                        <input v-model="travelerForms[ti].firstName" type="text" class="form-control" placeholder="Given name(s) as in passport">
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                                    <label class="form-label mb-0 text-nowrap personal-detail-label">Surname <span class="text-danger">*</span></label>
                                                                    <div class="flex-grow-1">
                                                                        <input v-model="travelerForms[ti].lastName" type="text" class="form-control" placeholder="Surname as in passport">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 col-md-6 mt-2">
                                                                <!-- dob with live age badge + validation -->
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <label class="form-label mb-0 text-nowrap personal-detail-label">Date of Birth <span class="text-danger">*</span></label>
                                                                    <div class="flex-grow-1">
                                                                        <DobWithAge
                                                                            v-model="travelerForms[ti].dob"
                                                                            :pax-type="traveler.type"
                                                                            :travel-date="bookingStore.form?.dep_date"
                                                                            placeholder="Date of Birth"
                                                                        />
                                                                    </div>
                                                                </div>
                                                                <!-- gender -->
                                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                                    <label class="form-label mb-0 text-nowrap personal-detail-label">Gender <span class="text-danger">*</span></label>
                                                                    <div class="flex-grow-1">
                                                                        <Select2 v-model="travelerForms[ti].gender" :options="genderOptions" placeholder="Select Gender" />
                                                                    </div>
                                                                </div>
                                                                <!-- nationality -->
                                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                                    <label class="form-label mb-0 text-nowrap personal-detail-label">Nationality <span class="text-danger">*</span></label>
                                                                    <div class="flex-grow-1">
                                                                        <Select2 v-model="travelerForms[ti].nationality" :options="nationalityOptions" placeholder="Select Nationality" />
                                                                    </div>
                                                                </div>
                                                                <!-- frequent flyer - adults only -->
                                                                <div v-if="traveler.type === 'Adult'" class="d-flex align-items-center gap-2 mt-1">
                                                                    <label class="form-label mb-0 text-nowrap personal-detail-label">Frequent Flyer No</label>
                                                                    <div class="flex-grow-1">
                                                                        <input v-model="travelerForms[ti].frequentFlyer" type="text" class="form-control" placeholder="Enter Flyer Number">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Travel Document -->
                                                            <div class="col-12 mt-5">
                                                                <div class="traveler-form-section-title">
                                                                    <i class="fa-solid fa-passport"></i>
                                                                    <span>Travel Document</span>
                                                                </div>
                                                            </div>
                                                            <!-- passport -->
                                                            <div class="col-12 col-sm-6 col-md-4 mt-2">
                                                                <label class="form-label">Passport Number <span class="text-danger">*</span></label>
                                                                <input v-model="travelerForms[ti].passportNo" type="text" class="form-control" placeholder="Enter Passport Number">
                                                                <label class="form-label mt-2">Expiry Date <span class="text-danger">*</span></label>
                                                                <AppDatePicker v-model="travelerForms[ti].expiryDate" placeholder="Expiry Date" />
                                                            </div>
                                                            <div class="col-6 col-sm-3 col-md-4 mt-2">
                                                                <label class="form-label">Passport Image</label>
                                                                <ImageUploader
                                                                    v-model="travelerFiles[ti].passportFiles"
                                                                    :max-files="1"
                                                                />
                                                            </div>
                                                            <div class="col-6 col-sm-3 col-md-4 mt-2">
                                                                <label class="form-label">Visa Image</label>
                                                                <ImageUploader
                                                                    v-model="travelerFiles[ti].visaFiles"
                                                                    :max-files="1"
                                                                />
                                                            </div>

                                                            <!-- Contact -->
                                                            <div class="col-12 col-md-7 mt-5">
                                                                <div class="traveler-form-section-title">
                                                                    <i class="fa-solid fa-address-book"></i>
                                                                    <span>Contact</span>
                                                                    <div v-if="traveler.type === 'Adult'" class="form-check ms-auto mb-0 contact-primary-check">
                                                                        <input v-model="travelerForms[ti].isPrimaryContact" class="form-check-input" type="checkbox" :id="`primary-${ti}`" @change="setPrimaryContact(ti)">
                                                                        <label class="form-check-label" :for="`primary-${ti}`">Select as Primary Contact</label>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-8 mt-2">
                                                                        <label class="form-label">
                                                                            Email
                                                                            <span v-if="travelerForms[ti]?.isPrimaryContact" class="text-danger">*</span>
                                                                        </label>
                                                                        <input v-model="travelerForms[ti].email" type="text" class="form-control" placeholder="Enter Email">
                                                                    </div>
                                                                    <div class="col-4 mt-2">
                                                                        <label class="form-label">
                                                                            Phone
                                                                            <span v-if="travelerForms[ti]?.isPrimaryContact" class="text-danger">*</span>
                                                                        </label>
                                                                        <input v-model="travelerForms[ti].phone" type="text" class="form-control" placeholder="Enter Phone">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- SSR -->
                                                            <div class="col-12 col-md-5 mt-5 ssr-section">
                                                                <div class="traveler-form-section-title">
                                                                    <i class="fa-solid fa-wheelchair"></i>
                                                                    <span>SSR</span>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-6 mt-2">
                                                                        <label class="form-label">Meal Type</label>
                                                                        <Select2 v-model="travelerForms[ti].meal" :options="mealOptions" placeholder="Choose One..." />
                                                                    </div>
                                                                    <div class="col-6 mt-2">
                                                                        <label class="form-label">Wheel Chair Need ?</label>
                                                                        <Select2 v-model="travelerForms[ti].wheelchair" :options="wheelchairOptions" placeholder="Choose One..." />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- traveler-level error surfaces here; Continue action lives in the sidebar footer now -->
                                                            <div v-if="ti === travelers.length - 1 && travelerSubmitError" class="col-12 mt-3 text-end">
                                                                <div class="text-danger small mb-0">{{ travelerSubmitError }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
                                                            <LoadingSpinner :inline="true" size="sm" />
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
                                                                    <LoadingSpinner v-if="isBookingAncillary" :inline="true" size="sm" />
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
                                                    <LoadingSpinner v-if="isApplyingSsr" :inline="true" size="sm" class="text-white" />
                                                    <span v-else>Apply SSR to booking</span>
                                                </button>
                                            </div>
                                            <div v-if="ssrError" class="text-danger small mt-2">{{ ssrError }}</div>
                                            <div v-if="ssrSubmitted && ssrSkipped" class="text-muted small mt-1">Skipped — nothing to send.</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 4: Review & confirm -->
                                <div v-show="activeStep === 'review'" class="fadeIn">
                                    <BookingReviewConfirm
                                        :snapshot="reviewSnapshotDisplay"
                                        :loading="isReviewLoading"
                                        :error="reviewError"
                                    />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <aside class="booking-page-sidebar" :style="{ top: sidebarStickyTop + 'px' }">
                <!-- Gross Fare + discount only on Review; earlier steps show slim fare/baggage -->
                <BookingFareSidebar
                    v-if="activeStep === 'review'"
                    :price="bookingStore.priceData"
                />
                <BookingTripSidebar
                    v-else
                    :price="bookingStore.priceData"
                    :form="bookingStore.form"
                />

                <!-- Back/Continue live here so every step shares one location -->
                <div class="booking-sidebar-actions">
                    <div v-if="activeStep === 'travelers' && travelerSubmitError" class="text-danger small mb-2">{{ travelerSubmitError }}</div>
                    <div v-if="activeStep === 'review' && reviewError" class="text-danger small mb-2">{{ reviewError }}</div>
                    <div class="booking-sidebar-actions__row">
                        <button
                            type="button"
                            class="booking-sidebar-back-btn"
                            title="Back"
                            @click="handleSidebarBack"
                        >
                            <i class="fa-solid fa-arrow-left" aria-hidden="true" />
                        </button>

                        <button
                            v-if="activeStep === 'travelers'"
                            type="button"
                            class="btn wizard-btn-continue"
                            :disabled="isSubmittingTravelers || !isTravelerFormValid"
                            @click="handleTravelerContinue"
                        >
                            <template v-if="isSubmittingTravelers">
                                <LoadingSpinner :inline="true" size="sm" class="text-white me-2" />
                                Processing...
                            </template>
                            <template v-else>
                                Continue
                                <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true" />
                            </template>
                        </button>

                        <button
                            v-else-if="activeStep === 'addons'"
                            type="button"
                            class="btn wizard-btn-continue"
                            @click="goToStep('ssr')"
                        >
                            Continue
                            <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true" />
                        </button>

                        <button
                            v-else-if="activeStep === 'ssr'"
                            type="button"
                            class="btn wizard-btn-continue"
                            :disabled="isReviewLoading"
                            @click="goToReviewStep"
                        >
                            <template v-if="isReviewLoading">
                                <LoadingSpinner :inline="true" size="sm" class="text-white me-2" />
                                Processing...
                            </template>
                            <span v-else>
                                Continue
                                <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true" />
                            </span>
                        </button>

                        <button
                            v-else-if="activeStep === 'review' && !bookingStore.reviewConfirmed"
                            type="button"
                            class="btn wizard-btn-continue"
                            :disabled="isConfirmingBooking"
                            @click="handleConfirmBooking"
                        >
                            <LoadingSpinner v-if="isConfirmingBooking" :inline="true" size="sm" class="text-white me-2" />
                            <i v-else class="fa-solid fa-lock me-2" aria-hidden="true" />
                            Confirm Booking
                        </button>

                        <button
                            v-else-if="activeStep === 'review'"
                            type="button"
                            class="btn wizard-btn-continue"
                            @click="openConfirmModal"
                        >
                            <i v-if="bookingFullyCommitted" class="fa-solid fa-check me-2" aria-hidden="true" />
                            <i v-else class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true" />
                            {{ bookingFullyCommitted ? 'Booking Confirmed' : 'View Booking Status' }}
                        </button>
                    </div>
                </div>
            </aside>
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

    <BookingConfirmModal
        :visible="showConfirmModal"
        :receipt="confirmReceipt"
        :commit-error="commitDisplay.commit_error"
        :commit-pending="commitDisplay.commit_pending"
        :workbench-expired="!!commitDisplay.workbench_expired"
        :loading="isConfirmingBooking"
        @close="showConfirmModal = false"
        @retry="handleRetryCommit"
        @new-search="handleStartNewSearch"
        @go-to-list="handleConfirmModalGoToList"
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
.booking-sticky-header {
    position: sticky;
    top: 60px;
    z-index: 5;
    background: #fff;
    padding-top: 0.75rem;
    padding-bottom: 0.5rem;
    padding-left: 1.25rem;
    padding-right: 1.25rem;
}

[data-bs-theme="dark"] .booking-sticky-header {
    background: var(--bs-body-bg);
}

.booking-page-layout {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
    padding-bottom: 2rem;
    margin-bottom: 0.5rem;
}

.booking-page-main {
    flex: 1 1 0;
    min-width: 0;
}

.booking-page-sidebar {
    flex: 0 0 300px;
    position: sticky;
    top: 72px;
}

#accordionTravelers {
    --bs-accordion-btn-focus-box-shadow: none;
}

#accordionTravelers .accordion-button:focus {
    outline: none;
    box-shadow: none;
}

#accordionTravelers .accordion-button:not(.collapsed):focus {
    box-shadow: inset 0 calc(-1 * var(--bs-accordion-border-width)) 0 var(--bs-accordion-border-color);
}

#accordionTravelers .accordion-button::after {
    margin-left: auto !important;
}

/* Wizard continue button — sits in the sidebar footer now, one style for every step */
.wizard-btn-continue {
    cursor: pointer;
    background: linear-gradient(135deg, #0880e1, #3b9eff);
    border: none;
    color: #fff;
    font-weight: 700;
    font-size: 0.8rem;
    padding-top: 0.6rem;
    padding-bottom: 0.6rem;
    padding-left: 1rem;
    padding-right: 1rem;
    line-height: 1.2;
    box-shadow: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.wizard-btn-continue:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 28px rgba(2, 125, 226, 0.38);
    color: #fff;
}

.wizard-btn-continue:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.wizard-btn-continue:focus-visible {
    outline: 2px solid #027de2;
    outline-offset: 2px;
}

.wizard-btn-continue i.fa-lock {
    font-size: 0.75em;
}

.wizard-btn-continue i {
    display: inline-block;
    transition: transform 0.18s ease;
}

.wizard-btn-continue:hover:not(:disabled) i.fa-arrow-right {
    transform: translateX(3px);
}

.wizard-btn-continue:hover:not(:disabled) i.fa-lock {
    transform: scale(1.1);
}

/* Sidebar back/continue — sits below the fare/baggage cards, same spot for every step */
.booking-sidebar-actions {
    margin-top: 0.65rem;
}

.booking-sidebar-actions__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.booking-sidebar-actions .wizard-btn-continue {
    flex: 0 0 auto;
    justify-content: center;
    display: inline-flex;
    align-items: center;
}

/* Back button reuses AppBreadcrumbs' circular style so it reads as the same affordance */
.booking-sidebar-back-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    color: #64748b;
    background: #fff;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.15s;
}

.booking-sidebar-back-btn:hover {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}

.booking-sidebar-back-btn:focus-visible {
    outline: 2px solid #027de2;
    outline-offset: 2px;
}

[data-bs-theme="dark"] .booking-sidebar-back-btn {
    background: #1e293b;
    border-color: #334155;
    color: #94a3b8;
}

[data-bs-theme="dark"] .booking-sidebar-back-btn:hover {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}

.traveler-header-btn {
    gap: 0.75rem;
}

.traveler-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #f1edff;
    flex-shrink: 0;
}

.traveler-avatar img {
    width: 14px;
    height: 14px;
}

.traveler-avatar i {
    font-size: 12px;
    color: #7239ea;
}

.traveler-avatar--child,
.traveler-avatar--infant {
    background: #e6fbf8;
}

.traveler-avatar--child i,
.traveler-avatar--infant i {
    color: #0aa89e;
}

.traveler-header-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.25;
}

.traveler-header-index {
    font-size: 0.98rem;
    font-weight: 700;
    color: #1e1b2e;
}

.traveler-header-type {
    font-size: 0.78rem;
    font-weight: 500;
    color: #8b8398;
}

.traveler-primary-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: 14px;
    padding: 4px 12px;
    border-radius: 999px;
    background: rgba(25, 135, 84, 0.1);
    color: #198754;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}

.traveler-primary-badge i {
    font-size: 11px;
}

[data-bs-theme="dark"] .traveler-avatar {
    background: rgba(114, 57, 234, 0.18);
}

[data-bs-theme="dark"] .traveler-avatar--child,
[data-bs-theme="dark"] .traveler-avatar--infant {
    background: rgba(10, 168, 158, 0.18);
}

[data-bs-theme="dark"] .traveler-header-index {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .traveler-header-type {
    color: #94a3b8;
}

[data-bs-theme="dark"] .traveler-primary-badge {
    background: rgba(25, 135, 84, 0.18);
    color: #6ee7b7;
}

@media (max-width: 991px) {
    .booking-page-layout { flex-direction: column; }
    .booking-page-sidebar { flex: 1 1 auto; width: 100%; position: static; }
}

.booking-header-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.booking-flight-details-btn {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.booking-flight-details-btn:hover {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}

.compact-timer-block {
    display: flex;
    align-items: center;
    gap: 7px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(26, 158, 181, 0.22);
    border-radius: 10px;
    padding: 4px 12px;
    flex-shrink: 0;
}

.compact-timer-icon {
    font-size: 15px;
    color: #1a9eb5;
    flex-shrink: 0;
    line-height: 1;
}

.compact-timer-digits {
    display: flex;
    align-items: center;
    gap: 0;
    font-variant-numeric: tabular-nums;
    font-weight: 800;
    font-size: 19px;
    color: #0f172a;
    letter-spacing: -0.01em;
    line-height: 1;
}

.digit-slot {
    position: relative;
    overflow: hidden;
    height: 1.15em;
    width: 0.62em;
    display: inline-block;
    vertical-align: middle;
}

.digit-val {
    display: block;
    line-height: 1.15em;
    text-align: center;
    width: 100%;
}

.digit-colon {
    font-size: 17px;
    font-weight: 800;
    color: #94a3b8;
    margin: 0 2px;
    line-height: 1;
    position: relative;
    top: -1px;
}

.digit-slip-enter-active {
    transition: transform 0.32s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.32s ease;
}
.digit-slip-leave-active {
    transition: transform 0.32s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.32s ease;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
}
.digit-slip-enter-from { transform: translateY(-100%); opacity: 0; }
.digit-slip-enter-to   { transform: translateY(0);     opacity: 1; }
.digit-slip-leave-from { transform: translateY(0);     opacity: 1; }
.digit-slip-leave-to   { transform: translateY(100%);  opacity: 0; }

.compact-timer-block--critical {
    border-color: rgba(220, 38, 38, 0.3);
}

.compact-timer-block--critical .compact-timer-icon,
.compact-timer-block--critical .compact-timer-digits {
    color: #dc2626;
}

[data-bs-theme="dark"] .booking-flight-details-btn {
    background: #1e293b;
    border-color: #334155;
    color: #93c5fd;
}

[data-bs-theme="dark"] .compact-timer-block {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(125, 211, 252, 0.22);
}

[data-bs-theme="dark"] .compact-timer-icon {
    color: #7dd3fc;
}

[data-bs-theme="dark"] .compact-timer-digits {
    color: #f1f5f9;
}

[data-bs-theme="dark"] .digit-colon {
    color: #64748b;
}

.booking-wizard {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 16px;
}

.booking-wizard__step {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    height: 42px;
    border: none;
    background: #cfd2d8;
    color: #5e5f60;
    font-size: 14px;
    /* font-weight: 600; */
    padding-left: 30px;
    padding-right: 28px;
    clip-path: polygon(
        2.8px 2.8px,
        4px 0,
        calc(100% - 26px) 0,
        calc(100% - 19.2px) 2.8px,
        calc(100% - 2.8px) 19.2px,
        calc(100% - 2.8px) 24.8px,
        calc(100% - 19.2px) 41.2px,
        calc(100% - 26px) 44px,
        4px 44px,
        2.8px 41.2px,
        19.2px 24.8px,
        19.2px 19.2px
    );
    margin-left: -13px;
    transition: background 0.2s ease, color 0.2s ease;
}

.booking-wizard__step:first-child {
    margin-left: 0;
}

.booking-wizard__step:focus {
    outline: none;
}

.booking-wizard__step:focus-visible {
    outline: 2px solid #7239ea;
    outline-offset: -3px;
}

.booking-wizard__step:hover:not(:disabled) {
    background: #e2e6ee;
}

.booking-wizard__step--completed:not(.booking-wizard__step--active) {
    background: #dfe3ea;
    color: #475569;
}

.booking-wizard__step--active {
    background: #7239ea;
    color: #fff;
    z-index: 1;
}

.booking-wizard__step--disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.booking-wizard__label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.booking-wizard__icon {
    font-size: 14px;
    flex-shrink: 0;
}

@media (max-width: 575px) {
    .booking-wizard {
        grid-template-columns: 1fr;
    }
    .booking-wizard__step,
    .booking-wizard__step:first-child {
        clip-path: none;
        margin-left: 0;
        border-radius: 8px;
        margin-bottom: 4px;
    }
}

[data-bs-theme="dark"] .booking-wizard__step {
    background: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
}
[data-bs-theme="dark"] .booking-wizard__step--completed:not(.booking-wizard__step--active) {
    background: rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
}

.booking-step-panel {
    border: none;
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

.traveler-form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #7239ea;
    padding-bottom: 6px;
    margin-bottom: 4px;
    border-bottom: 1px solid rgba(114, 57, 234, 0.15);
}

.traveler-form-section-title i {
    font-size: 12px;
}

[data-bs-theme="dark"] .traveler-form-section-title {
    color: #c084fc;
    border-bottom-color: rgba(192, 132, 252, 0.25);
}

.personal-detail-label {
    width: 135px;
    flex-shrink: 0;
}

.auto-fill-btn {
    text-transform: none;
    font-weight: 600;
    font-size: 0.8rem;
    letter-spacing: normal;
}

.contact-primary-check {
    text-transform: none;
    font-weight: 400;
    font-size: 0.875rem;
    letter-spacing: normal;
    color: var(--bs-body-color);
    display: flex;
    align-items: center;
    gap: 0.5em;
    padding-left: 0;
}

.contact-primary-check .form-check-input {
    margin: 0;
    flex-shrink: 0;
}

.contact-primary-check .form-check-label {
    margin: 0;
}

@media (min-width: 768px) {
    .ssr-section {
        border-left: 1px solid var(--bs-border-color);
        padding-left: 1.5rem;
    }
}

/* passport/visa preview thumbnail: match the empty dropzone's own footprint, not a tiny 82x82 crop */
.traveller-accordion-body .img-uploader__previews {
    width: 100% !important;
    margin-top: 0 !important;
}

.traveller-accordion-body .img-uploader__zone,
.traveller-accordion-body .img-uploader__preview {
    width: 100% !important;
    height: 160px !important;
    border-radius: 8px !important;
}

.traveller-accordion-body .img-uploader__zone {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.traveller-accordion-body .img-uploader__remove {
    color: #dc3545 !important;
}

.traveller-accordion-body .img-uploader__remove:hover {
    color: #fff !important;
}

.traveller-accordion-body .img-uploader__size {
    color: #22c55e !important;
    font-size: 12px !important;
    font-weight: 700 !important;
}

.traveller-accordion-body .img-uploader__size--invalid {
    color: #dc3545 !important;
}

/* Select2 + date pickers render as form-control-sm by default; match plain input height in this form */
.traveller-accordion-body .app-select2-control.form-control-sm,
.traveller-accordion-body input.form-control.form-control-sm {
    min-height: 38px;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
}

@media (max-width: 991px) {
    .booking-wizard__icon {
        display: none;
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

.passport-notice {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    background-color: rgba(255, 250, 238, 1);
    border-radius: 5px;
    line-height: 1.45;
}

.passport-notice__icon {
    color: rgba(240, 180, 27, 1);
    font-size: 12px;
    margin-top: 2px;
    flex-shrink: 0;
}

.passport-notice__text {
    font-size: 12px;
    color: rgba(119, 95, 35, 1);
}

/* ── Dark mode overrides ──────────────────────────────────────── */
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
[data-bs-theme="dark"] .passport-notice__icon {
    color: #f0b41b !important;
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
