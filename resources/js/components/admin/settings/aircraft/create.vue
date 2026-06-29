<script setup>
import AppBreadcrumbs from '../../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../../stores/authStore";
import axiosInstance from "../../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
//**** create function start
const form = reactive({ iata_code: "", icao_code: "", model: "", useEmail: authStore.email });

onMounted(() => {

});

async function save() {

    try {

        const response = await axiosInstance.post("/AircraftType/save", form);
        document.getElementById("addAirCraftform").reset();

        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}


</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'aircraftList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Aircraft List', to: { name: 'aircraftList' } },
            { label: 'Create New Aircraft' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Create New Aircraft</h5>
        </div>

        <form id="addAirCraftform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label for="input1" class="form-label">Aircraft Model</label>
                        <input type="text" v-model="form.model" class="form-control" id="model" name="model"
                            placeholder="Aircraft Model">
                    </div>
                    <div class="col-md-4">
                        <label for="input1" class="form-label">IATA Code</label>
                        <input type="text" v-model="form.iata_code" class="form-control" id="iata_code" name="iata_code"
                            placeholder="IATA Code">
                    </div>
                    <div class="col-md-4">
                        <label for="input1" class="form-label">ICAO Code</label>
                        <input type="text" v-model="form.icao_code" class="form-control" id="icao_code" name="icao_code"
                            placeholder="ICAO Code">
                    </div>

                </div>
            </div>
            <div class="card-footer">
                <button type="button" @click="save()"
                    class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                <button class="m-2 btn btn-sm btn-danger px-4 ms-2  float-end">Back</button>
            </div>
        </form>
    </div>
</template>

