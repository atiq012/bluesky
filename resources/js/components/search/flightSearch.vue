<script setup lang="ts">
import { ref } from "vue";
import "@vuepic/vue-datepicker/dist/main.css";

import DatePicker from 'vue-datepicker-next';
import 'vue-datepicker-next/index.css';

const tripType = ref('roundTrip');
const origin = ref('');
const destination = ref('');
const value1 = ref(null);
const shortcuts = ref([
    { text: 'today', onClick: () => new Date() },
    {
        text: 'Yesterday',
        onClick: () => {
            const date = new Date();
            date.setTime(date.getTime() - 3600 * 1000 * 24);
            return date;
        },
    },
]);

const shortcutsRange = ref([
    { text: 'today', onClick: () => new Date() },
    {
        text: 'Yesterday',
        onClick: () => {
            const date = new Date();
            date.setTime(date.getTime() - 3600 * 1000 * 24);
            return date;
        },
    },
]);


const departureDate = ref(null);
const returnDate = ref(null);
const travellers = ref(1);
const travelClass = ref('economy');
const fareType = ref('regular');

const searchFlights = () => {
    // Implement flight search logic here
    console.log({
        tripType: tripType.value,
        origin: origin.value,
        destination: destination.value,
        departureDate: departureDate.value,
        returnDate: returnDate.value,
        travellers: travellers.value,
        travelClass: travelClass.value,
        fareType: fareType.value
    });
};
</script>

<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Settingsx</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboardx</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Flight Searchx</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="flight-search-panel">
                <!-- <div class="trip-type">
                    <input type="radio" id="oneWay" value="oneWay" v-model="tripType">
                    <label for="oneWay">One Way</label>
                    <input type="radio" id="roundTrip" value="roundTrip" v-model="tripType">
                    <label for="roundTrip">Round Trip</label>
                    <input type="radio" id="multiCity" value="multiCity" v-model="tripType">
                    <label for="multiCity">Multi City</label>
                </div>

                <div class="flight-details">
                    <input type="text" v-model="origin" placeholder="Origin (e.g., DAC)">
                    <input type="text" v-model="destination" placeholder="Destination (e.g., JFK)">
                    <VueDatePicker v-model="departureDate" placeholder="Departure Date"></VueDatePicker>

                    <VueDatePicker v-if="tripType === 'roundTrip'" v-model="returnDate" placeholder="Return Date">
                    </VueDatePicker>
                </div> -->

                <div class="box">


                    <section>
                        <p>Date Range</p>
                        <date-picker v-model:value="value1" :shortcuts="shortcutsRange" type="date" range
                            placeholder="Select date range"></date-picker>
                    </section>

                </div>

                <div class="additional-options">
                    <select v-model="travellers">
                        <option value="1">1 Traveller</option>
                        <option value="2">2 Travellers</option>
                        <option value="3">3 Travellers</option>
                        <option value="4">4 Travellers</option>
                    </select>
                    <select v-model="travelClass">
                        <option value="economy">Economy</option>
                        <option value="business">Business</option>
                        <option value="first">First Class</option>
                    </select>
                </div>

                <div class="fare-type">
                    <input type="radio" id="regularFare" value="regular" v-model="fareType">
                    <label for="regularFare">Regular Fare</label>
                    <input type="radio" id="studentFare" value="student" v-model="fareType">
                    <label for="studentFare">Student Fare</label>
                </div>

                <button @click="searchFlights">Search</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.flight-search-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background-color: #fff;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.trip-type,
.flight-details,
.additional-options,
.fare-type {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.trip-type input[type="radio"] {
    margin-right: 0.5rem;
}

.flight-details input,
.additional-options select,
.fare-type input[type="radio"] {
    padding: 0.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    flex: 1;
}

button {
    padding: 0.5rem 1rem;
    background-color: #ff6600;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    align-self: flex-start;
}
</style>
