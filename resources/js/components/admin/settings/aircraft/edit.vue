<script setup>
import AppBreadcrumbs from '../../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../../stores/authStore";
import axiosInstance from "../../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
const props = defineProps(['id'])
const Loading = ref(true);

const form = reactive({ model: "", aircraft_id: '', iata_code: "", icao_code: "", useEmail: authStore.email });

async function update(props) {

    form.aircraft_id = props.id;

    try {
        const response = await axiosInstance.post("/AircraftType/update", form);

        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}



getAirCraftData(props);

async function getAirCraftData(props) {

    try {
        authStore.GlobalLoading = true;
        Loading.value = false;

        const response = await axiosInstance.post('editAircraft', { 'id': props });


        const model = response.data[0].model;
        const iata_code = response.data[0].iata_code;
        const icao_code = response.data[0].icao_code;

        form.model = model;
        form.iata_code = iata_code;
        form.icao_code = icao_code;


        authStore.GlobalLoading = false;
        Loading.value = true;
    } catch (error) {
        authStore.GlobalLoading = false;
        Loading.value = true;
        console.log(error);
    }
}

onMounted(() => {

});


</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'aircraftList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Aircraft List', to: { name: 'aircraftList' } },
            { label: 'Aircraft Edit' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Aircraft Edit</h5>
        </div>

        <div v-if="authStore.GlobalLoading" class="mt-2 center-body position-absolute top-50 start-50">
            <div class="loader-circle-57">
                <img class="position-absolute" src="../../../.../../../../../public/theme/appimages/blueskywings.png" height="22"
                    width="22" alt="">
            </div>
        </div>

        <form v-show="Loading" id="addZoneform">
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
                <button type="button" @click="update(props)"
                    class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Update</button>
                <button class="m-2 btn btn-sm btn-danger px-4 ms-2  float-end">Back</button>
            </div>
        </form>
    </div>
</template>
