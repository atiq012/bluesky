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

function travelerDetails() {
    $("#addones-service").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Addons_Services_Inactive.svg');
    $("#travelerDetails").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Traveler_Details.svg');
    $("#coupon-offers").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Coupon_Offers_Inactive.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Review_Payment_Inactive.svg');

    $("#traveller-details").removeClass("d-none");
    $("#addones-sevice-details").addClass("d-none");
    $("#coupon-offer-details").addClass("d-none");
    $("#review-payment-details").addClass("d-none");

}
function addonesSevice() {
    $("#addones-service").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Addons_Service.svg');
    $("#travelerDetails").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Traveler_Details_Inactive.svg');
    $("#coupon-offers").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Coupon_Offers_Inactive.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Review_Payment_Inactive.svg');

    $("#addones-sevice-details").removeClass("d-none");
    $("#traveller-details").addClass("d-none");
    $("#coupon-offer-details").addClass("d-none");
    $("#review-payment-details").addClass("d-none");

}

function couponOffers() {
    $("#addones-service").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Addons_Services_Inactive.svg');
    $("#coupon-offers").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Coupon_Offers.svg');
    $("#travelerDetails").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Traveler_Details_Inactive.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Review_Payment_Inactive.svg');

    $("#coupon-offer-details").removeClass("d-none");
    $("#addones-sevice-details").addClass("d-none");
    $("#traveller-details").addClass("d-none");
    $("#review-payment-details").addClass("d-none");

}

