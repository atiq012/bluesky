<script setup>
import AppBreadcrumbs from '../common/AppBreadcrumbs.vue';

import { ref, onMounted, onUnmounted, reactive, computed, nextTick } from "vue";
import axiosInstance from "../../axiosInstance"
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import "simplebar-vue/dist/simplebar.min.css";
import { useAuthStore } from '../../stores/authStore';

</script>
<template>
        <AppBreadcrumbs
        title="Flight Management"
        :back-to="{ name: 'Home' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Flight Search Result' },
        ]"
    />

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
                            <h4 class="text-danger-">We didn't find any flight based on your search !!!</h4>
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

<style scoped>

</style>
