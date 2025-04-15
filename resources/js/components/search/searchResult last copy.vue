<script setup>
import { ref, onMounted, onUnmounted, reactive, watch } from "vue";
import axiosInstance from "../../axiosInstance"
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import CustomMinMaxSlider from "../../components/search/CustomMinMaxSlider.vue";
import SimpleBar from "simplebar-vue";
import "simplebar-vue/dist/simplebar.min.css";
import { useAuthStore } from '../../stores/authStore';
import '../../../css/searchpanel.css'

import flightData from './dacjfk.json'; // Import the JSON file

const authStore = useAuthStore();



const airports = ref([]); // All airports
const fareRulesData = ref([]); // All fareRulesData
const initialLoadLimit = 20; // Limit for init
const showOriginList = ref(false);
const showDestinationList = ref(false);
const filteredOriginAirports = ref([]);
const filteredDestinationAirports = ref([]);

const totalFlights = ref(0);
const loadging = ref(false);
const fareRuleloading = ref(false);
const fdate = ref();
const sliderMin = ref(150);
const sliderMax = ref(180);
const flights = ref([])
const ExecutionTime = ref([])

const isAutoApply = ref(true);
const isMultiCalendar = ref(false);
const isRanges = ref();

const isRounded = 'oneway';
const tdate = ref();

const form = reactive({
    Way: '',
    from: '',
    fromInput: '',
    to: '',
    toInput: '',
    dep_date: '',
    arrival_date: '',
    ADT: 1,
    CNN: '',
    KID: '',
    INF: ''
});

const selectedOriginDetails = ref(null);
const selectedDestinationDetails = ref(null);

function toggleRule(ruleName) {

    // If clicking the same rule again, keep it open
    if (fareRulesData.value === ruleName) {
        fareRulesData.value = null;  // Close current rule if clicked again
    } else {
        fareRulesData.value = ruleName;  // Open new rule
    }
}

onMounted(() => {
    form.Way = 1;
    getAirports();
    document.addEventListener("click", handleClickOutside);
    //  loadging.value = true;
    const updateTotalPassengers = () => {
        const totalAdult = parseInt($(".adult").val());
        const totalChild = parseInt($(".child").val());
        const totalKids = parseInt($(".kids").val());
        const totalInfant = parseInt($(".infant").val());

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

    // Adult section
    updatePassengerCount('.adult-left-minus', false, 1, 9);
    updatePassengerCount('.adult-right-plus', true, 1, 9);

    // Child section
    updatePassengerCount('.child-left-minus', false, 0, 4);
    updatePassengerCount('.child-right-plus', true, 0, 4);

    // Kids section
    updatePassengerCount('.kids-left-minus', false, 0, 4);
    updatePassengerCount('.kids-right-plus', true, 0, 4);

    // Infant section
    updatePassengerCount('.infant-left-minus', false, 0, 4);
    updatePassengerCount('.infant-right-plus', true, 0, 4);
});

const format = (fdate) => {
    const day = fdate.getDate();
    const month = fdate.getMonth() + 1;
    const year = fdate.getFullYear();
    const date = `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;


    form.dep_date = date;
    return date;
}

const formats = (fdates) => {

    const day = fdates[0].getDate();
    const month = fdates[0].getMonth() + 1;
    const year = fdates[0].getFullYear();
    $("#fromdateVal input").val(`${day}/${month}/${year}`);

    if (fdates[1]) {

        const day2 = fdates[1].getDate();
        const month2 = fdates[1].getMonth() + 1;
        const year2 = fdates[1].getFullYear();
        const date2 = `${year2}-${String(month2).padStart(2, "0")}-${String(day2).padStart(2, "0")}`;
        $("#todateVal input").val(date2);

        form.arrival_date = date2;
    }

    // return `${day}/${month}/${year}`;
    const date = `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
    form.dep_date = date;
    return date;
}

function tourTypeChange(type) {

    if (type == 1) {
        form.Way = 1;
        $('.one-way').addClass('bg-checkbox-active');
        $('.round-way').removeClass('bg-checkbox-active');
        $('.multi-city').removeClass('bg-checkbox-active');
        $('.one-way').removeClass('bg-checkbox');
        $('.round-way').addClass('bg-checkbox');
        $('.multi-city').addClass('bg-checkbox');
        $('#toDateChange').addClass('d-none');
        this.isAutoApply = !this.isAutoApply;
        this.isMultiCalendar = !this.isMultiCalendar;
        this.isRanges = !this.isRanges;
        this.isRounded = 'oneway';


    } else if (type == 2) {
        form.Way = 2;
        $('.one-way').removeClass('bg-checkbox-active');
        $('.round-way').addClass('bg-checkbox-active');
        $('.multi-city').removeClass('bg-checkbox-active');
        $('.round-way').removeClass('bg-checkbox');
        $('.one-way').addClass('bg-checkbox');
        $('.multi-city').addClass('bg-checkbox');

        $('#toDateChange').removeClass('d-none');
        this.isAutoApply = !this.isAutoApply;
        this.isMultiCalendar = !this.isMultiCalendar;
        this.isRanges = !this.isRanges;
        this.isRounded = 'round';


    } else {
        form.Way = 3;
        $('.one-way').removeClass('bg-checkbox-active');
        $('.round-way').removeClass('bg-checkbox-active');
        $('.multi-city').addClass('bg-checkbox-active');
        $('.multi-city').removeClass('bg-checkbox');
        $('.one-way').addClass('bg-checkbox');
        $('.round-way').addClass('bg-checkbox');
    }

}

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
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

function onHover() {
    $("#s_image").attr('src', 'http://[::1]:5173/public/theme/appimages/s_Hover_State.jpg');
}

function offHover() {
    $("#s_image").attr('src', 'http://[::1]:5173/public/theme/appimages/s_With_Icon.jpg');
}

async function Lowfaresearch() {
    try {
        // Clear existing data
        flights.value = []; // Clear flights array
        totalFlights.value = 0; // Reset total flights count
        ExecutionTime.value = 0; // Reset execution time

        // Show loading
        authStore.GlobalLoading = true;
        loadging.value = true;

        const startTime = performance.now();
        const response = await axiosInstance.post("Lowfaresearch", form);
        const endTime = performance.now();
        ExecutionTime.value = ((endTime - startTime) / 1000).toFixed(2);

        flights.value = response.data.flights;
        totalFlights.value = response.data.flights.length;

    } catch (error) {
        console.log(error);
    } finally {
        // Hide loading after a minimum duration of 1 second for better UX
        setTimeout(() => {
            loadging.value = false;
            authStore.GlobalLoading = false;
        }, 1000);
    }
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


function number_format(nStr) {
    nStr += '';
    x = nStr.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? '.' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ',' + '$2');
    }
    return x1;
}

async function fareRuleClick(param) {
    $(".segment-container").hide();
    fareRuleloading.value = true;
    const response = await axiosInstance.post("farerules", param);
    $(".segment-container").show();

    fareRulesData.value = response.data;
    fareRuleloading.value = false;
    console.log(response.data);
}

