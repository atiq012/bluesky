<script setup>
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../../stores/authStore';
const authStore = useAuthStore();
import axiosInstance from "../../../axiosInstance";
import { ref, reactive, onMounted, render } from "vue";

const form = reactive({ payment_acc: "", requested_amount: "", service_charge: "", total_amount: "", reference_number: "", reference_date: "", reference_file: "", remarks: "", payment_type: "", issued_bank: "" });

onMounted(() => {
    $(".payment_acc").on('change', function () {
        form.payment_acc = $(this).val();
    });
    $(".issued_bank").on('change', function () {
        form.issued_bank = $(this).val();
    });
});
async function caseSave() {
    try {
        form.payment_type = "Cash";
        const response = await axiosInstance.post("/deposit/save", form);
        if (response.data.message) {
            Notification.showToast('s', response.data.message);
            router.push({ name: 'depositList' });
        }
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

async function caseMFS() {
    try {
        form.payment_type = "MFS";
        const response = await axiosInstance.post("/deposit/save", form);
        if (response.data.message) {
            Notification.showToast('s', response.data.message);
            router.push({ name: 'depositList' });
        }
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

async function caseCheque() {
    try {
        form.payment_type = "Cheque";
        const response = await axiosInstance.post("/deposit/save", form);
        if (response.data.message) {
            Notification.showToast('s', response.data.message);
            router.push({ name: 'depositList' });
        }
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

async function caseBankTransfer() {
    try {
        console.log(form);
        form.payment_type = "Bank_Transfer";
        const response = await axiosInstance.post("/deposit/save", form);
        if (response.data.message) {
            Notification.showToast('s', response.data.message);
            router.push({ name: 'depositList' });
        }
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

async function creditReqSave() {
    try {
        form.payment_type = "Credit_Request";
        const response = await axiosInstance.post("/deposit/save", form);
        if (response.data.message) {
            Notification.showToast('s', response.data.message);
            router.push({ name: 'depositList' });
        }
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}
</script>
<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3"> Deposit Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">

                        <router-link :to="{ name: 'depositList' }">Deposit Management</router-link>

                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        New Deposit Request
                    </li>

                </ol>
            </nav>
        </div>

    </div>

    <div class="row position-relative">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs nav-primary mb-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-bs-toggle="tab" href="#cash_tab" role="tab"
                                aria-selected="true">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title"> Cash </div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#mfs_tab" role="tab" aria-selected="false"
                                tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">MFS</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#cheque_tab" role="tab" aria-selected="false"
                                tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">Cheque/DD</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#bank_transfer_tab" role="tab"
                                aria-selected="false" tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">Bank Transfer</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#credit_req_tab" role="tab"
                                aria-selected="false" tabindex="-1">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon">
                                    </div>
                                    <div class="tab-title">Credit Request</div>
                                </div>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade active show" id="cash_tab" role="tabpanel">
                            <form id="cash_form">
                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Payment Information</h5>
                                            </div>

                                            <div class="card-body">
                                                <input type="hidden" v-model="form.payment_type">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select name="payment_acc"
                                                            class="payment_acc form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bank">Bank</option>
                                                            <option value="MFS">MFS</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.requested_amount" id="requested_amount"
                                                            name="requested_amount" placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.service_charge" id="service_charge"
                                                            name="service_charge" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.total_amount" id="total_amount"
                                                            name="total_amount" placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>


                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Cash Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label for="input1" class="form-label">
                                                            Referece Number</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.reference_number" id="reference_number"
                                                            name="reference_number" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-6 ">
                                                        <label for="input1" class="form-label">
                                                            Referece Date</label>
                                                        <input type="date" v-model="form.reference_date"
                                                            class="form-control form-control-sm" id="reference_date"
                                                            name="reference_date" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece file</label>
                                                        <input type="file" class="form-control form-control-sm"
                                                            id="reference_file" name="reference_file"
                                                            placeholder="Enter Service Charge">
                                                    </div>

                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Remarks</label>

                                                        <textarea v-model="form.remarks"
                                                            class="form-control form-control-sm" id="remarks"
                                                            name="remarks" rows="3"></textarea>

                                                    </div>
                                                    <div class="col-md-6">

                                                        <div class="d-flex align-items-center "><img
                                                                src="../../../../../public/theme/appimages/rqf.png"
                                                                height="60" width="60"
                                                                class="border border-1 rounded rounded-2"
                                                                alt="Profile Picture">
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="mb-0"><i
                                                                        class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                    Uploaded successfully.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="d-flex ">
                                            <button
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                            <button @click="caseSave()"
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-end"
                                                type="button">Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="mfs_tab" role="tabpanel">
                            <form id="mfs_form">

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Payment Information</h5>
                                            </div>

                                            <div class="card-body">
                                                <input type="hidden" v-model="form.payment_type">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select name="payment_acc"
                                                            class="payment_acc form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bkash">Bkash</option>
                                                            <option value="Nagad">Nagad</option>
                                                            <option value="Rocket">Rocket</option>
                                                            <option value="Upay">Upay</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.requested_amount" id="requested_amount"
                                                            name="requested_amount" placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.service_charge" id="service_charge"
                                                            name="service_charge" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.total_amount" id="total_amount"
                                                            name="total_amount" placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>


                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Cash Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label for="input1" class="form-label">
                                                            Referece Number</label>
                                                        <select name="issued_bank"
                                                            class="issued_bank form-control form-control-sm">
                                                            <option value="">Select Issued Bank</option>
                                                            <option value="1">AB Bank</option>
                                                            <option value="2">DBBL</option>
                                                            <option value="3">BRAC Bank</option>
                                                            <option value="4">City Bank</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 ">
                                                        <label for="input1" class="form-label">
                                                            Referece Date</label>
                                                        <input type="date" v-model="form.reference_date"
                                                            class="form-control form-control-sm" id="reference_date"
                                                            name="reference_date" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece file</label>
                                                        <input type="file" class="form-control form-control-sm"
                                                            id="reference_file" name="reference_file"
                                                            placeholder="Enter Service Charge">
                                                    </div>

                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Remarks</label>

                                                        <textarea v-model="form.remarks"
                                                            class="form-control form-control-sm" id="remarks"
                                                            name="remarks" rows="3"></textarea>

                                                    </div>
                                                    <div class="col-md-6">

                                                        <div class="d-flex align-items-center "><img
                                                                src="../../../../../public/theme/appimages/rqf.png"
                                                                height="60" width="60"
                                                                class="border border-1 rounded rounded-2"
                                                                alt="Profile Picture">
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="mb-0"><i
                                                                        class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                    Uploaded successfully.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="d-flex ">
                                            <button
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                            <button type="button" @click="caseMFS()"
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="cheque_tab" role="tabpanel">
                            <form id="cheque_form">
                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Payment Information</h5>
                                            </div>

                                            <div class="card-body">
                                                <input type="hidden" v-model="form.payment_type">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select name="payment_acc"
                                                            class="form-control form-control-sm payment_acc">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bkash">Bkash</option>
                                                            <option value="Nagad">Nagad</option>
                                                            <option value="Rocket">Rocket</option>
                                                            <option value="Upay">Upay</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.requested_amount" id="requested_amount"
                                                            name="requested_amount" placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.service_charge" id="service_charge"
                                                            name="service_charge" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="form.total_amount" id="total_amount"
                                                            name="total_amount" placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>


                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Cash Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label for="input1" class="form-label">
                                                            Issued Bank</label>
                                                        <select name="issued_bank"
                                                            class="issued_bank form-control form-control-sm">
                                                            <option value="">Select Issued Bank</option>
                                                            <option value="1">AB Bank</option>
                                                            <option value="2">DBBL</option>
                                                            <option value="3">BRAC Bank</option>
                                                            <option value="4">City Bank</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 ">
                                                        <label for="input1" class="form-label">
                                                            Referece Number</label>
                                                        <input type="input" v-model="form.reference_number"
                                                            class="form-control form-control-sm" id="reference_number"
                                                            name="reference_number" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece Date</label>
                                                        <input type="date" v-model="form.reference_date"
                                                            class="form-control form-control-sm" id="reference_date"
                                                            name="reference_date" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece file</label>
                                                        <input type="file" class="form-control form-control-sm"
                                                            id="reference_file" name="reference_file"
                                                            placeholder="Enter Service Charge">
                                                    </div>

                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Remarks</label>

                                                        <textarea v-model="form.remarks"
                                                            class="form-control form-control-sm" id="remarks"
                                                            name="remarks" rows="3"></textarea>

                                                    </div>
                                                    <div class="col-md-6 mt-4">

                                                        <div class="d-flex align-items-center "><img
                                                                src="../../../../../public/theme/appimages/rqf.png"
                                                                height="60" width="60"
                                                                class="border border-1 rounded rounded-2"
                                                                alt="Profile Picture">
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="mb-0"><i
                                                                        class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                    Uploaded successfully.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="d-flex ">
                                            <button
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                            <button type="button" @click="caseCheque()"
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="bank_transfer_tab" role="tabpanel">
                            <form id="bank_transfer_form">
                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Payment Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label for="input1" class="form-label">Payment Account</label>
                                                        <select name="payment_acc"
                                                            class="payment_acc form-control form-control-sm">
                                                            <option selected="" value="">Choose...</option>
                                                            <option value="Bank">Bank</option>
                                                            <option value="MFS">MFS</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mt-2">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="requested_amount" v-model="form.requested_amount"
                                                            name="requested_amount" placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="service_charge" v-model="form.service_charge"
                                                            name="service_charge" placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" v-model="form.total_amount"
                                                            class="form-control form-control-sm" id="total_amount"
                                                            name="total_amount" placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Bank Transfer Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label for="input1" class="form-label">
                                                            Issued Bank & MFS</label>
                                                        <select name="issued_bank"
                                                            class="form-control form-control-sm issued_bank">
                                                            <option value="">Select Issued Bank</option>
                                                            <option value="1">AB Bank</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="input1" class="form-label">
                                                            Referece Number</label>
                                                        <input type="input" class="form-control form-control-sm"
                                                            id="reference_num" v-model="form.reference_num"
                                                            name="reference_num" placeholder="Enter Reference Number">
                                                    </div>
                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece file</label>
                                                        <input type="file" class="form-control form-control-sm"
                                                            id="reference_file" name="reference_file"
                                                            placeholder="Enter ">
                                                    </div>

                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece Date</label>
                                                        <input type="date" class="form-control form-control-sm"
                                                            id="reference_date" v-model="form.reference_date"
                                                            name="reference_date" placeholder="Enter Service Charge">
                                                    </div>

                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Remarks</label>

                                                        <textarea class="form-control form-control-sm" id="remarks"
                                                            name="remarks" v-model="form.remarks" rows="3"></textarea>

                                                    </div>
                                                    <div class="col-md-6 mt-3">

                                                        <div class="d-flex align-items-center "><img
                                                                src="../../../../../public/theme/appimages/rqf.png"
                                                                height="60" width="60"
                                                                class="border border-1 rounded rounded-2"
                                                                alt="Profile Picture">
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="mb-0"><i
                                                                        class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                    Uploaded successfully.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="d-flex ">
                                            <button
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-start">Cancel</button>
                                            <button type="button" @click="caseBankTransfer()"
                                                class="btn btn-sm btn-info px-4 ms-2 justify-content-end">Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="credit_req_tab" role="tabpanel">
                            <form id="credit_req_form">
                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Payment Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">

                                                    <div class="col-md-4">
                                                        <label for="input1" class="form-label">Request Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="requested_amount" v-model="form.requested_amount" name="requested_amount"
                                                            placeholder="Enter Routing Number">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="input1" class="form-label">
                                                            Charge</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="service_charge" v-model="form.service_charge" name="service_charge"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="input1" class="form-label">
                                                            Total Amount</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="total_amount" v-model="form.total_amount" name="total_amount"
                                                            placeholder="Enter Service Charge">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="m-0 p-0" style="border-left: 5px solid rgb(114, 57, 234);">
                                                    &nbsp; Credit Request Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label for="input1" class="form-label">
                                                            Issued Bank</label>
                                                        <select name="issued_bank" id=""
                                                            class="form-control form-control-sm issued_bank">
                                                            <option value="">Select Issued Bank</option>
                                                            <option value="1">AB Bank</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="input1" class="form-label">
                                                            Referece Number</label>
                                                        <input type="input" class="form-control form-control-sm"
                                                            id="reference_num" v-model="form.reference_num" name="reference_num"
                                                            placeholder="Enter Reference Number">
                                                    </div>
                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece file</label>
                                                        <input type="file" class="form-control form-control-sm"
                                                            id="reference_file"  name="reference_file"
                                                            placeholder="Enter ">
                                                    </div>

                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Referece Date</label>
                                                        <input type="date" class="form-control form-control-sm"
                                                            id="reference_date" v-model="form.reference_date" name="reference_date"
                                                            placeholder="Enter Service Charge">
                                                    </div>

                                                    <div class="col-md-6 mt-2">
                                                        <label for="input1" class="form-label">
                                                            Remarks</label>

                                                        <textarea v-model="form.remarks" class="form-control form-control-sm" id="remarks"
                                                            name="remarks" rows="3"></textarea>

                                                    </div>
                                                    <div class="col-md-6 mt-3">

                                                        <div class="d-flex align-items-center "><img
                                                                src="../../../../../public/theme/appimages/rqf.png"
                                                                height="60" width="60"
                                                                class="border border-1 rounded rounded-2"
                                                                alt="Profile Picture">
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="mb-0"><i
                                                                        class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                                    Uploaded successfully.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="d-flex align-content-start">
                                            <button class="btn btn-sm btn-danger px-4 ms-2">Cancel</button>
                                        </div>
                                        <div class="d-flex align-content-end">

                                            <button type="button" @click="creditReqSave()" class="btn btn-sm btn-info px-4 ms-2">Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
