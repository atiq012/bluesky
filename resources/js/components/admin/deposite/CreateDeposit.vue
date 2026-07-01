<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import AppDatePicker from '../../common/AppDatePicker.vue';
import ImageCropUpload from '../../common/ImageCropUpload.vue';
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

const router = useRouter();

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function todayDisplay() {
    const d = new Date();
    return `${String(d.getDate()).padStart(2,'0')}-${MONTHS[d.getMonth()]}-${d.getFullYear()}`;
}

const today = new Date();
today.setHours(23, 59, 59, 999);

const form = reactive({
    payment_acc: '',
    requested_amount: '',
    service_charge: '',
    total_amount: '',
    reference_number: '',
    reference_date: todayDisplay(),
    remarks: '',
    issued_bank: '',
});

const refFile    = ref(null);
const submitting = ref(false);
const chargeRate = ref(0); // % rate from selected payment account

function recalculate() {
    const amt  = parseFloat(form.requested_amount) || 0;
    const rate = parseFloat(chargeRate.value) || 0;
    const charge = Math.round(amt * rate / 100);
    form.service_charge = amt ? charge : '';
    form.total_amount   = amt ? Math.round(amt - charge) : '';
}

watch(() => form.requested_amount, recalculate);

const showZeroChargeNotice = computed(() => {
    if (!form.payment_acc) return false;
    return (parseFloat(form.service_charge) || 0) === 0;
});

onMounted(() => {
    $('.payment_acc').on('change', function () {
        form.payment_acc = $(this).val();
        var selected = $(this).select2('data')[0];
        chargeRate.value = (selected && selected.service_charge != null) ? selected.service_charge : 0;
        recalculate();
    });
    $('.issued_bank').on('change', function () { form.issued_bank  = $(this).val(); });
    loadPaymentAccounts();
});

async function loadPaymentAccounts() {
    try {
        const response = await axiosInstance.get('getAllPaymentAccount');
        const options = response.data.map(v => ({
            id: v.id,
            text: `${v.name} ${v.branch} ${v.acc_no}`,
            bank_name: v.name,
            acc_no: v.acc_no,
            branch: v.branch,
            service_charge: v.service_charge,
        }));

        function paymentAccTemplate(option) {
            if (!option.id) return option.text;
            return $(`<div class="pa-option">
                <div><strong>${option.bank_name}</strong> <span class="pa-sep">|</span> <span class="pa-branch">${option.branch ?? '—'}</span></div>
                <small>${option.acc_no} <span class="pa-sep">|</span> Charge: <strong>${option.service_charge ?? 0}%</strong></small>
            </div>`);
        }

        function paymentAccSelection(option) {
            if (!option.id) return option.text;
            return $(`<span><strong>${option.bank_name}</strong> — ${option.acc_no} | Charge: ${option.service_charge ?? 0}%</span>`);
        }

        $('.payment_acc').select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            data: options,
            templateResult: paymentAccTemplate,
            templateSelection: paymentAccSelection,
        });
    } catch {}
}

