<script setup>
import AppBreadcrumbs from '../../../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../../../stores/authStore";
import axiosInstance from "../../../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
const props = defineProps(['id']);

const form = reactive({ acc_type: "", bank_name: "", acc_name: '', acc_no: '', branch: '', useEmail: authStore.email, routing_no: '', service_charge: '' ,pay_acct_id:''});

async function update(props) {

    form.pay_acct_id = props.id;

    try {
        const response = await axiosInstance.post("/payment-acct/update", form);

            Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

getDeptData(props);

async function getDeptData(props) {
    try {
        const response = await axiosInstance.post('editPaymentAcct', { 'id': props });
        const bank_name = response.data[0].bank_name;
        $("#bank_name").val(bank_name);

        const acc_type = response.data[0].acc_type;
        $('#acc_type').val(acc_type);
        $('#acc_type').trigger('change');

        const acc_name = response.data[0].acc_name;
        $("#acc_name").val(acc_name);

        const acc_no = response.data[0].acc_no;
        $("#acc_no").val(acc_no);

        const branch = response.data[0].branch;
        $("#branch").val(branch);

        const routing_no = response.data[0].routing_no;
        $("#routing_no").val(routing_no);

        const service_charge = response.data[0].service_charge;
        $("#service_charge").val(service_charge);

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
        :back-to="{ name: 'issuedBankMFS' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Setings', to: { name: 'issuedBankMFS' } },
            { label: 'Deposit', to: { name: 'issuedBankMFS' } },
            { label: 'Edit Issued Bank MFS' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Edit Issued Bank MFS</h5>
        </div>

        <form id="addZoneform">
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
