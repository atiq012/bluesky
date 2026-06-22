<script setup>
import { ref, onMounted, onUnmounted, reactive, computed, nextTick } from "vue";
import { onBeforeRouteLeave } from "vue-router";
import { storeToRefs } from "pinia";
import axiosInstance from "../../axiosInstance"
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import CustomMinMaxSlider from "../../components/search/CustomMinMaxSlider.vue";
import SimpleBar from "simplebar-vue";
import "simplebar-vue/dist/simplebar.min.css";
import { useAuthStore } from '../../stores/authStore';
import { useBookingStore } from '../../stores/bookingStore';
import { useSearchStore } from '../../stores/searchStore';
import '../../../css/searchpanel.css'
import '../../../css/search-dark.css'
import FlightPricePanel from './FlightPricePanel.vue'
import AppTooltip from '../common/AppTooltip.vue'
import AppModal from '../common/AppModal.vue'
import { completePriceAttempt, completeSearchAttempt } from '../../utils/bookingAttemptSession'


const authStore    = useAuthStore();
const bookingStore = useBookingStore();
const searchStore  = useSearchStore();

const {
    flights, totalFlights,
    catalogIdentifier, searchLogId, activeSearchAttemptId,
    sliderMin, sliderMax, priceRangeMin, priceRangeMax,
    selectedAirlines, selectedStops, selectedRefundTypes, selectedLayovers,
    layoverSearch, airlineSearch,
    selectedScheduleSegment, scheduleMode,
    selectedOriginDetails, selectedDestinationDetails,
} = storeToRefs(searchStore);


const airports = ref([]);
const initialLoadLimit = 20;
const showOriginList = ref(false);
const showDestinationList = ref(false);
const filteredOriginAirports = ref([]);
const filteredDestinationAirports = ref([]);

const loadging = ref(false);
const ExecutionTime = ref([])
const apiTime = ref(0)
const uiTime = ref(0)

const isDownloadingSearchFiles = ref(false)

const fareRulesData           = ref({})
const fareRulesLoading        = ref({})
const fareRulesDownloadKey    = ref({})
const fareRulesDownloading    = ref({})
const showPricePanel          = ref(false)
const selectedFlightForPrice  = ref(null)
const selectedBrandForPrice   = ref(null)

const isDark = ref(document.documentElement.getAttribute('data-bs-theme') === 'dark')
const _themeObserver = new MutationObserver(() => {
    isDark.value = document.documentElement.getAttribute('data-bs-theme') === 'dark'
})
_themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] })

function selectFare(flight, brand) {
    selectedFlightForPrice.value = flight
    selectedBrandForPrice.value  = brand
    showPricePanel.value         = true
}

const bookingTimerMinutes = ref(30)
const bookingTimerSeconds = ref(0)
const bookingTimerInterval = ref(null)

const distinctLayovers = computed(() => {
    const map = {}
    flights.value.forEach(f => {
        (f.outbound?.connections?.stops ?? []).forEach(stop => {
            const code = stop.airport_code
            if (!code) return
            if (!map[code]) map[code] = { code, name: stop.airport_name, count: 0 }
            map[code].count++
        })
    })
    return Object.values(map).sort((a, b) => b.count - a.count)
})

const filteredLayoverList = computed(() => {
    const q = layoverSearch.value.trim().toLowerCase()
    return q
        ? distinctLayovers.value.filter(l => l.name.toLowerCase().includes(q) || l.code.toLowerCase().includes(q))
        : distinctLayovers.value
})

const refundTypeLabels = { refundable: 'Refundable', partial: 'Partially Refundable', non_refundable: 'Non Refundable' }


const distinctRefundTypes = computed(() => {
    const map = {}
    flights.value.forEach(f => {
        const t = f.outbound?.refund_type
        if (t) map[t] = (map[t] || 0) + 1
    })
    return Object.keys(map).sort().map(t => ({ type: t, label: refundTypeLabels[t] ?? t, count: map[t] }))
})

const distinctStops = computed(() => {
    const map = {}
    flights.value.forEach(f => {
        const n = f.outbound?.connections?.stops?.length ?? 0
        map[n] = (map[n] || 0) + 1
    })
    return Object.keys(map).map(Number).sort((a, b) => a - b).map(n => ({
        count: n,
        label: n === 0 ? 'Non-Stop' : `${n} Stop`,
        flightCount: map[n],
    }))
})

const scheduleSegments = [
    { key: '00-06', min: 0,  max: 5  },
    { key: '06-12', min: 6,  max: 11 },
    { key: '12-18', min: 12, max: 17 },
    { key: '18-24', min: 18, max: 23 },
]

function parseTimeHour(timeStr) {
    if (!timeStr) return -1
    const m = timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i)
    if (!m) return -1
    let h = parseInt(m[1])
    const period = m[3].toUpperCase()
    if (period === 'AM' && h === 12) h = 0
    if (period === 'PM' && h !== 12) h += 12
    return h
}

const distinctAirlines = computed(() => {
    const map = {}
    flights.value.forEach(f => {
        const name = f.outbound?.first_airline_name
        const code = f.outbound?.first_carrier_code
        const logo = f.outbound?.first_logo_path
        if (!name) return
        if (!map[name]) map[name] = { name, code, logo, count: 0 }
        map[name].count++
    })
    return Object.values(map).sort((a, b) => b.count - a.count)
})

const filteredAirlineList = computed(() => {
    const q = airlineSearch.value.trim().toLowerCase()
    return q ? distinctAirlines.value.filter(a => a.name.toLowerCase().includes(q)) : distinctAirlines.value
})

const filteredFlights = computed(() => {
    return flights.value.filter(f => {
        const price = calcOutboundPriceRaw(f)
        const airlineOk = !selectedAirlines.value.length || selectedAirlines.value.includes(f.outbound?.first_airline_name)
        const priceOk = priceRangeMax.value === 0 || (price >= sliderMin.value && price <= sliderMax.value)
        const stopCount = f.outbound?.connections?.stops?.length ?? 0
        const stopOk = !selectedStops.value.length || selectedStops.value.includes(stopCount)
        const refundOk = !selectedRefundTypes.value.length || selectedRefundTypes.value.includes(f.outbound?.refund_type)
        const flightStopCodes = (f.outbound?.connections?.stops ?? []).map(s => s.airport_code)
        const layoverOk = !selectedLayovers.value.length || selectedLayovers.value.some(code => flightStopCodes.includes(code))
        let scheduleOk = true
        if (selectedScheduleSegment.value) {
            const seg = scheduleSegments.find(s => s.key === selectedScheduleSegment.value)
            if (seg) {
                const timeStr = scheduleMode.value === 'departure'
                    ? f.outbound?.departure_time
                    : f.outbound?.arrival_time
                const h = parseTimeHour(timeStr)
                scheduleOk = h >= seg.min && h <= seg.max
            }
        }
        return airlineOk && priceOk && stopOk && refundOk && layoverOk && scheduleOk
    })
})

const form = reactive({
    Way: '',
    from: 'DAC',  // Set default origin
    fromInput: '',
    to: '',
    toInput: '',
    dep_date: '',
    arrival_date: '',
    ADT: 1,
    CNN: 0,
    KID: 0,
    INF: 0,
    cabin_class: 'Economy',
});



// Create a ref for the input element
const datePickerRef = ref(null);

// Initialize default flight dates: today+20 (departure), today+30 (return)
function addDaysFromToday(days) {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + days);
    return d;
}

function getDefaultStartDate() {
    return addDaysFromToday(20);
}

function getDefaultEndDate() {
    return addDaysFromToday(30);
}

function applyDefaultDateRange() {
    const start = getDefaultStartDate();
    const end = getDefaultEndDate();
    selectedDate.value = start;
    selectedDateRange.value = [start, end];
    returnDate.value = end;
    form.dep_date = formatDateForForm(start);
    form.arrival_date = formatDateForForm(end);
}

const _defaultStart = getDefaultStartDate();
const _defaultEnd = getDefaultEndDate();
const selectedDate = ref(_defaultStart);
const selectedDateRange = ref([_defaultStart, _defaultEnd]);

const returnDate = ref(_defaultEnd);
const returnDatePickerRef = ref(null);
const isRangePicker = ref(false);

const AVAIL_SOURCE_MAP = {
    A: { label: 'AVS (availability and schedules)',                                                          bg: '#d1fae5', color: '#065f46' },
    B: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    C: { label: 'AVS',                                                                                       bg: '#d1fae5', color: '#065f46' },
    D: { label: 'Direct Access',                                                                             bg: '#ffedd5', color: '#9a3412' },
    E: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    F: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    G: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    H: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    I: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    K: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    L: { label: 'Last seat availability',                                                                    bg: '#fee2e2', color: '#991b1b' },
    M: { label: 'Unknown source',                                                                            bg: '#f3f4f6', color: '#6b7280' },
    O: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    P: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    Q: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    S: { label: 'Seamless',                                                                                  bg: '#ede9fe', color: '#5b21b6' },
    T: { label: 'Travelport cache',                                                                          bg: '#dbeafe', color: '#1e40af' },
    U: { label: 'Unknown source',                                                                            bg: '#f3f4f6', color: '#6b7280' },
    X: { label: 'Customer reusing availability data from customer cache (originally from Travelport)',       bg: '#fef9c3', color: '#854d0e' },
    Y: { label: 'Customer reusing data obtained from another system (data may be fresh or stored in cache)', bg: '#fef9c3', color: '#854d0e' },
    Z: { label: 'Customer sold from e-streamed data stored in customer cache',                               bg: '#fef9c3', color: '#854d0e' },
};

const CLASSIFICATION_LABEL = {
    Refund:         'Refund',
    Rebooking:      'Rebooking',
    CheckedBag:     'Checked Baggage',
    CarryOn:        'Carry-on',
    WiFi:           'Wi-Fi',
    Meals:          'Meals',
    SeatAssignment: 'Seat Selection',
};
const CLASSIFICATION_ICON = {
    Refund:           'fa-solid fa-rotate-left',
    Rebooking:        'fa-solid fa-calendar-check',
    CheckedBag:       'fa-solid fa-suitcase-rolling',
    CarryOn:          'fa-solid fa-suitcase',
    WiFi:             'fa-solid fa-wifi',
    Meals:            'fa-solid fa-utensils',
    SeatAssignment:   'fa-solid fa-chair',
    'Mileage Accrual':'fa-solid fa-coins',
    Upgrade:          'fa-solid fa-arrow-up',
    'Lounge Access':  'fa-solid fa-couch',
};
const classLabel = (c) => CLASSIFICATION_LABEL[c] ?? c;
const classIcon  = (c) => CLASSIFICATION_ICON[c]  ?? 'fa-solid fa-circle-question';


const availSourceStyle = (code) => {
    const entry = AVAIL_SOURCE_MAP[code] ?? { bg: '#f3f4f6', color: '#374151' };
    return `background-color:${entry.bg};color:${entry.color};border:1px solid ${entry.color}33;`;
};