function swapLocations() {
    [form.from, form.to] = [form.to, form.from];
    [form.fromInput, form.toInput] = [form.toInput, form.fromInput];
    [selectedOriginDetails.value, selectedDestinationDetails.value] = [selectedDestinationDetails.value, selectedOriginDetails.value];
    [showOriginList.value, showDestinationList.value] = [showDestinationList.value, showOriginList.value];
    [filteredOriginAirports.value, filteredDestinationAirports.value] = [filteredDestinationAirports.value, filteredOriginAirports.value];
}

</script>
<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Flight Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Flight Search Result</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- search Panel start -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border border-1 bluesky-border-primary" style="min-height: 150px;">

                <div class="card-body">
                    <div class="d-flex align-items-center gap-2">

                        <div class="bg-checkbox-active one-way rounded rounded-1 p-1">
                            <input @click="tourTypeChange(1)" class="form-check-input" type="radio"
                                name="flexRadioDefault" id="flexRadioDefault1">

                            <label class="form-check-label-box" for="flexRadioDefault1">
                                &nbsp;One Way
                            </label>
                        </div>
                        <div class="bg-checkbox round-way rounded rounded-1 p-1">
                            <input @click="tourTypeChange(2)" class="form-check-input" type="radio"
                                name="flexRadioDefault" id="flexRadioDefault2">
                            <label class="form-check-label-box" for="flexRadioDefault2">
                                &nbsp;Round Trip
                            </label>
                        </div>
                        <div class="bg-checkbox rounded multi-city rounded-1 p-1">
                            <input @click="tourTypeChange(3)" class="form-check-input" type="radio"
                                name="flexRadioDefault" id="flexRadioDefault3">
                            <label class="form-check-label-box" for="flexRadioDefault3">
                                &nbsp; Multi City
                            </label>
                        </div>

                        <!-- in medium screen -->
                        <div class="ms-auto d-none d-md-block">
                            <div class="row">
                                <div class="col-md-4 pt-1 pr-0">
                                    <div class="input-group input-group-sm mb-3">
                                        <button class="btn bluesky-btn-outline-primary dropdown-toggle" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false"><span
                                                class="total_pass">1</span> Passangers</button>
                                        <ul class="dropdown-menu p-1" style="width: 300px;" @click.stop>
                                            <table class="table table-sm">
                                                <tbody>
                                                    <tr>
                                                        <td><small> Adult <br> <span style="font-size: 9px;">Above 12
                                                                    Years</span></small></td>
                                                        <td style="width: 150px;">
                                                            <div class="input-group product-qty">
                                                                <button type="button"
                                                                    class="adult-left-minus btn btn-light rounded-0 rounded-start btn-number"
                                                                    data-type="minus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-minus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                                <input type="text" name="adult"
                                                                    class="form-control input-number adult" value="1">
                                                                <button type="button"
                                                                    class="adult-right-plus btn btn-light rounded-0 rounded-end btn-number"
                                                                    data-type="plus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-plus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td><small>Children <br><span style="font-size: 9px;">02 to
                                                                    Under 12 Years
                                                                </span></small></td>
                                                        <td style="width: 150px;">
                                                            <div class="input-group product-qty">
                                                                <button type="button"
                                                                    class="child-left-minus btn btn-light rounded-0 rounded-start btn-number"
                                                                    data-type="minus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-minus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                                <input type="text" name="child"
                                                                    class="form-control input-number child" value="0">
                                                                <button type="button"
                                                                    class="child-right-plus btn btn-light rounded-0 rounded-end btn-number"
                                                                    data-type="plus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-plus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td><small>Kids <br><span style="font-size: 9px;">02 to
                                                                    Under 5 Years
                                                                </span></small></td>
                                                        <td style="width: 150px;">
                                                            <div class="input-group product-qty">
                                                                <button type="button"
                                                                    class="kids-left-minus btn btn-light rounded-0 rounded-start btn-number"
                                                                    data-type="minus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-minus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                                <input type="text" name="kids"
                                                                    class="form-control input-number kids" value="0">
                                                                <button type="button"
                                                                    class="kids-right-plus btn btn-light rounded-0 rounded-end btn-number"
                                                                    data-type="plus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-plus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td><small>Infant <br><span style="font-size: 9px;">Under 02
                                                                    Years</span></small></td>
                                                        <td style="width: 150px;">
                                                            <div class="input-group product-qty">
                                                                <button type="button"
                                                                    class="infant-left-minus btn btn-light rounded-0 rounded-start btn-number"
                                                                    data-type="minus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-minus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                                <input type="text" name="infant"
                                                                    class="form-control input-number infant" value="0">
                                                                <button type="button"
                                                                    class="infant-right-plus btn btn-light rounded-0 rounded-end btn-number"
                                                                    data-type="plus">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                        height="24" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2"
                                                                        stroke-linecap="round" stroke-linejoin="round"
                                                                        class="feather feather-plus-circle custom-text-purple">
                                                                        <circle cx="12" cy="12" r="10"></circle>
                                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </ul>

                                    </div>
                                </div>

                                <div class="col-md-4 p-1">
                                    <select name="" id="class_type" class="form-control form-control-sm select2">
                                        <option value="" selected>Economy</option>
                                        <option value="">Premium Economy</option>
                                        <option value="">Business Class</option>
                                        <option value="">First Class</option>
                                    </select>
                                </div>

                                <div class="col-md-4 p-1">
                                    <select name="" id="pre_airline" class="form-control form-control-sm select2">
                                        <option value="" selected>Prefered Airlines</option>
                                        <option value="">Qatar </option>
                                        <option value="">Saudia</option>
                                        <option value="">Emirates</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!-- end in medium  screen -->
                    </div>

                    <!-- in small screen -->
                    <div class="row d-sm-block d-md-none mt-2">

                        <button class="col-md-12 btn bluesky-btn-outline-primary dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">1 Passangers</button>
                        <ul class="dropdown-menu p-1" style="width: 300px;">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td><small> Adult <br> <span style="font-size: 9px;">Above 12
                                                    Years</span></small></td>
                                        <td style="width: 150px;">
                                            <div class="input-group product-qty">
                                                <button type="button"
                                                    class="quantity-left-minus btn btn-light rounded-0 rounded-start btn-number"
                                                    data-type="minus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="feather feather-minus-circle custom-text-purple">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                    </svg>
                                                </button>
                                                <input type="text" name="quantity"
                                                    class="form-control input-number quantity" value="1">
                                                <button type="button"
                                                    class="quantity-right-plus btn btn-light rounded-0 rounded-end btn-number"
                                                    data-type="plus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="feather feather-plus-circle custom-text-purple">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><small>Children <br><span style="font-size: 9px;">02 to
                                                    Under 12 Years
                                                </span></small></td>
                                        <td style="width: 150px;">
                                            <div class="input-group product-qty">
                                                <button type="button"
                                                    class="quantity-left-minus btn btn-light rounded-0 rounded-start btn-number"
                                                    data-type="minus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="feather feather-minus-circle custom-text-purple">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                    </svg>
                                                </button>
                                                <input type="text" name="quantity"
                                                    class="form-control input-number quantity" value="1">
                                                <button type="button"
                                                    class="quantity-right-plus btn btn-light rounded-0 rounded-end btn-number"
                                                    data-type="plus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="feather feather-plus-circle custom-text-purple">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><small>Infant <br><span style="font-size: 9px;">Under 02
                                                    Years</span></small></td>
                                        <td style="width: 150px;">
                                            <div class="input-group product-qty">
                                                <button type="button"
                                                    class="quantity-left-minus btn btn-light rounded-0 rounded-start btn-number"
                                                    data-type="minus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="feather feather-minus-circle custom-text-purple">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                    </svg>
                                                </button>
                                                <input type="text" name="quantity"
                                                    class="form-control input-number quantity" value="1">
                                                <button type="button"
                                                    class="quantity-right-plus btn btn-light rounded-0 rounded-end btn-number"
                                                    data-type="plus">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="feather feather-plus-circle custom-text-purple">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </ul>

                        <div class="col-md-12 p-1 mt-2">
                            <select name="" id="class_type" class="form-control form-control-sm">
                                <option value="" selected>Economy</option>
                                <option value="">Premium Economy</option>
                                <option value="">Business Class</option>
                                <option value="">First Class</option>
                            </select>
                        </div>

                        <div class="col-md-12 p-1 mt-2">
                            <select name="" id="pre_airline" class="form-control form-control-sm">
                                <option value="" selected>Prefered Airlines</option>
                                <option value="">Qatar </option>
                                <option value="">Saudia</option>
                                <option value="">Emirates</option>
                            </select>
                        </div>
                    </div>
                    <!-- end in small screen -->

                    <div class="row">
                        <div class="col-md-7">
                            <div class="row position-relative">
                                <div class="col-md-6">
                                    <div class="location-input-wrapper">
                                        <div v-if="form.from && selectedOriginDetails" class="selected-location">

                                            <div class="hstack align-items-center">
                                                <div id="oFrom" class="font-12 fw-bold pe-2 fly-in" style="color: rgb(62, 73, 87);">{{ form.from }}</div>
                                                <div id="oCityAirport" class="flex-grow-1 border-start ps-2 fly-in">
                                                    <div class="font-11 fw-bold fcolor">{{ selectedOriginDetails.city }}</div>
                                                    <div class="text-muted font-10">{{ selectedOriginDetails.text }}</div>
                                                </div>
                                            </div>

                                        </div>
                                        <input id="origin_id" v-model="form.fromInput" name="origin_name" class="form-control origin_name"
                                        :class="{ 'has-value': form.from && !showOriginList}"
                                        @input="filterOriginAirports($event.target.value)"
                                        @focus="onOriginFocus" placeholder="From" autocomplete="off" />
                                        <span v-if="form.from" @click="clearOrigin" class="clear-icon">✖</span>
                                        <div v-if="showOriginList" id="origin_results" class="position-absolute w-100 mt-2" style="z-index: 1000; animation: fadeIn 0.3s ease-in-out">
                                            <SimpleBar style="max-height: 300px" class="search-results-simplebar">
                                                <div v-for="airport in filteredOriginAirports" :key="airport.id" class="cursor-pointer border-bottom border-light" @click="selectOrigin(airport)">
                                                    <div class="hstack align-items-center">
                                                        <div class="font-12 fw-bold pe-2" style="color: rgb(62, 73, 87);">{{ airport.id }}</div>
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
                                <div class="col-md-6">
                                    <div class="location-input-wrapper">
                                        <div v-if="form.to && selectedDestinationDetails" class="selected-location">

                                            <div class="hstack align-items-center">
                                                <div id="dFrom" class="font-12 fw-bold pe-2 fly-in" style="color: rgb(62, 73, 87);">{{ form.to }}</div>
                                                <div id="dCityAirport" class="flex-grow-1 border-start ps-2 fly-in">
                                                    <div class="font-11 fw-bold fcolor fly-in">{{ selectedDestinationDetails.city }}</div>
                                                    <div class="text-muted font-10 fly-in">{{ selectedDestinationDetails.text }}</div>
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

                                                        <div class="font-12 fw-bold pe-2" style="color: rgb(62, 73, 87);">{{ airport.id }}</div>
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
                        <div class="col-md-5">
                            <div class="row">
                                <div class="col-md-4 mt-2 mt-md-0" v-if="isRounded == 'oneway'">
                                    <VueDatePicker class="dateChange" id="fromdateVal" v-model="fdate" placeholder="Select Date"
                                        :enable-time-picker="false" :format="format" :auto-apply="isAutoApply"
                                        :multi-calendars="isMultiCalendar" :range="isRanges"></VueDatePicker>

                                </div>
                                <div class="col-md-4 mt-2 mt-md-0" v-if="isRounded == 'round'">
                                    <VueDatePicker class="dateChange" id="fromdateVal" v-model="fdate" placeholder="Select Date"
                                        :enable-time-picker="false" :format="formats" :auto-apply="isAutoApply"
                                        :multi-calendars="isMultiCalendar" :range="isRanges"></VueDatePicker>
                                </div>

                                <div class="col-md-2 d-none mt-2 mt-md-0" id="toDateChange">
                                    <VueDatePicker v-model="tdate" id="todateVal" placeholder="Select Date"
                                        :enable-time-picker="false">
                                    </VueDatePicker>
                                </div>
                                <div class="col-md-1 mt-2 mt-md-0">
                                    <!-- <router-link> -->
                                    <img @click="Lowfaresearch()"
                                        src="../../../../public/theme/appimages/Mobile_Button With_Icon.jpg" alt=""
                                        class="d-sm-block d-md-none" style="width: 100%;" id="img">
                                    <img @click="Lowfaresearch()" src="../../../../public/theme/appimages/s_With_Icon.jpg"
                                        alt="" style="width: 53px; cursor:pointer" @mouseover="onHover();"
                                        @mouseout="offHover();" id="s_image" class="d-none d-md-block">
                                    <!-- </router-link> -->
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <!-- search Panel end -->

    <div class="row">
        <!-- left filter panel start -->
        <div class="col-md-3">
            <div class="row">
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
                                    <p class="text-black mb-0" style="font-weight: 600;">30</p>
                                </div>
                                &nbsp;
                                <div class="ml-1 mr-1" style="margin-top: 9px;"><b>:</b></div>
                                &nbsp;
                                <div class="dash-lable bg-light-primary custom-text-purple rounded-1"
                                    style="padding-top: 8px;">
                                    <p class="text-black mb-0" style="font-weight: 600;">00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
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
                                        <CustomMinMaxSlider :min="65" :max="368" v-model:min-value="sliderMin"
                                            v-model:max-value="sliderMax" />
                                        <p class="text-center mb-0"> BDT {{
                                            sliderMin }} - BDT {{ sliderMax }}
                                        </p>
                                        <div class="text-center p-0">
                                            <span class="text-danger">*</span> Price may change based on
                                            policy
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Flight Schedule -->
                    <div class="accordion" id="class-type">
                        <div class="accordion-item mt-3">
                            <h6 class="accordion-header" id="headingSix">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix"
                                    aria-expanded="false" aria-controls="collapseSix">
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
                                                    class="btn btn-sm bluesky-route-btn-primary w-100">Departure</button>
                                                <button type="button"
                                                    class="btn btn-sm px-0 py-0 btn-sm-size bluesky-btn-outline-primary w-100">Arrival</button>
                                            </div>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <div class="d-flex gap-2">
                                                <div style="background: linear-gradient(180deg, rgba(135, 160, 174, 0.50) 0%, rgba(208, 200, 187, 0.30) 50%, rgba(254, 191, 84, 0.20) 100%); height: 70px !important; min-width: 55px !important;"
                                                    class="p-1 border border-1 text-center rounded rounded-1 accorion-item-title-color cursor-pointer">

                                                    <img src="../../../../public/theme/animation/Sun_Rise.gif"
                                                        style="width: 42px;" alt="">
                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">00-06
                                                        AM</span>
                                                </div>
                                                <div style="background: linear-gradient(180deg, rgba(255, 240.15, 143.65, 0.50) 0%, rgba(221.85, 201.52, 51.76, 0.30) 53%, rgba(187.85, 164.39, 11.90, 0.20) 100%); height: 70px !important; min-width: 55px;"
                                                    class="p-1 border border-1 text-center rounded rounded-1 accorion-item-title-color cursor-pointer">
                                                    <img src="../../../../public/theme/animation/Noon.gif"
                                                        style="width: 42px;" alt="">
                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">06-12
                                                        PM</span>
                                                </div>

                                                <div style="background: linear-gradient(180deg, rgba(40.98, 55.67, 121.12, 0.50) 0%, rgba(110, 87, 100, 0.30) 52%, rgba(230, 141, 91, 0.20) 100%); height: 70px !important; min-width: 55px;"
                                                    class="p-1 border border-1 text-center rounded rounded-1 accorion-item-title-color cursor-pointer">
                                                    <img src="../../../../public/theme/animation/Sun_Set.gif"
                                                        style="width: 32px; margin-bottom: 11px;" alt="">
                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">12-06
                                                        PM</span>
                                                </div>
                                                <div style="background: linear-gradient(182deg, #081627 0%, rgba(0, 66.30, 132.60, 0.40) 55%, rgba(157.25, 227.55, 255, 0.60) 100%); height: 70px !important; min-width: 55px;"
                                                    class="p-1 border border-1 text-center rounded rounded-1 accorion-item-title-color cursor-pointer">
                                                    <img src="../../../../public/theme/animation/Night.gif"
                                                        style="width: 35px; margin-bottom: 8px;" alt="">

                                                    <br>
                                                    <span style="font-size: 8px; font-weight: bold;">06-12
                                                        AM</span>
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
                        <div class="accordion-item mt-3">
                            <h6 class="accordion-header" id="headingfour">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour"
                                    aria-expanded="false" aria-controls="collapseFour">
                                    <span class="" style="font-size: 13px;">Airlines</span>
                                </button>
                            </h6>
                            <div id="collapseFour" class="accordion-collapse collapse show"
                                aria-labelledby="headingfour" data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <div class="d-flex">
                                            <div class="bd-highlight">Emirates</div>
                                            <div class="ms-auto bd-highlight total-flight-from">05</div>
                                        </div>

                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">

                                        <div class="d-flex">
                                            <div class="bd-highlight">Qatar Airways</div>
                                            <div class="ms-auto bd-highlight total-flight-from">02</div>
                                        </div>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">

                                        <div class="d-flex">
                                            <div class="bd-highlight">US-Bangla</div>
                                            <div class="ms-auto bd-highlight total-flight-from">02</div>
                                        </div>
                                    </div>

                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <div class="d-flex">
                                            <div class="bd-highlight">Biman Bangladesh</div>
                                            <div class="ms-auto bd-highlight total-flight-from">02</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- stop -->
                    <div class="accordion" id="stop">
                        <div class="accordion-item mt-3">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo"
                                    aria-expanded="false" aria-controls="collapseTwo">

                                    <span class="" style="font-size: 13px;">Stop
                                    </span>
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                                data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Non-Stop
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            1 Stop
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            2 Stop
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Refund availability -->
                    <div class="accordion" id="refund">
                        <div class="accordion-item mt-3">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree"
                                    aria-expanded="false" aria-controls="collapseThree">

                                    <span style="font-size: 13px;">Refund Availability</span>
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse show"
                                aria-labelledby="headingThree" data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Refundable
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Partially Refundable
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Non Refundable
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Baggage -->
                    <div class="accordion" id="class-type">
                        <div class="accordion-item mt-3">
                            <h2 class="accordion-header" id="headingSeven">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven"
                                    aria-expanded="false" aria-controls="collapseSeven">

                                    <span style="font-size: 13px;">Baggage</span>

                                </button>
                            </h2>
                            <div id="collapseSeven" class="accordion-collapse collapse show"
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
                    <div class="accordion" id="class-type">
                        <div class="accordion-item mt-3">
                            <h6 class="accordion-header" id="headingEight">
                                <button class="accordion-button accorion-item-title-color m-0 p-0 px-2 py-2 d-flex justify-content-between w-100 align-items-center"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight"
                                    aria-expanded="false" aria-controls="collapseEight">

                                    <span style="font-size: 13px;">Layover</span>

                                </button>
                            </h6>
                            <div id="collapseEight" class="accordion-collapse collapse show"
                                aria-labelledby="headingEight" data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Netaji Subhas Chandra Bose..
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Kuwait International Airport
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Kunming Changshui Interna..
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                        <label class="form-check-label" for="flexCheckDefault">
                                            Kiala lumpur International Ai..
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 mt-4">
                    <button class="btn btn-block btn-sm bluesky-btn-outline-primary w-100">
                        Clear All Filters
                    </button>
                </div>
            </div>
        </div>
        <!-- left filter panel end -->

        <!-- result panel start -->
        <div class="col-md-9">

            <div class="row">

                <span v-show="flights.length > 0">Showing {{ flights.length }} of {{ totalFlights }} Total Flights (In
                    {{ ExecutionTime }} seconds)</span>
                <div class="col-md-12 pb-2">
                    <hr>
                </div>

                <div v-for="(flight, index) in flights" :key="index" class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">

                                    <!-- outbound start-->
                                    <div class="row" v-if="flight.outbound">
                                        <div class="col-md-10 col-sm-12">
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
                                                                    <div class="text-black"><b>{{ flight.outbound.origin
                                                                            }}-{{
                                                                                flight.outbound.destination }}</b>
                                                                    </div>
                                                                    <div style="font-size: 11px; color: #8327a4">
                                                                        {{ flight.outbound.first_carrier_code }} | {{
                                                                            flight.outbound.first_airline_name }}
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
                                                                <div class="text-black"><b>{{
                                                                    flight.outbound.departure_time }}</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">
                                                                        {{ formatDate(flight.outbound.departure_date) }}
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
                                                                    <div class="text-black">
                                                                        <img src="../../../../public/theme/appimages/Left_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>
                                                                    <div>
                                                                        <img style="transform: rotate(0deg)"
                                                                            src="../../../../public/theme/animation/Route_Aircraft.svg"
                                                                            alt="" height="22" width="22">
                                                                    </div>
                                                                    <div class="text-black">
                                                                        <img src="../../../../public/theme/appimages/Right_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>


                                                                </div>

                                                                <div class="d-inline-flex ms-2">
                                                                    <div class="d-flex flex-row" v-for="stop,iter in flight.outbound.connections.stops">
                                                                        <div class="bd-highlight">
                                                                            <img  :title="`Layover at ${ stop.city_name } | ${ stop.layover_time }\n${ stop.airport_name }`"
                                                                                src="../../../../public/theme/appimages/Layover.svg"  alt="">
                                                                        </div>
                                                                        <div class="bd-highlight text-black" v-if="iter != (flight.outbound.connections.stops.length -1)">
                                                                            ....
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex ps-2" v-for="stop,index in flight.outbound.connections.stops">
                                                                    <span style="font-size: 7px; color: #5e6878;">{{ stop.airport_code }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-4">
                                                            <div
                                                                class="d-block justify-content-center align-items-center h-100 w-100">
                                                                <div class="text-black"><b>{{
                                                                    flight.outbound.arrival_time }}</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">
                                                                        {{ formatDate(flight.outbound.arrival_date)
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
                                        <div v-show="form.Way == 1" class="col-12 col-sm-12 col-md-2">
                                            <div class="d-grid">
                                                <button class="btn btn-sm bluesky-btn-primary" data-bs-toggle="collapse"
                                                    :data-bs-target="`#flight-package-${index}`" :aria-controls="`flight-package-${index}`">

                                                    <div class="text-right">
                                                        <p class="p-0 m-0">
                                                            <b>
                                                                <span v-for="itemPrice, index in flight.outbound.priceBreakdown">
                                                                    <b>
                                                                        <span v-if="index == 0" >
                                                                            BDT {{
                                                                                ['Adult', 'Child', 'Infant'].reduce((total, type) => {
                                                                                    const breakdown = flight.outbound.priceBreakdown.find(item => item.type === type) || {};
                                                                                    const count = form[type === 'Adult' ? 'ADT' : type === 'Child' ? 'CNN' : 'INF'];
                                                                                    return total + (count * (breakdown.taxes || 0)) + (count * (breakdown.baseFare || 0));
                                                                                }, 0)
                                                                            }}
                                                                        </span>
                                                                    </b>
                                                                </span>
                                                                <!-- {{ flight.outbound.currency }} {{flight.outbound.totalPrice }} -->
                                                            </b>
                                                            </p>
                                                        <!-- <small style="color: #dbdbdb"><del>
                                                                    BDT 77000</del></small> -->
                                                        <small style="font-size: 10px;">Economy Class</small>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <div v-show="form.Way == 2" class="col-12 col-sm-12 col-md-2 ">

                                            <div class="d-grid">
                                                <button class="btn btn-sm bluesky-btn-primary" data-bs-toggle="collapse"
                                                :data-bs-target="`#flight-package-${index}`" :aria-controls="`flight-package-${index}`">

                                                    <div class="text-right">
                                                        <p class="p-0 m-0">
                                                            <span v-for="itemPrice, index in flight.outbound.priceBreakdown">
                                                                    <b>
                                                                        <span v-if="index == 0">
                                                                            BDT {{
                                                                                ['Adult', 'Child', 'Infant'].reduce((total, type) => {
                                                                                    const breakdown = flight.outbound.priceBreakdown.find(item => item.type === type) || {};
                                                                                    const count = form[type === 'Adult' ? 'ADT' : type === 'Child' ? 'CNN' : 'INF'];
                                                                                    return total + (count * (breakdown.taxes || 0)) + (count * (breakdown.baseFare || 0));
                                                                                }, 0)
                                                                            }}
                                                                        </span>
                                                                    </b>
                                                                </span>
                                                        </p>

                                                        <small style="font-size: 10px;">Economy Class</small>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                    <!-- outbound end -->

                                    <!-- inbound start -->
                                    <div class="row" v-if="flight.inbound">
                                        <div class="col-md-10 col-sm-12 border-top mt-3">
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
                                                                    <div class="text-black"><b>{{ flight.inbound.origin
                                                                            }} - {{ flight.inbound.destination }}</b>
                                                                    </div>
                                                                    <div style="font-size: 11px; color: #8327a4">
                                                                        {{ flight.inbound.first_carrier_code }} | {{
                                                                            flight.inbound.first_airline_name }}
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
                                                                <div class="text-black"><b>{{
                                                                    flight.inbound.departure_time }}</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">{{
                                                                        formatDate(flight.inbound.departure_date)
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
                                                                    <div class="text-black">
                                                                        <img src="../../../../public/theme/appimages/Left_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>
                                                                    <div>
                                                                        <img style="transform: rotate(180deg)"
                                                                            src="../../../../public/theme/animation/Route_Aircraft.svg"
                                                                            alt="" height="22" width="22">
                                                                    </div>
                                                                    <div class="text-black">
                                                                        <img src="../../../../public/theme/appimages/Right_Aligned Line.svg"
                                                                            alt="">
                                                                    </div>
                                                                </div>

                                                                <div class="d-inline-flex ms-2">
                                                                    <div class="d-flex flex-row" v-for="stop,iter in flight.inbound.connections.stops">
                                                                        <div class="bd-highlight">
                                                                            <img  :title="`Layover at ${ stop.city_name } | ${ stop.layover_time }\n${ stop.airport_name }`"
                                                                                src="../../../../public/theme/appimages/Layover.svg"  alt="">
                                                                        </div>
                                                                        <div class="bd-highlight text-black" v-if="iter != flight.inbound.connections.stops.length -1">
                                                                            ....
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex ps-2">
                                                                    <span v-for="stop,index in flight.inbound.connections.stops"
                                                                        style="font-size: 7px; color: #5e6878;">{{ stop.airport_code }} &nbsp; &nbsp; &nbsp;</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-4">
                                                            <div
                                                                class="d-block justify-content-center align-items-center h-100 w-100">
                                                                <div class="text-black"><b>10:50 AM</b>
                                                                </div>
                                                                <div>
                                                                    <small style="font-size: 12px; color: #5e6878;">03
                                                                        Jan,
                                                                        Fri</small>
                                                                </div>
                                                                <div>
                                                                    <small
                                                                        style="font-size: 12px; color: #5e6878;">Departure</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-12 col-md-2 d-sm-block d-md-none">

                                            <div class="d-grid">
                                                <button class="btn btn-sm bluesky-btn-primary" data-bs-toggle="collapse"
                                                    :data-bs-target="`flight-package-${index}`" :aria-controls="`flight-package-${index}`">

                                                    <div class="text-right">
                                                        <p class="p-0 m-0"><b> {{ flight.outbound.currency }} {{
                                                            flight.outbound.totalPrice }}</b></p>
                                                        <!-- <small style="color: #dbdbdb"><del>
                                                                    BDT 77000</del></small> -->
                                                        <small style="font-size: 10px;">Economy Class</small>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- inbound end -->

                                </div>
                            </div>
                        </div>
                        <div class="card-footer p-0" style="background-color:#f1f4f7;">
                            <div class="float-start p-2">
                                <div class="d-flex gap-2">

                                    <div v-if="flight.outbound.refundable" class="border border-1 text-center p-1"
                                        style="background-color: #def1ec; color: #12ce69; font-size: 12px;">
                                        <img src="../../../../public/theme/appimages/refund-able.svg" alt="">
                                        Refundable
                                    </div>
                                    <div v-else class="border border-1 text-center p-1"
                                        style="background-color: #f1dede; color: #ce1212; font-size: 12px;">
                                        <img src="../../../../public/theme/appimages/Non-Refundable.svg" alt="">
                                        Non-refundable
                                    </div>

                                    <div class="border border-1 text-center p-1"
                                        style="background-color: #e4e3f6; color: #7944eb; font-size: 12px;">
                                        <i class="fa-regular fa-seat-airline"></i> Available Seats: 09
                                    </div>

                                    <div v-if="flight.outbound.segments[0].is_codeshare==true" class="border border-1 text-center p-1"
                                        style="background-color: #E8EFF4 ; color: #127EB8; font-size: 12px;">
                                         <img src="../../../../public/theme/appimages/codeshare.svg" alt="">
                                         Codeshare By {{ flight.outbound.segments[0].codeshare_info.operating_airline_name }}
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
                                                    style=" font-size: 12px; background: #f1f4f7 !important;">
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
                                                    <a class="nav-link" @click="fareRuleClick( flight.outbound.farerulekey )" data-bs-toggle="tab" :href="`#primaryprofile-${index}`"
                                                        role="tab" aria-selected="false" tabindex="-1">
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
                                                            <div class="card-header accorion-item-title-color m-0 p-0 "
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
                                                                                    class="text-black"><b>{{
                                                                                        route.departure_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span></b></small>
                                                                                <span style="font-size: 11px;">{{
                                                                                    formatDate(route.departure_date)
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
                                                                                    class="text-black"><b>{{
                                                                                        route.arrival_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span></b></small>
                                                                                <span style="font-size: 11px;">{{
                                                                                    formatDate(route.arrival_date)
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

                                                                            <div class="border border-1 text-center p-1"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">
                                                                                {{ route.flight }}-{{
                                                                                    route.aircraft_name }}</div>
                                                                            <div class="border border-1 text-center p-1"
                                                                                style="background-color: rgb(222, 241, 236); color: rgb(18, 206, 105); font-size: 10px; white-space: nowrap;">
                                                                                {{ route.cabin_class }} - {{
                                                                                    route.booking_code }}

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

                                                                            <div class="border border-1 text-center p-1"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">

                                                                               {{ route.codeshare_info.operating_carrier }}{{ route.codeshare_info.operating_flight_number }}-{{ route.aircraft_name }}
                                                                                    </div>
                                                                            <div class="border border-1 text-center p-1"
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
                                                                        <div class="mt-2 mb-0"
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
                                                            <div class="card-header accorion-item-title-color m-0 p-0"
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
                                                                                    class="text-black"><b>{{
                                                                                        returnRoute.departure_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span>
                                                                                    </b></small>
                                                                                <span style="font-size: 11px;">{{
                                                                                    formatDate(returnRoute.departure_date)
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
                                                                                    class="text-black"><b>{{
                                                                                        returnRoute.arrival_time }}
                                                                                        <span
                                                                                            class="vertical-line">|</span></b></small>
                                                                                <span style="font-size: 11px;">
                                                                                    {{
                                                                                        formatDate(returnRoute.arrival_date)
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

                                                                            <div class="border border-1 text-center p-1"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">
                                                                                {{ returnRoute.flight }}-{{
                                                                                    returnRoute.aircraft_name }}</div>
                                                                            <div class="border border-1 text-center p-1"
                                                                                style="background-color: rgb(222, 241, 236); color: rgb(18, 206, 105); font-size: 10px; white-space: nowrap;">
                                                                                {{ returnRoute.cabin_class }} - {{
                                                                                    returnRoute.booking_code }}
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

                                                                            <div class="border border-1 text-center p-1"
                                                                                style="background-color: rgb(228, 227, 246); color: rgb(121, 68, 235); font-size: 10px; white-space: nowrap;">
                                                                                {{ returnRoute.codeshare_info.operating_carrier }}{{ returnRoute.codeshare_info.operating_flight_number }}-{{
                                                                                    returnRoute.aircraft_name }}</div>
                                                                            <div class="border border-1 text-center p-1"
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
                                                                                <div class="mt-2 mb-0"
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
                                                    <div class="row">
                                                        <div class="col-md-12" v-show="fareRuleloading">
                                                            <div class="text-center">
                                                                <img class="border w3-circle" height="70" src="../../../../public/theme/appimages/pp.gif" alt="">
                                                                <p class="mt-2">Loading fare rules...</p>
                                                            </div>
                                                        </div>

                                                        <div v-for="(segment, segmentKey, iterm) in fareRulesData.data" :key="segmentKey" class="segment-container">
                                                            <h3 class="segment-title">{{ segmentKey }}</h3>


                                                                <div class="accordion" id="accordionExample">
                                                                    <div class="accordion-item" v-for="(rule, ruleName) in segment.rules">
                                                                        <h2 class="accordion-header" id="headingOne">
                                                                            <button class="d-flex justify-content-between w-100 align-items-center accordion-button collapsed accorion-item-title-color m-0 p-0 px-2 py-2" type="button" data-bs-toggle="collapse" :data-bs-target="`#${ruleName}_${index}_${iterm}`" aria-expanded="false" :aria-controls="`${ruleName}_${index}_${iterm}`">
                                                                                <!-- {{ ruleName.replace(/_/g, ' ') }} -->
                                                                                {{ ruleName=="CATEGORY_0" ?"General":  ruleName.replace(/_/g, ' ')}}
                                                                            </button>
                                                                        </h2>
                                                                        <div :id="`${ruleName}_${index}_${iterm}`" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                                                            <div class="accordion-body">
                                                                                <span class="text-capitalize-">
                                                                                    {{ rule.content.charAt(0).toUpperCase() + rule.content.slice(1).toLowerCase() }}</span>
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
                                        <div class="col-md-4" style="background-color: #f4f4ff;">

                                            <div class="accordion accordion-flush mt-3" id="accordionFlushExample">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header rounded" id="flush-headingOne"
                                                        style="background-color: #7944eb !important;">
                                                        <button class="accordion-button m-0 p-0 px-3 py-2 collapsed d-flex justify-content-between w-100 align-items-center"
                                                            type="button" data-bs-toggle="collapse"

                                                            :data-bs-target="'#flush-fare-summary-' + index"
                                                            aria-expanded="false"
                                                            :aria-controls="'flush-fare-summary-' + index">
                                                            Fare Summary
                                                        </button>
                                                    </h2>
                                                    <div :id="'flush-fare-summary-' + index" class="accordion-collapse collapse"
                                                        aria-labelledby="flush-headingOne"
                                                        data-bs-parent="#accordionFlushExample" style="">
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
                                                                            <tbody class="text-start">

                                                                                <tr v-for="itemPrice in flight.outbound.priceBreakdown"
                                                                                    style="font-size: 10px;">
                                                                                    <td>{{ itemPrice.type }}: <span
                                                                                            v-if="itemPrice.type == 'Adult'">{{
                                                                                                form.ADT }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Child'">{{
                                                                                                form.CNN }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Infant'">{{
                                                                                                form.INF }}</span>
                                                                                        x {{
                                                                                            itemPrice.baseFare }}
                                                                                    </td>
                                                                                    <td>

                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Adult'">BDT
                                                                                            {{ form.ADT *
                                                                                                itemPrice.baseFare }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Child'">BDT
                                                                                            {{ form.CNN *
                                                                                                itemPrice.baseFare }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Infant'">BDT
                                                                                            {{ form.INF *
                                                                                                itemPrice.baseFare }}</span>


                                                                                    </td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>

                                                                    <div
                                                                        class="border fare-summary-bg p-1 rounded-1 mb-1">
                                                                        <span class="custom-text-purple">
                                                                            TAX
                                                                        </span>
                                                                    </div>

                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-striped ">
                                                                            <tbody class="text-start">
                                                                                <tr v-for="itemPrice in flight.outbound.priceBreakdown"
                                                                                    style="font-size: 10px;">
                                                                                    <td> {{ itemPrice.type }}:
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Adult'">{{
                                                                                                form.ADT }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Child'">
                                                                                            {{ form.CNN }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Infant'">{{
                                                                                                form.INF }}</span>
                                                                                        x {{ itemPrice.taxes
                                                                                        }}
                                                                                    </td>
                                                                                    <td>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Adult'">BDT
                                                                                            {{ form.ADT *
                                                                                                itemPrice.taxes }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Child'">
                                                                                            BDT {{ form.CNN *
                                                                                                itemPrice.taxes }}</span>
                                                                                        <span
                                                                                            v-if="itemPrice.type == 'Infant'">BDT
                                                                                            {{ form.INF *
                                                                                                itemPrice.taxes }}</span>
                                                                                    </td>

                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>


                                                                    <!-- <div
                                                                        class="border fare-summary-bg p-1 rounded-1 mb-1">
                                                                        <span class="custom-text-purple">
                                                                            Gross Amount
                                                                        </span>
                                                                    </div> -->

                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-striped ">
                                                                            <tbody class="text-start">
                                                                                <tr style="font-size: 10px;">
                                                                                    <td>
                                                                                        <b>Gross Fare &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</b>
                                                                                    </td>
                                                                                    <td v-for="itemPrice, index in flight.outbound.priceBreakdown">
                                                                                        <b>
                                                                                            <span v-if="index == 0">
                                                                                                BDT {{
                                                                                                    ['Adult', 'Child', 'Infant'].reduce((total, type) => {
                                                                                                        const breakdown = flight.outbound.priceBreakdown.find(item => item.type === type) || {};
                                                                                                        const count = form[type === 'Adult' ? 'ADT' : type === 'Child' ? 'CNN' : 'INF'];
                                                                                                        return total + (count * (breakdown.taxes || 0)) + (count * (breakdown.baseFare || 0));
                                                                                                    }, 0)
                                                                                                }}
                                                                                            </span>
                                                                                        </b>

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
                                                            Baggadge Information
                                                        </button>
                                                    </h2>


                                                    <div  :id="'flush-collapseTwo-' + index" class="accordion-collapse collapse"
                                                        aria-labelledby="flush-headingTwo"
                                                        data-bs-parent="#accordionFlushExample" style="">
                                                        <div class="accordion-body">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm ">
                                                                            <tbody class="text-start">
                                                                                <tr v-for="baggage,index in flight.outbound.baggage_allowance">

                                                                                    <td style="font-size: 11px;" >
                                                                                        <span v-if="index==0">
                                                                                            <b> {{ flight.outbound.origin }} - {{ flight.outbound.destination }}</b>
                                                                                        <br>
                                                                                        </span>
                                                                                        <small>{{ baggage.type }}</small>
                                                                                    </td>

                                                                                    <td style="font-size: 11px;">
                                                                                        <b v-if="flight.outbound.segments">{{ flight.outbound.segments[0].cabin_class }}</b>
                                                                                        <br>
                                                                                        <small v-if="baggage.max_weight">{{ (baggage.max_weight).includes('Kilograms') ? (baggage.max_weight).replace('Kilograms','Kg') : ''}}/Person</small>

                                                                                        <small v-else="baggage.pieces">{{ flight.outbound.baggage_allowance[0].pieces }} Pcs/Person</small>
                                                                                    </td>

                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>

                                                                    <div v-if="flight.inbound" class="table-responsive">
                                                                        <table class="table table-sm ">
                                                                            <tbody>
                                                                                <tr v-for="baggage,index in flight.inbound.baggage_allowance">

                                                                                    <td style="font-size: 11px;" >
                                                                                        <span v-if="index==0">
                                                                                            <b> {{ flight.inbound.origin }} - {{ flight.inbound.destination }}</b>
                                                                                        <br>
                                                                                        </span>
                                                                                        <small>{{ baggage.type }}</small>
                                                                                    </td>

                                                                                    <td style="font-size: 11px;">
                                                                                        <b v-if="flight.inbound.segments">{{ flight.inbound.segments[0].cabin_class }}</b>
                                                                                        <br>
                                                                                        <small v-if="baggage.max_weight">{{ (baggage.max_weight).includes('Kilograms') ? (baggage.max_weight).replace('Kilograms','Kg') : ''}}/Person</small>
                                                                                        <small v-else="baggage.pieces">{{ flight.inbound.baggage_allowance[0].pieces }} Pcs/Person</small>
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
                                                <div class="row">

                                                    <div class="col-md-4" v-if="flight.brand.length > 0" v-for="fbrand,itere in flight.brand">
                                                        <div :class="`card ${ itere%2==0 ? 'border-eco' : 'border-primium' }`" style="min-height: 460px; ">
                                                            <div class="card-header border-bottom-1">
                                                                <h5 class="card-title text-center" style="font-size: 15px;">{{ fbrand.name }}</h5>
                                                                <h6 class="text-center"> {{ fbrand.price.total }}</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <ul style="list-style-type:none;" class="">
                                                                    <li class="menu-item d-inline-flex" v-for="service in fbrand.services">
                                                                        <i class="fa fa-refresh icon-color mt-1"></i>
                                                                        <span class="ms-1">

                                                                            {{ service.name }}
                                                                        </span>
                                                                    </li>
                                                                    <!-- <li class="menu-item d-inline-flex">
                                                                        <i class="fa fa-refresh icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Flexible to make 2 changes
                                                                        </span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-circle-xmark icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Cancellation within 24hrs
                                                                        </span>

                                                                    </li>

                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-solid fa-suitcase-rolling icon-color mt-1"></i>
                                                                        <span class="ms-1">Baggage: 2 pieces, 23
                                                                            kg each</span>

                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-suitcase icon-color mt-1"></i>
                                                                        <span class="ms-1">Baggage: 2 pieces, 7 kg</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Standard Seat selection
                                                                            included</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Preferred
                                                                            Seat without fees</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa fa-utensils icon-color mt-1"></i>
                                                                        <span class="ms-1">Free meal</span>

                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-brands fa-youtube icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Multimedia
                                                                            Support
                                                                        </span>
                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-wifi icon-color mt-1"></i>
                                                                        <span class="ms-1">Free Wi-Fi</span>

                                                                    </li> -->
                                                                </ul>
                                                                <!-- <button >Book</button> -->
                                                                <router-link class="btn btn-outline-book w-100" :to="{ name: 'bookingCreate' }">Book</router-link>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4" v-else >
                                                        <div class="card border-eco" style="min-height: 480px; ">
                                                            <div class="card-header border-bottom-1">
                                                                <h5 class="card-title text-center" style="font-size: 15px;">Ecomony Class</h5>
                                                                <h6 class="text-center">

                                                                    <span v-for="itemPrice, index in flight.outbound.priceBreakdown">
                                                                        <span v-if="index == 0" >
                                                                            BDT {{
                                                                                ['Adult', 'Child', 'Infant'].reduce((total, type) => {
                                                                                    const breakdown = flight.outbound.priceBreakdown.find(item => item.type === type) || {};
                                                                                    const count = form[type === 'Adult' ? 'ADT' : type === 'Child' ? 'CNN' : 'INF'];
                                                                                    return total + (count * (breakdown.taxes || 0)) + (count * (breakdown.baseFare || 0));
                                                                                }, 0)
                                                                            }}
                                                                        </span>
                                                                </span>
                                                                </h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <ul style="list-style-type:none;" class="">
                                                                    <li class="menu-item d-inline-flex">
                                                                        <i class="fa fa-refresh icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Flexible to make 2 changes
                                                                        </span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-circle-xmark icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Cancellation within 24hrs
                                                                        </span>

                                                                    </li>

                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-solid fa-suitcase-rolling icon-color mt-1"></i>
                                                                        <span class="ms-1">Checked Baggage: 2 pieces, 23
                                                                            kg each</span>

                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-suitcase icon-color mt-1"></i>
                                                                        <span class="ms-1">Checked
                                                                            Baggage: 2 pieces, 7 kg</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Standard Seat selection
                                                                            included</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Preferred
                                                                            Seat without fees</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa fa-utensils icon-color mt-1"></i>
                                                                        <span class="ms-1">Free meal</span>

                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-brands fa-youtube icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Multimedia
                                                                            Support
                                                                        </span>
                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-wifi icon-color mt-1"></i>
                                                                        <span class="ms-1">Free Wi-Fi</span>

                                                                    </li>
                                                                </ul>
                                                                <!-- <button >Book</button> -->
                                                                <router-link class="btn btn-outline-book w-100" :to="{ name: 'bookingCreate' }">Book</router-link>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- <div class="col-md-4">
                                                        <div class="card border-primium" style="min-height: 480px; ">
                                                            <div class="card-header text-black border-bottom-1">
                                                                <h5 class="card-title text-center">Premium Economy</h5>
                                                                <h6 class="text-center text-black">BDT 70500</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <ul style="list-style-type:none;" class="">
                                                                    <li class="menu-item d-inline-flex">
                                                                        <i class="fa fa-refresh icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Flexible to make 2 changes
                                                                        </span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-circle-xmark icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Cancellation within 24hrs
                                                                        </span>

                                                                    </li>

                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-solid fa-suitcase-rolling icon-color mt-1"></i>
                                                                        <span class="ms-1">Checked Baggage: 2 pieces, 23
                                                                            kg each</span>

                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-suitcase icon-color mt-1"></i>
                                                                        <span class="ms-1">Checked
                                                                            Baggage: 2 pieces, 7 kg</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Standard Seat selection
                                                                            included</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Preferred Seat without fees</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa fa-utensils icon-color mt-1"></i>
                                                                        <span class="ms-1">Free meal</span>

                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-brands fa-youtube icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Multimedia
                                                                            Support
                                                                        </span>
                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-wifi icon-color mt-1"></i>
                                                                        <span class="ms-1">Free
                                                                            Wi-Fi</span>

                                                                    </li>
                                                                </ul>
                                                                <button class="btn btn-outline-book w-100">Book</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="card border-first" style="min-height: 480px; ">
                                                            <div class="card-header text-black border-bottom-1">
                                                                <h5 class="card-title text-center">First Class</h5>
                                                                <h6 class="text-center text-black">BDT 90500</h6>
                                                            </div>
                                                            <div class="card-body">
                                                                <ul style="list-style-type:none;" class="">
                                                                    <li class="menu-item d-inline-flex">
                                                                        <i class="fa fa-refresh icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Flexible to make 2 changes
                                                                        </span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-circle-xmark icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Cancellation within 24hrs
                                                                        </span>

                                                                    </li>

                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-solid fa-suitcase-rolling icon-color mt-1"></i>
                                                                        <span class="ms-1">Checked Baggage: 2 pieces, 23
                                                                            kg each</span>

                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-suitcase icon-color mt-1"></i>
                                                                        <span class="ms-1">Checked
                                                                            Baggage: 2 pieces, 7 kg</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex">
                                                                        <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Standard Seat selection
                                                                            included</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-regular fa-seat-airline icon-color mt-1"></i>
                                                                        <span class="ms-1">Preferred Seat without fees</span>
                                                                    </li>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa fa-utensils icon-color mt-1"></i>
                                                                        <span class="ms-1">Free meal</span>

                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-brands fa-youtube icon-color mt-1"></i>
                                                                        <span class="ms-1">
                                                                            Multimedia
                                                                            Support
                                                                        </span>
                                                                    </li>
                                                                    <br>
                                                                    <li class="menu-item d-inline-flex"> <i
                                                                            class="fa-solid fa-wifi icon-color mt-1"></i>
                                                                        <span class="ms-1">Free
                                                                            Wi-Fi</span>

                                                                    </li>
                                                                </ul>
                                                                <button class="btn btn-outline-book w-100">Book</button>
                                                            </div>
                                                        </div>
                                                    </div> -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- ./end Price Details -->
                </div>

            </div>

            <Transition name="fade">
                <div v-show="loadging" class="loading-container">
                    <div class="loading-plane"></div>
                </div>
            </Transition>

        </div>
        <!-- result panel end -->
    </div>

</template>

<style>
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

.border-eco {
    border-image: linear-gradient(231deg, #16B4A1 8.86%, rgba(46, 194, 210, 0.00) 100.22%) 1;
    border-width: 2px;
    border-style: solid;
}

.border-primium {
    border-image:  linear-gradient(255deg, #3B79F2 6.09%, rgba(128, 163, 255, 0.00) 99.25%) 1;
    border-width: 2px;
    border-style: solid;
}

.icon-color {
    /* color: #875ae9; */
    color: #08a79a;
    font-size: 15px;
    padding-right: 3px;
}

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
