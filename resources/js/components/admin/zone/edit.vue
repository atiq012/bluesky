<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
const props = defineProps(['id'])
const Loading = ref(true);

const form = reactive({ area_name: "", area_id: '', division_id: "", district_id: "", status_val: "", useEmail: authStore.email });

async function update(props) {

    form.area_id = props.id;

    try {
        const response = await axiosInstance.post("/zone/update", form);

        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}



getAreaData(props);

async function getAreaData(props) {

    try {
        authStore.GlobalLoading = true;
        Loading.value = false;
        await getDivision();
        const response = await axiosInstance.post('editArea', { 'id': props });
        const name = response.data[0].name;

        const division_id = response.data[0].division_id;
        const district_id = response.data[0].district_id;
        const status = response.data[0].status;

        $('#division_id').val(division_id);
        $('#division_id').trigger('change');
        $('#status').val(status);
        $('#status').trigger('change');
        form.area_name = name;
        await getDistrict(district_id);
        authStore.GlobalLoading = false;
        Loading.value = true;
    } catch (error) {
        authStore.GlobalLoading = false;
        Loading.value = true;
        console.log(error);
    }
}

onMounted(() => {
    $('.division_name').on("change", function () {

        form.division_id = $(this).val();
        $('.district_name').empty(); // empty previous data

        getDistrict($(this).val());
        $('#district_id').prepend('<option selected=""></option>');
    });

    $('.district_name').on("change", function () {
        form.district_id = $(this).val();
    });

    $("#status").on('change', function () {
        form.status_val = $(this).val();
    });
});


async function getDivision() {
    try {
        const response = await axiosInstance.get('divisions');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);

        });

        let select = $("#division_id")
        select.select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
        });


    } catch (error) {
        ErrorCatch.CatchError(error);


    }
}

async function getDistrict(id) {

    try {
        const response = await axiosInstance.post('districts', { 'id': id });
        // console.log(response.data);

        var getDatas = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            getDatas.push(obj);

        });

        let select = $("#district_id")
        select.select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: getDatas,
        });


    } catch (error) {
        ErrorCatch.CatchError(error);


    }
}

</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'zoneList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Area List', to: { name: 'zoneList' } },
            { label: 'Edit Area' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Edit Area</h5>
        </div>

        <div v-if="authStore.GlobalLoading" class="mt-2 center-body position-absolute top-50 start-50">
            <div class="loader-circle-57">
                <img class="position-absolute" src="../../../../../public/theme/appimages/blueskywings.png" height="22"
                    width="22" alt="">
            </div>
        </div>

        <form v-show="Loading" id="addZoneform">
            <div class="card-body">

                <div class="row">
                    <div class="col-md-6">
                        <label for="input1" class="form-label">Area Name</label>
                        <input type="text" class="form-control form-control-sm" id="area_name" name="area_name"
                            placeholder="Enter Name" v-model="form.area_name">
                    </div>

                    <div class="col-md-6">
                        <label for="input1" class="form-label">Division</label>
                        <select id="division_id" name="division_name"
                            class="form-control form-control-sm single-select-fields division_name">
                        </select>
                    </div>

                    <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label ">District</label>
                        <select id="district_id" name="district_name"
                            class="form-control form-control-sm single-select-field district_name">

                        </select>
                    </div>

                    <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label">Status</label>
                        <select id="status" name="status" class="form-control form-control-sm">
                            <option selected value="">Choose...</option>
                            <option value="1">Active</option>
                            <option value="2">Inactive</option>
                        </select>
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
