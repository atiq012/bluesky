<script setup>
import { ref, onMounted, onUnmounted, reactive, computed, nextTick } from "vue";
import axiosInstance from "../../axiosInstance"
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import "simplebar-vue/dist/simplebar.min.css";
import { useAuthStore } from '../../stores/authStore';

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
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column bd-highlight mb-3">
                        <div class="text-center mb-0 pb-0">
                            <img style="width: 49%; height: 49%;"
                                src="../../../../public/theme/appimages/Serach_Reult_Not_Found.jpg" alt="">
                        </div>
                        <div class="text-center mb-0 pb-0">
                            <h4 class="text-danger">We didn't find any flight based on your search !!!</h4>
                            <p class="text-muted">Please try again changing the search details.</p>
                        </div>
                        <div class="text-center mb-0 pb-0">
                            <router-link :to="{ name: 'searchResult' }" class="btn bluesky-btn-primary">Search
                                Again</router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>

<style scoped></style>