const formatDisplayDate = (date) => {
    if (!date) return '';
    const dateObj = new Date(date);
    return dateObj.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

const formatDateForForm = (date) => {
    if (!date) return '';
    const dateObj = new Date(date);
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

form.dep_date = formatDateForForm(_defaultStart);
form.arrival_date = formatDateForForm(_defaultEnd);

const animateDateCard = ref(false);
const animateReturnDateCard = ref(false);
const showPaxPanel = ref(false);
const isFilterScrollHover = ref(false);
const isResultsScrollHover = ref(false);
const selectedCabinClass = ref('Economy');

const dateNumberFlyState = ref('');
const dateInfoFlyState = ref('');
const returnDateNumberFlyState = ref('');
const returnDateInfoFlyState = ref('');

const animateDateElements = (isReturn = false) => {
    const numberState = isReturn ? returnDateNumberFlyState : dateNumberFlyState;
    const infoState = isReturn ? returnDateInfoFlyState : dateInfoFlyState;

    // Fly out
    numberState.value = 'fly-out';
    infoState.value = 'fly-out';

    // Fly in after a short delay
    setTimeout(() => {
        numberState.value = 'fly-in';
        infoState.value = 'fly-in';
    }, 300);

    // Reset classes
    setTimeout(() => {
        numberState.value = '';
        infoState.value = '';
    }, 600);
};

const handleDateChange = (dates) => {
    if (form.Way === 1) { // One Way
        const newDate = new Date(dates);
        animateDateElements();
        selectedDate.value = newDate;
        form.dep_date = formatDateForForm(newDate);
        form.arrival_date = '';
    } else if (form.Way === 2 && Array.isArray(dates)) { // Round Trip
        const [start, end] = dates;
        if (start && end) {
            animateDateElements();
            if (end) animateDateElements(true);
            selectedDateRange.value = [new Date(start), new Date(end)];
            form.dep_date = formatDateForForm(start);
            form.arrival_date = formatDateForForm(end);
        }
    }
};

const handleReturnDateChange = (date) => {
    if (form.Way === 2) {
        animateDateElements(true);
        selectedDateRange.value[1] = date;
        form.arrival_date = formatDateForForm(date);
    }
};

function stopBookingTimerDisplay() {
    if (bookingTimerInterval.value) clearInterval(bookingTimerInterval.value)
    bookingStore.timerStartedAt = null
    bookingTimerMinutes.value = 30
    bookingTimerSeconds.value = 0
}

function syncBookingTimerFromStore() {
    if (!bookingStore.timerStartedAt || !searchStore.isValid) {
        stopBookingTimerDisplay()
        return false
    }

    const elapsedSec = Math.floor((Date.now() - bookingStore.timerStartedAt) / 1000)
    const remainingSec = 30 * 60 - elapsedSec
    if (remainingSec <= 0) return false

    bookingTimerMinutes.value = Math.floor(remainingSec / 60)
    bookingTimerSeconds.value = remainingSec % 60
    startTimerInterval()
    return true
}

async function purgeExpiredSearchSession() {
    if (!searchStore.isExpired()) return false
    await clearAndReset()
    return true
}

function handleVisibilityChange() {
    if (document.visibilityState !== 'visible') return
    if (searchStore.isExpired()) {
        void purgeExpiredSearchSession()
        return
    }
    syncBookingTimerFromStore()
}

onMounted(async () => {
    tourTypeChange(2);
    getAirports();
    document.addEventListener("click", handleClickOutside);

    const updateTotalPassengers = () => {
        const totalAdult = parseInt($(".adult").val()) || 0;
        const totalChild = parseInt($(".child").val()) || 0;
        const totalKids = parseInt($(".kids").val()) || 0;
        const totalInfant = parseInt($(".infant").val()) || 0;

        form.ADT = totalAdult;
        form.CNN = totalChild;
        form.KID = totalKids;
        form.INF = totalInfant;
        $(".total_pass").html(totalAdult + totalChild + totalKids + totalInfant);
    };

    const updatePassengerCount = (selector, increment, min, max) => {
        $(selector).on('click', function () {
            const input = $(this).siblings('input');
            let count = parseInt(input.val());
            count = increment ? Math.min(count + 1, max) : Math.max(count - 1, min);
            input.val(count);
            updateTotalPassengers();
        });
    };

    updatePassengerCount('.adult-left-minus', false, 1, 9);
    updatePassengerCount('.adult-right-plus', true, 1, 9);
    updatePassengerCount('.child-left-minus', false, 0, 4);
    updatePassengerCount('.child-right-plus', true, 0, 4);
    updatePassengerCount('.kids-left-minus', false, 0, 4);
    updatePassengerCount('.kids-right-plus', true, 0, 4);
    updatePassengerCount('.infant-left-minus', false, 0, 4);
    updatePassengerCount('.infant-right-plus', true, 0, 4);

    document.addEventListener('visibilitychange', handleVisibilityChange);

    if (await purgeExpiredSearchSession()) return;

    // Restore saved search session if valid (survives navigation + hard refresh)
    if (searchStore.isValid && searchStore.savedForm) {
        const saved = searchStore.savedForm
        Object.assign(form, saved)

        // Restore date picker refs from saved form strings
        if (saved.dep_date) {
            const dep = new Date(saved.dep_date)
            selectedDate.value = dep
            if (saved.arrival_date) {
                const ret = new Date(saved.arrival_date)
                returnDate.value = ret
                selectedDateRange.value = [dep, ret]
            }
        }

        // Fix trip-type UI classes without resetting dates
        if (saved.Way == 1) {
            $('.one-way').addClass('bg-checkbox-active')
            $('.round-way').removeClass('bg-checkbox-active')
        }
        // Way == 2 already active from tourTypeChange(2) above
    } else if (!selectedOriginDetails.value) {
        selectedOriginDetails.value = {
            id: 'DAC',
            text: 'Hazrat Shahjalal International Airport',
            city: 'Dhaka',
        }
    }

    // Restore persistent timer — survives refresh + navigation
    if (searchStore.isValid && bookingStore.timerStartedAt) {
        const elapsedSec = Math.floor((Date.now() - bookingStore.timerStartedAt) / 1000)
        const remainingSec = 30 * 60 - elapsedSec
        if (remainingSec <= 0) {
            void clearAndReset()
        } else {
            syncBookingTimerFromStore()
        }
    } else if (bookingStore.timerStartedAt) {
        stopBookingTimerDisplay()
    }
});

const totalTravellers = computed(() => {
    return Number(form.ADT || 0) + Number(form.CNN || 0) + Number(form.KID || 0) + Number(form.INF || 0);
});

const paxSummary = computed(() => `${totalTravellers.value} Travellers - ${form.cabin_class}`);

const BOOKING_FLOW_ROUTE_NAMES = new Set(['bookingCreate']);

async function finalizeSearchSession() {
    const id = activeSearchAttemptId.value;
    if (!id) return;

    await completePriceAttempt(id);
    await completeSearchAttempt(id);
    activeSearchAttemptId.value = null;
}

async function clearAndReset() {
    await finalizeSearchSession();
    showPricePanel.value = false;
    searchStore.clearSearch();
    // Reset form to defaults
    form.Way = '';
    form.from = 'DAC';
    form.fromInput = '';
    form.to = '';
    form.toInput = '';
    form.ADT = 1;
    form.CNN = 0;
    form.KID = 0;
    form.INF = 0;
    form.cabin_class = 'Economy';
    // Reset airport select2 display
    selectedOriginDetails.value = {
        id: 'DAC',
        text: 'Hazrat Shahjalal International Airport',
        city: 'Dhaka',
    };
    selectedDestinationDetails.value = null;
    // Reset pax jQuery spinners
    $('.adult').val(1);
    $('.child').val(0);
    $('.kids').val(0);
    $('.infant').val(0);
    $('.total_pass').html(1);
    // Stop timer + clear persisted timestamp
    stopBookingTimerDisplay()
    // Reset to round trip + default dates
    tourTypeChange(2);
}

const changePassenger = (type, delta) => {
    const limits = {
        ADT: { min: 1, max: 9 },
        CNN: { min: 0, max: 4 },
        INF: { min: 0, max: 4 },
    };

    const current = Number(form[type] || 0);
    const next = Math.max(limits[type].min, Math.min(current + delta, limits[type].max));
    form[type] = next;
};


function tourTypeChange(type) {
    if (type == 1) { // One Way
        form.Way = 1;
        selectedDate.value = getDefaultStartDate();
        form.dep_date = formatDateForForm(selectedDate.value);
        form.arrival_date = '';
        $('.one-way').addClass('bg-checkbox-active');
        $('.round-way').removeClass('bg-checkbox-active');
    } else if (type == 2) { // Round Trip
        form.Way = 2;
        applyDefaultDateRange();
        $('.one-way').removeClass('bg-checkbox-active');
        $('.round-way').addClass('bg-checkbox-active');
    }
}

onBeforeRouteLeave(async (to) => {
    if (BOOKING_FLOW_ROUTE_NAMES.has(to.name)) return true;
    if (!searchStore.hasSearchSession() && !bookingStore.timerStartedAt) return true;

    showPricePanel.value = false;
    stopBookingTimerDisplay();
    await finalizeSearchSession();
    searchStore.clearSearch();

    return true;
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    if (bookingTimerInterval.value) clearInterval(bookingTimerInterval.value)
    _themeObserver.disconnect()
});

async function getAirports() {
    try {
        const response = await axiosInstance.get("airports");
        airports.value = response.data.map((value) => ({
            id: value.code,
            text: value.Airport_Name,
            city: value.City_name,
        }));
    } catch (error) {
        console.error("Error fetching airports:", error);
    }
}

function handleClickOutside(event) {
    const originInput = document.getElementById("origin_id");
    const originResults = document.getElementById("origin_results");
    const destinationInput = document.getElementById("destination_id");
    const destinationResults = document.getElementById("destination_results");

    if (!originInput?.contains(event.target) && !originResults?.contains(event.target)) {
        showOriginList.value = false;
    }

    if (
        !destinationInput?.contains(event.target) &&
        !destinationResults?.contains(event.target)
    ) {
        showDestinationList.value = false;
    }

    if (!event.target.closest('.pax-panel-wrapper')) {
        showPaxPanel.value = false;
    }
}

// Generalized filtering function
function filterAirports(searchText, airports) {
    if (!searchText) {
        return airports.slice(0, initialLoadLimit);
    }
    const search = searchText.toLowerCase();
    // First, check for matches in the id field
    const idMatches = airports.filter(airport =>
        airport.id.toLowerCase().includes(search)
    );

    // Then check for matches in other fields
    const otherMatches = airports.filter(airport =>
        !airport.id.toLowerCase().includes(search) && // Exclude id matches
        (airport.text.toLowerCase().includes(search) ||
            airport.city.toLowerCase().includes(search))
    );

    // Combine the results, with id matches first
    return [...idMatches, ...otherMatches];
}

// Update filter functions
function filterOriginAirports(searchText) {
    filteredOriginAirports.value = filterAirports(searchText, airports.value);
}

function filterDestinationAirports(searchText) {
    filteredDestinationAirports.value = filterAirports(searchText, airports.value);
}

function onOriginFocus() {
    $('#oFrom').addClass('fly-out');
    $('#oCityAirport').addClass('fly-out');
    setTimeout(() => {
        $('#origin_id').val('');
        form.from = '';
        form.fromInput = '';
        selectedOriginDetails.value = null;
        showOriginList.value = true;
        if (!filteredOriginAirports.value.length) {
            filteredOriginAirports.value = airports.value.slice(0, initialLoadLimit);
        }
    }, 300);
}

function onDestinationFocus() {
    $('#dFrom').addClass('fly-out');
    $('#dCityAirport').addClass('fly-out');
    setTimeout(() => {
        $('#destination_id').val('');
        form.to = '';
        form.toInput = '';
        selectedDestinationDetails.value = null;
        showDestinationList.value = true;
        filteredDestinationAirports.value = airports.value.slice(0, initialLoadLimit);
    }, 300);
}

function selectOrigin(airport) {
    $('#origin_id').attr('placeholder', '');
    form.from = airport.id;
    form.fromInput = '';
    selectedOriginDetails.value = airport;
    showOriginList.value = false;
    setTimeout(() => {
        selectedDestinationDetails.value = null;
        showDestinationList.value = true;
        filteredDestinationAirports.value = airports.value.slice(0, initialLoadLimit);
        $('#destination_id').focus();
    }, 100);
}

function selectDestination(airport) {
    $('#destination_id').attr('placeholder', '');
    form.to = airport.id;
    form.toInput = '';
    selectedDestinationDetails.value = airport;
    showDestinationList.value = false;
}

function clearOrigin() {
    $('#origin_id').attr('placeholder', 'From');
    form.from = "";
    form.fromInput = "";
    selectedOriginDetails.value = null;
    showOriginList.value = false;
}

function clearDestination() {
    $('#destination_id').attr('placeholder', 'To');
    form.to = "";
    form.toInput = "";
    selectedDestinationDetails.value = null;
    showDestinationList.value = false;
}

async function Lowfaresearch() {
    try {
        await finalizeSearchSession();
        showPricePanel.value = false;
        flights.value = [];
        totalFlights.value = 0;
        ExecutionTime.value = 0;
        apiTime.value = 0;
        uiTime.value = 0;
        loadging.value = true;

        const t0 = performance.now();
        const response = await axiosInstance.post("v2/search", form);
        const t1 = performance.now();
        apiTime.value = ((t1 - t0) / 1000).toFixed(2);

        flights.value = response?.data?.flights ?? [];
        totalFlights.value = flights.value.length;
        searchLogId.value = response?.data?.search_log_id ?? null;
        activeSearchAttemptId.value = response?.data?.booking_attempt_id ?? null;
        catalogIdentifier.value = response?.data?.catalog_identifier ?? null;
        selectedAirlines.value = [];
        airlineSearch.value = '';
        selectedStops.value = [];
        selectedRefundTypes.value = [];
        selectedLayovers.value = [];
        layoverSearch.value = '';
        selectedScheduleSegment.value = null;
        startBookingTimer();

        if (flights.value.length) {
            const prices = flights.value.map(f => calcOutboundPriceRaw(f))
            const minP = Math.floor(Math.min(...prices))
            const maxP = Math.ceil(Math.max(...prices))
            sliderMin.value = minP
            sliderMax.value = maxP
            priceRangeMin.value = minP
            priceRangeMax.value = maxP
        }

        // Persist search state — survives navigation + hard refresh
        searchStore.saveSearch({
            form:                    { ...form },
            flights:                 flights.value,
            totalFlights:            totalFlights.value,
            catalogIdentifier:       catalogIdentifier.value,
            searchLogId:             searchLogId.value,
            activeSearchAttemptId:   activeSearchAttemptId.value,
            sliderMin:               sliderMin.value,
            sliderMax:               sliderMax.value,
            priceRangeMin:           priceRangeMin.value,
            priceRangeMax:           priceRangeMax.value,
            selectedOriginDetails:   selectedOriginDetails.value,
            selectedDestinationDetails: selectedDestinationDetails.value,
        })

        await nextTick();
        const t2 = performance.now();
        uiTime.value = ((t2 - t1) / 1000).toFixed(2);
        ExecutionTime.value = ((t2 - t0) / 1000).toFixed(2);
    } catch (error) {
        console.log(error);
        Notification.showToast("e", error?.response?.data?.message ?? "Token generation failed.");
    } finally {
        setTimeout(() => {
            loadging.value = false;
        }, 1000);
    }
}

async function fetchFareRules(flight, index) {
    if (fareRulesData.value[index] !== undefined) return;
    fareRulesLoading.value[index] = true;
    try {
        const offerings = [
            flight.outbound?._offering_id ? { leg: flight.outbound, label: `${flight.outbound.origin}→${flight.outbound.destination}`, direction: 'outbound' } : null,
            flight.inbound?._offering_id  ? { leg: flight.inbound,  label: `${flight.inbound.origin}→${flight.inbound.destination}`,   direction: 'inbound'  } : null,
        ].filter(Boolean);

        const results = await Promise.all(offerings.map(({ leg, direction }) =>
            axiosInstance.get('v2/fare-rules', {
                params: {
                    catalogProductOfferingsIdentifier: catalogIdentifier.value,
                    catalogProductOfferingID: leg._offering_id,
                    productIDs: leg._selected_productRef,
                    fareRuleType: 'Structured',
                    direction,
                },
            })
        ));

        const allSegments = results.flatMap((res, i) =>
            (res?.data?.fare_rules?.segments ?? []).map(seg => ({
                ...seg,
                displayLabel: offerings[i].label,
            }))
        );
        fareRulesData.value[index]        = { segments: allSegments };
        fareRulesDownloadKey.value[index] = results.map(r => r?.data?.download_key).filter(Boolean);
    } catch (e) {
        fareRulesData.value[index] = { error: true };
    } finally {
        fareRulesLoading.value[index] = false;
    }
}

async function downloadFareRules(index) {
    const keys = fareRulesDownloadKey.value[index];
    if (!keys?.length || fareRulesDownloading.value[index]) return;
    fareRulesDownloading.value[index] = true;
    try {
        const downloadJson = (data, filename) => {
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = filename; a.click();
            URL.revokeObjectURL(url);
        };
        const label = ['outbound', 'inbound'];
        for (let i = 0; i < keys.length; i++) {
            const res = await axiosInstance.get('v2/fare-rules/download', { params: { key: keys[i] } });
            downloadJson(res.data.payload,  `fare-rules-${label[i] ?? i}-payload-${index}.json`);
            downloadJson(res.data.response, `fare-rules-${label[i] ?? i}-response-${index}.json`);
        }
    } catch (e) {
        console.error('Fare rules download failed', e);
    } finally {
        fareRulesDownloading.value[index] = false;
    }
}

function getSegmentLabel(flight, flightRef) {
    const seg = flight.all_segments?.find(s => s.flightRef === flightRef);
    return seg ? `${seg.departure_code}→${seg.arrival_code}` : flightRef;
}

function formatTiming(timing) {
    return timing?.replace(/([A-Z])/g, ' $1').trim() ?? timing;
}

async function downloadSearchFiles() {
    if (!searchLogId.value || isDownloadingSearchFiles.value) return
    isDownloadingSearchFiles.value = true
    try {
        const res = await axiosInstance.post('flight-search-log/view', { id: searchLogId.value })
        const data = res?.data?.data
        if (!data) return

        const triggerDownload = (content, filename) => {
            const blob = new Blob([JSON.stringify(content, null, 2)], { type: 'application/json' })
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = filename
            a.click()
            URL.revokeObjectURL(url)
        }

        triggerDownload(data.search_payload, `payload-${searchLogId.value}.json`)
        await new Promise(resolve => setTimeout(resolve, 300))
        triggerDownload(data.response_json, `response-${searchLogId.value}.json`)
    } catch (e) {
        console.error(e)
    } finally {
        isDownloadingSearchFiles.value = false
    }
}

function clearAllFilters() {
    selectedAirlines.value = []
    selectedStops.value = []
    selectedRefundTypes.value = []
    selectedLayovers.value = []
    selectedScheduleSegment.value = null
    scheduleMode.value = 'departure'
    airlineSearch.value = ''
    layoverSearch.value = ''
    const prices = flights.value.map(f => calcOutboundPriceRaw(f))
    if (prices.length) {
        sliderMin.value = Math.floor(Math.min(...prices))
        sliderMax.value = Math.ceil(Math.max(...prices))
    }
}

function startTimerInterval() {
    if (bookingTimerInterval.value) clearInterval(bookingTimerInterval.value)
    bookingTimerInterval.value = setInterval(() => {
        if (bookingTimerSeconds.value > 0) {
            bookingTimerSeconds.value--
        } else if (bookingTimerMinutes.value > 0) {
            bookingTimerMinutes.value--
            bookingTimerSeconds.value = 59
        } else {
            clearInterval(bookingTimerInterval.value)
            void clearAndReset()
        }
    }, 1000)
}

function startBookingTimer() {
    bookingStore.timerStartedAt = Date.now()
    bookingTimerMinutes.value = 30
    bookingTimerSeconds.value = 0
    startTimerInterval()
}

function calcOutboundPriceRaw(flight) {
    return ['Adult','Child','Infant'].reduce((total, type) => {
        const b = flight.outbound.priceBreakdown.find(i => i.type === type) || {};
        const c = form[type === 'Adult' ? 'ADT' : type === 'Child' ? 'CNN' : 'INF'];
        return total + (c * (b.taxes || 0)) + (c * (b.baseFare || 0));
    }, 0);
}

function calcOutboundPrice(flight) {
    return calcOutboundPriceRaw(flight).toLocaleString();
}

function showRouteDetails(info,index) {
    const isActive = info === 2;

    $('#active-btn-'+index).toggleClass('bluesky-route-btn-primary '+index, !isActive);
    $('#deactive-btn-'+index).toggleClass('bluesky-route-btn-primary '+index, isActive);

    $('#active-btn-'+index).toggleClass('bluesky-route-btn-outline-primary '+index, isActive);
    $('#deactive-btn-'+index).toggleClass('bluesky-route-btn-outline-primary '+index, !isActive);
    $('.flight-tab-hide-'+index).toggleClass('d-none', !isActive);
    $('.flight-tab-active-'+index).toggleClass('d-none', isActive);
}


function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        day: 'numeric',
        month: 'short',
        weekday: 'short'
    });
};


function swapLocations() {
    [form.from, form.to] = [form.to, form.from];
    [form.fromInput, form.toInput] = [form.toInput, form.fromInput];
    [selectedOriginDetails.value, selectedDestinationDetails.value] = [selectedDestinationDetails.value, selectedOriginDetails.value];
    [showOriginList.value, showDestinationList.value] = [showDestinationList.value, showOriginList.value];
    [filteredOriginAirports.value, filteredDestinationAirports.value] = [filteredDestinationAirports.value, filteredOriginAirports.value];
}

// Add this function to handle click
const openPicker = () => {
    if (datePickerRef.value) {
        datePickerRef.value.openMenu();
    }
};

const openReturnPicker = () => {
    if (returnDatePickerRef.value) {
        returnDatePickerRef.value.openMenu();
    }
};


