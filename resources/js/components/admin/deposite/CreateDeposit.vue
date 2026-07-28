<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import AppDatePicker from '../../common/AppDatePicker.vue';
import ImageCropUpload from '../../common/ImageCropUpload.vue';
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { formatNumberWithCommas } from '../../../utils/numberFormat';
import { amountToTakaWords } from '../../../utils/numberToWords';
import Select2 from '../../common/Select2.vue';
import ImageUploader from '../../common/ImageUploader.vue';
import AppButton from '../../common/AppButton.vue';

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

const errors = reactive({
    payment_acc:      null,
    requested_amount: null,
    reference_number: null,
    reference_date:   null,
    issued_bank:      null,
});


const refFiles = ref([]);
const submitting = ref(false);
const chargeRate = ref(0); // % rate from selected payment account
// const paymentAccounts = ref([]);
const issuedBanks = ref([]);
const activeTab = ref('Cash'); 
const allPaymentAccounts = ref([]);


function recalculate() {
    const amt  = parseFloat(form.requested_amount) || 0;
    const rate = parseFloat(chargeRate.value) || 0;
    const charge = Math.round(amt * rate / 100);
    form.service_charge = amt ? charge : '';
    form.total_amount   = amt ? Math.round(amt - charge) : '';
}

watch(() => form.requested_amount, recalculate);

watch(() => form.payment_acc,      v => { if (v)        errors.payment_acc = null; });
watch(() => form.requested_amount, v => { if (v)        errors.requested_amount = null; });
watch(() => form.reference_number, v => { if (v?.trim()) errors.reference_number = null; });
watch(() => form.reference_date,   v => { if (v)        errors.reference_date = null; });
watch(() => form.issued_bank,      v => { if (v)        errors.issued_bank = null; });


const totalAmountDisplay = computed(() => formatNumberWithCommas(form.total_amount, 0) || '0');
const requestedAmountWords = computed(() => amountToTakaWords(form.requested_amount));

const showZeroChargeNotice = computed(() => {
    if (!form.payment_acc) return false;
    return (parseFloat(form.service_charge) || 0) === 0;
});

onMounted(() => {
    // $('.payment_acc').on('change', function () {
    //     form.payment_acc = $(this).val();
    //     var selected = $(this).select2('data')[0];
    //     chargeRate.value = (selected && selected.service_charge != null) ? selected.service_charge : 0;
    //     recalculate();
    // });
    $('.issued_bank').on('change', function () { form.issued_bank  = $(this).val(); });
    loadPaymentAccounts();
    loadIssuedBanks();
});

function validate(type) {
    // Reset all
    Object.keys(errors).forEach(k => errors[k] = null);

    if (activeTab.value !== 'Credit_Request' && !form.payment_acc)
        errors.payment_acc = 'Please select a payment account.';
    if (!String(form.requested_amount).trim()) 
        errors.requested_amount = 'Please enter the request amount.';
    if (!form.reference_date) 
        errors.reference_date = 'Please select a reference date.'; 
    if (!form.reference_number) 
        errors.reference_number = 'Please enter a reference number.';

    const tabsWithIssuedBank = ['MFS', 'Cheque', 'Bank_Transfer', 'Credit_Request'];
    if (tabsWithIssuedBank.includes(type) && !form.issued_bank) {
        errors.issued_bank = 'Please select an issued bank.';
    }
    

    return !Object.values(errors).some(Boolean);
}


