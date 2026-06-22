import { ref, computed } from 'vue'
import { defineStore } from 'pinia'

const TTL_MS = 30 * 60 * 1000

export const useSearchStore = defineStore('blueskySearch', () => {
    const savedForm = ref(null)
    const flights = ref([])
    const totalFlights = ref(0)
    const catalogIdentifier = ref(null)
    const searchLogId = ref(null)
    const activeSearchAttemptId = ref(null)
    const sliderMin = ref(0)
    const sliderMax = ref(0)
    const priceRangeMin = ref(0)
    const priceRangeMax = ref(0)
    const selectedAirlines = ref([])
    const selectedStops = ref([])
    const selectedRefundTypes = ref([])
    const selectedLayovers = ref([])
    const layoverSearch = ref('')
    const airlineSearch = ref('')
    const selectedScheduleSegment = ref(null)
    const scheduleMode = ref('departure')
    const selectedOriginDetails = ref(null)
    const selectedDestinationDetails = ref(null)
    const lastSearchedAt = ref(null)

    const isValid = computed(() =>
        !!lastSearchedAt.value &&
        flights.value.length > 0 &&
        (Date.now() - lastSearchedAt.value) < TTL_MS
    )

    function isExpired() {
        if (!lastSearchedAt.value) {
            return flights.value.length > 0 || !!savedForm.value
        }
        return Date.now() - lastSearchedAt.value >= TTL_MS
    }

    function hasSearchSession() {
        return !!(
            lastSearchedAt.value ||
            flights.value.length ||
            savedForm.value ||
            searchLogId.value ||
            activeSearchAttemptId.value
        )
    }

    function expireIfStale() {
        if (!isExpired()) return false
        clearSearch()
        return true
    }

    function saveSearch(payload) {
        savedForm.value = payload.form
        flights.value = payload.flights
        totalFlights.value = payload.totalFlights
        catalogIdentifier.value = payload.catalogIdentifier
        searchLogId.value = payload.searchLogId
        activeSearchAttemptId.value = payload.activeSearchAttemptId ?? null
        sliderMin.value = payload.sliderMin
        sliderMax.value = payload.sliderMax
        priceRangeMin.value = payload.priceRangeMin
        priceRangeMax.value = payload.priceRangeMax
        selectedOriginDetails.value = payload.selectedOriginDetails
        selectedDestinationDetails.value = payload.selectedDestinationDetails
        selectedAirlines.value = []
        selectedStops.value = []
        selectedRefundTypes.value = []
        selectedLayovers.value = []
        layoverSearch.value = ''
        airlineSearch.value = ''
        selectedScheduleSegment.value = null
        scheduleMode.value = 'departure'
        lastSearchedAt.value = Date.now()
    }

    function clearSearch() {
        savedForm.value = null
        flights.value = []
        totalFlights.value = 0
        catalogIdentifier.value = null
        searchLogId.value = null
        activeSearchAttemptId.value = null
        lastSearchedAt.value = null
        selectedOriginDetails.value = null
        selectedDestinationDetails.value = null
        selectedAirlines.value = []
        selectedStops.value = []
        selectedRefundTypes.value = []
        selectedLayovers.value = []
    }

    return {
        savedForm, flights, totalFlights,
        catalogIdentifier, searchLogId, activeSearchAttemptId,
        sliderMin, sliderMax, priceRangeMin, priceRangeMax,
        selectedAirlines, selectedStops, selectedRefundTypes, selectedLayovers,
        layoverSearch, airlineSearch,
        selectedScheduleSegment, scheduleMode,
        selectedOriginDetails, selectedDestinationDetails,
        lastSearchedAt, isValid,
        isExpired, hasSearchSession, expireIfStale,
        saveSearch, clearSearch,
    }
}, {
    persist: { storage: sessionStorage },
})
