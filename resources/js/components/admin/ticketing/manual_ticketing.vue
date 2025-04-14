<script setup>
import DataTable from "datatables.net-vue3";
import DataBS5 from "datatables.net-bs5";
import axiosInstance from "../../../axiosInstance";
import { data } from "jquery";
import { icons } from "lucide-vue-next";
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../../stores/authStore';
const authStore = useAuthStore();
import { ref, reactive, onMounted, render } from "vue";

function flightDetails() {
    $("#flightDetails").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Flight Details_active.svg');
    $("#travellerInfos").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Traveler_info_Inactive.svg');
    $("#fare_info").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Fare_Info_Inactive.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Review&Confirm_Inactive.svg');


    $("#traveller-details").removeClass("d-none");
    $("#addones-sevice-details").addClass("d-none");
    $("#coupon-offer-details").addClass("d-none");
    $("#review-payment-details").addClass("d-none");

}
function travelerInfo() {
    $("#flightDetails").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Flight Details_Inactive.svg');
    $("#travellerInfos").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Traveler_info_active.svg');
    $("#fare_info").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Fare_Info_Inactive.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Review&Confirm_Inactive.svg');


    $("#addones-sevice-details").removeClass("d-none");
    $("#traveller-details").addClass("d-none");
    $("#coupon-offer-details").addClass("d-none");
    $("#review-payment-details").addClass("d-none");

}

function couponOffers() {
    $("#flightDetails").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Flight Details_Inactive.svg');
    $("#travellerInfos").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Traveler_info_Inactive.svg');
    $("#fare_info").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Fare_Info_active.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Review&Confirm_Inactive.svg');


    $("#coupon-offer-details").removeClass("d-none");
    $("#addones-sevice-details").addClass("d-none");
    $("#traveller-details").addClass("d-none");
    $("#review-payment-details").addClass("d-none");

}