function reviewPayment() {
    $("#travelerDetails").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Traveler_Details_Inactive.svg');
    $("#addones-service").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Addons_Services_Inactive.svg');
    $("#coupon-offers").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Coupon_Offers_Inactive.svg');
    $("#review-payment").attr('src', 'http://[::1]:5173/public/theme/Booking_Steps/Review_Payment.svg');

    $("#review-payment-details").removeClass("d-none");
    $("#coupon-offer-details").addClass("d-none");
    $("#addones-sevice-details").addClass("d-none");
    $("#traveller-details").addClass("d-none");
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
            <div class="card m-0">
                <div class="row shadow-none rounded rounded-2 p-3 pb-0">
                    <div class="col-md-7">
                        <div class="d-flex flex-column bd-highlight">
                            <!-- part 1 -->
                            <div class="d-flex flex-row bd-highlight mb-2">
                                <div class="bd-highlight pe-1">
                                    <b><span style="font-size: 15px;">DAC-DXB</span></b>
                                </div>
                                <span style="margin-top: 1px;"><b>|</b></span>
                                <div class="bd-highlight ps-1">
                                    <b><span style="font-size: 15px;">DXB-DAC</span></b>
                                </div>
                            </div>
                            <!-- end of part 1 -->

                            <!-- part 2 -->
                            <div class="d-flex flex-row bd-highlight">
                                <div class="bd-highlight pe-1">
                                    <i class="fa fa-calendar-alt" style="color: #bfc3c9;"></i>
                                    <span class="booking-top-card-text">

                                        Departure: 20 June,2025 - Arrival: 20 July,2025
                                    </span>
                                </div>

                                <span>|</span>

                                <div class="bd-highlight ps-1">
                                    <i class="fa-solid fa-plane-up" style="color: #bfc3c9;"></i>
                                    <span class="booking-top-card-text">
                                        Round Way
                                    </span>

                                </div>

                                <span>|</span>

                                <div class="bd-highlight ps-1">
                                    <i class="fa-solid fa-seat-airline" style="color: #bfc3c9;"></i>
                                    <span class="booking-top-card-text">
                                        Economy
                                    </span>
                                </div>

                                <span>|</span>
                                <div class="bd-highlight ps-1">
                                    <i class="fa fa-user" style="color: #bfc3c9;"></i>
                                    <span class="booking-top-card-text">
                                        4 Travellers
                                    </span>
                                </div>
                            </div>
                            <!-- end of part 2 -->
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="d-flex flex-row bd-highlight">
                            <div class="pe-1 bd-highlight w-100">
                                <div class="card">
                                    <div class="card-body" style="background-color: #f6f2ff;">
                                        <div
                                            class="d-flex flex-row bd-highlight justify-content-center align-items-center">
                                            <div class="pe-1">Time Remaining</div>
                                            <div class="ps-1 bluesky-departure-text"><b>30 : 00</b></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="ps-1 bd-highlight w-75">
                                <div class="card">
                                    <div class="card-body" style="background-color: #f0f7fe;">
                                        <div
                                            class="d-flex flex-row bd-highlight justify-content-center align-items-center">
                                            <div class="pe-1 text-primary">Flight Details</div>
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

    <div class="row position-relative mt-4">
        <div class="col-12 col-md-12 com-sm-12">
            <div class="card m-0">
                <div class="row shadow-none rounded rounded-2 p-3 pb-0">
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex flex-row bd-highlight mb-3">
                                    <div class="bd-highlight">
                                        <img style="cursor:pointer" @click="travelerDetails" id="travelerDetails"
                                            src="../../../../../public/theme/Booking_Steps/Traveler_Details.svg" alt="">
                                    </div>
                                    <div @click="addonesSevice" class="bd-highlight">
                                        <img style="cursor:pointer" class="addones-service-inactive"
                                            id="addones-service"
                                            src="../../../../../public/theme/Booking_Steps/Addons_Services_Inactive.svg"
                                            alt="">
                                    </div>
                                    <div @click="couponOffers" class="bd-highlight">
                                        <img style="cursor:pointer" class="coupon-offers-inactive" id="coupon-offers"
                                            src="../../../../../public/theme/Booking_Steps/Coupon_Offers_Inactive.svg"
                                            alt="">
                                    </div>
                                    <div @click="reviewPayment" class="bd-highlight">
                                        <img style="cursor:pointer" class="review-payment-inactive" id="review-payment"
                                            src="../../../../../public/theme/Booking_Steps/Review_Payment_Inactive.svg"
                                            alt="">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <!-- traveller details -->
                                <div class="card fadeIn" id="traveller-details">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
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
                                                                        <input type="text"
                                                                            class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter First Name">
                                                                    </div>
                                                                    <div class="col-md-6  pe-3">
                                                                        <label for="name" class="form-label">Last Name
                                                                            (Sur Name)</label>
                                                                        <input type="text"
                                                                            class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter Last Name">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Date of
                                                                    Birth</label>
                                                                <input type="date" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Date of Birth">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Flyer Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport
                                                                    Number</label>
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Passport Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Expiry Date</label>
                                                                <input type="date" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Visa Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name" placeholder="Enter Email">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Phone</label>
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name" placeholder="Enter Phone">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
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
                                                                        <input type="text"
                                                                            class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter First Name">
                                                                    </div>
                                                                    <div class="col-md-6  pe-3">
                                                                        <label for="name" class="form-label">Last Name
                                                                            (Sur Name)</label>
                                                                        <input type="text"
                                                                            class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter Last Name">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Date of
                                                                    Birth</label>
                                                                <input type="date" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Date of Birth">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Flyer Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport
                                                                    Number</label>
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Passport Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Expiry Date</label>
                                                                <input type="date" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Visa Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name" placeholder="Enter Email">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Phone</label>
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name" placeholder="Enter Phone">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
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
                                                                        <input type="text"
                                                                            class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter First Name">
                                                                    </div>
                                                                    <div class="col-md-6  pe-3">
                                                                        <label for="name" class="form-label">Last Name
                                                                            (Sur Name)</label>
                                                                        <input type="text"
                                                                            class="form-control "
                                                                            id="name" name="name"
                                                                            placeholder="Enter Last Name">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-5 col-sm-5 col-md-5 mt-2">
                                                                <label for="name" class="form-label">Date of
                                                                    Birth</label>
                                                                <input type="date" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-1 col-sm-1 col-md-1 mt-2">
                                                                <label for="name" class="form-label">Age</label>
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name" placeholder="Age">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Flyer Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport
                                                                    Number</label>
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Passport Number">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Expiry Date</label>
                                                                <input type="date" class="form-control "
                                                                    id="name" name="name"
                                                                    placeholder="Enter Date of Birth">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Passport Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3">
                                                                    <input type="file" class="form-control"
                                                                        id="inputGroupFile02">
                                                                    <label class="input-group-text"
                                                                        for="inputGroupFile02">Upload</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-2">
                                                                <label for="name" class="form-label">Visa Image (Max
                                                                    2MB)</label>
                                                                <div class="input-group mb-3">
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
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name" placeholder="Enter Email">
                                                            </div>
                                                            <div class="col-6 col-sm-6 col-md-6 mt-1">
                                                                <label for="name" class="form-label">Phone</label>
                                                                <input type="text" class="form-control "
                                                                    id="name" name="name" placeholder="Enter Phone">
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
                                            <!-- <button @click="addAddOnServices()" class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button> -->
                                            <button type="button" @click="addonesSevice"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Continue</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end of traveller details -->


                                <!-- add on services -->
                                <div class="card d-none fadeIn" id="addones-sevice-details">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="card" style="background-color:rgba(248, 252, 255, 1);">
                                                    <div class="card-header p-2 m-0">
                                                        <span style="font-size: 16px;">
                                                            <i class="fa fa-suitcase" style="color: #7239ea;"></i>
                                                            Ancillary Services
                                                        </span>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="card"
                                                                    style="background-color: rgba(255, 255, 255, 1);">
                                                                    <div class="row">
                                                                        <div class="col-md-9">
                                                                            <div
                                                                                class="d-flex flex-column bd-highlight m-2">
                                                                                <div class="bd-highlight">
                                                                                    <span class="p-2">Extra
                                                                                        Baggage</span>
                                                                                </div>
                                                                                <div class="bd-highlight">
                                                                                    <span class="p-2"
                                                                                        style="font-size: 11px;">Carry
                                                                                        extra 1
                                                                                        piece baggage and +10 kgs extra
                                                                                        in cabin.</span>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <div
                                                                                class="d-flex flex-row justify-content-center align-items-center mt-3">
                                                                                <div class=" bd-highlight">
                                                                                    <span class="p-2"
                                                                                        style="font-size: 13px;"><b>BDT
                                                                                            3000</b></span>
                                                                                </div>
                                                                                <div class=" bd-highlight">
                                                                                    <button
                                                                                        class="w3-button w3-tiny w3-blue-sky-purple w3-round">+ADD</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-12">
                                                                <div class="card"
                                                                    style="background-color: rgba(255, 255, 255, 1);">
                                                                    <div class="row">
                                                                        <div class="col-md-9">
                                                                            <div
                                                                                class="d-flex flex-column bd-highlight m-2">
                                                                                <div class="bd-highlight">
                                                                                    <span class="p-2">Priority
                                                                                        Boarding</span>
                                                                                </div>
                                                                                <div class="bd-highlight">
                                                                                    <span class="p-2"
                                                                                        style="font-size: 11px;">Board
                                                                                        the plane before general
                                                                                        boarding and get attention
                                                                                        fast</span>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <div
                                                                                class="d-flex flex-row justify-content-center align-items-center mt-3">
                                                                                <div class=" bd-highlight">
                                                                                    <span class="p-2"
                                                                                        style="font-size: 13px;"><b>BDT
                                                                                            1000</b></span>
                                                                                </div>
                                                                                <div class=" bd-highlight">
                                                                                    <button
                                                                                        class="w3-button w3-tiny w3-blue-sky-purple w3-round">Added</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-12">
                                                                <div class="card"
                                                                    style="background-color: rgba(255, 255, 255, 1);">
                                                                    <div class="row">
                                                                        <div class="col-md-9">
                                                                            <div
                                                                                class="d-flex flex-column bd-highlight m-2">
                                                                                <div class="bd-highlight">
                                                                                    <span class="p-2">Inflight
                                                                                        Wi-Fi</span>
                                                                                </div>
                                                                                <div class="bd-highlight">
                                                                                    <span class="p-2"
                                                                                        style="font-size: 11px;">Get a
                                                                                        high speed wifi connection to
                                                                                        browse smoothly</span>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <div
                                                                                class="d-flex flex-row justify-content-center align-items-center mt-3">
                                                                                <div class=" bd-highlight">
                                                                                    <span class="p-2"
                                                                                        style="font-size: 13px;"><b>BDT
                                                                                            1000</b></span>
                                                                                </div>
                                                                                <div class=" bd-highlight">
                                                                                    <button
                                                                                        class="w3-button w3-tiny w3-blue-sky-purple w3-round">Added</button>
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
                                    <div class="card-footer">
                                        <div class="d-block">
                                            <button @click="travelerDetails" class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button>
                                            <button type="button" @click="couponOffers"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Continue</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end of add on services -->

                                <!-- coupon offers -->
                                <div class="card d-none fadeIn" id="coupon-offer-details">
                                    <div class="card-header">
                                        <span style="font-size: 16px;">
                                            <i class="fa-solid fa-ticket-perforated" style="color: #7239ea;"></i>
                                            Coupon
                                        </span>
                                    </div>
                                    <div class="card-body" style="min-height: 280px;">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="d-flex flex-row bd-highlight">
                                                    <div class="p-2 bd-highlight w-100">
                                                        <input type="text" class="form-control" id="name" name="name"
                                                            placeholder="Enter Coupon Code">
                                                    </div>
                                                    <div class="p-2 bd-highlight">
                                                        <button
                                                            class="w3-button w3-blue-sky-purple w3-round w3-medium">Apply</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-block">
                                            <button @click="couponOffers" class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button>
                                            <button type="button" @click="reviewPayment" class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Continue</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end coupon offers -->

                                <!--review payment details -->
                                <div class="card d-none fadeIn" id="review-payment-details">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <span style="font-size: 16px;">
                                                        <i class="fa-solid fa-ticket-perforated"
                                                            style="color: #7239ea;"></i>
                                                        Review
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="accordion" id="accordionExample">
                                                        <div class="accordion-item">
                                                            <h2 class="accordion-header" id="headingOne">
                                                                <button class="accordion-button collapsed" type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#collapseOne" aria-expanded="false"
                                                                    aria-controls="collapseOne">

                                                                    <img src="../../../../../public/theme/Booking_Steps/traveller_icon.svg"
                                                                        alt="">
                                                                    <span class="pt-1 ps-1">Traveller 1: Adult</span>

                                                                    <div style="margin-left: 20px;"
                                                                        class="badge rounded-pill text-success bg-light-success p-1 px-4">
                                                                        Primary Contact</div>
                                                                </button>
                                                            </h2>
                                                            <div id="collapseOne" class="accordion-collapse collapse"
                                                                aria-labelledby="headingOne"
                                                                data-bs-parent="#accordionExample" style="">
                                                                <div class="accordion-body"
                                                                    style="background-color: rgba(248, 252, 255, 1);">
                                                                    <div class="row">
                                                                        <table class="table table-bordered border-primary text-center">
                                                                            <tbody>
                                                                                <tr>
                                                                                    <td><span>First Name:</span> <br> Atiqur </td>
                                                                                    <td><span>Last Name:</span> <br> Rahman</td>
                                                                                    <td><span>Date Of Birth:</span> <br> 18 Aug 1995</td>
                                                                                    <td><span>Gender:</span> <br> Male</td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td><span>Email:</span> <br> atiq@xyz.com </td>
                                                                                    <td><span>Phone:</span> <br> 0171xxxxxx</td>
                                                                                    <td><span>Nationality:</span> <br> Bangladeshi</td>
                                                                                    <td><span>Passport Number:</span> <br> BD9255ddd21</td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td><span>Expiry Date:</span> <br> 18 Aug 2027 </td>
                                                                                    <td><span>Seat Preference:</span> <br> Window</td>
                                                                                    <td><span>Meal Preference:</span> <br> Muslim Meal</td>
                                                                                    <td><span>Wheel Chair:</span> <br> No</td>
                                                                                </tr>
                                                                            </tbody>

                                                                        </table>
                                                                    </div>
                                                                    <div class="row">
                                                                        <div class="col-md-3 border me-2">
                                                                            <span style="font-size: 12px;">Atiqur_rahman_passport.jpg</span>
                                                                            <img class="pl-0 p-2" height="80" width="100" src="../../../../../public/theme/appimages/bd_pass.jpg" alt="">
                                                                        </div>
                                                                        <div class="col-md-3 border me-2">
                                                                            <span style="font-size: 12px;">visa_image.jpg</span>
                                                                            <img class="pl-0 p-2" height="80" width="100" src="../../../../../public/theme/appimages/visa_images.jpg" alt="">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="accordion-item">
                                                            <h2 class="accordion-header" id="headingTwo">
                                                                <button class="accordion-button collapsed" type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#collapseTwo" aria-expanded="false"
                                                                    aria-controls="collapseTwo">
                                                                    <img src="../../../../../public/theme/Booking_Steps/traveller_icon.svg"
                                                                        alt="">
                                                                    <span class="pt-1 ps-1">Traveller 2: Adult</span>
                                                                </button>
                                                            </h2>
                                                            <div id="collapseTwo" class="accordion-collapse collapse"
                                                                aria-labelledby="headingTwo"
                                                                data-bs-parent="#accordionExample">
                                                                <div class="accordion-body"
                                                                    style="background-color: rgba(248, 252, 255, 1);">
                                                                    <div class="row">

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="accordion-item">
                                                            <h2 class="accordion-header" id="headingThree">
                                                                <button class="accordion-button collapsed" type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#collapseThree"
                                                                    aria-expanded="false" aria-controls="collapseThree">
                                                                    <i class="fa-solid fa-child-reaching"
                                                                        style="color: #7239ea;"></i>
                                                                    <span class="pt-1 ps-1">Traveller 3: Children</span>
                                                                </button>
                                                            </h2>
                                                            <div id="collapseThree" class="accordion-collapse collapse"
                                                                aria-labelledby="headingThree"
                                                                data-bs-parent="#accordionExample">
                                                                <div class="accordion-body"
                                                                    style="background-color: rgba(248, 252, 255, 1);">
                                                                    <div class="row">

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="col-md-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <span style="font-size: 16px;">
                                                        <i class="fa-solid fa-ticket-perforated"
                                                            style="color: #7239ea;"></i>
                                                        Payment
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-7">
                                                            <div class="d-flex flex-column bd-highlight mb-3">
                                                                <div class="bd-highlight">Payment From</div>
                                                                <div class="pt-2 bd-highlight">
                                                                    <button class="w3-btn w3-white w3-border  bluesky-border-primary w3-round"> <i class="fa-solid fa-wallet" style="color:#7239ea;"></i> <span class="fw-bold" style="color:#7239ea;">Main Balanace</span></button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <table class="table table-sm table-borderless text-center">
                                                                <tr>
                                                                    <td class="fw-bold p-2">Current Balance</td>
                                                                    <td class="fw-bold p-2">BDT 234500</td>
                                                                </tr>

                                                                <tr>
                                                                    <td class="fw-bold p-2">Deducted</td>
                                                                    <td class="fw-bold p-2">- BDT 34500</td>
                                                                </tr>

                                                                <tr>
                                                                    <td class="fw-bold p-2">Available</td>
                                                                    <td class="fw-bold p-2">BDT 200000</td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-footer">
                                        <div class="d-block">
                                            <button @click="couponOffers" class="w3-button w3-dark-gray w3-round w3-medium float-left">Back</button>
                                            <button type="button"
                                                class="w3-button w3-blue-sky-purple w3-round w3-medium float-end">Confirm Booking</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- end review payment details -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3" style="background-color: rgb(244, 244, 255);">
                        <div class="accordion accordion-flush mt-3" id="accordionFlushExample">
                            <div class="accordion-item">
                                <h2 class="accordion-header rounded" id="flush-headingOne"><button
                                        class="accordion-button m-0 p-0 px-3 py-2 collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#flush-fare-summary"
                                        aria-expanded="false" aria-controls="flush-fare-summary"> Fare Summary
                                    </button>
                                </h2>
                                <div id="flush-fare-summary" class="accordion-collapse collapse"
                                    aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
                                    <div class="accordion-body">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="border fare-summary-bg p-1 rounded-1 mb-1"><span
                                                        class="custom-text-purple"> Base Fare </span></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <tbody class="text-start">
                                                            <tr>
                                                                <td>Adults: 2x৳30000 </td>
                                                                <td> ৳60000 </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Childs: 2x৳20000 </td>
                                                                <td> ৳40000 </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="border fare-summary-bg p-1 rounded-1 mb-1"><span
                                                        class="custom-text-purple"> TAX </span></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <tbody class="text-start">
                                                            <tr>
                                                                <td>Adults: 2x৳5000</td>
                                                                <td> ৳10000 </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Childs: 2x৳2000</td>
                                                                <td> ৳4000 </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="border fare-summary-bg p-1 rounded-1 mb-1"><span
                                                        class="custom-text-purple"> AIT </span></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <tbody class="text-start">
                                                            <tr>
                                                                <td>Adults: 2x৳1275</td>
                                                                <td> ৳2550 </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Childs: 2x৳870</td>
                                                                <td> ৳1740 </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="border fare-summary-bg p-1 rounded-1 mb-1"><span
                                                        class="custom-text-purple"> Service Charge </span></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <tbody class="text-start">
                                                            <tr>
                                                                <td>Adults: 2x৳1275</td>
                                                                <td> ৳2550 </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Childs: 2x৳870</td>
                                                                <td> ৳1740 </td>
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
                                <h2 class="accordion-header" id="flush-headingTwo"><button
                                        class="accordion-button m-0 p-0 px-3 py-2 collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo"
                                        aria-expanded="false" aria-controls="flush-collapseTwo"> Baggadge
                                        Information
                                    </button></h2>
                                <div id="flush-collapseTwo" class="accordion-collapse collapse"
                                    aria-labelledby="flush-headingTwo" data-bs-parent="#accordionFlushExample">
                                    <div class="accordion-body">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <tbody class="text-start">
                                                            <tr>
                                                                <td style="font-size: 11px;">
                                                                    <b>DAC-BDX</b><br><small>Adult</small>
                                                                </td>
                                                                <td style="font-size: 11px;">
                                                                    <b>Economy</b><br><small>10
                                                                        Kg</small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="font-size: 11px;">
                                                                    <b>DAC-BDX</b><br><small>Child</small>
                                                                </td>
                                                                <td style="font-size: 11px;">
                                                                    <b>Economy</b><br><small>10
                                                                        Kg</small>
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

</template>

<style>
.booking-top-card-text {
    color: rgba(94, 104, 120, 1);
}

.addones-service-inactive {
    position: absolute;
    left: 208px;
    top: 16px;
    z-index: 16;
}

.coupon-offers-inactive {
    position: absolute;
    left: 422px;
    top: 16px;
    z-index: 16;
}

.review-payment-inactive {
    position: absolute;
    left: 633px;
    top: 16px;
    z-index: 16;
}

.w3-blue-sky-purple {
    color: #fff !important;
    background-color: #7239ea;
}
</style>