async function submitForm(type) {
    try {
        submitting.value = true;
        const fd = new FormData();
        fd.append('payment_type', type);
        fd.append('payment_acc', form.payment_acc);
        fd.append('requested_amount', form.requested_amount);
        fd.append('service_charge', form.service_charge);
        fd.append('total_amount', form.total_amount);
        fd.append('reference_number', form.reference_number);
        fd.append('reference_date', form.reference_date);
        fd.append('remarks', form.remarks);
        fd.append('issued_bank', form.issued_bank);
        if (refFile.value) {
            fd.append('referenceFile', refFile.value);
        }
        const res = await axiosInstance.post('/deposit/save', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data.message) {
            Notification.showToast('s', res.data.message);
            router.push({ name: 'depositList' });
        }
    } catch (error) {
        ErrorCatch.CatchError(error);
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <AppBreadcrumbs
        title="Deposit Management"
        :back-to="{ name: 'depositList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Deposit Management', to: { name: 'depositList' } },
            { label: 'New Deposit Request' },
        ]"
    />

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs nav-primary mb-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-bs-toggle="tab" href="#cash_tab" role="tab">
                                <div class="tab-title">Cash</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#mfs_tab" role="tab">
                                <div class="tab-title">MFS</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#cheque_tab" role="tab">
                                <div class="tab-title">Cheque/DD</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#bank_transfer_tab" role="tab">
                                <div class="tab-title">Bank Transfer</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#credit_req_tab" role="tab">
                                <div class="tab-title">Credit Request</div>
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">

                        <!-- ===== CASH ===== -->
                        <div class="tab-pane fade active show" id="cash_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Payment Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <label class="form-label">Payment Account</label>
                                                    <select class="payment_acc form-control form-control-sm">
                                                        <option value="">Choose...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount</label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.total_amount || '0.00'" readonly />
                                                </div>
                                                <div v-if="showZeroChargeNotice" class="col-12 mt-2">
                                                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0 py-2 small" role="alert">
                                                        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
                                                        <span>Charge is currently 0, but it may still apply as it is subject to bank action. If the bank charges a fee, the charge will be applied on approval.</span>
                                                    </div>
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
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Cash Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number</label>
                                                    <input type="text" class="form-control form-control-sm" v-model="form.reference_number" placeholder="Enter Reference Number" />
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Date</label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageCropUpload
                                                            v-model="refFile"
                                                            :max-file-size-mb="2"
                                                            accept="image/jpeg,image/png,image/webp"
                                                            crop-modal-title="Crop Reference Image"
                                                            shape="square"
                                                        />
                                                        <span class="text-muted small">
                                                            <template v-if="refFile"><i class="fa fa-circle-check text-success me-1"></i>Image selected — uploads on submit.</template>
                                                            <template v-else>Click box to upload image (JPG, PNG, WebP — max 2 MB)</template>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea v-model="form.remarks" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2">
                                        <router-link :to="{ name: 'depositList' }" class="btn btn-sm btn-secondary px-4">Cancel</router-link>
                                        <button type="button" class="btn btn-sm btn-info px-4" :disabled="submitting" @click="submitForm('Cash')">
                                            <template v-if="submitting"><i class="fa-solid fa-spinner fa-spin me-1"></i>Submitting...</template>
                                            <template v-else>Submit</template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== MFS ===== -->
                        <div class="tab-pane fade" id="mfs_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Payment Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <label class="form-label">Payment Account</label>
                                                    <select class="payment_acc form-control form-control-sm">
                                                        <option value="">Choose...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount</label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.total_amount || '0.00'" readonly />
                                                </div>
                                                <div v-if="showZeroChargeNotice" class="col-12 mt-2">
                                                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0 py-2 small" role="alert">
                                                        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
                                                        <span>Charge is currently 0, but it may still apply as it is subject to bank action. If the bank charges a fee, the charge will be applied on approval.</span>
                                                    </div>
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
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; MFS Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Issued Bank / MFS</label>
                                                    <select class="issued_bank form-control form-control-sm">
                                                        <option value="">Select Issued Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Date</label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageCropUpload
                                                            v-model="refFile"
                                                            :max-file-size-mb="2"
                                                            accept="image/jpeg,image/png,image/webp"
                                                            crop-modal-title="Crop Reference Image"
                                                            shape="square"
                                                        />
                                                        <span class="text-muted small">
                                                            <template v-if="refFile"><i class="fa fa-circle-check text-success me-1"></i>Image selected — uploads on submit.</template>
                                                            <template v-else>Click box to upload image (JPG, PNG, WebP — max 2 MB)</template>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea v-model="form.remarks" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2">
                                        <router-link :to="{ name: 'depositList' }" class="btn btn-sm btn-secondary px-4">Cancel</router-link>
                                        <button type="button" class="btn btn-sm btn-info px-4" :disabled="submitting" @click="submitForm('MFS')">
                                            <template v-if="submitting"><i class="fa-solid fa-spinner fa-spin me-1"></i>Submitting...</template>
                                            <template v-else>Submit</template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== CHEQUE/DD ===== -->
                        <div class="tab-pane fade" id="cheque_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Payment Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <label class="form-label">Payment Account</label>
                                                    <select class="payment_acc form-control form-control-sm">
                                                        <option value="">Choose...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount</label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.total_amount || '0.00'" readonly />
                                                </div>
                                                <div v-if="showZeroChargeNotice" class="col-12 mt-2">
                                                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0 py-2 small" role="alert">
                                                        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
                                                        <span>Charge is currently 0, but it may still apply as it is subject to bank action. If the bank charges a fee, the charge will be applied on approval.</span>
                                                    </div>
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
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Cheque/DD Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Issued Bank</label>
                                                    <select class="issued_bank form-control form-control-sm">
                                                        <option value="">Select Issued Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number</label>
                                                    <input type="text" v-model="form.reference_number" class="form-control form-control-sm" placeholder="Enter Reference Number" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference Date</label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageCropUpload
                                                            v-model="refFile"
                                                            :max-file-size-mb="2"
                                                            accept="image/jpeg,image/png,image/webp"
                                                            crop-modal-title="Crop Reference Image"
                                                            shape="square"
                                                        />
                                                        <span class="text-muted small">
                                                            <template v-if="refFile"><i class="fa fa-circle-check text-success me-1"></i>Image selected — uploads on submit.</template>
                                                            <template v-else>Click box to upload image (JPG, PNG, WebP — max 2 MB)</template>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea v-model="form.remarks" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2">
                                        <router-link :to="{ name: 'depositList' }" class="btn btn-sm btn-secondary px-4">Cancel</router-link>
                                        <button type="button" class="btn btn-sm btn-info px-4" :disabled="submitting" @click="submitForm('Cheque')">
                                            <template v-if="submitting"><i class="fa-solid fa-spinner fa-spin me-1"></i>Submitting...</template>
                                            <template v-else>Submit</template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== BANK TRANSFER ===== -->
                        <div class="tab-pane fade" id="bank_transfer_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Payment Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <label class="form-label">Payment Account</label>
                                                    <select class="payment_acc form-control form-control-sm">
                                                        <option value="">Choose...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount</label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.total_amount || '0.00'" readonly />
                                                </div>
                                                <div v-if="showZeroChargeNotice" class="col-12 mt-2">
                                                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0 py-2 small" role="alert">
                                                        <i class="fa-solid fa-circle-info mt-1" aria-hidden="true"></i>
                                                        <span>Charge is currently 0, but it may still apply as it is subject to bank action. If the bank charges a fee, the charge will be applied on approval.</span>
                                                    </div>
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
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Bank Transfer Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Issued Bank &amp; MFS</label>
                                                    <select class="issued_bank form-control form-control-sm">
                                                        <option value="">Select Issued Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number</label>
                                                    <input type="text" class="form-control form-control-sm" v-model="form.reference_number" placeholder="Enter Reference Number" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageCropUpload
                                                            v-model="refFile"
                                                            :max-file-size-mb="2"
                                                            accept="image/jpeg,image/png,image/webp"
                                                            crop-modal-title="Crop Reference Image"
                                                            shape="square"
                                                        />
                                                        <span class="text-muted small">
                                                            <template v-if="refFile"><i class="fa fa-circle-check text-success me-1"></i>Image selected — uploads on submit.</template>
                                                            <template v-else>Click box to upload image (JPG, PNG, WebP — max 2 MB)</template>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference Date</label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea class="form-control form-control-sm" v-model="form.remarks" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2">
                                        <router-link :to="{ name: 'depositList' }" class="btn btn-sm btn-secondary px-4">Cancel</router-link>
                                        <button type="button" class="btn btn-sm btn-info px-4" :disabled="submitting" @click="submitForm('Bank_Transfer')">
                                            <template v-if="submitting"><i class="fa-solid fa-spinner fa-spin me-1"></i>Submitting...</template>
                                            <template v-else>Submit</template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== CREDIT REQUEST ===== -->
                        <div class="tab-pane fade" id="credit_req_tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Payment Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">Request Amount</label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" />
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.total_amount || '0.00'" readonly />
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
                                            <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Credit Request Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Issued Bank</label>
                                                    <select class="issued_bank form-control form-control-sm">
                                                        <option value="">Select Issued Bank</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number</label>
                                                    <input type="text" class="form-control form-control-sm" v-model="form.reference_number" placeholder="Enter Reference Number" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageCropUpload
                                                            v-model="refFile"
                                                            :max-file-size-mb="2"
                                                            accept="image/jpeg,image/png,image/webp"
                                                            crop-modal-title="Crop Reference Image"
                                                            shape="square"
                                                        />
                                                        <span class="text-muted small">
                                                            <template v-if="refFile"><i class="fa fa-circle-check text-success me-1"></i>Image selected — uploads on submit.</template>
                                                            <template v-else>Click box to upload image (JPG, PNG, WebP — max 2 MB)</template>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference Date</label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" :full-width="true" :clear-button="true" :enable-time="false" />
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea v-model="form.remarks" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2">
                                        <router-link :to="{ name: 'depositList' }" class="btn btn-sm btn-secondary px-4">Cancel</router-link>
                                        <button type="button" class="btn btn-sm btn-info px-4" :disabled="submitting" @click="submitForm('Credit_Request')">
                                            <template v-if="submitting"><i class="fa-solid fa-spinner fa-spin me-1"></i>Submitting...</template>
                                            <template v-else>Submit</template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
