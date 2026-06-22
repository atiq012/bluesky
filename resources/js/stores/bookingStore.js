import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useBookingStore = defineStore('tpV2Booking', () => {
    const workbenchIdentifier = ref(null)
    const sessionId = ref(null)
    const offerId = ref(null)
    const priceData = ref(null)
    const flight = ref(null)
    const selectedBrand = ref(null)
    const form = ref(null)
    const timerStartedAt = ref(null)
    const activeStep = ref('travelers')
    const travelerForms = ref([])
    const paxIds = ref([])
    const travelportTravelerIds = ref([])
    const travelportTravelerRefIds = ref([])
    const paxesSubmitted = ref(false)
    const contentSource = ref('GDS')
    const ancillaryShopData = ref(null)
    const bookedAncillaryIds = ref([])
    const ssrSubmitted = ref(false)
    const ssrSkipped = ref(false)
    const agencySubmitted = ref(false)
    const agencyForm = ref({
        name: '',
        iata_number: '',
        phone: '',
        email: '',
        address_line: '',
        city: '',
        country_code: 'BD',
    })
    const ssrApiResponse = ref(null)
    const agencyApiResponse = ref(null)
    const workbenchApiResponse = ref(null)
    const addOfferApiResponse = ref(null)
    const addTravelerApiResponse = ref(null)
    const bookingAttemptId = ref(null)
    const reviewSnapshot = ref(null)
    const reviewConfirmed = ref(false)
    const commitResult = ref(null)

    function setBookingSession(data) {
        workbenchIdentifier.value = data.workbenchIdentifier ?? null
        sessionId.value = data.sessionId ?? null
        bookingAttemptId.value = data.bookingAttemptId ?? null
        offerId.value = data.offerId ?? null
        priceData.value = data.priceData ?? null
        flight.value = data.flight ?? null
        selectedBrand.value = data.selectedBrand ?? null
        form.value = data.form ?? null
        timerStartedAt.value = data.timerStartedAt ?? timerStartedAt.value ?? Date.now()
        activeStep.value = 'travelers'
        travelerForms.value = []
        paxIds.value = []
        travelportTravelerIds.value = []
        travelportTravelerRefIds.value = []
        paxesSubmitted.value = false
        contentSource.value = 'GDS'
        ancillaryShopData.value = null
        bookedAncillaryIds.value = []
        ssrSubmitted.value = false
        ssrSkipped.value = false
        agencySubmitted.value = false
        agencyForm.value = {
            name: '',
            iata_number: '',
            phone: '',
            email: '',
            address_line: '',
            city: '',
            country_code: 'BD',
        }
        ssrApiResponse.value = null
        agencyApiResponse.value = null
        workbenchApiResponse.value = data.workbenchApiResponse ?? null
        addOfferApiResponse.value = null
        addTravelerApiResponse.value = null
        reviewSnapshot.value = null
        reviewConfirmed.value = false
        commitResult.value = null
    }

    function setReviewSnapshot(data) {
        reviewSnapshot.value = data ?? null
    }

    function setReviewConfirmed(value = true) {
        reviewConfirmed.value = !!value
    }

    function setCommitResult(data) {
        commitResult.value = data ?? null
    }

    function setWorkbenchApiResponse(payload) {
        workbenchApiResponse.value = payload ?? null
    }

    function setAddOfferApiResponse(payload) {
        addOfferApiResponse.value = payload ?? null
    }

    function setAddTravelerApiResponse(payload) {
        addTravelerApiResponse.value = payload ?? null
    }

    function setPaxes({ paxIds: ids, travelportTravelerIds: tpIds, travelportTravelerRefIds: tpRefIds }) {
        paxIds.value = ids ?? []
        travelportTravelerIds.value = tpIds ?? []
        travelportTravelerRefIds.value = tpRefIds ?? []
        paxesSubmitted.value = true
    }

    function setContentSource(source) {
        contentSource.value = source ?? 'GDS'
    }

    function setAncillaryShopData(data) {
        ancillaryShopData.value = data ?? null
    }

    function addBookedAncillaryId(id) {
        if (id && !bookedAncillaryIds.value.includes(id)) {
            bookedAncillaryIds.value.push(id)
        }
    }

    function setSsrApplied({ skipped = false, response = null } = {}) {
        ssrSubmitted.value = true
        ssrSkipped.value = skipped
        ssrApiResponse.value = response
    }

    function setAgencyForm(form) {
        agencyForm.value = { ...agencyForm.value, ...(form ?? {}) }
    }

    function setAgencySubmitted(response = null) {
        agencySubmitted.value = true
        agencyApiResponse.value = response
    }

    function clearBookingSession() {
        workbenchIdentifier.value = null
        sessionId.value = null
        bookingAttemptId.value = null
        offerId.value = null
        priceData.value = null
        flight.value = null
        selectedBrand.value = null
        form.value = null
        timerStartedAt.value = null
        activeStep.value = 'travelers'
        travelerForms.value = []
        paxIds.value = []
        travelportTravelerIds.value = []
        travelportTravelerRefIds.value = []
        paxesSubmitted.value = false
        contentSource.value = 'GDS'
        ancillaryShopData.value = null
        bookedAncillaryIds.value = []
        ssrSubmitted.value = false
        ssrSkipped.value = false
        agencySubmitted.value = false
        agencyForm.value = {
            name: '',
            iata_number: '',
            phone: '',
            email: '',
            address_line: '',
            city: '',
            country_code: 'BD',
        }
        ssrApiResponse.value = null
        agencyApiResponse.value = null
        workbenchApiResponse.value = null
        addOfferApiResponse.value = null
        addTravelerApiResponse.value = null
        reviewSnapshot.value = null
        reviewConfirmed.value = false
        commitResult.value = null
    }

    return {
        workbenchIdentifier, sessionId, bookingAttemptId, offerId,
        priceData, flight, selectedBrand, form,
        timerStartedAt, activeStep, travelerForms,
        paxIds, travelportTravelerIds, travelportTravelerRefIds, paxesSubmitted,
        contentSource, ancillaryShopData, bookedAncillaryIds,
        ssrSubmitted, ssrSkipped, agencySubmitted, agencyForm,
        ssrApiResponse, agencyApiResponse,
        workbenchApiResponse, addOfferApiResponse, addTravelerApiResponse,
        reviewSnapshot, reviewConfirmed, commitResult,
        setBookingSession, clearBookingSession, setPaxes,
        setReviewSnapshot, setReviewConfirmed, setCommitResult,
        setContentSource, setAncillaryShopData, addBookedAncillaryId,
        setSsrApplied, setAgencyForm, setAgencySubmitted,
        setWorkbenchApiResponse, setAddOfferApiResponse, setAddTravelerApiResponse,
    }
}, {
    persist: { storage: sessionStorage },
})
