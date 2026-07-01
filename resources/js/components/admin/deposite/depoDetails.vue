<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import moment from 'moment';
import { resolveUploadUrl } from '../../../utils/resolveUploadUrl';
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import ZoomImagePreview from '../../common/ZoomImagePreview.vue';

const props = defineProps({
    id: { type: String, required: true },
});

const router = useRouter();
const loading = ref(true);
const deposit = ref(null);
const loadError = ref('');

const planePlaceholder = new URL('../../../../../public/theme/appimages/Plane_origin.svg', import.meta.url).href;

const logoUrl = computed(() => resolveUploadUrl(deposit.value?.logo_path) || planePlaceholder);
const referenceUrl = computed(() => resolveUploadUrl(deposit.value?.reference_file));
const logoBroken = ref(false);

watch(() => deposit.value?.logo_path, () => { logoBroken.value = false; });

const displayLogoUrl = computed(() => (logoBroken.value ? planePlaceholder : logoUrl.value));

const statusClass = computed(() => {
    const status = deposit.value?.status;
    if (status === 'Approved') return 'text-success bg-light-success';
    if (status === 'Rejected' || status === 'Cancelled') return 'text-danger bg-light-danger';
    return 'text-warning bg-light-warning';
});

function formatMoney(value) {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
}

async function loadDeposit() {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await axiosInstance.get(`deposit/${props.id}`);
        deposit.value = res.data?.data ?? null;
        if (!deposit.value) {
            loadError.value = 'Deposit not found.';
        }
    } catch {
        loadError.value = 'Failed to load deposit details.';
        deposit.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(loadDeposit);
</script>

<template>
    <AppBreadcrumbs
        title="Deposit Management"
        :back-to="{ name: 'depositList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Deposit Management', to: { name: 'depositList' } },
            { label: 'View' },
        ]"
    />

    <div v-if="loading" class="text-center py-5 text-muted">
        <i class="fa-solid fa-spinner fa-spin me-2"></i>Loading deposit details...
    </div>

    <div v-else-if="loadError" class="alert alert-danger">{{ loadError }}</div>

    <div v-else-if="deposit" class="row">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-column align-items-center text-center">
                        <img
                            :src="displayLogoUrl"
                            alt="Agent Logo"
                            class="rounded-circle p-1 bg-primary object-fit-cover"
                            width="110"
                            height="110"
                            @error="logoBroken = true"
                        >
                        <div class="mt-3">
                            <h4 class="mb-0">{{ deposit.agent_name }}</h4>
                        </div>
                        <p class="mb-0">
                            <small class="text-blue">{{ deposit.agent_code || '—' }}</small>
                            <template v-if="deposit.iata_number"> | IATA</template>
                        </p>
                    </div>

                    <hr class="my-3">

                    <div class="text-center">
                        Status:
                        <div class="badge rounded-pill p-2 text-uppercase px-3 mt-1" :class="statusClass">
                            <i class="bx bxs-circle me-1"></i>{{ deposit.status }}
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="row text-center">
                        <div class="col-6">
                            Credit:
                            <div class="text-red">
                                <i class="fa fa-bangladeshi-taka-sign" style="font-size: 10px;"></i>
                                {{ formatMoney(deposit.credit_balance) }}
                            </div>
                        </div>
                        <div class="col-6">
                            Current:
                            <div class="text-nblue">
                                <i class="fa fa-bangladeshi-taka-sign" style="font-size: 10px;"></i>
                                {{ formatMoney(deposit.net_balance) }}
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <table class="table table-responsive table-borderless table-purple bdr mb-0">
                        <tbody>
                            <tr>
                                <td>Requested Amount:</td>
                                <td>
                                    <i class="fa fa-bangladeshi-taka-sign" style="font-size: 10px;"></i>
                                    {{ formatMoney(deposit.amount) }}
                                </td>
                            </tr>
                            <tr>
                                <td>(-) Charge:</td>
                                <td>
                                    <i class="fa fa-bangladeshi-taka-sign" style="font-size: 10px;"></i>
                                    {{ formatMoney(deposit.charge) }}
                                </td>
                            </tr>
                            <tr class="sub-total-bdr">
                                <td>Total Amount:</td>
                                <td>
                                    <b>
                                        <i class="fa fa-bangladeshi-taka-sign" style="font-size: 14px; color: #7239ea;"></i>
                                        {{ formatMoney(deposit.total) }}
                                    </b>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <hr v-if="referenceUrl" class="my-3">

                    <div v-if="referenceUrl" class="text-center">
                        <div class="small text-muted mb-2">Reference File</div>
                        <ZoomImagePreview
                            :src="referenceUrl"
                            alt="Reference"
                            :thumb-width="220"
                            :thumb-height="160"
                            rounded
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="m-0 p-0" style="border-left: 5px solid #7239ea;">&nbsp; Payment Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm table-responsive mb-0">
                        <tbody>
                            <tr>
                                <td width="50%"><b>Payment Term:</b><p class="m-0">{{ deposit.type || '—' }}</p></td>
                                <td width="50%"><b>Payment Account:</b><p class="m-0">{{ deposit.payment_bank || '—' }}</p></td>
                            </tr>
                            <tr>
                                <td><b>Branch:</b><p class="m-0">{{ deposit.payment_branch || '—' }}</p></td>
                                <td v-if="deposit.issued_bank_name"><b>Issued Bank:</b><p class="m-0">{{ deposit.issued_bank_name }}</p></td>
                                <td v-else><b>Account No:</b><p class="m-0">{{ deposit.payment_acc_no || '—' }}</p></td>
                            </tr>
                            <tr>
                                <td><b>Reference Number:</b><p class="m-0">{{ deposit.reference_no || '—' }}</p></td>
                                <td><b>Reference Date:</b><p class="m-0">{{ deposit.reference_date ? moment(deposit.reference_date).format('DD-MMM-YYYY') : '—' }}</p></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="deposit.remarks && deposit.remarks !== 'null'" class="card mb-3">
                <div class="card-header">
                    <h5 class="m-0 p-0" style="border-left: 5px solid #39eadfcf;">&nbsp; Remarks</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ deposit.remarks }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="m-0 p-0" style="border-left: 5px solid #dfcd24cf;">&nbsp; Request Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm table-responsive mb-0">
                        <tbody>
                            <tr>
                                <td width="50%"><b>Requested By:</b><p class="m-0">{{ deposit.requested_by || deposit.agent_name }}</p></td>
                                <td width="50%">
                                    <b>Requested Date &amp; Time:</b>
                                    <p class="m-0">
                                        {{ moment(deposit.created_at).format('DD-MMM-YYYY') }} |
                                        {{ moment(deposit.created_at).format('hh:mm A') }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="m-0 p-0" style="border-left: 5px solid #00d54a;">&nbsp; Approval Info</h5>
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-1">Status</div>
                    <div class="badge rounded-pill p-2 text-uppercase px-3 mb-3" :class="statusClass">
                        {{ deposit.status }}
                    </div>
                    <div class="small text-muted mb-1">Requested By</div>
                    <div class="mb-3">{{ deposit.requested_by || deposit.agent_name }}</div>
                    <div class="small text-muted mb-1">Date</div>
                    <div class="mb-3">{{ moment(deposit.created_at).format('DD MMM YYYY hh:mm A') }}</div>
                    <div class="small text-muted mb-1">Total Amount</div>
                    <div class="fw-semibold text-success">৳ {{ formatMoney(deposit.total) }}</div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-sm btn-secondary w-100" @click="router.push({ name: 'depositList' })">
                        Back to List
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-red { color: red; }
.text-nblue { color: #00bfff; }
.text-blue { color: #3b82f6; }

.table-purple {
    --bs-table-color: #000;
    --bs-table-bg: #f1ecfd;
    --bs-table-border-color: #fff;
    color: var(--bs-table-color);
    border-color: var(--bs-table-border-color);
}

[data-bs-theme="dark"] .table-purple {
    --bs-table-color: #e2e8f0;
    --bs-table-bg: rgba(114, 57, 234, 0.12);
    --bs-table-border-color: #334155;
}

.bdr {
    border-radius: 6px;
    overflow: hidden;
}

.sub-total-bdr {
    border-top: 1px solid #1d1d1d4d !important;
}

</style>
