<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
//**** create function start
const form = reactive({ area_name: "", division_id: "", district_id: "", status_val: "", useEmail: authStore.email });

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

    $("#district_id").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '100%',
        allowClear: true,
        height: '50',
    });
});

async function save() {

    try {

        const response = await axiosInstance.post("/zone/save", form);
        document.getElementById("addZoneform").reset();
        $('#division_id option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        $('#district_id option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        $('#status option:first').prop('selected', true).trigger(
            "change"); // reset dropdown value
        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}



// it will load everytime page open
getDivision();

async function getDivision() {
    try {
        const response = await axiosInstance.get('divisions');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name, bn: value.bn_name, clr: MF.getRandomColor() }
            options.push(obj);

        });

        $("#division_id").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
            tags: true,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }

                var $state = $('<div class="clearfix"><div class="float-start"><i class="fa-solid fa-circle" style="color: ' + state.clr + '"></i> ' + state.text + '</div><div class="float-end">' + state.bn + '</div></div>');
                return $state;
            }
        });
        $('#division_id').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);

    }
}

async function getDistrict(id) {

    try {
        const response = await axiosInstance.post('districts', { 'id': id });
        // console.log(response.data);

        var getDatas = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name, bn: value.bn_name, clr: MF.getRandomColor() }
            getDatas.push(obj);

        });

        $("#district_id").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            tags: true,
            height: '50',
            data: getDatas,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }

                var $state = $('<div class="clearfix"><div class="float-start"><i class="fa-solid fa-circle m-1" style="color: ' + state.clr + '"></i> ' + state.text + '</div><div class="float-end">' + state.bn + '</div></div>');
                return $state;
            }
        });


    } catch (error) {
        // console.log(error);

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
            { label: 'Create New Area' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea; border-radius: 10px;"> &nbsp; Create New Area</h5>
        </div>

        <form id="addZoneform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="input1" class="form-label">Area Name</label>
                        <input type="text" v-model="form.area_name" class="form-control" id="area_name" name="area_name"
                            placeholder="Enter Name">
                    </div>

                    <div class="col-md-6">
                        <label for="division_id" class="form-label">Division</label>
                        <select id="division_id" name="division_name" class="form-control form-control division_name">
                        </select>
                    </div>

                    <div class="col-md-6 mt-2">
                        <label for="district_id" class="form-label ">District</label>
                        <select id="district_id" name="district_name" class="form-control form-control district_name">
                        </select>
                    </div>

                    <!-- <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label">Status</label>
                        <select id="status" v-model="form.status_val" name="status"
                            class="form-control form-control-sm">
                            <option value="1" selected>Active</option>
                            <option value="2">Inactive</option>
                        </select>
                    </div> -->
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