</script>
<template>
    <div class="search-page-layout">
    <!-- search Panel start -->
    <div class="search-sticky-panel">
    <div class="row search-sticky-row">
        <div class="col-md-12">
            <div class="card search-layout-card">

                <div class="card-body">
                    <div class="search-panel-top d-flex align-items-start justify-content-between gap-3">
                        <div class="search-trip-types">
                            <div class="bg-checkbox one-way rounded rounded-1 p-1">
                                <input @click="tourTypeChange(1)" class="form-check-input" type="radio"
                                    name="flexRadioDefault" id="flexRadioDefault1">
                                <label class="form-check-label-box" for="flexRadioDefault1">&nbsp;One way</label>
                            </div>
                            <div class="bg-checkbox-active round-way rounded rounded-1 p-1">
                                <input @click="tourTypeChange(2)" class="form-check-input" type="radio"
                                    name="flexRadioDefault" id="flexRadioDefault2" checked>
                                <label class="form-check-label-box" for="flexRadioDefault2">&nbsp;Round trip</label>
                            </div>
                        </div>

                        <div class="pax-panel-wrapper">
                            <button class="search-pax-trigger" type="button" @click.stop="showPaxPanel = !showPaxPanel">
                                <i class="fa-regular fa-user"></i>
                                <span>PAX</span>
                                <span>{{ paxSummary }}</span>
                                <i class="fa-solid fa-angle-down"></i>
                            </button>

                            <div v-if="showPaxPanel" class="search-pax-popup" @click.stop>
                                <div class="popup-title">TRAVELLERS</div>

                                <div class="popup-row">
                                    <div>
                                        <div class="row-name">Adults</div>
                                        <div class="row-sub">12 years and above</div>
                                    </div>
                                    <div class="input-group product-qty">
                                        <button type="button" class="btn btn-light btn-number" @click="changePassenger('ADT', -1)">−</button>
                                        <input type="text" name="adult" class="form-control input-number adult" :value="form.ADT" readonly>
                                        <button type="button" class="btn btn-light btn-number" @click="changePassenger('ADT', 1)">+</button>
                                    </div>
                                </div>

                                <div class="popup-row">
                                    <div>
                                        <div class="row-name">Children</div>
                                        <div class="row-sub">Aged 2-11</div>
                                    </div>
                                    <div class="input-group product-qty">
                                        <button type="button" class="btn btn-light btn-number" @click="changePassenger('CNN', -1)">−</button>
                                        <input type="text" name="child" class="form-control input-number child" :value="form.CNN" readonly>
                                        <button type="button" class="btn btn-light btn-number" @click="changePassenger('CNN', 1)">+</button>
                                    </div>
                                </div>

                                <div class="popup-row">
                                    <div>
                                        <div class="row-name">Infants</div>
                                        <div class="row-sub">Under 2 years</div>
                                    </div>
                                    <div class="input-group product-qty">
                                        <button type="button" class="btn btn-light btn-number" @click="changePassenger('INF', -1)">−</button>
                                        <input type="text" name="infant" class="form-control input-number infant" :value="form.INF" readonly>
                                        <button type="button" class="btn btn-light btn-number" @click="changePassenger('INF', 1)">+</button>
                                    </div>
                                </div>

                                <div class="popup-title cabin-class-title mb-2">CABIN CLASS</div>
                                <div class="cabin-switcher">
                                    <button type="button" :class="{ active: form.cabin_class === 'Economy' }" @click="form.cabin_class = 'Economy'">Economy</button>
                                    <button type="button" :class="{ active: form.cabin_class === 'Business' }" @click="form.cabin_class = 'Business'">Business</button>
                                    <button type="button" :class="{ active: form.cabin_class === 'First' }" @click="form.cabin_class = 'First'">First</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">

                        <div class="col-lg-7 col-md-12">
                            <div class="row position-relative">
                                <div class="col-md-6 mt-2">
                                    <div class="location-input-wrapper">
                                        <div v-if="form.from && selectedOriginDetails" class="selected-location">

                                            <div class="hstack align-items-center w-100 min-w-0">
                                                <div id="oFrom" class="pe-2 fly-in flex-shrink-0 fcolor" style="font-size: 1.2rem; font-weight: 900;">{{ form.from }}</div>
                                                <div id="oCityAirport" class="flex-grow-1 border-start ps-2 fly-in min-w-0">
                                                    <div class="font-11 fw-bold fcolor text-truncate">{{ selectedOriginDetails.city }}</div>
                                                    <div class="text-muted font-10 airport-name-ellipsis">{{ selectedOriginDetails.text }}</div>
                                                </div>
                                            </div>

                                        </div>
                                        <input id="origin_id" v-model="form.fromInput" name="origin_name" class="form-control origin_name"
                                        :class="{ 'has-value': form.from && !showOriginList}"
                                        @input="filterOriginAirports($event.target.value)"
                                        @focus="onOriginFocus" autocomplete="off" />
                                        <span v-if="form.from" @click="clearOrigin" class="clear-icon">✖</span>
                                        <div v-if="showOriginList" id="origin_results" class="position-absolute w-100 mt-2" style="z-index: 1000; animation: fadeIn 0.3s ease-in-out">
                                            <SimpleBar style="max-height: 300px" class="search-results-simplebar">
                                                <div v-for="airport in filteredOriginAirports" :key="airport.id" class="cursor-pointer border-bottom border-light" @click="selectOrigin(airport)">
                                                    <div class="hstack align-items-center">
                                                        <div class="font-12 fw-bold pe-2 fcolor">{{ airport.id }}</div>
                                                        <div class="flex-grow-1 border-start ps-2">
                                                            <div class="font-11 fw-bold fcolor">{{ airport.city }}</div>
                                                            <div class="text-muted font-10">{{ airport.text }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-if="filteredOriginAirports.length === 0" class="p-3 text-center text-muted">
                                                    No matching airports found
                                                </div>
                                            </SimpleBar>
                                        </div>
                                    </div>
                                </div>
                                    <div class="swap-icon-wrapper" @click="swapLocations">
                                        <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <div class="location-input-wrapper">
                                        <div v-if="form.to && selectedDestinationDetails" class="selected-location">

                                            <div class="hstack align-items-center w-100 min-w-0">
                                                <div id="dFrom" class="pe-2 fly-in flex-shrink-0 fcolor" style="font-size: 1.2rem; font-weight: 900;">{{ form.to }}</div>
                                                <div id="dCityAirport" class="flex-grow-1 border-start ps-2 fly-in min-w-0">
                                                    <div class="font-11 fw-bold fcolor fly-in text-truncate">{{ selectedDestinationDetails.city }}</div>
                                                    <div class="text-muted font-10 fly-in airport-name-ellipsis">{{ selectedDestinationDetails.text }}</div>
                                                </div>
                                            </div>

                                        </div>
                                        <input id="destination_id"
                                            v-model="form.toInput"
                                            name="destination_name"
                                            class="form-control destination_name"
                                            :class="{ 'has-value': form.to && !showDestinationList }"
                                            @input="filterDestinationAirports($event.target.value)"
                                            @focus="onDestinationFocus"
                                            placeholder="To"
                                            autocomplete="off" />
                                        <span v-if="form.to" @click="clearDestination" class="clear-icon">✖</span>

                                        <div v-if="showDestinationList" id="destination_results"
                                            class="position-absolute w-100 mt-2"
                                            style="z-index: 1000; animation: fadeIn 0.3s ease-in-out">
                                            <SimpleBar style="max-height: 300px" class="search-results-simplebar">
                                                <div v-for="airport in filteredDestinationAirports" :key="airport.id" class="cursor-pointer"
                                                    @click="selectDestination(airport)">
                                                    <div class="hstack align-items-center">

                                                        <div class="font-12 fw-bold pe-2 fcolor">{{ airport.id }}</div>
                                                        <div class="flex-grow-1 border-start ps-2">
                                                            <div class="font-11 fw-bold fcolor">{{ airport.city }}</div>
                                                            <div class="text-muted font-10">{{ airport.text }}</div>
                                                        </div>

                                                    </div>
                                                </div>
                                                <div v-if="filteredDestinationAirports.length === 0" class="p-3 text-center text-muted">
                                                    No matching airports found
                                                </div>
                                            </SimpleBar>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 col-md-12 mt-2 mt-lg-0">

                            <div v-if="form.Way === 1" class="row g-2 align-items-center justify-content-lg-end">
                                <div class="col-lg-8 col-md-6">
                                    <div class="date-picker-wrapper" :class="{ animate: animateDateCard }">
                                            <div class="date-card" @click="openPicker" :class="{ animate: animateDateCard }">
                                                <div class="date-number" :class="dateNumberFlyState">
                                                    {{ form.Way === 2 && selectedDateRange[0] ?
                                                        new Date(selectedDateRange[0]).getDate().toString().padStart(2, '0') :
                                                        (selectedDate ? new Date(selectedDate).getDate().toString().padStart(2, '0') : new Date().getDate().toString().padStart(2, '0'))
                                                    }}
                                                </div>
                                                <div class="date-info" :class="dateInfoFlyState">
                                                    <div class="day">
                                                        {{ form.Way === 2 && selectedDateRange[0] ?
                                                            new Date(selectedDateRange[0]).toLocaleDateString('en-US', { month: 'long' }) :
                                                            (selectedDate ? new Date(selectedDate).toLocaleDateString('en-US', { month: 'long' }) :
                                                            new Date().toLocaleDateString('en-US', { month: 'long' }))
                                                        }}
                                                    </div>
                                                    <div class="month-year">
                                                        {{ form.Way === 2 && selectedDateRange[0] ?
                                                            `${new Date(selectedDateRange[0]).toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date(selectedDateRange[0]).getFullYear()}` :
                                                            (selectedDate ?
                                                                `${new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date(selectedDate).getFullYear()}` :
                                                                `${new Date().toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date().getFullYear()}`)
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                            <VueDatePicker
                                                ref="datePickerRef"
                                                :model-value="form.Way === 1 ? selectedDate.value : selectedDateRange.value"
                                                @update:model-value="handleDateChange"
                                                :enable-time-picker="false"
                                                :format="formatDisplayDate"
                                                :range="form.Way === 2"
                                                :min-date="new Date()"
                                                :multi-calendars="form.Way === 2 ? 2 : undefined"
                                                :multi-calendars-solo="form.Way === 2"
                                                :month-picker="false"
                                                :auto-apply="true"
                                                :close-on-auto-apply="true"
                                                :partial-range="false"
                                                :text-input="false"
                                                :dark="isDark"
                                                :columns="form.Way === 2 ? 2 : 1"/>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <button id="search-flight-btn"
                                        class="search-flight-btn"
                                        @click="Lowfaresearch()"
                                        aria-label="Search Flights"
                                    ></button>
                                </div>
                            </div>

                            <div v-if="form.Way === 2" class="row g-2 align-items-center justify-content-lg-end">
                                <div class="col-lg-5 col-md-5">
                                    <div class="date-picker-wrapper" :class="{ animate: animateDateCard }">
                                            <div class="date-card" @click="openPicker" :class="{ animate: animateDateCard }">
                                                <div class="date-number" :class="dateNumberFlyState">
                                                    {{ form.Way === 2 && selectedDateRange[0] ?
                                                        new Date(selectedDateRange[0]).getDate().toString().padStart(2, '0') :
                                                        (selectedDate ? new Date(selectedDate).getDate().toString().padStart(2, '0') : new Date().getDate().toString().padStart(2, '0'))
                                                    }}
                                                </div>
                                                <div class="date-info" :class="dateInfoFlyState">
                                                    <div class="day">
                                                        {{ form.Way === 2 && selectedDateRange[0] ?
                                                            new Date(selectedDateRange[0]).toLocaleDateString('en-US', { month: 'long' }) :
                                                            (selectedDate ? new Date(selectedDate).toLocaleDateString('en-US', { month: 'long' }) :
                                                            new Date().toLocaleDateString('en-US', { month: 'long' }))
                                                        }}
                                                    </div>
                                                    <div class="month-year">
                                                        {{ form.Way === 2 && selectedDateRange[0] ?
                                                            `${new Date(selectedDateRange[0]).toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date(selectedDateRange[0]).getFullYear()}` :
                                                            (selectedDate ?
                                                                `${new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date(selectedDate).getFullYear()}` :
                                                                `${new Date().toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date().getFullYear()}`)
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                            <VueDatePicker
                                                ref="datePickerRef"
                                                :model-value="form.Way === 1 ? selectedDate.value : selectedDateRange.value"
                                                @update:model-value="handleDateChange"
                                                :enable-time-picker="false"
                                                :format="formatDisplayDate"
                                                :range="form.Way === 2"
                                                :min-date="new Date()"
                                                :multi-calendars="form.Way === 2 ? 2 : undefined"
                                                :multi-calendars-solo="form.Way === 2"
                                                :month-picker="false"
                                                :auto-apply="true"
                                                :close-on-auto-apply="true"
                                                :partial-range="false"
                                                :text-input="false"
                                                :dark="isDark"
                                                :columns="form.Way === 2 ? 2 : 1"/>
                                    </div>
                                </div>
                                <div class="col-lg-5 col-md-5">
                                    <div class="date-picker-wrapper" :class="{ animate: animateReturnDateCard }">
                                        <div class="date-card" @click="openReturnPicker" :class="{ animate: animateReturnDateCard }">
                                            <div class="date-number" :class="returnDateNumberFlyState">
                                                {{ selectedDateRange[1] ?
                                                    new Date(selectedDateRange[1]).getDate().toString().padStart(2, '0') :
                                                    new Date().getDate().toString().padStart(2, '0')
                                                }}
                                            </div>
                                            <div class="date-info" :class="returnDateInfoFlyState">
                                                <div class="day">
                                                    {{ selectedDateRange[1] ?
                                                        new Date(selectedDateRange[1]).toLocaleDateString('en-US', { month: 'long' }) :
                                                        new Date().toLocaleDateString('en-US', { month: 'long' })
                                                    }}
                                                </div>
                                                <div class="month-year">
                                                    {{ selectedDateRange[1] ?
                                                        `${new Date(selectedDateRange[1]).toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date(selectedDateRange[1]).getFullYear()}` :
                                                        `${new Date().toLocaleDateString('en-US', { weekday: 'short' })}, ${new Date().getFullYear()}`
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <VueDatePicker
                                            ref="returnDatePickerRef"
                                            v-model="selectedDateRange[1]"
                                            :enable-time-picker="false"
                                            auto-apply
                                            :format="formatSelectedDate"
                                            @update:model-value="handleReturnDateChange"
                                            :teleport="true"
                                            :auto-position="true"
                                            :dark="isDark"
                                        />
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2">
                                    <button
                                        id="search-flight-btn"
                                        class="search-flight-btn"
                                        @click="Lowfaresearch()"
                                        aria-label="Search Flights"
                                    ></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="search-panel-bottom">
                        <div class="bottom-stats">
                            <span class="stat-item"><i class="bx bxs-plane stat-icon stat-icon--airline"></i>{{ distinctAirlines.length }} AIRLINES</span>
                            <span class="stat-item"><i class="bx bx-git-branch stat-icon stat-icon--route"></i>{{ totalFlights }} ROUTES</span>
                            <span v-show="flights.length > 0" class="search-result-meta d-flex align-items-center gap-2 flex-wrap">
                                Showing {{ flights.length }} of {{ totalFlights }} Total Flights &nbsp;|&nbsp;
                                API: {{ apiTime }}s &nbsp;|&nbsp; UI: {{ uiTime }}s &nbsp;|&nbsp; Total: {{ ExecutionTime }}s
                                <button
                                    v-if="flights.length > 0 && searchLogId"
                                    class="icon-action-btn icon-download"
                                    type="button"
                                    title="Download payload & response"
                                    :disabled="isDownloadingSearchFiles"
                                    @click="downloadSearchFiles"
                                >
                                    <i :class="isDownloadingSearchFiles ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-download'"></i>
                                </button>
                            </span>
                        </div>
                        <span
                            v-if="searchStore.isValid"
                            class="clear-search-btn"
                            @click="clearAndReset"
                        ><span class="clear-label">Clear</span> <span class="clear-x">✕</span></span>
                    </div>

                </div>
            </div>
        </div>
    </div>
    </div>
    <!-- search Panel end -->

    <div class="row search-content-row">
        <!-- left filter panel start -->
        <div class="col-md-3 search-filter-column">
            <div
                class="search-filter-scroll"
                :class="{ 'is-scroll-hover': isFilterScrollHover }"
                @mouseenter="isFilterScrollHover = true"
                @mouseleave="isFilterScrollHover = false"
            >
            <div class="row search-filter-inner-row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex">
                                <img src="../../../../public/theme/animation/Session_Timer.gif" height="36" width="36"
                                    alt="">
                                &nbsp;&nbsp;
                                <span class="pt-2" style="font-size: 12px; margin-top: 4px;"><b>Book Flight
                                        within</b></span>
                                &nbsp; &nbsp;
                                <div class="dash-lable bg-light-primary custom-text-purple rounded-1"
                                    style="padding-top: 8px;">
                                    <p class="fcolor mb-0" style="font-weight: 600;">{{ String(bookingTimerMinutes).padStart(2, '0') }}</p>
                                </div>
                                &nbsp;
                                <div class="ml-1 mr-1" style="margin-top: 9px;"><b>:</b></div>
                                &nbsp;
                                <div class="dash-lable bg-light-primary custom-text-purple rounded-1"
                                    style="padding-top: 8px;">
                                    <p class="fcolor mb-0" style="font-weight: 600;">{{ String(bookingTimerSeconds).padStart(2, '0') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 search-filters">
                    <!-- price-range -->

                    <div class="accordion" id="price-range">
                        <div class="accordion-item">
                            <p class="accordion-header" id="headingOne">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne"
                                    aria-expanded="true" aria-controls="collapseOne">
                                    <span style="float:left;">
                                        <span style="font-size: 13px;">Price Range</span>
                                    </span>
                                </button>
                            </p>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                                data-bs-parent="#price-range">
                                <div class="accordion-body">
                                    <div class="slider-cont">
                                        <CustomMinMaxSlider
                                            :key="`slider-${priceRangeMin}-${priceRangeMax}`"
                                            :min="priceRangeMin"
                                            :max="priceRangeMax"
                                            v-model:min-value="sliderMin"
                                            v-model:max-value="sliderMax"
                                        />
                                        <p class="text-center mb-0">
                                            BDT {{ sliderMin.toLocaleString() }} - BDT {{ sliderMax.toLocaleString() }}
                                        </p>
                                        <div class="text-center p-0">
                                            <span class="text-danger">*</span> Price may change based on policy
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Flight Schedule -->
                    <div class="accordion" id="flight-schedule">
                        <div class="accordion-item filter-accordion-gap">
                            <h6 class="accordion-header" id="headingSix">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix"
                                    aria-expanded="true" aria-controls="collapseSix">
                                    <span style="font-size: 13px;">Flight Schedule</span>
                                </button>
                            </h6>
                            <div id="collapseSix" class="accordion-collapse collapse show" aria-labelledby="headingSix"
                                data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="d-flex gap-2 border p-1">
                                                <button type="button"
                                                    :class="['btn btn-sm w-100', scheduleMode === 'departure' ? 'bluesky-route-btn-primary' : 'bluesky-btn-outline-primary px-0 py-0 btn-sm-size']"
                                                    @click="scheduleMode = 'departure'; selectedScheduleSegment = null">Departure</button>
                                                <button type="button"
                                                    :class="['btn btn-sm w-100', scheduleMode === 'arrival' ? 'bluesky-route-btn-primary' : 'bluesky-btn-outline-primary px-0 py-0 btn-sm-size']"
                                                    @click="scheduleMode = 'arrival'; selectedScheduleSegment = null">Arrival</button>
                                            </div>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <div class="d-flex gap-2">
                                                <div style="background: linear-gradient(180deg, rgba(135, 160, 174, 0.50) 0%, rgba(208, 200, 187, 0.30) 50%, rgba(254, 191, 84, 0.20) 100%); height: 70px !important; min-width: 55px !important;"
                                                    :class="['p-1 border text-center rounded rounded-1 accorion-item-title-color cursor-pointer', selectedScheduleSegment === '00-06' ? 'border-primary border-2' : 'border-1']"
                                                    @click="selectedScheduleSegment = selectedScheduleSegment === '00-06' ? null : '00-06'">
                                                    <img src="../../../../public/theme/animation/Sun_Rise.gif"
                                                        style="width: 42px;" alt="">
                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">00-06 AM</span>
                                                </div>
                                                <div style="background: linear-gradient(180deg, rgba(255, 240.15, 143.65, 0.50) 0%, rgba(221.85, 201.52, 51.76, 0.30) 53%, rgba(187.85, 164.39, 11.90, 0.20) 100%); height: 70px !important; min-width: 55px;"
                                                    :class="['p-1 border text-center rounded rounded-1 accorion-item-title-color cursor-pointer', selectedScheduleSegment === '06-12' ? 'border-primary border-2' : 'border-1']"
                                                    @click="selectedScheduleSegment = selectedScheduleSegment === '06-12' ? null : '06-12'">
                                                    <img src="../../../../public/theme/animation/Noon.gif"
                                                        style="width: 42px;" alt="">
                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">06-12 PM</span>
                                                </div>
                                                <div style="background: linear-gradient(180deg, rgba(40.98, 55.67, 121.12, 0.50) 0%, rgba(110, 87, 100, 0.30) 52%, rgba(230, 141, 91, 0.20) 100%); height: 70px !important; min-width: 55px;"
                                                    :class="['p-1 border text-center rounded rounded-1 accorion-item-title-color cursor-pointer', selectedScheduleSegment === '12-18' ? 'border-primary border-2' : 'border-1']"
                                                    @click="selectedScheduleSegment = selectedScheduleSegment === '12-18' ? null : '12-18'">
                                                    <img src="../../../../public/theme/animation/Sun_Set.gif"
                                                        style="width: 32px; margin-bottom: 11px;" alt="">
                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">12-06 PM</span>
                                                </div>
                                                <div style="background: linear-gradient(182deg, #081627 0%, rgba(0, 66.30, 132.60, 0.40) 55%, rgba(157.25, 227.55, 255, 0.60) 100%); height: 70px !important; min-width: 55px;"
                                                    :class="['p-1 border text-center rounded rounded-1 accorion-item-title-color cursor-pointer', selectedScheduleSegment === '18-24' ? 'border-primary border-2' : 'border-1']"
                                                    @click="selectedScheduleSegment = selectedScheduleSegment === '18-24' ? null : '18-24'">
                                                    <img src="../../../../public/theme/animation/Night.gif"
                                                        style="width: 35px; margin-bottom: 8px;" alt="">
                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">06-12 AM</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- airlines -->
                    <div class="accordion" id="arilines">
                        <div class="accordion-item filter-accordion-gap">
                            <h6 class="accordion-header" id="headingfour">
                                <button class="accordion-button accorion-item-title-color collapsed m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour"
                                    aria-expanded="false" aria-controls="collapseFour">
                                    <span class="" style="font-size: 13px;">Airlines</span>
                                </button>
                            </h6>
                            <div id="collapseFour" class="accordion-collapse collapse"
                                aria-labelledby="headingfour" data-bs-parent="#accordionExample">
                                <div class="accordion-body p-2">
                                    <!-- search box — only shown when 5+ airlines -->
                                    <div v-if="distinctAirlines.length >= 5" class="mb-2 position-relative">
                                        <input
                                            v-model="airlineSearch"
                                            type="text"
                                            class="form-control form-control-sm pe-4"
                                            placeholder="Search airline..."
                                            style="font-size: 12px;"
                                        >
                                        <button
                                            v-if="airlineSearch"
                                            @click="airlineSearch = ''"
                                            class="airline-search-clear"
                                            type="button"
                                            title="Clear"
                                        >&times;</button>
                                    </div>

                                    <div
                                        v-for="airline in filteredAirlineList"
                                        :key="airline.name"
                                        :class="['airline-filter-row d-flex align-items-center gap-2 px-2 py-1 rounded cursor-pointer mb-1', selectedAirlines.includes(airline.name) ? 'airline-filter-row--active' : '']"
                                        @click="selectedAirlines.includes(airline.name) ? selectedAirlines.splice(selectedAirlines.indexOf(airline.name), 1) : selectedAirlines.push(airline.name)"
                                        role="checkbox" :aria-checked="selectedAirlines.includes(airline.name)"
                                    >
                                        <img
                                            :src="airline.logo"
                                            :alt="airline.code"
                                            class="airline-filter-logo flex-shrink-0"
                                            @error="$event.target.style.display='none'"
                                        >
                                        <span class="flex-grow-1 airline-filter-name">{{ airline.name }}</span>
                                        <span class="airline-filter-count">{{ String(airline.count).padStart(2, '0') }}</span>
                                    </div>

                                    <div v-if="filteredAirlineList.length === 0" class="text-muted px-2" style="font-size: 12px;">
                                        {{ distinctAirlines.length ? 'No match' : 'No results yet' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- stop -->
                    <div class="accordion" id="stop">
                        <div class="accordion-item filter-accordion-gap">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button accorion-item-title-color collapsed m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo"
                                    aria-expanded="false" aria-controls="collapseTwo">

                                    <span class="" style="font-size: 13px;">Stop
                                    </span>
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo"
                                data-bs-parent="#accordionExample">
                                <div class="accordion-body p-2">
                                    <div class="d-flex flex-column gap-2">
                                        <div
                                            v-for="s in distinctStops" :key="s.count"
                                            :class="['stop-card cursor-pointer', selectedStops.includes(s.count) ? 'stop-card--active' : '']"
                                            @click="selectedStops.includes(s.count) ? selectedStops.splice(selectedStops.indexOf(s.count), 1) : selectedStops.push(s.count)"
                                        >
                                            <div class="stop-card__route">
                                                <span class="stop-card__endpoint"></span>
                                                <span class="stop-card__track">
                                                    <span v-for="i in s.count" :key="i" class="stop-card__dot"></span>
                                                </span>
                                                <span class="stop-card__endpoint"></span>
                                            </div>
                                            <span class="stop-card__label">{{ s.label }}</span>
                                            <span class="stop-card__count">{{ String(s.flightCount).padStart(2, '0') }}</span>
                                        </div>
                                        <div v-if="!distinctStops.length" class="text-muted" style="font-size: 12px;">No data</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Refund availability -->
                    <div class="accordion" id="refund">
                        <div class="accordion-item filter-accordion-gap">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button accorion-item-title-color collapsed m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree"
                                    aria-expanded="false" aria-controls="collapseThree">

                                    <span style="font-size: 13px;">Refund Availability</span>
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse"
                                aria-labelledby="headingThree" data-bs-parent="#accordionExample">
                                <div class="accordion-body p-2">
                                    <div class="d-flex flex-column gap-2">
                                        <div
                                            v-for="r in distinctRefundTypes" :key="r.type"
                                            :class="['refund-card cursor-pointer', `refund-card--${r.type}`, selectedRefundTypes.includes(r.type) ? 'refund-card--active' : '']"
                                            @click="selectedRefundTypes.includes(r.type) ? selectedRefundTypes.splice(selectedRefundTypes.indexOf(r.type), 1) : selectedRefundTypes.push(r.type)"
                                        >
                                            <i :class="['refund-card__icon fa-solid', r.type === 'refundable' ? 'fa-rotate-left' : r.type === 'partial' ? 'fa-clock-rotate-left' : 'fa-ban']"></i>
                                            <span class="refund-card__label">{{ r.label }}</span>
                                            <span class="refund-card__count ms-auto">{{ String(r.count).padStart(2, '0') }}</span>
                                        </div>
                                        <div v-if="!distinctRefundTypes.length" class="text-muted" style="font-size: 12px;">No data</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Baggage -->
                    <div class="accordion" id="baggage">
                        <div class="accordion-item filter-accordion-gap">
                            <h2 class="accordion-header" id="headingSeven">
                                <button class="accordion-button accorion-item-title-color collapsed m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven"
                                    aria-expanded="false" aria-controls="collapseSeven">

                                    <span style="font-size: 13px;">Baggage</span>

                                </button>
                            </h2>
                            <div id="collapseSeven" class="accordion-collapse collapse"
                                aria-labelledby="headingSeven" data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            10 kg
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            20 kg
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            30 kg
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            02 Piece
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layover -->
                    <div class="accordion" id="layover">
                        <div class="accordion-item filter-accordion-gap">
                            <h6 class="accordion-header" id="headingEight">
                                <button class="accordion-button accorion-item-title-color collapsed m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight"
                                    aria-expanded="false" aria-controls="collapseEight">

                                    <span style="font-size: 13px;">Layover</span>

                                </button>
                            </h6>
                            <div id="collapseEight" class="accordion-collapse collapse"
                                aria-labelledby="headingEight" data-bs-parent="#accordionExample">
                                <div class="accordion-body p-2">
                                    <div v-if="distinctLayovers.length >= 5" class="mb-2 position-relative">
                                        <input
                                            v-model="layoverSearch"
                                            type="text"
                                            class="form-control form-control-sm pe-4"
                                            placeholder="Search airport..."
                                            style="font-size: 12px;"
                                        >
                                        <button v-if="layoverSearch" @click="layoverSearch = ''" class="airline-search-clear" type="button">&times;</button>
                                    </div>
                                    <div class="d-flex flex-column gap-1">
                                        <div
                                            v-for="l in filteredLayoverList" :key="l.code"
                                            :class="['layover-row cursor-pointer d-flex align-items-center gap-2', selectedLayovers.includes(l.code) ? 'layover-row--active' : '']"
                                            @click="selectedLayovers.includes(l.code) ? selectedLayovers.splice(selectedLayovers.indexOf(l.code), 1) : selectedLayovers.push(l.code)"
                                        >
                                            <span class="layover-code-badge">{{ l.code }}</span>
                                            <span class="layover-airport-name">{{ l.name }}</span>
                                            <span class="layover-flight-count ms-auto">{{ String(l.count).padStart(2, '0') }}</span>
                                        </div>
                                        <div v-if="filteredLayoverList.length === 0" class="text-muted px-1" style="font-size: 12px;">
                                            {{ distinctLayovers.length ? 'No match' : 'No layovers' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 mt-4">
                    <button class="btn btn-block btn-sm bluesky-btn-outline-primary w-100" @click="clearAllFilters">
                        Clear All Filters
                    </button>
                </div>
            </div>
            </div>
        </div>
        <!-- left filter panel end -->

        <!-- result panel start -->
        <div class="col-md-9 search-results-column">
            <div
                class="search-results-scroll"
                :class="{ 'is-scroll-hover': isResultsScrollHover }"
                @mouseenter="isResultsScrollHover = true"
                @mouseleave="isResultsScrollHover = false"
            >
            <div class="row search-results-inner-row">
                <!-- skeleton loader: shown while waiting for API -->
                <template v-if="loadging">
                    <div v-for="n in 5" :key="'sk-'+n" class="col-md-12 mb-3">
                        <div class="card" style="border-radius:8px; overflow:hidden;">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col-md-2 col-3">
                                        <div class="skeleton-box" style="width:50px;height:50px;border-radius:6px;"></div>
                                        <div class="skeleton-box mt-2" style="width:80px;height:12px;border-radius:4px;"></div>
                                    </div>
                                    <div class="col-md-3 col-4">
                                        <div class="skeleton-box" style="width:60px;height:18px;border-radius:4px;"></div>
                                        <div class="skeleton-box mt-2" style="width:90px;height:12px;border-radius:4px;"></div>
                                    </div>
                                    <div class="col-md-3 col-3 text-center">
                                        <div class="skeleton-box mx-auto" style="width:70px;height:12px;border-radius:4px;"></div>
                                        <div class="skeleton-box mx-auto mt-2" style="width:40px;height:12px;border-radius:4px;"></div>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <div class="skeleton-box" style="width:60px;height:18px;border-radius:4px;"></div>
                                        <div class="skeleton-box mt-2" style="width:80px;height:12px;border-radius:4px;"></div>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <div class="skeleton-box" style="width:100%;height:48px;border-radius:6px;"></div>
                                    </div>
                                </div>
                                <hr class="my-2">
                                <div class="row align-items-center">
                                    <div class="col-md-2 col-3">
                                        <div class="skeleton-box" style="width:50px;height:50px;border-radius:6px;"></div>
                                        <div class="skeleton-box mt-2" style="width:80px;height:12px;border-radius:4px;"></div>
                                    </div>
                                    <div class="col-md-3 col-4">
                                        <div class="skeleton-box" style="width:60px;height:18px;border-radius:4px;"></div>
                                        <div class="skeleton-box mt-2" style="width:90px;height:12px;border-radius:4px;"></div>
                                    </div>
                                    <div class="col-md-3 col-3 text-center">
                                        <div class="skeleton-box mx-auto" style="width:70px;height:12px;border-radius:4px;"></div>
                                        <div class="skeleton-box mx-auto mt-2" style="width:40px;height:12px;border-radius:4px;"></div>
                                    </div>
                                    <div class="col-md-2 col-4">
                                        <div class="skeleton-box" style="width:60px;height:18px;border-radius:4px;"></div>
                                        <div class="skeleton-box mt-2" style="width:80px;height:12px;border-radius:4px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-for="(flight, index) in filteredFlights" :key="index" class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-stretch">
                                <div class="flex-grow-1 min-w-0">

                                    <!-- outbound start-->
                                    <div v-if="flight.outbound">
                                        <div class="row">
                                                <div class="col-md-4 col-sm-3 p-0">
                                                    <div class="d-flex flex-row">
                                                        <div class="p-1">
                                                            <img :src="flight.outbound.first_logo_path" height="50"
                                                                width="50">
                                                        </div>
                                                        <div class="pt-1 ps-2">
                                                            <div
                                                                class="d-flex justify-content-center align-items-center h-100 w-100">
                                                                <div>
                                                                    <div class="fcolor"><b>{{ flight.outbound.origin
                                                                            }}-{{
                                                                                flight.outbound.destination }}</b>
                                                                    </div>
                                                                    <div style="font-size: 11px; color: #8327a4">
                                                                        {{ flight.outbound.first_carrier_code }} | {{
                                                                            flight.outbound.first_airline_name }}
                                                                    </div>
                                                                    <div style="font-size: 9px; color: #5e6878;">
                                                                        {{ flight.outbound.segments.map(s => s.is_codeshare ? (s.flight_number + '(' + s.codeshare_info.operating_carrier + s.codeshare_info.operating_flight_number + ')') : s.flight_number).join(' | ') }} - {{ flight.outbound.segments.map(s => s.flightRef).join(' | ') }} | {{ flight.outbound._offering_id }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="col-md-8 col-12 border-md-start border-md-end">
                                                    <div class="row p-2">
                                                        <div class="col-md-4 col-4">
                                                            <div
                                                                class="d-block justify-content-center align-items-center h-100 w-100">
                                                                <div class="fcolor"><b>{{
                                                                    flight.outbound.departure_time }}</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">
                                                                        {{ formatDisplayDate(flight.outbound.departure_date) }}
                                                                    </small>
                                                                </div>
                                                                <div>
                                                                    <small
                                                                        style="font-size: 12px; color: #5e6878;">Departure</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-4">
                                                            <div class="d-block">
                                                                <div> <small class="ps-2"
                                                                        style="font-size: 11px; color: #5e6878;">{{
                                                                            flight.outbound.total_flight_time }}</small>
                                                                </div>


                                                                <div class="d-flex">
                                                                    <div class="fcolor">
                                                                        <img src="../../../../public/theme/appimages/Left_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>
                                                                    <div>
                                                                        <img style="transform: rotate(0deg)"
                                                                            src="../../../../public/theme/animation/Route_Aircraft.svg"
                                                                            alt="" height="22" width="22">
                                                                    </div>
                                                                    <div class="fcolor">
                                                                        <img src="../../../../public/theme/appimages/Right_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>


                                                                </div>

                                                                <div class="d-inline-flex ms-2" style="align-items: flex-start;">
                                                                    <div class="d-flex flex-row align-items-start" v-for="stop,iter in flight.outbound.connections.stops">
                                                                        <div class="text-center">
                                                                            <AppTooltip :content="`Layover at ${stop.city_name} (${stop.airport_code}) | ${stop.layover_time} | ${stop.airport_name}`" placement="top">
                                                                                <img src="../../../../public/theme/appimages/Layover.svg" alt="" style="cursor:pointer;">
                                                                            </AppTooltip>
                                                                            <div style="font-size: 7px; color: #5e6878; line-height: 1;">{{ stop.airport_code }}</div>
                                                                        </div>
                                                                        <div class="bd-highlight fcolor" v-if="iter != (flight.outbound.connections.stops.length - 1)" style="padding-top: 2px;">
                                                                            ....
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-4">
                                                            <div
                                                                class="d-block justify-content-center align-items-center h-100 w-100">
                                                                <div class="fcolor"><b>{{
                                                                    flight.outbound.arrival_time }}</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">
                                                                        {{ formatDisplayDate(flight.outbound.arrival_date)
                                                                        }}</small>
                                                                </div>
                                                                <div>
                                                                    <small
                                                                        style="font-size: 12px; color: #5e6878;">Arrival</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <!-- outbound end -->

                                    <!-- inbound start -->
                                    <div v-if="flight.inbound" class="border-top mt-3">
                                        <div class="row mt-3">
                                                <div class="col-md-4 col-sm-3 p-0">
                                                    <div class="d-flex flex-row">
                                                        <div class="p-1">
                                                            <img :src="flight.inbound.first_logo_path" height="50"
                                                                width="50">
                                                        </div>
                                                        <div class="pt-1 ps-2">
                                                            <div
                                                                class="d-flex justify-content-center align-items-center h-100 w-100">
                                                                <div>
                                                                    <div class="fcolor"><b>{{ flight.inbound.origin
                                                                            }} - {{ flight.inbound.destination }}</b>
                                                                    </div>
                                                                    <div style="font-size: 11px; color: #8327a4">
                                                                        {{ flight.inbound.first_carrier_code }} | {{
                                                                            flight.inbound.first_airline_name }}
                                                                    </div>
                                                                    <div style="font-size: 9px; color: #5e6878;">
                                                                        {{ flight.inbound.segments.map(s => s.is_codeshare ? (s.flight_number + '(' + s.codeshare_info.operating_carrier + s.codeshare_info.operating_flight_number + ')') : s.flight_number).join(' | ') }} - {{ flight.inbound.segments.map(s => s.flightRef).join(' | ') }} | {{ flight.inbound._offering_id }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="col-md-8 col-12 border-md-start border-md-end">
                                                    <div class="row p-2">
                                                        <div class="col-md-4 col-4">
                                                            <div
                                                                class="d-block justify-content-center align-items-center h-100 w-100">
                                                                <div class="fcolor"><b>{{
                                                                    flight.inbound.departure_time }}</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">{{
                                                                        formatDisplayDate(flight.inbound.departure_date)
                                                                        }}</small>
                                                                </div>
                                                                <div>
                                                                    <small
                                                                        style="font-size: 12px; color: #5e6878;">Departure</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-4">
                                                            <div class="d-block">
                                                                <div> <small class="ps-2"
                                                                        style="font-size: 11px; color: #5e6878;">{{
                                                                            flight.inbound.total_flight_time }}</small>
                                                                </div>


                                                                <div class="d-flex">
                                                                    <div class="fcolor">
                                                                        <img src="../../../../public/theme/appimages/Left_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>
                                                                    <div>
                                                                        <img style="transform: rotate(180deg)"
                                                                            src="../../../../public/theme/animation/Route_Aircraft.svg"
                                                                            alt="" height="22" width="22">
                                                                    </div>
                                                                    <div class="fcolor">
                                                                        <img src="../../../../public/theme/appimages/Right_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>
                                                                </div>

                                                                <div class="d-inline-flex ms-2" style="align-items: flex-start;">
                                                                    <div class="d-flex flex-row align-items-start" v-for="stop,iter in flight.inbound.connections.stops">
                                                                        <div class="text-center">
                                                                            <AppTooltip :content="`Layover at ${stop.city_name} (${stop.airport_code}) | ${stop.layover_time} | ${stop.airport_name}`" placement="top">
                                                                                <img src="../../../../public/theme/appimages/Layover.svg" alt="" style="cursor:pointer;">
                                                                            </AppTooltip>
                                                                            <div style="font-size: 7px; color: #5e6878; line-height: 1;">{{ stop.airport_code }}</div>
                                                                        </div>
                                                                        <div class="bd-highlight fcolor" v-if="iter != (flight.inbound.connections.stops.length - 1)" style="padding-top: 2px;">
                                                                            ....
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-4">
                                                            <div
                                                                class="d-block justify-content-center align-items-center h-100 w-100">
                                                                <div class="fcolor"><b>{{ flight.inbound.arrival_time }}</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">{{ formatDisplayDate(flight.inbound.arrival_date) }}</small>
                                                                </div>
                                                                <div>
                                                                    <small
                                                                        style="font-size: 12px; color: #5e6878;">Arrival</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                    </div>
                                    <!-- inbound end -->

                                </div>
                                <!-- price button: spans full height of both rows, centered -->
                                <div class="price-cta-col d-flex align-items-center justify-content-center" style="width:190px;flex-shrink:0;padding:0 10px 0 22px;">
                                    <button class="price-cta-btn w-100" data-bs-toggle="collapse"
                                        :data-bs-target="`#flight-package-${index}`" :aria-controls="`flight-package-${index}`">
                                        <div class="price-cta-btn__top">
                                            <span class="price-cta-btn__from-label">from</span>
                                            <i class="fa-solid text-info fa-layer-group price-cta-btn__layers"></i>
                                        </div>
                                        <div class="price-cta-btn__amount">
                                            <span class="price-cta-btn__currency">BDT</span>
                                            <span class="price-cta-btn__number">
                                                <template v-for="(char, ci) in calcOutboundPrice(flight).split('')" :key="ci">
                                                    <span v-if="!/[0-9]/.test(char)" class="price-rolling-sep">{{ char }}</span>
                                                    <span v-else class="price-rolling-digit"
                                                        :style="{ '--roll-target': `-${parseInt(char) * 1.2}em`, animationDelay: `${ci * 0.08}s` }">
                                                        <span v-for="n in 10" :key="n">{{ n - 1 }}</span>
                                                    </span>
                                                </template>
                                            </span>
                                        </div>
                                        <div class="price-cta-btn__divider"></div>
                                        <div class="price-cta-btn__cta">
                                            <span class="price-cta-btn__cabin">{{ flight.outbound.cabin || 'Economy' }}</span>
                                            <span class="price-cta-btn__hint text-info"><i class="fa-solid fa-tags me-1"></i>View fares</span>
                                        </div>
                                        <div class="price-cta-btn__chevron">
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer p-0 search-result-card-footer" style="background-color:#f1f4f7;">
                            <div class="float-start p-2">
                                <div class="d-flex gap-2">

                                    <div v-if="flight.outbound.refund_type === 'refundable'" class="border border-1 text-center p-1 flight-badge flight-badge--refundable"
                                        style="background-color: #def1ec; color: #12ce69; font-size: 12px;">
                                        <img src="../../../../public/theme/appimages/refund-able.svg" alt="">
                                        Refundable
                                    </div>
                                    <div v-else-if="flight.outbound.refund_type === 'partial'" class="border border-1 text-center p-1 flight-badge flight-badge--partial"
                                        style="background-color: #fef9ec; color: #d4a017; font-size: 12px;">
                                        <img src="../../../../public/theme/appimages/refund-able.svg" alt="">
                                        Partially Refundable
                                    </div>
                                    <div v-else class="border border-1 text-center p-1 flight-badge flight-badge--non-refundable"
                                        style="background-color: #f1dede; color: #ce1212; font-size: 12px;">
                                        <img src="../../../../public/theme/appimages/Non-Refundable.svg" alt="">
                                        Non-refundable
                                    </div>

                                    <div class="border border-1 text-center p-1 flight-badge flight-badge--seats"
                                        style="background-color: #e4e3f6; color: #7944eb; font-size: 12px;">
                                        <i class="fa-regular fa-seat-airline"></i> Available Seats: 09
                                    </div>

                                    <div v-if="flight.outbound.segments.some(s => s.is_codeshare)" class="border border-1 text-center p-1"
                                        style="background-color: #fff3e0; color: #e65100; font-size: 12px;">
                                         <i class="fa-solid fa-plane-circle-check"></i>
                                         Operated by {{ flight.outbound.segments.find(s => s.is_codeshare)?.codeshare_info.operating_airline_name }}
                                    </div>
                                    <div v-if="flight.inbound && flight.inbound.segments.some(s => s.is_codeshare)" class="border border-1 text-center p-1"
                                        style="background-color: #fff3e0; color: #e65100; font-size: 12px;">
                                         <i class="fa-solid fa-plane-circle-check"></i>
                                         Return operated by {{ flight.inbound.segments.find(s => s.is_codeshare)?.codeshare_info.operating_airline_name }}
                                    </div>
                                </div>
                            </div>
                            <div class="float-end me-2">
                                <div class="d-flex gap-2">
                                    <div class="accordion accordion-flush" id="accordionFlushExample">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="flush-headingOne">
                                                <a class="accordion-button custom-text-purple collapsed m-0 p-0 px-2 py-2"
                                                    :data-bs-target="'#flight-details-' + index"
                                                    :aria-controls="'flight-details-' + index" data-bs-toggle="collapse"
                                                    aria-expanded="false"
                                                    style="font-size: 12px;">
                                                    <b>Flight Details</b>
                                                </a>
                                            </h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- flight details2 -->
                    <div :id="`flight-details-${index}`" class="accordion-collapse collapse m-0"
                        aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample" style="">
                        <div class="accordion-body">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <ul class="nav nav-tabs nav-primary mb-0" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <a class="nav-link active" data-bs-toggle="tab" :href="`#primaryhome-${index}`"
                                                        role="tab" aria-selected="true">
                                                        <div class="d-flex align-items-center">
                                                            <div class="tab-icon"><i
                                                                    class="bx bx-comment-detail font-18 me-1"></i>
                                                            </div>
                                                            <div class="tab-title"> Flight Details</div>
                                                        </div>
                                                    </a>
                                                </li>
                                                <!-- flight.outbound.farerulekey -->
                                                <li class="nav-item" role="presentation">
                                                    <a class="nav-link" data-bs-toggle="tab" :href="`#primaryprofile-${index}`"
                                                        role="tab" aria-selected="false" tabindex="-1"
                                                        @click="fetchFareRules(flight, index)">
                                                        <div class="d-flex align-items-center">
                                                            <div class="tab-icon"><i
                                                                    class="bx bx-bookmark-alt font-18 me-1"></i>
                                                            </div>
                                                            <div class="tab-title">Fare Rules</div>
                                                        </div>
                                                    </a>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <a class="nav-link" data-bs-toggle="tab" :href="`#primarycontact-${index}`"
                                                        role="tab" aria-selected="false" tabindex="-1">
                                                        <div class="d-flex align-items-center">
                                                            <div class="tab-icon"><i
                                                                    class="bx bx-star font-18 me-1"></i>
                                                            </div>
                                                            <div class="tab-title">Refund Policy</div>
                                                        </div>
                                                    </a>
                                                </li>
                                            </ul>
                                            <div class="tab-content pt-3">
                                                <div class="tab-pane fade active show" :id="`primaryhome-${index}`" role="tabpanel">
                                                    <div class="d-flex d-flex-row mb-1">
                                                        <div v-if="flight.outbound" class="p-1 bd-highlight">
                                                            <button @click="showRouteDetails(1,index)"
                                                                :class="`btn btn-sm px-2 py-1 bluesky-route-btn-primary ${index}`" :id="'active-btn-'+index">{{
                                                                    flight.outbound.origin }}-{{ flight.outbound.destination
                                                                }}</button>
                                                        </div>
                                                        <div v-if="flight.inbound" class="p-1 bd-highlight">
                                                            <button @click="showRouteDetails(2,index)"
                                                                :class="`btn btn-sm px-2 py-1 bluesky-route-btn-outline-primary ${index}`" :id="'deactive-btn-'+index">{{
                                                                    flight.inbound.origin }}-{{ flight.inbound.destination
                                                                }}</button>
                                                        </div>
                                                    </div>

                                                    <div v-for="route in flight.outbound.segments"
                                                        :class="`flight-tab-active-${index} fadeIn`">
                                                        <div class="card">
                                                            <div class="card-header accorion-item-title-color m-0 p-0 flight-detail-card-header"
                                                                style="background-color: #f2f5f7;">
                                                                <div class="d-flex">
                                                                    <div class="p-2 flex-grow-1">
                                                                        <b>
                                                                            <img src="../../../../public/theme/appimages/Plane.svg"
                                                                                alt="">
                                                                        </b>
                                                                        <small><b><span
                                                                                    class="bluesky-departure-text">Departure
                                                                                    From </span></b>
                                                                            <b><span
                                                                                    class="bluesky-departure-airport-text">
                                                                                    {{ route.Origin_Airport_Name }}
                                                                                </span></b>
                                                                        </small>
                                                                    </div>

                                                                    <div class="p-2 bluesky-departure-text flight-time">
                                                                        <small><b>Flight Time: {{ route.flightTime1
                                                                        }}</b></small>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-sm-4 col-4 col-md-4 col-4">
                                                                        <div
                                                                            class="d-block justify-content-center align-items-center h-100 w-100">
                                                                            <div class="text-black-"
                                                                                style="color: #0fb3a6;">
                                                                                <b>{{ route.departure_code }}</b>
                                                                            </div>
                                                                            <div>
                                                                                <small style="font-size: 11px;"
                                                                                    class="fcolor"><b>{{
                                                                                        route.departure_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span></b></small>
                                                                                <span style="font-size: 11px;">{{
                                                                                    formatDisplayDate(route.departure_date)
                                                                                }}</span>
                                                                            </div>
                                                                            <div>
                                                                                <small
                                                                                    style="font-size: 12px; color: #5e6878;">Terminal:
                                                                                    {{ route.originTerminal }}</small>
                                                                            </div>


                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4 col-4 col-md-4 ">
                                                                        <img src="../../../../public/theme/appimages/Route.svg"
                                                                            alt="" class="details-route-image">
                                                                        <span class="flight-time-mobile">{{
                                                                            route.flightTime1
                                                                            }}</span>
                                                                    </div>
                                                                    <div class="col-sm-4 col-md-4 col-4">
                                                                        <div
                                                                            class="d-block justify-content-center align-items-center h-100 w-100">
                                                                            <div class="text-black-"
                                                                                style="color: #0fb3a6;">
                                                                                <b>{{ route.arrival_code }}</b>
                                                                            </div>
                                                                            <div>
                                                                                <small style="font-size: 11px;"
                                                                                    class="fcolor"><b>{{
                                                                                        route.arrival_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span></b></small>
                                                                                <span style="font-size: 11px;">{{
                                                                                    formatDisplayDate(route.arrival_date)
                                                                                    }}</span>
                                                                            </div>
                                                                            <div>
                                                                                <small
                                                                                    style="font-size: 12px; color: #5e6878;">Terminal:
                                                                                    {{ route.destinationTerminal
                                                                                    }}</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div v-if="route.is_codeshare !=true" class="row border-top mt-3 d-flex justify-content-center align-items-center">
                                                                    <div class="col-md-6">
                                                                        <div class="d-flex gap-2 mt-2">

                                                                            <div class="border border-1 text-center p-1 detail-badge-flight"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">
                                                                                {{ route.flight }}-{{
                                                                                    route.aircraft_name }}</div>
                                                                            <div class="border border-1 text-center p-1 detail-badge-cabin"
                                                                                style="background-color: rgb(222, 241, 236); color: rgb(18, 206, 105); font-size: 10px; white-space: nowrap;">
                                                                                {{ route.cabin_class }} - {{
                                                                                    route.booking_code }}

                                                                            </div>
                                                                            <div class="border border-1 p-1 detail-badge-ref"
                                                                                style="background-color: #fff3cd; color: #856404; font-size: 9px; line-height: 1.4; white-space: nowrap;">
                                                                                {{ flight.outbound._offering_id }} | {{ route.flightRef }} | {{ flight.outbound._selected_productRef }} | {{ flight.outbound._selected_brandRef }} | {{ flight.outbound._selected_tncRef }}
                                                                            </div>
                                                                        </div>


                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="float-end mt-2">

                                                                            <img height="60" width="65"
                                                                                :src="route.logo_path" alt="">
                                                                            <span class="pt-2 ms-2 fw-bold"
                                                                                style="font-size: 11px;">{{
                                                                                    route.airline_name }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div v-if="route.is_codeshare ==true" class="row border-top mt-3 d-flex justify-content-center align-items-center">
                                                                    <div class="col-md-6">
                                                                        <div class="d-flex gap-2 mt-2">

                                                                            <div class="border border-1 text-center p-1 detail-badge-flight"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">

                                                                               {{ route.codeshare_info.operating_carrier }}{{ route.codeshare_info.operating_flight_number }}-{{ route.aircraft_name }}
                                                                                    </div>
                                                                            <div class="border border-1 text-center p-1 detail-badge-cabin"
                                                                                style="background-color: rgb(222, 241, 236); color: rgb(18, 206, 105); font-size: 10px; white-space: nowrap;">
                                                                                {{ route.cabin_class }} - {{
                                                                                    route.booking_code }}

                                                                            </div>
                                                                        </div>


                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="float-end mt-2">

                                                                            <img height="60" width="60"
                                                                                :src="route.codeshare_info.logo_path" alt="">
                                                                            <span class="pt-2 ms-2 fw-bold"
                                                                                style="font-size: 11px;">{{ route.codeshare_info.operating_airline_name }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="row border-top mt-3 d-flex justify-content-center align-items-center">
                                                                    <div class="col-md-12">
                                                                        <div class="mt-2 mb-0 layover-dest-chip"
                                                                            style="font-size: 13px !important; color: #7944eb; background-color:#e4e3f6; border-radius:5px;">

                                                                            <!-- section image -->
                                                                            <img style="height: 20px;width: 20px;margin: 8px 5px 10px 10px;"
                                                                                src="../../../../public/theme/appimages/Layover_&_Destination.svg"
                                                                                alt="">

                                                                            <!-- section text -->

                                                                            <span
                                                                                class="bluesky-departure-text mobile-chips-text">

                                                                                <span v-if="route.lastitem">Reached
                                                                                    Destination</span>
                                                                                <span v-else>Layover </span>
                                                                                at <span>{{
                                                                                    route.Destination_City_Name }} <span
                                                                                        v-if="!route.lastitem">-{{
                                                                                            route.layover_time
                                                                                        }}</span></span>
                                                                                <span class="vertical-line">|</span>
                                                                            </span>
                                                                            <br class="br-on-mobile">
                                                                            <span
                                                                                class="bluesky-departure-airport-text w-100 ml-3 mobile-chip mobile-chips-text">
                                                                                {{ route.Destination_Airport_Name
                                                                                }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div v-if="flight.inbound"
                                                        v-for="returnRoute in flight.inbound.segments"
                                                        :class="`flight-tab-hide-${index} d-none fadeIn`">
                                                        <div class="card">
                                                            <div class="card-header accorion-item-title-color m-0 p-0 flight-detail-card-header"
                                                                style="background-color: #f2f5f7;">
                                                                <div class="d-flex">
                                                                    <div class="p-2 flex-grow-1">
                                                                        <b>
                                                                            <!-- <img :src="returnRoute"
                                                                                alt=""> -->
                                                                        </b>
                                                                        <small><b><span
                                                                                    class="bluesky-departure-text">Departure
                                                                                    From </span></b>
                                                                            <b><span
                                                                                    class="bluesky-departure-airport-text">
                                                                                    {{ returnRoute.Origin_Airport_Name
                                                                                    }}
                                                                                </span></b>
                                                                        </small>
                                                                    </div>

                                                                    <div class="p-2 bluesky-departure-text flight-time">
                                                                        <small>Flight Time: {{ returnRoute.flightTime1
                                                                        }}</small>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-4 col-4 col-sm-4">
                                                                        <div
                                                                            class="d-block justify-content-center align-items-center h-100 w-100">
                                                                            <div class="text-black-"
                                                                                style="color: #0fb3a6;">
                                                                                <b>{{ returnRoute.departure_code }}</b>
                                                                            </div>
                                                                            <div>
                                                                                <small style="font-size: 13px;"
                                                                                    class="fcolor"><b>{{
                                                                                        returnRoute.departure_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span>
                                                                                    </b></small>
                                                                                <span style="font-size: 11px;">{{
                                                                                    formatDisplayDate(returnRoute.departure_date)
                                                                                }}</span>
                                                                            </div>
                                                                            <div>
                                                                                <small
                                                                                    style="font-size: 12px; color: #5e6878;">Terminal:
                                                                                    {{ returnRoute.originTerminal
                                                                                    }}</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-4 col-4 col-md-4 ">
                                                                        <img src="../../../../public/theme/appimages/Route.svg"
                                                                            alt="" class="details-route-image">
                                                                        <span class="flight-time-mobile">{{
                                                                            returnRoute.flightTime1
                                                                            }}</span>
                                                                    </div>

                                                                    <div class="col-md-4 col-4">
                                                                        <div
                                                                            class="d-block justify-content-center align-items-center h-100 w-100">
                                                                            <div class="text-black-"
                                                                                style="color: #0fb3a6;">
                                                                                <b>{{ returnRoute.arrival_code }}</b>
                                                                            </div>
                                                                            <div>
                                                                                <small style="font-size: 13px;"
                                                                                    class="fcolor"><b>{{
                                                                                        returnRoute.arrival_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span></b></small>
                                                                                <span style="font-size: 11px;">
                                                                                    {{
                                                                                        formatDisplayDate(returnRoute.arrival_date)
                                                                                    }}</span>
                                                                            </div>
                                                                            <div>
                                                                                <small
                                                                                    style="font-size: 12px; color: #5e6878;">Terminal:
                                                                                    {{ returnRoute.destinationTerminal
                                                                                    }}</small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div v-if="returnRoute.is_codeshare!=true" class="row border-top mt-3 d-flex justify-content-center align-items-center">
                                                                    <div class="col-md-6">
                                                                        <div class="d-flex gap-2 mt-2">

                                                                            <div class="border border-1 text-center p-1 detail-badge-flight"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">
                                                                                {{ returnRoute.flight }}-{{
                                                                                    returnRoute.aircraft_name }}</div>
                                                                            <div class="border border-1 text-center p-1 detail-badge-cabin"
                                                                                style="background-color: rgb(222, 241, 236); color: rgb(18, 206, 105); font-size: 10px; white-space: nowrap;">
                                                                                {{ returnRoute.cabin_class }} - {{
                                                                                    returnRoute.booking_code }}
                                                                            </div>
                                                                            <div class="border border-1 p-1 detail-badge-ref"
                                                                                style="background-color: #fff3cd; color: #856404; font-size: 9px; line-height: 1.4; white-space: nowrap;">
                                                                                {{ flight.inbound._offering_id }} | {{ returnRoute.flightRef }} | {{ flight.inbound._selected_productRef }} | {{ flight.inbound._selected_brandRef }} | {{ flight.inbound._selected_tncRef }}
                                                                            </div>
                                                                        </div>


                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="float-end mt-2">
                                                                            <img height="60" width="65"
                                                                                :src="returnRoute.logo_path" alt="">
                                                                            <span class="pt-2 ms-2 fw-bold"
                                                                                style="font-size: 11px;">{{
                                                                                    returnRoute.airline_name }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div v-if="returnRoute.is_codeshare==true" class="row border-top mt-3 d-flex justify-content-center align-items-center">
                                                                    <div class="col-md-6">
                                                                        <div class="d-flex gap-2 mt-2">

                                                                            <div class="border border-1 text-center p-1 detail-badge-flight"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">
                                                                                {{ returnRoute.codeshare_info.operating_carrier }}{{ returnRoute.codeshare_info.operating_flight_number }}-{{
                                                                                    returnRoute.aircraft_name }}</div>
                                                                            <div class="border border-1 text-center p-1 detail-badge-cabin"
                                                                                style="background-color: rgb(222, 241, 236); color: rgb(18, 206, 105); font-size: 10px; white-space: nowrap;">
                                                                                {{ returnRoute.cabin_class }} - {{
                                                                                    returnRoute.booking_code }}
                                                                            </div>
                                                                        </div>


                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="float-end mt-2">
                                                                            <img height="60" width="65"
                                                                                :src="returnRoute.codeshare_info.logo_path" alt="">
                                                                            <span class="pt-2 ms-2 fw-bold"
                                                                                style="font-size: 11px;">{{
                                                                                    returnRoute.codeshare_info.operating_airline_name }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row border-top mt-3 d-flex justify-content-center align-items-center">
                                                                    <div class="col-md-12">

                                                                        <div
                                                                            class="row border-top mt-3 d-flex justify-content-center align-items-center">
                                                                            <div class="col-md-12">
                                                                                <div class="mt-2 mb-0 layover-dest-chip"
                                                                                    style="font-size: 13px !important; color: #7944eb; background-color:#e4e3f6; border-radius:5px;">


                                                                                    <img style="height: 20px;width: 20px;margin: 8px 5px 10px 10px;"
                                                                                        src="../../../../public/theme/appimages/Layover_&_Destination.svg"
                                                                                        alt="">



                                                                                    <span
                                                                                        class="bluesky-departure-text mobile-chips-text">

                                                                                        <span
                                                                                            v-if="returnRoute.lastitem">Reached
                                                                                            Destination</span>
                                                                                        <span v-else>Layover </span>
                                                                                        at <span>{{
                                                                                            returnRoute.Destination_City_Name
                                                                                        }} <span
                                                                                                v-if="!returnRoute.lastitem">-{{
                                                                                                    returnRoute.layover_time
                                                                                                }}</span></span>
                                                                                        <span
                                                                                            class="vertical-line">|</span>
                                                                                    </span>
                                                                                    <br class="br-on-mobile">
                                                                                    <span
                                                                                        class="bluesky-departure-airport-text w-100 ml-3 mobile-chip mobile-chips-text">
                                                                                        {{
                                                                                            returnRoute.Destination_Airport_Name
                                                                                        }}</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="tab-pane fade" :id="`primaryprofile-${index}`" role="tabpanel">
                                                    <div class="p-3">

                                                        <!-- Loading -->
                                                        <div v-if="fareRulesLoading[index]" class="text-center py-5">
                                                            <div class="spinner-border text-primary" role="status"></div>
                                                            <p class="mt-3 text-muted small mb-0">Loading fare rules...</p>
                                                        </div>

                                                        <!-- Error -->
                                                        <div v-else-if="fareRulesData[index]?.error" class="text-center py-5 text-danger">
                                                            <i class="bx bx-error-circle" style="font-size:2rem"></i>
                                                            <p class="mt-2 small mb-0">Failed to load fare rules. Please try again.</p>
                                                        </div>

                                                        <!-- Not yet fetched -->
                                                        <div v-else-if="fareRulesData[index] === undefined" class="text-center py-5 text-muted">
                                                            <i class="bx bx-file-blank" style="font-size:2rem"></i>
                                                            <p class="mt-2 small mb-0">Click the Fare Rules tab to load.</p>
                                                        </div>

                                                        <!-- Data -->
                                                        <div v-else>
                                                            <!-- Header: direction pills + download -->
                                                            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                                                <ul class="nav nav-pills gap-1 mb-0" role="tablist">
                                                                    <li v-for="(seg, sIdx) in fareRulesData[index].segments" :key="sIdx" class="nav-item">
                                                                        <button class="nav-link px-3 py-1" style="font-size:.8rem"
                                                                                :class="{ active: sIdx === 0 }"
                                                                                data-bs-toggle="pill"
                                                                                :data-bs-target="`#fare-seg-${index}-${sIdx}`"
                                                                                type="button">
                                                                            <i class="bx bx-plane-take-off me-1"></i>{{ seg.displayLabel || seg.flightRef }}
                                                                        </button>
                                                                    </li>
                                                                </ul>
                                                                <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1"
                                                                        :disabled="fareRulesDownloading[index]"
                                                                        @click="downloadFareRules(index)">
                                                                    <i class="bx" :class="fareRulesDownloading[index] ? 'bx-loader-alt bx-spin' : 'bx-download'"></i>
                                                                    {{ fareRulesDownloading[index] ? 'Downloading...' : 'Download' }}
                                                                </button>
                                                            </div>

                                                            <div class="tab-content">
                                                                <div v-for="(seg, sIdx) in fareRulesData[index].segments" :key="sIdx"
                                                                     class="tab-pane fade" :class="{ 'show active': sIdx === 0 }"
                                                                     :id="`fare-seg-${index}-${sIdx}`">

                                                                    <div class="row g-3">

                                                                        <!-- Accordion: Cancellation + Changes -->
                                                                        <div class="col-12">
                                                                            <div class="accordion" :id="`fare-acc-${index}-${sIdx}`">

                                                                                <!-- Cancellation -->
                                                                                <div class="accordion-item border rounded mb-2" style="overflow:hidden">
                                                                                    <h2 class="accordion-header mb-0">
                                                                                        <button class="accordion-button fare-btn collapsed py-2 px-3 d-flex align-items-center w-100 fare-danger-bg"
                                                                                                style="font-size:.875rem"
                                                                                                type="button" data-bs-toggle="collapse"
                                                                                                :data-bs-target="`#fare-cancel-${index}-${sIdx}`">
                                                                                            <i class="bx bx-x-circle text-danger"></i>
                                                                                            <span class="fw-semibold ms-2">Cancellation</span>
                                                                                            <span class="badge bg-danger ms-2" style="font-size:.7rem">{{ seg.cancellation.length }}</span>
                                                                                            <i class="bx bx-chevron-down ms-auto fare-chevron"></i>
                                                                                        </button>
                                                                                    </h2>
                                                                                    <div :id="`fare-cancel-${index}-${sIdx}`" class="accordion-collapse collapse">
                                                                                        <div class="accordion-body p-0">
                                                                                            <div v-if="!seg.cancellation.length" class="px-3 py-2 text-muted small">No data available</div>
                                                                                            <table v-else class="table table-sm table-hover mb-0" style="font-size:.8rem">
                                                                                                <thead class="fare-table-head">
                                                                                                    <tr>
                                                                                                        <th class="fw-semibold text-muted">Condition</th>
                                                                                                        <th class="fw-semibold text-muted text-center">Status</th>
                                                                                                        <th class="fw-semibold text-muted text-end">Charge</th>
                                                                                                    </tr>
                                                                                                </thead>
                                                                                                <tbody>
                                                                                                    <tr v-for="(c, ci) in seg.cancellation" :key="ci">
                                                                                                        <td class="text-muted">{{ formatTiming(c.timing) }}</td>
                                                                                                        <td class="text-center">
                                                                                                            <span class="badge" :class="c.permitted ? 'bg-success' : 'bg-danger'">
                                                                                                                {{ c.permitted ? 'Permitted' : 'Not Permitted' }}
                                                                                                            </span>
                                                                                                        </td>
                                                                                                        <td class="text-end fw-semibold">
                                                                                                            <span v-if="c.amount">{{ c.amount.code }} {{ c.amount.value.toLocaleString() }}</span>
                                                                                                            <span v-else class="text-muted">—</span>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                            <div v-if="seg.cancellation.some(c => c.taxes_refundable === false)"
                                                                                                 class="px-3 py-1 small text-danger fare-danger-bg" style="font-size:.75rem">
                                                                                                * Taxes non-refundable
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <!-- Changes -->
                                                                                <div class="accordion-item border rounded" style="overflow:hidden">
                                                                                    <h2 class="accordion-header mb-0">
                                                                                        <button class="accordion-button fare-btn fare-changes-btn collapsed py-2 px-3 d-flex align-items-center w-100"
                                                                                                style="font-size:.875rem;background:#f0f7ff"
                                                                                                type="button" data-bs-toggle="collapse"
                                                                                                :data-bs-target="`#fare-change-${index}-${sIdx}`">
                                                                                            <i class="bx bx-transfer text-primary"></i>
                                                                                            <span class="fw-semibold ms-2">Changes</span>
                                                                                            <span class="badge bg-primary ms-2" style="font-size:.7rem">{{ seg.changes.length }}</span>
                                                                                            <i class="bx bx-chevron-down ms-auto fare-chevron"></i>
                                                                                        </button>
                                                                                    </h2>
                                                                                    <div :id="`fare-change-${index}-${sIdx}`" class="accordion-collapse collapse">
                                                                                        <div class="accordion-body p-0">
                                                                                            <div v-if="!seg.changes.length" class="px-3 py-2 text-muted small">No data available</div>
                                                                                            <table v-else class="table table-sm table-hover mb-0" style="font-size:.8rem">
                                                                                                <thead class="fare-table-head">
                                                                                                    <tr>
                                                                                                        <th class="fw-semibold text-muted">Condition</th>
                                                                                                        <th class="fw-semibold text-muted text-center">Status</th>
                                                                                                        <th class="fw-semibold text-muted text-end">Charge</th>
                                                                                                    </tr>
                                                                                                </thead>
                                                                                                <tbody>
                                                                                                    <tr v-for="(c, ci) in seg.changes" :key="ci">
                                                                                                        <td class="text-muted">{{ formatTiming(c.timing) }}</td>
                                                                                                        <td class="text-center">
                                                                                                            <span class="badge" :class="c.permitted ? 'bg-success' : 'bg-danger'">
                                                                                                                {{ c.permitted ? 'Permitted' : 'Not Permitted' }}
                                                                                                            </span>
                                                                                                        </td>
                                                                                                        <td class="text-end fw-semibold">
                                                                                                            <span v-if="c.amount">{{ c.amount.code }} {{ c.amount.value.toLocaleString() }}</span>
                                                                                                            <span v-else class="text-muted">—</span>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                            </div>
                                                                        </div>

                                                                        <!-- Stay + Advance Booking + Stopovers: 3-col info cards -->
                                                                        <div class="col-12">
                                                                            <div class="row g-2">

                                                                                <!-- Min / Max Stay -->
                                                                                <div class="col-6 col-md-3">
                                                                                    <div class="border rounded p-2 h-100 text-center">
                                                                                        <div class="text-muted mb-1" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Min Stay</div>
                                                                                        <div class="fw-semibold" style="font-size:.8rem">{{ seg.min_stay ?? 'No restriction' }}</div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6 col-md-3">
                                                                                    <div class="border rounded p-2 h-100 text-center">
                                                                                        <div class="text-muted mb-1" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Max Stay</div>
                                                                                        <div class="fw-semibold" style="font-size:.8rem">{{ seg.max_stay ?? 'No restriction' }}</div>
                                                                                    </div>
                                                                                </div>

                                                                                <!-- Stopovers -->
                                                                                <div v-if="seg.stopover !== null" class="col-6 col-md-3">
                                                                                    <div class="border rounded p-2 h-100 text-center">
                                                                                        <div class="text-muted mb-1" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Stopovers</div>
                                                                                        <span class="badge" :class="seg.stopover ? 'bg-success' : 'bg-danger'" style="font-size:.75rem">
                                                                                            {{ seg.stopover ? 'Permitted' : 'Not Permitted' }}
                                                                                        </span>
                                                                                    </div>
                                                                                </div>

                                                                                <!-- Advance Booking -->
                                                                                <div v-if="seg.advance_booking" class="col-6 col-md-3">
                                                                                    <div class="border rounded p-2 h-100 text-center">
                                                                                        <div class="text-muted mb-1" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Advance Booking</div>
                                                                                        <div v-if="seg.advance_booking.book_by" class="fw-semibold" style="font-size:.8rem">{{ seg.advance_booking.book_by }}</div>
                                                                                        <div v-if="seg.advance_booking.pay_after_booking" class="text-muted" style="font-size:.75rem">Pay {{ seg.advance_booking.pay_after_booking }}</div>
                                                                                        <div v-if="seg.advance_booking.pay_before_departure" class="text-muted" style="font-size:.75rem">Pay {{ seg.advance_booking.pay_before_departure }}</div>
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
                                                <div class="tab-pane fade" :id="`primarycontact-${index}`" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <p class="text-start fw-bold">Max Stay</p>
                                                            <span>Maximum stay none for economy unrestricted
                                                                fares.</span>
                                                        </div>
                                                        <div class="col-md-12 mt-2">
                                                            <p class="text-start fw-bold pt-0 mt-0">Layover
                                                            </p>
                                                            <span>Stopovers for economy unrestricted fares
                                                                unlimited
                                                                stopovers permitted.</span>
                                                        </div>
                                                        <div class="col-md-12 mt-2">
                                                            <p class="text-start fw-bold pt-0 mt-0">
                                                                Combinations
                                                            </p>
                                                            <span>
                                                                Permitted combinations fares may be combined
                                                                on
                                                                a half
                                                                round trip basis with any fare for any
                                                                carrier
                                                                in any

                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 fare-summary-col" style="background-color: #f4f4ff;">

                                            <div class="accordion accordion-flush mt-3">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header rounded"
                                                        style="background-color: #7944eb !important;">
                                                        <button class="accordion-button m-0 p-0 px-3 py-2 d-flex justify-content-between w-100 align-items-center"
                                                            type="button">
                                                            Fare Summary
                                                        </button>
                                                    </h2>
                                                    <div style="">
                                                        <div class="accordion-body">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <div
                                                                        class="border fare-summary-bg p-1 rounded-1 mb-1">
                                                                        <span class="custom-text-purple">
                                                                            Base Fare
                                                                        </span>
                                                                    </div>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-striped">
                                                                            <tbody>
                                                                                <tr v-for="itemPrice in flight.outbound.priceBreakdown" style="font-size: 10px;">
                                                                                    <td>
                                                                                        <i v-if="itemPrice.type == 'Adult'" class="fa-solid fa-person" style="color:#3b82f6;font-size:11px;"></i>
                                                                                        <i v-if="itemPrice.type == 'Child'" class="fa-solid fa-child" style="color:#22c55e;font-size:11px;"></i>
                                                                                        <i v-if="itemPrice.type == 'Infant'" class="fa-solid fa-baby" style="color:#f97316;font-size:11px;"></i>
                                                                                        {{ itemPrice.type }}:
                                                                                        <span v-if="itemPrice.type == 'Adult'">{{ form.ADT }}</span>
                                                                                        <span v-if="itemPrice.type == 'Child'">{{ form.CNN }}</span>
                                                                                        <span v-if="itemPrice.type == 'Infant'">{{ form.INF }}</span>
                                                                                        x {{ itemPrice.baseFare.toLocaleString() }}
                                                                                    </td>
                                                                                    <td class="text-end">
                                                                                        <span v-if="itemPrice.type == 'Adult'">BDT {{ (form.ADT * itemPrice.baseFare).toLocaleString() }}</span>
                                                                                        <span v-if="itemPrice.type == 'Child'">BDT {{ (form.CNN * itemPrice.baseFare).toLocaleString() }}</span>
                                                                                        <span v-if="itemPrice.type == 'Infant'">BDT {{ (form.INF * itemPrice.baseFare).toLocaleString() }}</span>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr style="font-size: 10px; font-weight: bold;">
                                                                                    <td>Total Base Fare</td>
                                                                                    <td class="text-end">BDT {{
                                                                                        flight.outbound.priceBreakdown.reduce((total, itemPrice) => {
                                                                                            const count = itemPrice.type === 'Adult' ? form.ADT : itemPrice.type === 'Child' ? form.CNN : form.INF;
                                                                                            return total + (count * (itemPrice.baseFare || 0));
                                                                                        }, 0).toLocaleString()
                                                                                    }}</td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>

                                                                    <div class="border fare-summary-bg p-1 rounded-1 mb-1">
                                                                        <span class="custom-text-purple">TAX</span>
                                                                    </div>

                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-striped">
                                                                            <tbody>
                                                                                <tr v-for="itemPrice in flight.outbound.priceBreakdown" style="font-size: 10px;">
                                                                                    <td>
                                                                                        <i v-if="itemPrice.type == 'Adult'" class="fa-solid fa-person" style="color:#3b82f6;font-size:11px;"></i>
                                                                                        <i v-if="itemPrice.type == 'Child'" class="fa-solid fa-child" style="color:#22c55e;font-size:11px;"></i>
                                                                                        <i v-if="itemPrice.type == 'Infant'" class="fa-solid fa-baby" style="color:#f97316;font-size:11px;"></i>
                                                                                        {{ itemPrice.type }}:
                                                                                        <span v-if="itemPrice.type == 'Adult'">{{ form.ADT }}</span>
                                                                                        <span v-if="itemPrice.type == 'Child'">{{ form.CNN }}</span>
                                                                                        <span v-if="itemPrice.type == 'Infant'">{{ form.INF }}</span>
                                                                                        x {{ itemPrice.taxes.toLocaleString() }}
                                                                                    </td>
                                                                                    <td class="text-end">
                                                                                        <span v-if="itemPrice.type == 'Adult'">BDT {{ (form.ADT * itemPrice.taxes).toLocaleString() }}</span>
                                                                                        <span v-if="itemPrice.type == 'Child'">BDT {{ (form.CNN * itemPrice.taxes).toLocaleString() }}</span>
                                                                                        <span v-if="itemPrice.type == 'Infant'">BDT {{ (form.INF * itemPrice.taxes).toLocaleString() }}</span>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr style="font-size: 10px; font-weight: bold;">
                                                                                    <td>Total TAX</td>
                                                                                    <td class="text-end">BDT {{
                                                                                        flight.outbound.priceBreakdown.reduce((total, itemPrice) => {
                                                                                            const count = itemPrice.type === 'Adult' ? form.ADT : itemPrice.type === 'Child' ? form.CNN : form.INF;
                                                                                            return total + (count * (itemPrice.taxes || 0));
                                                                                        }, 0).toLocaleString()
                                                                                    }}</td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>

                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-striped">
                                                                            <tbody>
                                                                                <tr style="font-size: 10px;">
                                                                                    <td><b>Gross Fare</b></td>
                                                                                    <td class="text-end">
                                                                                        <b>BDT {{
                                                                                            ['Adult', 'Child', 'Infant'].reduce((total, type) => {
                                                                                                const breakdown = flight.outbound.priceBreakdown.find(item => item.type === type) || {};
                                                                                                const count = form[type === 'Adult' ? 'ADT' : type === 'Child' ? 'CNN' : 'INF'];
                                                                                                return total + (count * (breakdown.taxes || 0)) + (count * (breakdown.baseFare || 0));
                                                                                            }, 0).toLocaleString()
                                                                                        }}</b>
                                                                                    </td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="accordion-item mt-2">
                                                    <h2 class="accordion-header" id="flush-headingTwo">
                                                        <button class="accordion-button m-0 p-0 px-3 py-2 collapsed d-flex justify-content-between w-100 align-items-center"
                                                            type="button" data-bs-toggle="collapse"
                                                            :data-bs-target="'#flush-collapseTwo-' + index" aria-expanded="false"
                                                            :aria-controls="'flush-collapseTwo-' + index">
                                                            Baggage Information
                                                        </button>
                                                    </h2>


                                                    <div  :id="'flush-collapseTwo-' + index" class="accordion-collapse collapse"
                                                        aria-labelledby="flush-headingTwo" style="">
                                                        <div class="accordion-body px-2 py-2">
                                                            <!-- Outbound baggage -->
                                                            <div class="mb-2">
                                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                                    <i class="bx bx-transfer-alt text-primary" style="font-size:13px;"></i>
                                                                    <span style="font-size:11px;font-weight:600;">{{ flight.outbound.origin }} → {{ flight.outbound.destination }}</span>
                                                                    <span class="badge bg-light text-secondary border ms-1" style="font-size:10px;">{{ flight.outbound.segments?.[0]?.cabin_class }}</span>
                                                                    <span style="font-size:9px;color:#999;">Per Person</span>
                                                                </div>
                                                                <div v-for="baggage in flight.outbound.baggage_allowance" :key="baggage.type"
                                                                    class="d-flex align-items-center justify-content-between py-1 px-2 mb-1 rounded baggage-row-bg"
                                                                    style="font-size:11px;">
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <i :class="baggage.type === 'carry_on' ? 'bx bx-briefcase-alt-2 text-warning' : 'bx bxs-briefcase text-primary'" style="font-size:16px;"></i>
                                                                        <span style="font-weight:500;white-space:nowrap;">{{ baggage.label }}</span>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <span v-if="baggage.quantity && baggage.weight" class="fcolor">
                                                                            {{ baggage.quantity }} Bag{{ baggage.quantity > 1 ? 's' : '' }} · {{ baggage.weight }} each
                                                                        </span>
                                                                        <span v-else-if="baggage.weight" class="fcolor">{{ baggage.weight }}</span>
                                                                        <span v-else-if="baggage.quantity" class="fcolor">{{ baggage.quantity }} Bag{{ baggage.quantity > 1 ? 's' : '' }}</span>
                                                                                                                                                <span v-if="!baggage.included" class="badge bg-warning text-dark ms-1" style="font-size:9px;">Chargeable</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Inbound baggage -->
                                                            <div v-if="flight.inbound" class="mt-2">
                                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                                    <i class="bx bx-transfer-alt text-success" style="font-size:13px;"></i>
                                                                    <span style="font-size:11px;font-weight:600;">{{ flight.inbound.origin }} → {{ flight.inbound.destination }}</span>
                                                                    <span class="badge bg-light text-secondary border ms-1" style="font-size:10px;">{{ flight.inbound.segments?.[0]?.cabin_class }}</span>
                                                                    <span style="font-size:9px;color:#999;">Per Person</span>
                                                                </div>
                                                                <div v-for="baggage in flight.inbound.baggage_allowance" :key="baggage.type"
                                                                    class="d-flex align-items-center justify-content-between py-1 px-2 mb-1 rounded baggage-row-bg"
                                                                    style="font-size:11px;">
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <i :class="baggage.type === 'carry_on' ? 'bx bx-briefcase-alt-2 text-warning' : 'bx bxs-briefcase text-success'" style="font-size:16px;"></i>
                                                                        <span style="font-weight:500;white-space:nowrap;">{{ baggage.label }}</span>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <span v-if="baggage.quantity && baggage.weight" class="fcolor">
                                                                            {{ baggage.quantity }} Bag{{ baggage.quantity > 1 ? 's' : '' }} · {{ baggage.weight }} each
                                                                        </span>
                                                                        <span v-else-if="baggage.weight" class="fcolor">{{ baggage.weight }}</span>
                                                                        <span v-else-if="baggage.quantity" class="fcolor">{{ baggage.quantity }} Bag{{ baggage.quantity > 1 ? 's' : '' }}</span>
                                                                                                                                                <span v-if="!baggage.included" class="badge bg-warning text-dark ms-1" style="font-size:9px;">Chargeable</span>
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
                            </div>
                        </div>
                    </div>
                    <!-- ./ end flight details2 -->


                    <!-- Price Details -->
                    <div :id="`flight-package-${index}`" class="accordion-collapse collapse m-0"
                                    aria-labelledby="flush-headingpackage" data-bs-parent="#accordionFlushExample"
                                    style="">
                                    <div class="accordion-body">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="brand-cards-scroll">
                                                    <template v-if="flight.outbound.brand_options && flight.outbound.brand_options.length">
                                                        <div v-for="(brand, bIdx) in flight.outbound.brand_options" :key="bIdx"
                                                            class="brand-card-item">
                                                            <div class="fare-card" style="height:100%;" :class="['fare-card--eco','fare-card--flex','fare-card--first'][bIdx] ?? 'fare-card--eco'">
                                                                <!-- Header -->
                                                                <div class="fare-card__header">
                                                                    <div>
                                                                        <div class="fare-card__label">{{ brand.label }}<span v-if="brand.fare_basis_code" class="text-muted ms-2" style="font-size:10px;font-weight:normal;">| {{ brand.fare_basis_code }}</span></div>
                                                                        <span class="fare-card__class-badge">Class {{ brand.class_of_service }}</span>
                                                                        <div v-if="brand._refs" class="mt-1" style="font-size:9px;line-height:1.7;">
                                                                            <!-- Outbound row -->
                                                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                                <span style="background:#e8f4fd;color:#1a6ea8;padding:0 4px;border-radius:3px;font-weight:600;">↑ {{ flight.outbound._offering_id }}</span>
                                                                                <span class="text-muted">{{ brand._refs }}</span>
                                                                                <template v-if="brand.availability_source_codes">
                                                                                    <AppTooltip
                                                                                        v-for="(code, ref) in brand.availability_source_codes"
                                                                                        :key="ref"
                                                                                        :content="AVAIL_SOURCE_MAP[code]?.label ?? code"
                                                                                        placement="top"
                                                                                    >
                                                                                        <span
                                                                                            :style="availSourceStyle(code)"
                                                                                            style="font-size:9px;padding:1px 5px;border-radius:3px;cursor:default;font-weight:600;">
                                                                                            {{ code }}
                                                                                        </span>
                                                                                    </AppTooltip>
                                                                                </template>
                                                                            </div>
                                                                            <!-- Inbound row (round-trip only) -->
                                                                            <div v-if="flight.inbound" class="d-flex align-items-center gap-1 flex-wrap">
                                                                                <span style="background:#fde8f4;color:#a81a6e;padding:0 4px;border-radius:3px;font-weight:600;">↓ {{ flight.inbound._offering_id }}</span>
                                                                                <span class="text-muted">flights:{{ flight.inbound.segments.map(s => s.flightRef).join(',') }}</span>
                                                                                <span class="text-muted" style="font-style:italic;opacity:0.7;">(best combinable)</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                                                                        <span v-if="brand.is_default_brand" style="font-size:9px;background:#6c757d;color:#fff;padding:1px 6px;border-radius:3px;font-weight:600;letter-spacing:0.5px;">Default</span>
                                                                        <div class="fare-card__price-block">
                                                                            <span class="fare-card__currency">{{ brand.currency }}</span>
                                                                            <span class="fare-card__amount">{{ brand.price.toLocaleString() }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="fare-card__divider"></div>
                                                                <!-- Features -->
                                                                <div class="fare-card__features">
                                                                    <div v-for="(attr, aIdx) in brand.attributes" :key="aIdx" class="fare-card__feature">
                                                                        <span class="fare-card__status-dot" :class="{
                                                                            'fare-card__status-dot--ok':  attr.inclusion === 'Included',
                                                                            'fare-card__status-dot--fee': attr.inclusion === 'Chargeable',
                                                                            'fare-card__status-dot--no':  attr.inclusion === 'Not Offered',
                                                                        }">
                                                                            <i :class="{
                                                                                'fa-solid fa-check':       attr.inclusion === 'Included',
                                                                                'fa-solid fa-dollar-sign': attr.inclusion === 'Chargeable',
                                                                                'fa-solid fa-xmark':       attr.inclusion === 'Not Offered',
                                                                            }"></i>
                                                                        </span>
                                                                        <span class="fare-card__cat-icon">
                                                                            <i :class="classIcon(attr.classification)"></i>
                                                                        </span>
                                                                        <span class="fare-card__feature-text" :class="{
                                                                            'fare-card__feature-text--fee': attr.inclusion === 'Chargeable',
                                                                            'fare-card__feature-text--no':  attr.inclusion === 'Not Offered',
                                                                        }">
                                                                            {{ classLabel(attr.classification) }} ({{ attr.inclusion }})
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <!-- Book button -->
                                                                <div class="fare-card__footer">
                                                                    <button class="fare-card__book-btn" @click="selectFare(flight, brand)">
                                                                        Select fare <i class="fa-solid fa-arrow-right ms-1"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <div v-else class="col-12 text-center text-muted py-3">No brand options available.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                    <!-- ./end Price Details -->

                </div>

            </div>

            </div>
        </div>
        <!-- result panel end -->
    </div>
    </div>

    <FlightPricePanel
        :visible="showPricePanel"
        :flight="selectedFlightForPrice"
        :selected-brand="selectedBrandForPrice"
        :form="form"
        :catalog-identifier="catalogIdentifier"
        :search-log-id="searchLogId"
        @close="showPricePanel = false"
    />

    <AppModal
        :is-open="loadging"
        :show-header="false"
        :close-on-backdrop="false"
        size="md"
        max-width="440px"
    >
        <div class="search-cooking-modal">
            <div class="search-cooking-animation" aria-hidden="true"></div>
            <p class="search-cooking-text mb-0">Cooking..</p>
        </div>
    </AppModal>

</template>

<style>
.page-content:has(.search-page-layout) {
    overflow: hidden;
    height: calc(100dvh - 60px);
    box-sizing: border-box;
    background: #eef1f7 !important;
}

.search-page-layout {
    gap: 8px;
}

.search-page-layout .search-sticky-panel {
    margin-bottom: 0;
}

.search-page-layout .search-sticky-row {
    --bs-gutter-y: 0 !important;
    margin-bottom: 0;
}

.search-page-layout .search-sticky-row > [class*="col-"] {
    padding-bottom: 0 !important;
    margin-top: 0 !important;
}

.search-page-layout .search-layout-card {
    box-shadow: 0 4px 14px rgba(41, 51, 81, 0.06);
    margin-bottom: 0;
}

.search-page-layout > .search-content-row {
    margin-top: 0 !important;
    --bs-gutter-y: 0 !important;
    --bs-gutter-x: 0.5rem;
}

.search-page-layout > .search-content-row > [class*="col-"] {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

.search-page-layout .search-filter-inner-row {
    --bs-gutter-y: 0 !important;
    row-gap: 0 !important;
}

.search-page-layout .search-filter-inner-row > [class*="col-"] {
    margin-top: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}

.search-page-layout .search-filter-inner-row > [class*="col-"]:not(:first-child) {
    margin-top: 6px !important;
}

.search-page-layout .search-filter-scroll .card {
    margin-bottom: 0 !important;
}

.search-page-layout .search-results-inner-row {
    --bs-gutter-y: 0 !important;
    row-gap: 0 !important;
}

.search-page-layout .search-results-inner-row > [class*="col-"] {
    margin-top: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}

.search-page-layout .search-results-inner-row > [class*="col-"]:not(:first-child) {
    margin-top: 6px !important;
}

.search-page-layout .search-results-inner-row > [class*="col-"] > .card {
    margin-bottom: 0 !important;
}

.search-page-layout .search-results-inner-row > [class*="col-"] > .accordion-collapse {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

.search-filter-scroll,
.search-results-scroll {
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
}

.search-filter-scroll.is-scroll-hover,
.search-results-scroll.is-scroll-hover {
    scrollbar-color: rgba(168, 156, 210, 0.75) transparent;
}

.search-filter-scroll::-webkit-scrollbar,
.search-results-scroll::-webkit-scrollbar {
    width: 6px;
}

.search-filter-scroll::-webkit-scrollbar-track,
.search-results-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.search-filter-scroll::-webkit-scrollbar-thumb,
.search-results-scroll::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 999px;
    border: 2px solid transparent;
    background-clip: padding-box;
}

.search-filter-scroll.is-scroll-hover::-webkit-scrollbar-thumb,
.search-results-scroll.is-scroll-hover::-webkit-scrollbar-thumb {
    background: linear-gradient(
        180deg,
        rgba(147, 168, 235, 0.72) 0%,
        rgba(186, 168, 228, 0.78) 52%,
        rgba(130, 210, 205, 0.72) 100%
    );
    border-color: transparent;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
}

/* ── Stop cards ─────────────────────────────────── */
.stop-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    transition: border-color 0.15s, background 0.15s;
}
.stop-card:hover { border-color: #93b4e8; background: #f0f5ff; }
.stop-card--active { border-color: #4a90e2; background: #e8f0fe; }

.stop-card__route {
    display: flex;
    align-items: center;
    gap: 0;
    flex-shrink: 0;
    width: 70px;
}
.stop-card__endpoint {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #4a90e2;
    flex-shrink: 0;
}
.stop-card--active .stop-card__endpoint { background: #1a5fb4; }
.stop-card__track {
    flex: 1;
    height: 2px;
    background: #c8d9ef;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-evenly;
}
.stop-card--active .stop-card__track { background: #4a90e2; }
.stop-card__dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #f97316;
    border: 1.5px solid #fff;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}
.stop-card__label {
    flex: 1;
    font-size: 12px;
    font-weight: 600;
    color: #3e4957;
}
.stop-card--active .stop-card__label { color: #1a5fb4; }
.stop-card__count {
    font-size: 11px;
    font-weight: 700;
    color: #4a90e2;
    background: #dbeafe;
    border-radius: 20px;
    padding: 1px 8px;
    flex-shrink: 0;
}
.stop-card--active .stop-card__count { background: #4a90e2; color: #fff; }

/* ── Refund cards ────────────────────────────────── */
.refund-card {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1.5px solid transparent;
    background: #f8fafc;
    transition: background 0.15s, border-color 0.15s;
}
.refund-card__icon { font-size: 13px; flex-shrink: 0; }
.refund-card__label { font-size: 12px; font-weight: 500; }
.refund-card__count { font-size: 11px; font-weight: 700; border-radius: 20px; padding: 1px 8px; }

.refund-card--refundable { border-left: 3px solid #12ce69; }
.refund-card--refundable .refund-card__icon  { color: #12ce69; }
.refund-card--refundable .refund-card__label { color: #0a7a3e; }
.refund-card--refundable .refund-card__count { color: #12ce69; background: #d4f7e7; }
.refund-card--refundable:hover,
.refund-card--refundable.refund-card--active { background: #e6fbf2; border-color: #12ce69; }
.refund-card--refundable.refund-card--active .refund-card__count { background: #12ce69; color: #fff; }

.refund-card--partial { border-left: 3px solid #d4a017; }
.refund-card--partial .refund-card__icon  { color: #d4a017; }
.refund-card--partial .refund-card__label { color: #7a5a00; }
.refund-card--partial .refund-card__count { color: #d4a017; background: #fef3cd; }
.refund-card--partial:hover,
.refund-card--partial.refund-card--active { background: #fef9ec; border-color: #d4a017; }
.refund-card--partial.refund-card--active .refund-card__count { background: #d4a017; color: #fff; }

.refund-card--non_refundable { border-left: 3px solid #ce1212; }
.refund-card--non_refundable .refund-card__icon  { color: #ce1212; }
.refund-card--non_refundable .refund-card__label { color: #7a0a0a; }
.refund-card--non_refundable .refund-card__count { color: #ce1212; background: #fde8e8; }
.refund-card--non_refundable:hover,
.refund-card--non_refundable.refund-card--active { background: #fdf0f0; border-color: #ce1212; }
.refund-card--non_refundable.refund-card--active .refund-card__count { background: #ce1212; color: #fff; }

/* ── Layover rows ────────────────────────────────── */
.layover-row {
    padding: 6px 8px;
    border-radius: 7px;
    border: 1.5px solid transparent;
    transition: background 0.15s, border-color 0.15s;
}
.layover-row:hover { background: #f0f4ff; border-color: #c7d7f5; }
.layover-row--active { background: #e8f0fe; border-color: #4a90e2; }
.layover-code-badge {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #fff;
    background: #5e6878;
    border-radius: 4px;
    padding: 2px 5px;
    flex-shrink: 0;
}
.layover-row--active .layover-code-badge { background: #4a90e2; }
.layover-airport-name {
    font-size: 11.5px;
    color: #3e4957;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
    flex: 1;
}
.layover-flight-count {
    font-size: 11px;
    font-weight: 700;
    color: #5e6878;
    flex-shrink: 0;
}
.layover-row--active .layover-flight-count { color: #4a90e2; }

/* Airline search clear button */
.airline-search-clear {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
    font-size: 15px;
    color: #9aa5b4;
    cursor: pointer;
}
.airline-search-clear:hover { color: #3e4957; }

/* Airline filter rows */
.airline-filter-row {
    transition: background 0.15s;
    border: 1px solid transparent;
    user-select: none;
}
.airline-filter-row:hover {
    background: #f0f4ff;
    border-color: #c7d7f5;
}
.airline-filter-row--active {
    background: #e8f0fe;
    border-color: #4a90e2 !important;
}
.airline-filter-logo {
    width: 28px;
    height: 28px;
    object-fit: contain;
    border-radius: 4px;
    background: #fff;
    border: 1px solid #e8edf2;
    padding: 2px;
}
.airline-filter-name {
    font-size: 12px;
    color: #3e4957;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.airline-filter-count {
    font-size: 11px;
    font-weight: 700;
    color: #4a90e2;
    background: #e8f0fe;
    border-radius: 20px;
    padding: 1px 7px;
    flex-shrink: 0;
}
.airline-filter-row--active .airline-filter-count {
    background: #4a90e2;
    color: #fff;
}

/* Skeleton shimmer */
.skeleton-box {
    background: linear-gradient(90deg, #e8e8e8 25%, #f5f5f5 50%, #e8e8e8 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    display: block;
}
@keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Leading */

.fcolor{
    color: rgb(62, 73, 87);
}

.mobile-chips-text {
    font-size: 12px;
}

.border-md-start {
    border-left: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important
}

.border-md-end {
    border-right: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important;
}

.flight-time-mobile {
    display: none;
}

.br-on-mobile {
    display: none;
}


@media only screen and (max-width: 600px) {
    .br-on-mobile {
        display: static;
    }

    .details-route-image {
        height: 70px;
        width: 70px;
        padding-top: 30px;
    }

    .mobile-chip {
        margin-left: 35px;
    }

    .vertical-line {
        display: none;
    }

    .flight-time {
        display: none;
    }

    .flight-time-mobile {
        display: block;
        font-size: 9px;
    }

    .border-md-start {
        border-left: none !important;
    }

    .border-md-end {
        border-right: none !important;
    }

    .mobile-chips-text {
        font-size: 10px;
    }
}
</style>

<style scoped>
.fare-rules-container {
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.segment-title {
    color: #2c3e50;
    padding: 10px 0;
    margin-bottom: 20px;
    border-bottom: 2px solid #3498db;
}

.rule-item {
    margin-bottom: 10px;
    border: 1px solid #eee;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.rule-header {
    padding: 15px;
    background: #f8f9fa;
    cursor: pointer;
    transition: background 0.3s ease;
}

.rule-header:hover {
    background: #e9ecef;
}

.rule-category {
    font-weight: 500;
    color: #2c3e50;
}

.rule-content {
    max-height: 0;
    overflow: hidden;
    padding: 0 15px;
    transition: all 0.3s ease-in-out;
}

.rule-content.show {
    max-height: 1000px; /* Increased to handle longer content */
    padding: 15px;
    background: #fff;
}

.active {
    border-color: #3498db;
}

/* Improve scrollbar styling */
.rules-accordion {
    max-height: 600px;
    overflow-y: auto;
}

.rules-accordion::-webkit-scrollbar {
    width: 6px;
}

.rules-accordion::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.rules-accordion::-webkit-scrollbar-thumb {
    background: #3498db;
    border-radius: 3px;
}

/* Animation classes */
.fadeIn {
    animation: fadeIn 0.3s ease-in-out;
}

.accorion-item-title-color {
    background: linear-gradient(249deg, #E9F6FF 0%, #F1EDFF 100%);
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.01);
}

.search-filters .filter-accordion-gap {
    margin-top: 0.3rem;
}

/* ── Price CTA button ──────────────────────────────────────── */
.price-cta-btn {
    width: 100%;
    border: none;
    border-radius: 14px;
    /* UNDO-V1: background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 55%, #1fa8a4 80%, #05b7b2 100%); */
    background: linear-gradient(160deg, #05b7b2 0%, #1a9eb5 28%, #4e54c8 58%, #7c3aed 100%);
    color: #fff;
    padding: 13px 15px 11px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 20px rgba(78,84,200,0.25), 0 2px 8px rgba(5,183,178,0.15);
    text-align: left;
    display: block;
}
.price-cta-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(78,84,200,0.30), 0 4px 14px rgba(5,183,178,0.22);
}
.price-cta-btn:active {
    transform: translateY(1px);
    box-shadow: 0 2px 8px rgba(5,183,178,0.15);
}
.price-cta-btn__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 3px;
}
.price-cta-btn__from-label {
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: rgba(255,255,255,0.65);
}
.price-cta-btn__layers {
    font-size: 11px;
    color: rgba(255,255,255,0.55);
    opacity: 1;
}
.price-cta-btn__amount {
    display: flex;
    align-items: baseline;
    gap: 4px;
    line-height: 1.2;
    white-space: nowrap;
}
.price-cta-btn__currency {
    font-size: 11px;
    font-weight: 600;
    color: rgba(255,255,255,0.72);
    letter-spacing: 0.5px;
}
.price-cta-btn__number {
    display: inline-flex;
    overflow: hidden;
    height: 1.2em;
    vertical-align: bottom;
    font-size: 22px;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: -0.8px;
}
.price-rolling-digit {
    display: inline-flex;
    flex-direction: column;
    animation: priceSlotRoll 2.4s cubic-bezier(0.1, 0.45, 0.1, 1) both;
    height: 12em;
}
.price-rolling-digit > span {
    display: block;
    height: 1.2em;
    line-height: 1.2em;
    text-align: center;
    flex-shrink: 0;
}
.price-rolling-sep {
    display: inline-block;
    font-size: 15px;
    font-weight: 700;
    opacity: 0.7;
    align-self: flex-end;
    margin-bottom: 0.08em;
}
@keyframes priceSlotRoll {
    from { transform: translateY(0); }
    to   { transform: translateY(var(--roll-target)); }
}
.price-cta-btn__divider {
    height: 1px;
    background: linear-gradient(90deg, rgba(255,255,255,0.22) 0%, rgba(5,183,178,0.35) 100%);
    margin: 9px 0 7px;
}
.price-cta-btn__cta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
}
.price-cta-btn__cabin {
    font-size: 11px;
    font-weight: 600;
    color: rgba(255,255,255,0.88);
    letter-spacing: 0.3px;
    white-space: nowrap;
}
.price-cta-btn__hint {
    font-size: 10px;
    font-weight: 600;
    color: rgba(255,255,255,0.92);
    background: rgba(5,183,178,0.28);
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
    letter-spacing: 0.2px;
}
.price-cta-btn__chevron {
    text-align: center;
    margin-top: 6px;
    font-size: 11px;
    color: rgba(255,255,255,0.55);
    animation: chevronBounce 1.4s ease-in-out infinite;
}
@keyframes chevronBounce {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(3px); }
}

/* ── Fare tier cards ───────────────────────────────────────── */
.fare-card {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e4e9f2;
    background: #fff;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.2s, transform 0.2s;
}
.fare-card:hover {
    box-shadow: 0 8px 28px rgba(0,0,0,0.09);
    transform: translateY(-2px);
}
.fare-card--eco  { border-top: 4px solid #16B4A1; }
.fare-card--flex { border-top: 4px solid #3B79F2; }
.fare-card--first{ border-top: 4px solid #875ae9; }

.fare-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px 18px 12px;
    background: #f8fafc;
}
.fare-card__label {
    font-size: 15px;
    font-weight: 700;
    color: #1a2436;
    line-height: 1.2;
}
.fare-card__class-badge {
    display: inline-block;
    margin-top: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #6b7a99;
    background: #eef0f7;
    padding: 2px 9px;
    border-radius: 20px;
    letter-spacing: 0.4px;
}
.fare-card__price-block {
    text-align: right;
    line-height: 1;
}
.fare-card__currency {
    font-size: 11px;
    font-weight: 600;
    color: #6b7a99;
    display: block;
    margin-bottom: 2px;
}
.fare-card__amount {
    font-size: 22px;
    font-weight: 800;
    color: #1a2436;
    letter-spacing: -0.5px;
}
.fare-card__divider {
    height: 1px;
    background: #eef0f6;
    margin: 0;
}
/* Brand card scroll layout */
.brand-cards-scroll {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 6px;
    align-items: stretch;
}
.brand-cards-scroll::-webkit-scrollbar {
    height: 4px;
}
.brand-cards-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}
.brand-cards-scroll::-webkit-scrollbar-thumb {
    background: #c5cae9;
    border-radius: 2px;
}
.brand-card-item {
    flex: 1 0 260px;
    min-width: 260px;
    max-width: 380px;
    display: flex;
    flex-direction: column;
}

.fare-card__features {
    padding: 10px 18px;
    flex: 1;
}
.fare-card__feature {
    display: flex;
    align-items: center;
    padding: 7px 0;
    font-size: 13px;
    border-bottom: 1px solid #f4f5fa;
}
.fare-card__feature:last-child { border-bottom: none; }

.fare-card__status-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    flex-shrink: 0;
    margin-right: 9px;
}
.fare-card__status-dot--ok  { background: #e6f7f4; color: #0d9b6e; }
.fare-card__status-dot--fee { background: #fff5e6; color: #d97706; }
.fare-card__status-dot--no  { background: #f3f3f3; color: #b0b8c8; }

.fare-card__cat-icon {
    width: 20px;
    font-size: 12px;
    color: #9aa3b8;
    flex-shrink: 0;
    margin-right: 8px;
    text-align: center;
}
.fare-card__feature-text {
    color: #3a4563;
    flex: 1;
}
.fare-card__feature-text--fee { color: #d97706; }
.fare-card__feature-text--no  { color: #b0b8c8; }

.fare-card__footer {
    padding: 14px 18px 18px;
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
.fare-card--eco  .fare-card__book-btn:hover { background: #0e9b8b; box-shadow: 0 4px 12px rgba(22,180,161,0.35); }
.fare-card--flex .fare-card__book-btn { background: #3B79F2; color: #fff; }
.fare-card--flex .fare-card__book-btn:hover { background: #2963d8; box-shadow: 0 4px 12px rgba(59,121,242,0.35); }
.fare-card--first .fare-card__book-btn { background: #875ae9; color: #fff; }
.fare-card--first .fare-card__book-btn:hover { background: #6e42cc; box-shadow: 0 4px 12px rgba(135,90,233,0.35); }

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.fly-in {
    animation: flyIn 0.5s ease-out;
}

@keyframes flyIn {
    0% {
        transform: translateY(20px);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

.fly-out {
    animation: flyOut 0.4s ease-in;
}

@keyframes flyOut {
    0% {
        transform: translateY(0);
        opacity: 1;
    }
    100% {
        transform: translateY(-20px);
        opacity: 0;
    }
}

</style>



<style>
/* Update styles */
.fare-danger-bg {
    background: #fff3f3;
}

.baggage-row-bg {
    background: #f8f9fa;
}

.location-input-wrapper.fd {
    position: relative;
    background: white;
    border-radius: 8px;
    cursor: pointer;
}

.to_date {
    padding: 12px;
}

.custom-datepicker-menu {
    z-index: 1000;
}

.custom-datepicker-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    cursor: pointer;
}

/* Scoped to flight-search date cards only — do not leak to AppDatePicker elsewhere */
.date-picker-wrapper .dp__input_wrap {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
}

.date-picker-wrapper .dp__input_icon {
    display: none;
}
</style>


<style scoped>
.date-picker-wrapper {
    position: relative;
    min-width: 0;
    width: 100%;
}

.date-card {
    background: white;
    border-radius: 8px;
    height: 64px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    margin-top: 8px;
    border: 1px solid #ddd;
    width: 100%;
    overflow: hidden;
}

.date-number {
    font-size: 1.6rem;
    font-weight: bold;
    margin-left: 5px;
    color: rgb(62, 73, 87);
    line-height: 1;
    letter-spacing: normal;
    word-spacing: normal;
    white-space: nowrap;
    flex-shrink: 0;
}

.date-info {
    border-left: 1px solid #dee2e6;
    padding-left: 15px;
    min-width: 0;
}

.day {
    font-size: .9rem;
    font-weight: bold;
    color: rgb(62, 73, 87);
    letter-spacing: normal;
    word-spacing: normal;
    white-space: nowrap;
    text-align: left;
}

.month-year {
    font-size: .9rem;
    color: #6c757d;
    letter-spacing: normal;
    word-spacing: normal;
    white-space: nowrap;
    text-align: left;
}

.date-picker-wrapper :deep(.dp__input) {
    display: none;
}

.date-picker-wrapper :deep(.dp__menu) {
    position: absolute;
    z-index: 1000;
    margin-top: 5px;
}

.search-flight-btn {
    width: 100%;
    min-width: 0;
}

.search-page-layout {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    overflow: hidden;
}

.search-sticky-panel {
    position: sticky;
    top: 0;
    z-index: 30;
    flex-shrink: 0;
}

.search-content-row {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    align-items: stretch;
}

.search-filter-column,
.search-results-column {
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: 100%;
}

.search-filter-scroll,
.search-results-scroll {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
}

.search-layout-card {
    border: 0;
    border-radius: 22px;
    overflow: visible;
    background: #fff;
}

.search-panel-top {
    border-bottom: 1px solid #ececf2;
    margin-bottom: 14px;
    padding-bottom: 12px;
    flex-wrap: wrap;
}

.search-trip-types {
    background: #f3f3f8;
    border-radius: 999px;
    padding: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.search-trip-types .form-check-input {
    display: none;
}

.search-trip-types .bg-checkbox,
.search-trip-types .bg-checkbox-active {
    border-radius: 999px !important;
    min-height: 30px;
    display: inline-flex;
    align-items: center;
    padding: 0 12px !important;
    margin: 0;
    border: 0;
}

.search-trip-types .bg-checkbox {
    background: transparent;
}

.search-trip-types .bg-checkbox-active {
    background: linear-gradient(90deg, #2b4fd8 0%, #8c2cc7 100%);
}

.search-trip-types .form-check-label-box {
    margin-bottom: 0;
    font-size: 13px;
    font-weight: 500;
    color: #363d52;
}

.search-trip-types .bg-checkbox-active .form-check-label-box {
    color: #fff;
}

.pax-panel-wrapper {
    margin-left: auto;
    position: relative;
}

.search-pax-trigger {
    border: 1px solid #e7e7ef;
    background: #fff;
    height: 38px;
    border-radius: 12px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #5d657a;
    font-size: 12px;
}

.search-pax-popup {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 340px;
    background: #fff;
    border: 1px solid #ececf2;
    border-radius: 18px;
    padding: 14px;
    box-shadow: 0 18px 36px rgba(26, 34, 67, 0.2);
    z-index: 1200;
}

.popup-title {
    font-size: 13px;
    font-weight: 700;
    color: #8b91a7;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
}

.cabin-class-title {
    margin-top: 14px;
}

.popup-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px dashed #e7e7ef;
}

.row-name {
    font-size: 14px;
    color: #2f3447;
    font-weight: 600;
}

.row-sub {
    font-size: 12px;
    color: #7f869e;
}

.search-pax-popup .product-qty {
    min-width: 102px;
    max-width: 102px;
    border: 1px solid #ececf2;
    border-radius: 999px;
    overflow: hidden;
}

.search-pax-popup .btn-number {
    border: 0;
    width: 34px;
    background: #f7f7fb;
    color: #4e556b;
}

.search-pax-popup .input-number {
    border: 0;
    box-shadow: none;
    text-align: center;
    font-weight: 600;
    color: #30364a;
}

.cabin-switcher {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 8px;
}

.cabin-switcher button {
    border: 0;
    border-radius: 12px;
    background: #ececf2;
    color: #4e556b;
    height: 32px;
    font-size: 13px;
    font-weight: 600;
}

.cabin-switcher button.active {
    background: linear-gradient(90deg, #2b4fd8 0%, #8c2cc7 100%);
    color: #fff;
}

.search-panel-bottom {
    border-top: 1px solid #ececf2;
    margin-top: 14px;
    padding-top: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.bottom-stats {
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-family: 'Roboto', sans-serif;
    font-weight: 600;
    letter-spacing: 0.5px;
    color: #7f869e;
}

.stat-icon {
    font-size: 12px;
    line-height: 1;
}

.stat-icon--airline { color: #505fd6; transform: rotate(-45deg); display: inline-block; }
.stat-icon--route   { color: #22b07d; }

.search-result-meta {
    font-size: 11px;
    font-weight: 500;
    color: #7f869e;
    letter-spacing: 0.2px;
}

.clear-x {
    display: inline-block;
    text-decoration: none;
    transition: transform 0.3s ease;
}

.clear-search-btn:hover .clear-x {
    animation: spinX 0.4s ease forwards;
}

@keyframes spinX {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(180deg); }
}

.clear-search-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-family: 'Roboto', sans-serif;
    font-weight: 600;
    letter-spacing: 0.4px;
    color: #e74c3c;
    cursor: pointer;
    user-select: none;
}

.clear-label {
    text-decoration: underline;
    text-underline-offset: 2px;
}


.icon-action-btn {
    width: 22px;
    height: 22px;
    min-width: 22px;
    min-height: 22px;
    border-radius: 6px;
    border: 1px solid transparent;
    padding: 0;
    display: inline-grid;
    place-items: center;
    transition: all 0.2s ease;
    line-height: 1;
    vertical-align: middle;
    outline: none;
    cursor: pointer;
}

.icon-action-btn i {
    font-size: 10px;
    line-height: 1;
    display: block;
    margin: 0;
}

.icon-download {
    background: #e8fff6;
    color: #0f9d58;
    border-color: #b8f0d8;
}

.icon-download:hover {
    background: #0f9d58;
    color: #fff;
}

.icon-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.fare-chevron {
    transition: transform 0.2s ease;
}
.accordion-button:not(.collapsed) .fare-chevron {
    transform: rotate(180deg);
}
.accordion-button.fare-btn::after {
    display: none !important;
}

.app-modal-dialog:has(.search-cooking-modal) .app-modal-content {
    background: #ffffff;
    border: 1px solid #e8e4f8;
}

.search-cooking-modal {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 400px;
    min-height: 360px;
    padding: 2.5rem 2rem 2rem;
    text-align: center;
    background: #ffffff;
}

.search-cooking-animation {
    width: 240px;
    height: 240px;
    background: url('/theme/appimages/pp.gif') no-repeat center;
    background-size: contain;
    border: 1px solid #8adfdb;
    border-radius: 50%;
}

.search-cooking-text {
    margin-top: 1.25rem;
    color: #875ae9;
    font-size: 1.25rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

</style>
