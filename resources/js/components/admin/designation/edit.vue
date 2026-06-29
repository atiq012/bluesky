<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
const props = defineProps(['id'])

const form = reactive({ des_name: "", dept_id: '', status_val: "", useEmail: authStore.email });

async function update(props) {

    form.dept_id = props.id;

    try {
        const response = await axiosInstance.post("/Designation/update", form);

            Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

getDeptData(props);

async function getDeptData(props) {
    try {
        const response = await axiosInstance.post('editDesignation', { 'id': props });
        const name = response.data[0].name;
        $("#des_name").val(name);
        const status = response.data[0].status;
        $('#status').val(status);
        $('#status').trigger('change');

    } catch (error) {
        console.log(error);
    }
}

onMounted(() => {

    $("#status").on('change', function () {
        form.status_val = $(this).val();
    });
});

</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'designationList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Setings', to: { name: 'designationList' } },
            { label: 'Designation List', to: { name: 'designationList' } },
            { label: 'Edit Designation' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Edit Designation</h5>
        </div>

        <form id="addZoneform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="input1" class="form-label">Name</label>
                        <input type="text" class="form-control form-control-sm" id="des_name" name="des_name"
                            placeholder="Enter Name" v-model="form.des_name">
                    </div>

                    <div class="col-md-6">
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