function reviewPayment() {
    $("#flightDetails").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Flight Details_Inactive.svg');
    $("#travellerInfos").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Traveler_info_Inactive.svg');
    $("#fare_info").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Fare_Info_Inactive.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Manual_Ticketing/Review&Confirm_active.svg');

    $("#review-payment-details").removeClass("d-none");
    $("#coupon-offer-details").addClass("d-none");
    $("#addones-sevice-details").addClass("d-none");
    $("#traveller-details").addClass("d-none");
}
function addSegment() {
    const segment = document.querySelector('.segment');
    const newSegment = segment.cloneNode(true);
    segment.parentNode.insertBefore(newSegment, segment.nextSibling);
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
                    <!-- <li class="breadcrumb-item" aria-current="page">
                        <router-link :to="{ name: 'bookingList' }">Flight Management</router-link>
                    </li> -->
                    <li class="breadcrumb-item active" aria-current="page">

                        <router-link :to="{ name: 'bookingList' }">Booking & Ticketing List</router-link>

                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Manual Ticketing
                    </li>

                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <div class="btn-group">
                <router-link :to="{ name: 'CreateAgency' }" class="btn btn-outline-primary btn-sm pt-2">
                    <i class="fa fa-file-import"></i> Import PNR
                </router-link>
                &nbsp;
                <router-link :to="{ name: 'manualticketing' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i> Manual Ticketing
                </router-link>
            </div>
        </div>
    </div>

    <div class="row position-relative mt-4">
        <div class="col-12 col-md-12 com-sm-12">
            <div class="card m-0">
                <div class="row shadow-none rounded rounded-2 p-3 pb-0">
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex flex-row bd-highlight mb-3">
                                    <div class="bd-highlight">
                                        <img style="cursor:pointer" @click="flightDetails" id="flightDetails"
                                            src="../../../../../public/theme/Manual_Ticketing/Flight Details_active.svg"
                                            alt="">
                                    </div>
                                    <div @click="travelerInfo" class="bd-highlight">
                                        <img style="cursor:pointer" class="travellerInfos-inactive" id="travellerInfos"
                                            src="../../../../../public/theme/Manual_Ticketing/Traveler_info_Inactive.svg"
                                            alt="">
                                    </div>
                                    <div @click="couponOffers" class="bd-highlight">
                                        <img style="cursor:pointer" class="fare_info-inactive" id="fare_info"
                                            src="../../../../../public/theme/Manual_Ticketing/Fare_Info_Inactive.svg"
                                            alt="">
                                    </div>
                                    <div @click="reviewPayment" class="bd-highlight">
                                        <img style="cursor:pointer" class="review-payment-inactive" id="review-payment"
                                            src="../../../../../public/theme/Manual_Ticketing/Review&Confirm_Inactive.svg"
                                            alt="">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <!-- flight details -->
                                <div class="card fadeIn" id="traveller-details">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-center gap-3">
                                                    <span class="text-bold"><b>Way Type</b></span>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio"
                                                            name="flexRadioDefault" id="flexRadioDefault1">
                                                        <label class="form-check-label" for="flexRadioDefault1">
                                                            One Way
                                                        </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio"
                                                            name="flexRadioDefault" id="flexRadioSuccess">
                                                        <label class="form-check-label" for="flexRadioSuccess">
                                                            Round Trip
                                                        </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio"
                                                            name="flexRadioDefault" id="flexRadioDanger">
                                                        <label class="form-check-label" for="flexRadioDanger">
                                                            Multi City
                                                        </label>
                                                    </div>

                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <span class="text-bold"><b>Type</b></span>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio" name="type"
                                                            id="type">
                                                        <label class="form-check-label" for="type">
                                                            Booking
                                                        </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio" name="type"
                                                            id="type">
                                                        <label class="form-check-label" for="type">
                                                            Ticketing
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3">

                                            <!-- accordions one -->
                                            <div class="accordion" id="accordionExample">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="headingOne">
                                                        <button class="accordion-button" type="button"
                                                            data-bs-toggle="collapse" data-bs-target="#flight_details"
                                                            aria-expanded="true" aria-controls="flight_details">
                                                            Flight Details
                                                        </button>
                                                    </h2>
                                                    <div id="flight_details" class="accordion-collapse collapse show"
                                                        aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                                        <div class="accordion-body">
                                                            <div class="card segment"
                                                                style="background-color: #f8fcff;">

                                                                <div class="row p-3">
                                                                    <div class="col-md-3">
                                                                        <label for="departure"
                                                                            class="form-label"><b>Departure</b></label>
                                                                        <input type="input" class="form-control "
                                                                            id="departure" name="departure"
                                                                            placeholder="Enter Departure">
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <label for="date_time"
                                                                            class="form-label"><b>Date &
                                                                                Time</b></label>
                                                                        <input type="datetime-local"
                                                                            class="form-control " id="date_time"
                                                                            name="date_time"
                                                                            placeholder="Select Date & Time">
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <label for="arrival"
                                                                            class="form-label"><b>Arrival</b></label>
                                                                        <input type="input" class="form-control "
                                                                            id="arrival" name="arrival"
                                                                            placeholder="Enter Arrival">
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <label for="arrival_date_time"
                                                                            class="form-label"><b>Date &
                                                                                Time</b></label>
                                                                        <input type="datetime-local"
                                                                            class="form-control " id="arrival_date_time"
                                                                            name="arrival_date_time"
                                                                            placeholder="Select Arrival Date & Time">
                                                                    </div>
                                                                    <div class="col-md-3 mt-2">
                                                                        <label for="airline"
                                                                            class="form-label"><b>Airline</b></label>
                                                                        <select class="form-select form-control"
                                                                            id="airline" name="airline">
                                                                            <option value="1">EK</option>
                                                                            <option value="2">B6</option>
                                                                            <option value="3">TK</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-3 mt-2">
                                                                        <label for="class"
                                                                            class="form-label"><b>Class</b></label>
                                                                        <select class="form-select form-control"
                                                                            id="class" name="class">
                                                                            <option value="1">Economy</option>
                                                                            <option value="2">Business</option>
                                                                            <option value="3">First</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-3 mt-2">
                                                                        <label for="flight_no"
                                                                            class="form-label"><b>Flight
                                                                                Number</b></label>
                                                                        <input type="input" class="form-control "
                                                                            id="flight_no" name="flight_no"
                                                                            placeholder="Enter Flight Number">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="d-flex mt-3 justify-content-center">
                                                                <button
                                                                    class="w3-button w3-blue-sky-purple w3-round w3-medium add-more"
                                                                    @click="addSegment"> <i class="fa fa-plus"></i>Add
                                                                    New Segment</button>
                                                            </div>


                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- accordions one -->
                                        </div>

                                        <div class="mt-3">

                                            <!-- accordions two -->
                                            <div class="accordion" id="info">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="info">
                                                        <button class="accordion-button" type="button"
                                                            data-bs-toggle="collapse" data-bs-target="#general_info"
                                                            aria-expanded="true" aria-controls="general_info">
                                                            General Info
                                                        </button>
                                                    </h2>
                                                    <div id="general_info" class="accordion-collapse collapse show"
                                                        aria-labelledby="info" data-bs-parent="#info">
                                                        <div class="accordion-body">


                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <label for="gds_pnr" class="form-label"><b>GDS
                                                                            PNR</b></label>
                                                                    <input type="input" class="form-control "
                                                                        id="gds_pnr" name="gds_pnr"
                                                                        placeholder="Enter PNR">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label for="date_time" class="form-label"><b>Issue
                                                                            Date</b></label>
                                                                    <input type="datetime-local" class="form-control "
                                                                        id="date_time" name="date_time"
                                                                        placeholder="Select Date & Time">
                                                                </div>

                                                                <div class="col-md-6 mt-2">
                                                                    <label for="class"
                                                                        class="form-label"><b>Agency</b></label>
                                                                    <select class="form-select form-control" id="class"
                                                                        name="class">
                                                                        <option value="1">Agent 1</option>
                                                                        <option value="2">Agent 2</option>
                                                                    </select>
                                                                </div>

                                                                <div class="col-md-2 mt-2">
                                                                    <label for="class"
                                                                        class="form-label"><b>Adult</b></label>
                                                                    <select class="form-select form-control" id="class"
                                                                        name="class">
                                                                        <option value="1">1</option>
                                                                        <option value="2">2</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-2 mt-2">
                                                                    <label for="class"
                                                                        class="form-label"><b>Child</b></label>
                                                                    <select class="form-select form-control" id="class"
                                                                        name="class">
                                                                        <option value="1">1</option>
                                                                        <option value="2">2</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-2 mt-2">
                                                                    <label for="class"
                                                                        class="form-label"><b>Infant</b></label>
                                                                    <select class="form-select form-control" id="class"
                                                                        name="class">
                                                                        <option value="1">1</option>
                                                                        <option value="2">2</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- accordions two -->
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-block">
                                            <!-- <button @click="addAddOnServices()" class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button> -->
                                            <button type="button" @click="travelerInfo"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Continue</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end of flight details -->


                                <!-- traveler informations -->
                                <div class="card d-none fadeIn" id="addones-sevice-details">
                                    <div class="card-body">
                                        <div class="accordion" id="accordionExample">
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="headingOne">
                                                    <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#collapseOne"
                                                        aria-expanded="false" aria-controls="collapseOne">

                                                        <img src="../../../../../public/theme/Booking_Steps/traveller_icon.svg"
                                                            alt="">
                                                        <span class="pt-1 ps-1">Traveller 1: Adult</span>

                                                        <div style="margin-left: 20px;"
                                                            class="badge rounded-pill text-success bg-light-success p-1 px-4">
                                                            Primary Contact</div>
                                                    </button>
                                                </h2>
                                                <div id="collapseOne" class="accordion-collapse collapse"
                                                    aria-labelledby="headingOne" data-bs-parent="#accordionExample"
                                                    style="">
                                                    <div class="accordion-body"
                                                        style="background-color: rgba(248, 252, 255, 1);">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="mt-2 mb-0 p-2"
                                                                    style="font-size: 13px !important; background-color: rgba(255, 250, 238, 1); border-radius: 5px;">
                                                                    <span
                                                                        class="bluesky-departure-text mobile-chips-text">
                                                                        <i style="color: rgba(240, 180, 27, 1);"
                                                                            class="fa fa-info-circle"></i>
                                                                        <span
                                                                            style="font-size: 12px; color: rgba(119, 95, 35, 1); ">
                                                                            Please fill-up all the information below as
                                                                            same as given in your passport, to avoid
                                                                            complications at immigration
                                                                            proccess.</span>
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-lg-12 mt-3">
                                                                <label for="name" class="form-label">Existing
                                                                    Traveller</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name"
                                                                    placeholder="Search with name, phone, email, password">
                                                            </div>

                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="text-center"
                                                                    style="color: rgba(161, 171, 183, 1);font-size: 10px;">
                                                                    Or fill up the information below</div>
                                                            </div>
                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="row bd-highlight mb-3">
                                                                    <div class="col-md-2 bd-highlight pe-3">
                                                                        <label for="name"
                                                                            class="form-label">Title</label>
                                                                        <select
                                                                            class="form-select form-control form-select-sm">

                                                                            <option value="1">Mr.</option>
                                                                            <option value="1">Miss.</option>
                                                                            <option value="2">Mrs.</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-4  pe-3">
                                                                        <label for="name" class="form-label">First Name
                                                                            (Given Name)</label>
                                                                        <input type="text" class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter First Name">
                                                                    </div>
                                                                    <div class="col-md-6  pe-3">
                                                                        <label for="name" class="form-label">Last Name
                                                                            (Sur Name)</label>
                                                                        <input type="text" class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter Last Name">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Date of
                                                                    Birth</label>
                                                                <input type="date" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Gender</label>
                                                                <select class="form-select form-control form-select-sm">

                                                                    <option value="1">Male</option>
                                                                    <option value="2">Female</option>
                                                                    <option value="3">Others</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Nationality</label>
                                                                <select class="form-select form-select-sm">
                                                                    <option value="1">Bangladeshi</option>
                                                                    <option value="2">American</option>
                                                                    <option value="3">Pakistani</option>
                                                                    <option value="4">Indian</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Frequent Flyer
                                                                    Number</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Flyer Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport
                                                                    Number</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Passport Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Expiry Date</label>
                                                                <input type="date" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3 input-group-sm">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Visa Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3 input-group-sm">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <hr>

                                                            <!-- part 2 -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Email</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Email">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Phone</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Phone">
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Meal Type</label>
                                                                <select class="form-select form-select-sm">
                                                                    <option value="">Choose One...</option>
                                                                    <option value="1">Veg</option>
                                                                    <option value="2">Non Veg</option>
                                                                </select>
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Wheel Chair Needed
                                                                    ?</label>
                                                                <select class="form-select form-select-sm">
                                                                    <option value="">Choose One...</option>
                                                                    <option value="1">Yes</option>
                                                                    <option value="2">No</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox"
                                                                        value="" id="flexCheckChecked" checked="">
                                                                    <label class="form-check-label"
                                                                        for="flexCheckChecked">Select as Primary
                                                                        Contact</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="headingTwo">
                                                    <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#collapseTwo"
                                                        aria-expanded="false" aria-controls="collapseTwo">
                                                        <img src="../../../../../public/theme/Booking_Steps/traveller_icon.svg"
                                                            alt="">
                                                        <span class="pt-1 ps-1">Traveller 2: Adult</span>
                                                    </button>
                                                </h2>
                                                <div id="collapseTwo" class="accordion-collapse collapse"
                                                    aria-labelledby="headingTwo" data-bs-parent="#accordionExample">
                                                    <div class="accordion-body"
                                                        style="background-color: rgba(248, 252, 255, 1);">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="mt-2 mb-0 p-2"
                                                                    style="font-size: 13px !important; background-color: rgba(255, 250, 238, 1); border-radius: 5px;">
                                                                    <span
                                                                        class="bluesky-departure-text mobile-chips-text">
                                                                        <i style="color: rgba(240, 180, 27, 1);"
                                                                            class="fa fa-info-circle"></i>
                                                                        <span
                                                                            style="font-size: 12px; color: rgba(119, 95, 35, 1); ">
                                                                            Please fill-up all the information below as
                                                                            same as given in your passport, to avoid
                                                                            complications at immigration
                                                                            proccess.</span>
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-lg-12 mt-3">
                                                                <label for="name" class="form-label">Existing
                                                                    Traveller</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name"
                                                                    placeholder="Search with name, phone, email, password">
                                                            </div>

                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="text-center"
                                                                    style="color: rgba(161, 171, 183, 1);font-size: 10px;">
                                                                    Or fill up the information below</div>
                                                            </div>
                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="row bd-highlight mb-3">
                                                                    <div class="col-md-2 bd-highlight pe-3">
                                                                        <label for="name"
                                                                            class="form-label">Title</label>
                                                                        <select
                                                                            class="form-select form-control form-select-sm">

                                                                            <option value="1">Mr.</option>
                                                                            <option value="1">Miss.</option>
                                                                            <option value="2">Mrs.</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-4  pe-3">
                                                                        <label for="name" class="form-label">First Name
                                                                            (Given Name)</label>
                                                                        <input type="text" class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter First Name">
                                                                    </div>
                                                                    <div class="col-md-6  pe-3">
                                                                        <label for="name" class="form-label">Last Name
                                                                            (Sur Name)</label>
                                                                        <input type="text" class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter Last Name">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Date of
                                                                    Birth</label>
                                                                <input type="date" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Gender</label>
                                                                <select class="form-select form-control form-select-sm">

                                                                    <option value="1">Male</option>
                                                                    <option value="2">Female</option>
                                                                    <option value="3">Others</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Nationality</label>
                                                                <select class="form-select form-select-sm">

                                                                    <option value="1">Bangladeshi</option>
                                                                    <option value="2">American</option>
                                                                    <option value="3">Pakistani</option>
                                                                    <option value="4">Indian</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Frequent Flyer
                                                                    Number</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Flyer Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport
                                                                    Number</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Passport Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Expiry Date</label>
                                                                <input type="date" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3 input-group-sm">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Visa Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3 input-group-sm">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <hr>

                                                            <!-- part 2 -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Email</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Email">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Phone</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Phone">
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Meal Type</label>
                                                                <select class="form-select form-select-sm">
                                                                    <option value="">Choose One...</option>
                                                                    <option value="1">Veg</option>
                                                                    <option value="2">Non Veg</option>
                                                                </select>
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Wheel Chair Needed
                                                                    ?</label>
                                                                <select class="form-select form-select-sm">
                                                                    <option value="">Choose One...</option>
                                                                    <option value="1">Yes</option>
                                                                    <option value="2">No</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox"
                                                                        value="" id="flexCheckChecked">
                                                                    <label class="form-check-label"
                                                                        for="flexCheckChecked">Select as Primary
                                                                        Contact</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="headingThree">
                                                    <button class="accordion-button collapsed" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#collapseThree"
                                                        aria-expanded="false" aria-controls="collapseThree">
                                                        <i class="fa-solid fa-child-reaching"
                                                            style="color: #7239ea;"></i>
                                                        <span class="pt-1 ps-1">Traveller 3: Children</span>
                                                    </button>
                                                </h2>
                                                <div id="collapseThree" class="accordion-collapse collapse"
                                                    aria-labelledby="headingThree" data-bs-parent="#accordionExample">
                                                    <div class="accordion-body"
                                                        style="background-color: rgba(248, 252, 255, 1);">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="mt-2 mb-0 p-2"
                                                                    style="font-size: 13px !important; background-color: rgba(255, 250, 238, 1); border-radius: 5px;">
                                                                    <span
                                                                        class="bluesky-departure-text mobile-chips-text">
                                                                        <i style="color: rgba(240, 180, 27, 1);"
                                                                            class="fa fa-info-circle"></i>
                                                                        <span
                                                                            style="font-size: 12px; color: rgba(119, 95, 35, 1); ">
                                                                            Please fill-up all the information below as
                                                                            same as given in your passport, to avoid
                                                                            complications at immigration
                                                                            proccess.</span>
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-lg-12 mt-3">
                                                                <label for="name" class="form-label">Existing
                                                                    Traveller</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name"
                                                                    placeholder="Search with name, phone, email, password">
                                                            </div>

                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="text-center"
                                                                    style="color: rgba(161, 171, 183, 1);font-size: 10px;">
                                                                    Or fill up the information below</div>
                                                            </div>
                                                            <div class="col-12 col-sm-12 col-md-12 mt-3">
                                                                <div class="row bd-highlight mb-3">
                                                                    <div class="col-md-2 bd-highlight pe-3">
                                                                        <label for="name"
                                                                            class="form-label">Title</label>
                                                                        <select
                                                                            class="form-select form-control form-select-sm">

                                                                            <option value="1">Mr.</option>
                                                                            <option value="1">Miss.</option>
                                                                            <option value="2">Mrs.</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-4  pe-3">
                                                                        <label for="name" class="form-label">First Name
                                                                            (Given Name)</label>
                                                                        <input type="text" class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter First Name">
                                                                    </div>
                                                                    <div class="col-md-6  pe-3">
                                                                        <label for="name" class="form-label">Last Name
                                                                            (Sur Name)</label>
                                                                        <input type="text" class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter Last Name">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-5 col-sm-5 col-md-5 mt-2">
                                                                <label for="name" class="form-label">Date of
                                                                    Birth</label>
                                                                <input type="date" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-1 col-sm-1 col-md-1 mt-2">
                                                                <label for="name" class="form-label">Age</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Age">
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Gender</label>
                                                                <select class="form-select form-control form-select-sm">

                                                                    <option value="1">Male</option>
                                                                    <option value="2">Female</option>
                                                                    <option value="3">Others</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Nationality</label>
                                                                <select class="form-select form-select-sm">

                                                                    <option value="1">Bangladeshi</option>
                                                                    <option value="2">American</option>
                                                                    <option value="3">Pakistani</option>
                                                                    <option value="4">Indian</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Frequent Flyer
                                                                    Number</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Flyer Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport
                                                                    Number</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Passport Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Expiry Date</label>
                                                                <input type="date" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3 input-group-sm">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Visa Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3 input-group-sm">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <hr>

                                                            <!-- part 2 -->
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Email</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Email">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Phone</label>
                                                                <input type="text" class="form-control " id="name"
                                                                    name="name" placeholder="Enter Phone">
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Meal Type</label>
                                                                <select class="form-select form-select-sm">
                                                                    <option value="">Choose One...</option>
                                                                    <option value="1">Veg</option>
                                                                    <option value="2">Non Veg</option>
                                                                </select>
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Wheel Chair Needed
                                                                    ?</label>
                                                                <select class="form-select form-select-sm">
                                                                    <option value="">Choose One...</option>
                                                                    <option value="1">Yes</option>
                                                                    <option value="2">No</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-block">
                                            <button @click="flightDetails"
                                                class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button>
                                            <button type="button" @click="couponOffers"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Continue</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end of traveler informations -->

                                <!-- coupon offers -->
                                <div class="card d-none fadeIn" id="coupon-offer-details">
                                    <div class="card-header">
                                        <span style="font-size: 16px;">
                                            Fare & payment
                                        </span>
                                    </div>
                                    <div class="card-body" style="min-height: 280px;">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="ribbon">Adult</div>
                                                <div class="card ribbon-c">
                                                    <div class="card-body">
                                                        <!-- Passenger Input Group -->
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control"
                                                                placeholder="Base Fare" aria-label="Base Fare">

                                                        </div>
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control" placeholder="TAX"
                                                                aria-label="TAX">

                                                        </div>
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control" placeholder="AIT"
                                                                aria-label="AIT">

                                                        </div>
                                                        <span class="ps-1">Total : </span>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="ribbon">Child</div>
                                                <div class="card ribbon-c">
                                                    <div class="card-body">
                                                        <!-- Input Group -->
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control"
                                                                placeholder="Base Fare" aria-label="Base Fare">

                                                        </div>
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control" placeholder="TAX"
                                                                aria-label="TAX">

                                                        </div>
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control" placeholder="AIT"
                                                                aria-label="AIT">

                                                        </div>
                                                        <span class="ps-1">Total : </span>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="ribbon">Infant</div>
                                                <div class="card ribbon-c">
                                                    <div class="card-body">
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control"
                                                                placeholder="Base Fare" aria-label="Base Fare">

                                                        </div>
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control" placeholder="TAX"
                                                                aria-label="TAX">

                                                        </div>
                                                        <div class="input-group mb-3 input-group-sm">
                                                            <span class="input-group-text passenger-count"
                                                                id="passenger-addon">
                                                                <span class="me-2">2x</span>
                                                                <i class="bi bi-person-fill"></i>
                                                            </span>
                                                            <input type="text" class="form-control" placeholder="AIT"
                                                                aria-label="AIT">

                                                        </div>

                                                        <span class="ps-1">Total : </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6 mt-3">
                                                <label for="name" class="form-label"><b>Service Charge (+)</b></label>
                                                <input type="text" class="form-control " id="name" name="name"
                                                    placeholder="Enter Service Charge ">
                                            </div>
                                            <div class="col-md-6 mt-3">
                                                <label for="name" class="form-label"><b>Total</b></label>
                                                <input type="text" class="form-control " id="total" name="total"
                                                    placeholder="Total" readonly>
                                            </div>
                                            <div class="col-md-6 mt-3">
                                                <label for="name" class="form-label"><b>Discount</b></label>
                                                <div class="input-group">

                                                    <input type="text" class="form-control " id="discount"
                                                        name="discount" placeholder="Total" readonly>
                                                    <select class="form-select" id="total-type">
                                                        <option value="percentage">(%) Percentage</option>
                                                        <option value="flat">Flat</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mt-3">
                                                <label for="name" class="form-label"><b>Total Payable</b></label>
                                                <input type="text" class="form-control " id="total_paybable"
                                                    name="total_paybable" placeholder="Total Paybable" readonly>
                                            </div>
                                            <div class="col-md-8 mt-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <span class="text-bold"><b>Refund Status</b></span>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio"
                                                            name="refund_status" id="type">
                                                        <label class="form-check-label" for="type">
                                                            Non-refundable
                                                        </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio"
                                                            name="refund_status" id="type">
                                                        <label class="form-check-label" for="type">
                                                            Partial Refundable
                                                        </label>
                                                    </div>
                                                    <div class="form-check pt-1">
                                                        <input class="form-check-input" type="radio"
                                                            name="refund_status" id="type">
                                                        <label class="form-check-label" for="type">
                                                            Refundable
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-block">
                                            <button @click="couponOffers"
                                                class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button>
                                            <button type="button" @click="reviewPayment"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Continue</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end coupon offers -->

                                <!--review payment details -->
                                <div class="card d-none fadeIn" id="review-payment-details">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="card">

                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <div class="d-flex ">
                                                                        <img src="../../../../../public/uploads/agents/agency_img/02022025-1738483880.png"
                                                                            height="60" width="60"
                                                                            class="border border-1 rounded rounded-2"
                                                                            alt="Profile Picture">
                                                                    </div>
                                                                    <div class="mt-2">
                                                                        <p class="text-secondary">Agency: ABC Travels
                                                                            Ltd.</p>
                                                                        <p class="text-secondary">GDS PNR: GHTR78</p>
                                                                        <p class="text-secondary">Issue
                                                                            Date:`03-July-2025</p>
                                                                        <p class="text-secondary">Adult: 02 | Child: 01
                                                                            | Infant: 01</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-9">
                                                            <div class="card">
                                                                <div class="card-header">
                                                                    <h5 class="m-0 p-0"
                                                                        style="border-left:5px solid #7239ea;"> &nbsp;
                                                                        Payment Information </h5>
                                                                </div>
                                                                <div class="card-body">
                                                                    <table class="table table-border table-sm table-responsive">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Departure:</b></label>
                                                                                    <p class="m-0">DHAKA | 20-Feb-2025,14:05</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Arrival:</b></label>
                                                                                    <p class="m-0">DHAKA | 20-March-2025,14:05</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Airline:</b></label>
                                                                                    <p class="m-0">Qatar Airways</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Class:
                                                                                        </b></label>
                                                                                    <p class="m-0">Economy(Y)</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Flight
                                                                                            No:</b></label>
                                                                                    <p class="m-0">QR8567</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">

                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>


                                                                    <h5 class="m-0 p-0"> <i class="fa fa-user" style="color:#7239ea;"></i>
                                                                       Traveler 1: Adult  </h5>
                                                                       <table class="table table-border table-sm table-responsive mt-2">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Full Name:</b></label>
                                                                                    <p class="m-0">Mr. Md. Abu Syed</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Gender:</b></label>
                                                                                    <p class="m-0">Male</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Nationality:</b></label>
                                                                                    <p class="m-0">Bangladeshi</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Date of Birth:
                                                                                        </b></label>
                                                                                    <p class="m-0">02-Jan-1993</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Phone:</b></label>
                                                                                    <p class="m-0">+88010000000</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Email:</b></label>
                                                                                    <p class="m-0">syed@info.com</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Passport No:</b></label>
                                                                                    <p class="m-0">BD64585152GTY89</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Expiry Date:</b></label>
                                                                                    <p class="m-0">22-May-2028</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Airline PNR:</b></label>
                                                                                    <p class="m-0">QWR45T</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>GDS PNR:</b></label>
                                                                                    <p class="m-0">FCC56H</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Ticket No:</b></label>
                                                                                    <p class="m-0">03587566</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Check-in Baggage:</b></label>
                                                                                    <p class="m-0">2 Piece</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Cabin Baggage:</b></label>
                                                                                    <p class="m-0">2KG</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Meal Type:</b></label>
                                                                                    <p class="m-0">Vegitarian</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Wheel Chair:</b></label>
                                                                                    <p class="m-0">Yes</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">

                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>

                                                                    <h5 class="m-0 p-0"> <i class="fa-solid fa-child-reaching" style="color:#7239ea;"></i>
                                                                       Traveler 2: Child  </h5>
                                                                       <table class="table table-border table-sm table-responsive mt-2">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Full Name:</b></label>
                                                                                    <p class="m-0">Mr. Md. Abu Syed</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Gender:</b></label>
                                                                                    <p class="m-0">Male</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Nationality:</b></label>
                                                                                    <p class="m-0">Bangladeshi</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%"><label
                                                                                        for=""><b>Date of Birth:
                                                                                        </b></label>
                                                                                    <p class="m-0">02-Jan-1993</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Phone:</b></label>
                                                                                    <p class="m-0">+88010000000</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Email:</b></label>
                                                                                    <p class="m-0">syed@info.com</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Passport No:</b></label>
                                                                                    <p class="m-0">BD64585152GTY89</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Expiry Date:</b></label>
                                                                                    <p class="m-0">22-May-2028</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Airline PNR:</b></label>
                                                                                    <p class="m-0">QWR45T</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>GDS PNR:</b></label>
                                                                                    <p class="m-0">FCC56H</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Ticket No:</b></label>
                                                                                    <p class="m-0">03587566</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Check-in Baggage:</b></label>
                                                                                    <p class="m-0">2 Piece</p>
                                                                                </td>
                                                                            </tr>

                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Cabin Baggage:</b></label>
                                                                                    <p class="m-0">2KG</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Meal Type:</b></label>
                                                                                    <p class="m-0">Vegitarian</p>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="m-0 pl-2" width="50%">
                                                                                    <label for=""><b>Wheel Chair:</b></label>
                                                                                    <p class="m-0">Yes</p>
                                                                                </td>
                                                                                <td class="m-0 pl-2" width="50%">

                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>

                                                                    <h5 class="m-0 p-0"
                                                                        style="border-left:5px solid #7239ea;"> &nbsp;
                                                                        Fare Info</h5>
                                                                        <table class="table table-border table-sm table-responsive mt-2 ">
                                                                            <tbody>
                                                                                <tr>
                                                                                    <td class="m-0 pl-2" width="50%"><label
                                                                                            for=""><b>Adult <i class="fa fa-info-circle" style="color: #7239ea;"></i></b></label>

                                                                                    </td>
                                                                                    <td class="m-0 pl-2" width="50%">
                                                                                        <p class="m-0 text-center"> <b>BDT 123321</b></p>
                                                                                    </td>
                                                                                </tr>

                                                                                <tr>
                                                                                    <td class="m-0 pl-2" width="50%"><label
                                                                                            for=""><b>Child <i class="fa fa-info-circle" style="color: #7239ea;"></i></b></label>

                                                                                    </td>
                                                                                    <td class="m-0 pl-2" width="50%">
                                                                                        <p class="m-0 text-center"> <b>BDT 45788</b></p>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td class="m-0 pl-2" width="50%"><label
                                                                                            for=""><b>Infant <i class="fa fa-info-circle" style="color: #7239ea;"></i></b></label>

                                                                                    </td>
                                                                                    <td class="m-0 pl-2" width="50%">
                                                                                        <p class="m-0 text-center"> <b>BDT 33234</b></p>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td class="m-0 pl-2" width="50%"><label
                                                                                            for=""><b>Service Charge </b></label>

                                                                                    </td>
                                                                                    <td class="m-0 pl-2" width="50%">
                                                                                        <p class="m-0 text-center"> <b>BDT 3000</b></p>
                                                                                    </td>
                                                                                </tr>

                                                                                <tr>
                                                                                    <td class="m-0 pl-2" width="50%"><label
                                                                                            for=""><b>Sub Total </b></label>

                                                                                    </td>
                                                                                    <td class="m-0 pl-2" width="50%">
                                                                                        <p class="m-0 text-center"> <b>BDT 205645</b></p>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td class="m-0 pl-2 text-danger" width="50%"><label
                                                                                            for=""><b>Discount </b></label>

                                                                                    </td>
                                                                                    <td class="m-0 pl-2 text-danger" width="50%">
                                                                                        <p class="m-0 text-center"> <b>- 10%</b></p>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td class="m-0 pl-2 " width="50%"><label
                                                                                            for=""><b>Total Payable </b></label>

                                                                                    </td>
                                                                                    <td class="m-0 pl-2 " width="50%">
                                                                                        <p class="m-0 text-center"> <b> BDT 185081</b></p>
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

                                    <div class="card-footer">
                                        <div class="d-block">
                                            <button @click="couponOffers"
                                                class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button>
                                            <button type="button"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Create
                                                </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end review payment details -->
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.booking-top-card-text {
    color: rgba(94, 104, 120, 1);
}

.travellerInfos-inactive {
    position: absolute;
    left: 210px;
    top: 16px;
    z-index: 16;
}

.fare_info-inactive {
    position: absolute;
    left: 430px;
    top: 16px;
    z-index: 16;
}

.review-payment-inactive {
    position: absolute;
    left: 648px;
    top: 16px;
    z-index: 16;
}

.w3-blue-sky-purple {
    color: #fff !important;
    background-color: #7239ea;
}

.card .ribbon-c {
    position: relative;
    overflow: hidden;
    top: 18px;
}

.ribbon {
    position: absolute;
    background: #7239ea;
    color: white;
    padding: 5px 30px;
    font-size: 10px;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    border-radius: 4px;
    z-index: 100;
}
</style>