async function loadPaymentAccounts() {
    // try {
    //     const response = await axiosInstance.get('getAllPaymentAccount');
    //     const options = response.data.map(v => ({
    //         id: v.id,
    //         text: `${v.name} ${v.branch} ${v.acc_no}`,
    //         bank_name: v.name,
    //         acc_no: v.acc_no,
    //         branch: v.branch,
    //         service_charge: v.service_charge,
    //     }));

    //     function paymentAccTemplate(option) {
    //         if (!option.id) return option.text;
    //         return $(`<div class="pa-option">
    //             <div><strong>${option.bank_name}</strong> <span class="pa-sep">|</span> <span class="pa-branch">${option.branch ?? '—'}</span></div>
    //             <small>${option.acc_no} <span class="pa-sep">|</span> Charge: <strong>${option.service_charge ?? 0}%</strong></small>
    //         </div>`);
    //     }

    //     function paymentAccSelection(option) {
    //         if (!option.id) return option.text;
    //         return $(`<span><strong>${option.bank_name}</strong> — ${option.acc_no} | Charge: ${option.service_charge ?? 0}%</span>`);
    //     }

    //     $('.payment_acc').select2({
    //         placeholder: '=Select=',
    //         theme: 'bootstrap-5',
    //         width: '100%',
    //         allowClear: true,
    //         data: options,
    //         templateResult: paymentAccTemplate,
    //         templateSelection: paymentAccSelection,
    //     });
    // } catch {}

    try {
        const response = await axiosInstance.get('getAllPaymentAccount');
        //console.log('Payment Accounts:', response.data);
        allPaymentAccounts.value = response.data.map(v => ({
            id:             v.id,
            text:           `${v.name} | ${v.acc_no} | ${v.branch || '-'} | Charge: ${v.service_charge || 0}%`,
            bank_name:      v.name,
            acc_no:         v.acc_no,
            branch:         v.branch,
            service_charge: v.service_charge,
            acc_type:       v.acc_type,
        }))
        .sort((a, b) => a.bank_name.localeCompare(b.bank_name));
    } catch {
        if (window.Notification?.showToast) {
            window.Notification.showToast('e', 'Failed to load payment accounts.');
        }
    }
}

async function loadIssuedBanks() {
    try {
        const response = await axiosInstance.get('getBankMFS');
        //console.log('Issued Banks:', response.data.data);
        issuedBanks.value = response.data.data
            .filter(b => b.status == 1)           // only active banks
            .map(b => ({
                id:    b.idd,
                label: b.name,
            }))
            .sort((a, b) => a.label.localeCompare(b.label)); 
    } catch {
        if (window.Notification?.showToast) {
            window.Notification.showToast('e', 'Failed to load issued banks.');
        }
    }
}

const paymentAccounts = computed(() => {
    if (!activeTab.value) return allPaymentAccounts.value;

    return allPaymentAccounts.value.filter(account => {
        const type = (account.acc_type || '').trim().toLowerCase();

        switch (activeTab.value) {
        
            case 'MFS':
                return type === 'mfs';
            case 'Cheque':
            case 'Bank_Transfer':
                return type === 'bank' || type === 'cheque' || type === 'bank transfer';
            default:
                return true;
        }
    });
});



function onPaymentAccChange(selectedId) {
    form.payment_acc = selectedId;
    const selected = paymentAccounts.value.find(p => p.id === selectedId);
    chargeRate.value = (selected && selected.service_charge != null) ? selected.service_charge : 0;
    recalculate();
}
function onIssuedBankChange(selectedId) {
    form.issued_bank = selectedId;
}


function resetForm() {
    // Reset form fields
    form.payment_acc      = '';
    form.requested_amount = '';
    form.service_charge   = '';
    form.total_amount     = '';
    form.reference_number = '';
    form.reference_date   = todayDisplay();
    form.remarks          = '';
    form.issued_bank      = '';
    chargeRate.value      = 0;
    refFiles.value        = []; 

    // Reset all errors
    Object.keys(errors).forEach(k => errors[k] = null);
}


