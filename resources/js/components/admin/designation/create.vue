<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
//**** create function start
const form = reactive({ des_name: "", useEmail: authStore.email });


async function save() {

    try {
        const response = await axiosInstance.post("/Designation/save", form);
        document.getElementById("addDeptform").reset();
        Notification.showToast('s', response.data.message);
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}




</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'designationList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Setings', to: { name: 'designationList' } },
            { label: 'Designation List', to: { name: 'designationList' } },
            { label: 'Create New Designation' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Create New Designation</h5>
        </div>

        <form id="addDeptform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <label for="input1" class="form-label">Name</label>
                        <input type="text" v-model="form.des_name" class="form-control form-control-sm" id="des_name"
                            name="des_name" placeholder="Enter Name">
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
