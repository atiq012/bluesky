<script setup>
import AppBreadcrumbs from '../../../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../../../stores/authStore";
import axiosInstance from "../../../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
//**** create function start
const form = reactive({ acc_type: "", bank_name: "", acc_name: '', acc_no: '', branch: '', useEmail: authStore.email, routing_no: '', service_charge: '' });


async function save() {

    try {
        const response = await axiosInstance.post("/paymentAcct/save", form);
        document.getElementById("addBankform").reset();
        Notification.showToast('s', response.data.message);
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

onMounted(() => {
    $('#acc_type').on("change", function () {
        form.acc_type = $(this).val();
    });
});


</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'issuedBankMFS' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Setings', to: { name: 'issuedBankMFS' } },
            { label: 'Deposit', to: { name: 'issuedBankMFS' } },
            { label: 'Create New Payment Account' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Create New Payment Account</h5>
        </div>

        <form id="addBankform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="input1" class="form-label">Account Type</label>

                        <select id="acc_type" name="acc_type" class="form-control form-control-sm">
                            <option selected value="">Choose...</option>
                            <option value="Bank">Bank</option>
                            <option value="MFS">MFS</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="input1" class="form-label">Bank Name</label>
                        <input type="text" v-model="form.bank_name" class="form-control form-control-sm" id="bank_name"
                            name="bank_name" placeholder="Enter Bank Name">

                    </div>
                    <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label">Account Name</label>
                        <input type="text" v-model="form.acc_name" class="form-control form-control-sm" id="acc_name"
                            name="acc_name" placeholder="Enter Account Name">

                    </div>
                    <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label">Account Number</label>
                        <input type="text" v-model="form.acc_no" class="form-control form-control-sm" id="acc_no"
                            name="acc_no" placeholder="Enter Account Number">

                    </div>
                    <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label">Branch</label>
                        <input type="text" v-model="form.branch" class="form-control form-control-sm" id="branch"
                            name="branch" placeholder="Enter Branch">

                    </div>
                    <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label">Routing Number</label>
                        <input type="text" v-model="form.routing_no" class="form-control form-control-sm"
                            id="routing_no" name="routing_no" placeholder="Enter Routing Number">

                    </div>
                    <div class="col-md-6 mt-2">
                        <label for="input1" class="form-label">Service Charge</label>
                        <input type="text" v-model="form.service_charge" class="form-control form-control-sm"
                            id="service_charge" name="service_charge" placeholder="Enter Service Charge">

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