async function submitForm(type) {
    if (!validate(type)) return;
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
        if (refFiles.value.length > 0) {
            fd.append('referenceFile', refFiles.value[0].file);
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
                            <a class="nav-link active" data-bs-toggle="tab" href="#cash_tab" role="tab" 
                            @click="activeTab = 'Cash'; resetForm()">
                                <div class="tab-title">Cash</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#mfs_tab" role="tab" 
                            @click="activeTab = 'MFS'; resetForm()">
                                <div class="tab-title">MFS</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#cheque_tab" role="tab" 
                            @click="activeTab = 'Cheque'; resetForm()">
                                <div class="tab-title">Cheque/DD</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#bank_transfer_tab" role="tab" 
                            @click="activeTab = 'Bank_Transfer'; resetForm()">
                                <div class="tab-title">Bank Transfer</div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#credit_req_tab" role="tab" 
                            @click="activeTab = 'Credit_Request'; resetForm()">
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
                                                    <label class="form-label">Payment Account
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <!-- <select class="payment_acc form-control form-control-sm">
                                                        <option value="">Choose...</option>
                                                    </select> -->
                                                    <div :class="{ 'select2-error': errors.payment_acc }">
                                                        <Select2 v-model="form.payment_acc" :options="paymentAccounts"
                                                            value-key="id" label-key="text"
                                                            placeholder="=Select Payment Account=" :clearable="true"
                                                            @update:modelValue="onPaymentAccChange" />
                                                    </div>
                                                    <div v-if="errors.payment_acc" class="invalid-feedback d-block">
                                                        {{ errors.payment_acc }}
                                                    </div>
                                                    
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" :class="{ 'is-invalid': errors.requested_amount }"/>
                                                    <div v-if="errors.requested_amount" class="invalid-feedback d-block">
                                                        {{ errors.requested_amount }}
                                                    </div>
                                                    <small v-if="requestedAmountWords" class="text-muted d-block mt-2">{{ requestedAmountWords }}</small>
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="totalAmountDisplay" readonly />
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
                                                    <label class="form-label">Reference Number
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm" v-model="form.reference_number" placeholder="Enter Reference Number"
                                                        :class="{ 'is-invalid': errors.reference_number }" />
                                                    <div v-if="errors.reference_number" class="invalid-feedback d-block">
                                                        {{ errors.reference_number }}
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Date
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" 
                                                        :full-width="true" :clear-button="true" :enable-time="false"
                                                        :input-class="errors.reference_date ? 'form-control is-invalid' : 'form-control'" />
                                                    
                                                    <div v-if="errors.reference_date" class="invalid-feedback d-block">
                                                        {{ errors.reference_date }}
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <!-- <ImageCropUpload
                                                            v-model="refFile"
                                                            :max-file-size-mb="2"
                                                            accept="image/jpeg,image/png,image/webp"
                                                            crop-modal-title="Crop Reference Image"
                                                            shape="square"
                                                            :free-aspect="true"
                                                        /> -->
                                                        <ImageUploader v-model="refFiles" :max-files="1"
                                                            preview-size="large" />
                                                        <!-- <span class="text-muted small">
                                                            <template v-if="refFile"><i class="fa fa-circle-check text-success me-1"></i>Image selected — uploads on submit.</template>
                                                            <template v-else>Click box to upload image (JPG, PNG, WebP — max 2 MB)</template>
                                                        </span> -->
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
                                    <div class="d-flex gap-2 justify-content-end">
                                        <AppButton variant="cancel" tag="router-link" :to="{ name: 'depositList' }" />
                                        <AppButton variant="save" label="Submit" :loading="submitting"
                                            loading-text="Submitting..." @click="submitForm('Cash')" />
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
                                                    <label class="form-label">Payment Account
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <div :class="{ 'select2-error': errors.payment_acc }">
                                                        <Select2 v-model="form.payment_acc" :options="paymentAccounts"
                                                        value-key="id" label-key="text"
                                                        placeholder="=Select Payment Account=" :clearable="true"
                                                        @update:modelValue="onPaymentAccChange" />
                                                    </div>
                                                    <div v-if="errors.payment_acc" class="invalid-feedback d-block">
                                                        {{ errors.payment_acc }}
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" :class="{ 'is-invalid': errors.requested_amount }"/>
                                                    <div v-if="errors.requested_amount" class="invalid-feedback d-block">
                                                        {{ errors.requested_amount }}
                                                    </div>
                                                    <small v-if="requestedAmountWords" class="text-muted d-block mt-2">{{ requestedAmountWords }}</small>
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="totalAmountDisplay" readonly />
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
                                                    <label class="form-label">Issued Bank / MFS
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <div :class="{ 'select2-error': errors.issued_bank }">
                                                        <Select2 v-model="form.issued_bank" :options="issuedBanks"
                                                            value-key="id" label-key="label"
                                                            placeholder="=Select Issued Bank="
                                                            @update:modelValue="onIssuedBankChange" />
                                                    </div>
                                                    <div v-if="errors.issued_bank" class="invalid-feedback d-block">
                                                        {{ errors.issued_bank }}
                                                    </div>
                                                    
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm" v-model="form.reference_number" placeholder="Enter Reference Number"
                                                    :class="{ 'is-invalid': errors.reference_number }" />
                                                    <div v-if="errors.reference_number" class="invalid-feedback d-block">
                                                        {{ errors.reference_number }}
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Date</label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" 
                                                    :full-width="true" :clear-button="true" :enable-time="false"
                                                    :input-class="errors.reference_date ? 'form-control is-invalid' : 'form-control'" />
                                                    <div v-if="errors.reference_date" class="invalid-feedback d-block">
                                                        {{ errors.reference_date }}
                                                    </div>
                                                </div>   
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea v-model="form.remarks" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>

                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageUploader v-model="refFiles" :max-files="1"
                                                            preview-size="large" />
                                                        
                                                    </div>
                                                </div>
                                                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <AppButton variant="cancel" @click="router.push({ name: 'depositList' })" />
                                        <AppButton variant="save" label="Submit" :loading="submitting"
                                            loading-text="Submitting..." @click="submitForm('MFS')" />
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
                                                    <label class="form-label">Payment Account
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <div :class="{ 'select2-error': errors.payment_acc }">
                                                        <Select2 v-model="form.payment_acc" :options="paymentAccounts"
                                                        value-key="id" label-key="text"
                                                        placeholder="=Select Payment Account=" :clearable="true"
                                                        @update:modelValue="onPaymentAccChange" />
                                                    </div>
                                                    <div v-if="errors.payment_acc" class="invalid-feedback d-block">
                                                        {{ errors.payment_acc }}
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" :class="{ 'is-invalid': errors.requested_amount }"/>
                                                    <div v-if="errors.requested_amount" class="invalid-feedback d-block">
                                                        {{ errors.requested_amount }}
                                                    </div>
                                                    <small v-if="requestedAmountWords" class="text-muted d-block mt-2">{{ requestedAmountWords }}</small>
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="totalAmountDisplay" readonly />
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
                                                    <label class="form-label">Issued Bank
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <div :class="{ 'select2-error': errors.issued_bank }">
                                                        <Select2 v-model="form.issued_bank" :options="issuedBanks"
                                                        value-key="id" label-key="label"
                                                        placeholder="=Select Issued Bank="
                                                        @update:modelValue="onIssuedBankChange" />
                                                    </div>
                                                    <div v-if="errors.issued_bank" class="invalid-feedback d-block">
                                                        {{ errors.issued_bank }}
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" v-model="form.reference_number" class="form-control form-control-sm" placeholder="Enter Reference Number" 
                                                    :class="{ 'is-invalid': errors.reference_number }"/>
                                                    <div v-if="errors.reference_number" class="invalid-feedback d-block">
                                                        {{ errors.reference_number }}
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference Date
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" 
                                                    :full-width="true" :clear-button="true" :enable-time="false" 
                                                    :input-class="errors.reference_date ? 'form-control is-invalid' : 'form-control'"/>
                                                    <div v-if="errors.reference_date" class="invalid-feedback d-block">
                                                        {{ errors.reference_date }}
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea v-model="form.remarks" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageUploader v-model="refFiles" :max-files="1"
                                                            preview-size="large" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <AppButton variant="cancel" @click="router.push({ name: 'depositList' })" />
                                        <AppButton variant="save" label="Submit" :loading="submitting"
                                            loading-text="Submitting..." @click="submitForm('Cheque')" />
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
                                                    <label class="form-label">Payment Account
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <div>
                                                        <Select2 v-model="form.payment_acc" :options="paymentAccounts"
                                                        value-key="id" label-key="text"
                                                        placeholder="=Select Payment Account=" :clearable="true"
                                                        @update:modelValue="onPaymentAccChange" />
                                                    </div>
                                                    <div v-if="errors.payment_acc" class="invalid-feedback d-block">
                                                        {{ errors.payment_acc }}
                                                    </div>
                                                    
                                                </div>
                                                <div class="col-md-4 mt-2">
                                                    <label class="form-label">Request Amount
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" :class="{ 'is-invalid': errors.requested_amount }"/>
                                                    <div v-if="errors.requested_amount" class="invalid-feedback d-block">
                                                        {{ errors.requested_amount }}
                                                    </div>
                                                    <small v-if="requestedAmountWords" class="text-muted d-block mt-2">{{ requestedAmountWords }}</small>
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3 mt-2">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="totalAmountDisplay" readonly />
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
                                                    <label class="form-label">Issued Bank &amp; MFS
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <div :class="{ 'select2-error': errors.issued_bank }">
                                                        <Select2 v-model="form.issued_bank" :options="issuedBanks"
                                                        value-key="id" label-key="label"
                                                        placeholder="=Select Issued Bank="
                                                        @update:modelValue="onIssuedBankChange" />
                                                    </div>
                                                    <div v-if="errors.issued_bank" class="invalid-feedback d-block">
                                                        {{ errors.issued_bank }}
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm" v-model="form.reference_number" placeholder="Enter Reference Number" 
                                                    :class="{ 'is-invalid': errors.reference_number }"/>
                                                    <div v-if="errors.reference_number" class="invalid-feedback d-block">
                                                        {{ errors.reference_number }}
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference Date
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" 
                                                    :full-width="true" :clear-button="true" :enable-time="false"
                                                    :input-class="errors.reference_date ? 'form-control is-invalid' : 'form-control'" />
                                                    <div v-if="errors.reference_date" class="invalid-feedback d-block">
                                                        {{ errors.reference_date }}
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea class="form-control form-control-sm" v-model="form.remarks" rows="3"></textarea>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageUploader v-model="refFiles" :max-files="1"
                                                            preview-size="large" />
                                                        
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <AppButton variant="cancel" tag="router-link"
                                                :to="{ name: 'depositList' }" />
                                        <AppButton variant="save" label="Submit" :loading="submitting"
                                            loading-text="Submitting..." @click="submitForm('Bank_Transfer')" />
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
                                                    <label class="form-label">Request Amount
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <NumberInput v-model="form.requested_amount" placeholder="0.00" :class="{ 'is-invalid': errors.requested_amount }"/>
                                                    <div v-if="errors.requested_amount" class="invalid-feedback d-block">
                                                        {{ errors.requested_amount }}
                                                    </div>
                                                    <small v-if="requestedAmountWords" class="text-muted d-block mt-2">{{ requestedAmountWords }}</small>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Charge</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="form.service_charge || '0.00'" readonly />
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Total Amount</label>
                                                    <input type="text" class="form-control form-control-sm bg-light" :value="totalAmountDisplay" readonly />
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
                                                    <label class="form-label">Issued Bank
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <div :class="{ 'select2-error': errors.issued_bank }">
                                                        <Select2 v-model="form.issued_bank" :options="issuedBanks"
                                                        value-key="id" label-key="label"
                                                        placeholder="=Select Issued Bank="
                                                        @update:modelValue="onIssuedBankChange" />
                                                    </div>
                                                    <div v-if="errors.issued_bank" class="invalid-feedback d-block">
                                                        {{ errors.issued_bank }}
                                                    </div>
                                            
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Reference Number
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" class="form-control form-control-sm" v-model="form.reference_number" placeholder="Enter Reference Number" 
                                                    :class="{ 'is-invalid': errors.reference_number }"/>
                                                    <div v-if="errors.reference_number" class="invalid-feedback d-block">
                                                        {{ errors.reference_number }}
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference Date</label>
                                                    <AppDatePicker v-model="form.reference_date" :max-date="today" :inline="false" 
                                                    :full-width="true" :clear-button="true" :enable-time="false" 
                                                    :input-class="errors.reference_date ? 'form-control is-invalid' : 'form-control'"/>
                                                    <div v-if="errors.reference_date" class="invalid-feedback d-block">
                                                        {{ errors.reference_date }}
                                                    </div>
                                                </div>

                                                
                                                <div class="col-md-6 mt-2">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea v-model="form.remarks" class="form-control form-control-sm" rows="3"></textarea>
                                                </div>
                                                 <div class="col-md-6 mt-2">
                                                    <label class="form-label">Reference File</label>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <ImageUploader v-model="refFiles" :max-files="1"
                                                            preview-size="large" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <AppButton variant="cancel" @click="router.push({ name: 'depositList' })" />
                                        <AppButton variant="save" label="Submit" :loading="submitting"
                                            loading-text="Submitting..." @click="submitForm('Credit_Request')" />
                                        
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

<style scoped>
.select2-error :deep(.app-select2-control) {
    border-color: var(--bs-form-invalid-border-color);
}
</style>
