<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import moment from 'moment';
import { resolveUploadUrl } from '../../../utils/resolveUploadUrl';
import { formatDisplayAmount } from '../../../utils/numberFormat';
import DepositPrintModal from './DepositPrintModal.vue';
import ZoomImagePreview from '../../common/ZoomImagePreview.vue';

const props = defineProps({
    id: { type: [String, Number], default: null },
});

const route  = useRoute();
const router = useRouter();

const deposit = ref(null);
const loading = ref(true);
const showPrintModal = ref(false);
const logoBroken = ref(false);

const planePlaceholder = new URL('../../../../../public/theme/appimages/Plane_origin.svg', import.meta.url).href;

const logoUrl = computed(() => resolveUploadUrl(deposit.value?.logo_path));
const referenceUrl = computed(() => resolveUploadUrl(deposit.value?.reference_file));
const displayLogoUrl = computed(() => {
    if (!deposit.value?.logo_path || logoBroken.value) return planePlaceholder;
    return logoUrl.value || planePlaceholder;
});

watch(() => deposit.value?.logo_path, () => { logoBroken.value = false; });

async function loadDeposit() {
    loading.value = true;
    const depositId = props.id || route.params.id || route.params.hash;

    if (!depositId) {
        if (typeof iziToast !== 'undefined') {
            iziToast.error({ title: 'Error', message: 'Deposit ID not provided.' });
        }
        router.push({ name: 'depositList' });
        loading.value = false;
        return;
    }

    try {
        let res;
        try {
            res = await axiosInstance.get(`deposit/${depositId}`);
        } catch {
            res = await axiosInstance.get(`deposit/show/${depositId}`);
        }
        deposit.value = res.data?.data ?? res.data;
        console.log('Deposit data loaded:', deposit.value);
    } catch {
        if (typeof iziToast !== 'undefined') {
            iziToast.error({ title: 'Error', message: 'Deposit not found.' });
        }
        router.push({ name: 'depositList' });
    } finally {
        loading.value = false;
    }
}

function statusConfig(status) {
    switch (status) {
        case 'Approved':
        case 'Paid':
            return { cls: 'text-success bg-light-success', icon: 'fa-solid fa-circle-check' };
        case 'Requested':
        case 'Pending':
            return { cls: 'text-warning bg-light-warning', icon: 'fa-solid fa-clock' };
        case 'Rejected':
        case 'Declined':
        case 'Cancelled':
            return { cls: 'text-danger bg-light-danger', icon: 'fa-solid fa-circle-xmark' };
        default:
            return { cls: 'text-secondary bg-light', icon: 'fa-solid fa-circle' };
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

    <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="deposit" class="deposit-view-page row g-3">
        <!-- Left: Agent Info -->
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex flex-column align-items-center text-center">
                        <div class="agent-logo-wrap mb-2">
                            <img
                                :src="displayLogoUrl"
                                alt="Agent Logo"
                                class="agent-logo"
                                @error="logoBroken = true"
                            />
                        </div>
                        <h5 class="fw-bold mb-0">{{ deposit.agent_name || deposit.agent }}</h5>
                        <p class="mb-0 mt-1">
                            <small class="text-primary fw-semibold">{{ deposit.agent_code || '-' }}</small>
                            <span v-if="deposit.iata_number" class="text-muted ms-1">| IATA</span>
                        </p>
                    </div>

                    <hr class="my-3">

                    <div class="text-center">
                        <span class="text-muted me-1">Status:</span>
                        <span :class="['badge rounded-pill p-2 px-3 text-uppercase', statusConfig(deposit.status).cls]">
                            <i :class="[statusConfig(deposit.status).icon, 'me-1']"></i>
                            {{ deposit.status }}
                        </span>
                        <span
                            v-if="deposit.erp_sync_status === 'failed'"
                            class="badge rounded-pill bg-danger-subtle text-danger ms-1 mt-1 d-inline-block"
                            :title="deposit.erp_sync_error || 'ERP sync pending'"
                        >
                            ERP Pending
                        </span>
                    </div>

                    <hr class="my-2">

                    <div class="row text-center gx-0">
                        <div class="col-6 border-end">
                            <div class="small text-muted">Credit</div>
                            <div class="fw-semibold text-danger">
                                <i class="fa-solid fa-bangladeshi-taka-sign" style="font-size:10px"></i>
                                {{ formatDisplayAmount(deposit.credit_balance) }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Current</div>
                            <div class="fw-semibold text-primary">
                                <i class="fa-solid fa-bangladeshi-taka-sign" style="font-size:10px"></i>
                                {{ formatDisplayAmount(deposit.net_balance) }}
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <table class="table table-borderless table-sm table-purple bdr mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted small">Requested Amount:</td>
                                <td class="text-end small">
                                    <i class="fa-solid fa-bangladeshi-taka-sign" style="font-size:10px"></i>
                                    {{ formatDisplayAmount(deposit.amount) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">(-) Charge: 
                                    [{{ deposit.payment_service_charge ?? '0' }}%]
                                </td>
                                <td class="text-end small">
                                    <i class="fa-solid fa-bangladeshi-taka-sign" style="font-size:10px"></i>
                                    {{ formatDisplayAmount(deposit.charge) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">(-) Credit Amount:</td>
                                <td class="text-end small">
                                    <i class="fa-solid fa-bangladeshi-taka-sign" style="font-size:10px"></i>
                                    {{ formatDisplayAmount(deposit.credit_balance) }}
                                </td>
                            </tr>
                            <tr class="sub-total-bdr">
                                <td class="small fw-semibold">Total Amount:</td>
                                <td class="text-end fw-bold" style="color:#7239ea">
                                    <i class="fa-solid fa-bangladeshi-taka-sign" style="font-size:12px"></i>
                                    {{ formatDisplayAmount(deposit.total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <hr class="my-2">

                    <div class="text-center">
                        <span class="small fw-semibold d-block mb-2">Reference File</span>
                        <div class="reference-file-wrap">
                            <ZoomImagePreview
                                v-if="referenceUrl"
                                :src="referenceUrl"
                                alt="Reference"
                                thumb-width="100%"
                                thumb-height="100%"
                                class="reference-file-thumb"
                            />
                            <span v-else class="text-muted small">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle: Payment + Remarks + Request Info -->
        <div class="col-lg-6">
            <!-- Payment Information -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 section-header" style="--hdr-color:#7239ea">Payment Information</h6>
                    <button class="btn btn-sm btn-outline-secondary" @click="showPrintModal = true">
                        <i class="fa-solid fa-print me-1"></i>Print
                    </button>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr>
                                <td width="50%">
                                    <div class="field-label">Payment Term:</div>
                                    <div class="field-value">{{ deposit.type || '-' }}</div>
                                </td>
                                <td width="50%">
                                    <div class="field-label">Payment Account:</div>
                                    <div class="field-value">{{ deposit.payment_bank || deposit.bank_name || deposit.bank || '-' }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="field-label">Branch:</div>
                                    <div class="field-value">{{ deposit.payment_branch || deposit.branch || '-' }}</div>
                                </td>
                                <td>
                                    <div class="field-label">Issued Bank:</div>
                                    <div class="field-value">{{ deposit.issued_bank_name || deposit.issued_bank || '-' }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="field-label">Reference Number:</div>
                                    <div class="field-value">{{ deposit.reference_no || '-' }}</div>
                                </td>
                                <td>
                                    <div class="field-label">Reference Date:</div>
                                    <div class="field-value">
                                        {{ deposit.reference_date ? moment(deposit.reference_date).format('DD-MMM-YYYY') : '-' }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Remarks -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="m-0 section-header" style="--hdr-color:#39eadf">Remarks</h6>
                </div>
                <div class="card-body">
                    <p class="mb-0 text-muted">{{ deposit.remarks && deposit.remarks !== 'null' ? deposit.remarks : '-' }}</p>
                </div>
            </div>

            <!-- Request Information -->
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 section-header" style="--hdr-color:#dfcd24">Request Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr>
                                <td width="50%">
                                    <div class="field-label">Requested By:</div>
                                    <div class="field-value">{{ deposit.requested_by || deposit.created_by || deposit.agent_name || '-' }}</div>
                                </td>
                                <td width="50%">
                                    <div class="field-label">Requested Date & Time:</div>
                                    <div class="field-value">
                                        {{ deposit.created_at ? moment(deposit.created_at).format('DD-MMM-YYYY | hh:mm A') : '-' }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Approval Info -->
        <div class="col-lg-3">
            <div class="card approval-card">
                <div class="card-header approval-card-header">
                    <i class="fa-solid fa-shield-check me-2"></i>Approval Info
                </div>
                <div class="card-body p-0">
                    <!-- Status -->
                    <div class="approval-row">
                        <div class="approval-row-label">
                            <i class="fa-solid fa-circle-dot me-2 text-muted"></i>Status
                        </div>
                        <div class="mt-1">
                            <span :class="['approval-status-badge', `status-${(deposit.status || '').toLowerCase()}`]">
                                <i :class="[statusConfig(deposit.status).icon, 'me-1']"></i>
                                {{ deposit.status || '-' }}
                            </span>
                            <span
                                v-if="deposit.erp_sync_status === 'failed'"
                                class="badge bg-danger-subtle text-danger ms-1 mt-1"
                                :title="deposit.erp_sync_error || 'ERP sync pending'"
                            >
                                ERP Pending
                            </span>
                        </div>
                    </div>

                    <div class="approval-divider"></div>

                    <!-- Approved By -->
                    <div class="approval-row">
                        <div class="approval-row-label">
                            <i class="fa-solid fa-user-check me-2 text-muted"></i>Processed By
                        </div>
                        <div class="approval-row-value">
                            {{ deposit.approved_by || deposit.updated_by || '—' }}
                        </div>
                    </div>

                    <div class="approval-divider"></div>

                    <!-- Requested By -->
                    <!-- <div class="approval-row">
                        <div class="approval-row-label">
                            <i class="fa-solid fa-user me-2 text-muted"></i>Requested By
                        </div>
                        <div class="approval-row-value">{{ deposit.requested_by || deposit.created_by || deposit.agent_name || '—' }}</div>
                    </div> -->

                    <!-- <div class="approval-divider"></div> -->

                    <!-- Requested At -->
                    <div class="approval-row"> 
                        <div class="approval-row-label">
                            <i class="fa-regular fa-calendar me-2 text-muted"></i>Processed At
                        </div>
                        <div class="approval-row-value">
                            {{ (deposit.updated_at && deposit.approved_by)? moment(deposit.updated_at).format('DD MMM YYYY') : '—' }}
                            <span v-if="deposit.updated_at && deposit.approved_by" class="approval-time">
                                {{ moment(deposit.updated_at).format('hh:mm A') }}
                            </span>
                        </div>
                    </div>

                    <div class="approval-divider"></div>

                    <!-- Amount Breakdown -->
                    <div class="approval-row">
                        <div class="approval-row-label">
                            <i class="fa-solid fa-bangladeshi-taka-sign me-2 text-muted"></i>Total Amount
                        </div>
                        <div class="approval-amount">
                            ৳ {{ formatDisplayAmount(deposit.total) }}
                        </div>
                    </div>

                    <div class="approval-divider"></div>

                    <!-- Charge -->
                    <!-- <div class="approval-row">
                        <div class="approval-row-label">
                            <i class="fa-solid fa-percent me-2 text-muted"></i>Service Charge
                        </div>
                        <div class="approval-row-value text-warning">
                            {{ deposit.payment_service_charge ?? '0' }}% &nbsp;·&nbsp; ৳ {{ formatDisplayAmount(deposit.charge) }}
                        </div>
                    </div> -->
                </div>

                <div class="approval-card-footer">
                    <button
                        class="btn btn-sm w-100 approval-back-btn"
                        @click="router.push({ name: 'depositList' })"
                    >
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to List
                    </button>
                </div>
            </div>
        </div>
    </div>

    <DepositPrintModal
        :visible="showPrintModal"
        :deposit="deposit"
        :deposit-hash="route.params.hash || route.params.id || props.id || ''"
        @close="showPrintModal = false"
    />
</template>

<style scoped>
.agent-logo-wrap {
    width: 110px;
    height: 110px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9f9f9;
}
.agent-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.agent-logo-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.reference-file-wrap {
    width: 100%;
    height: 170px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9f9f9;
    padding: 8px;
}
.reference-file-thumb {
    width: 100% !important;
    height: 100% !important;
    object-fit: contain;
}

.table-purple {
    --bs-table-bg: #f1ecfd;
    --bs-table-border-color: #fff;
}
.bdr { border-radius: 6px; overflow: hidden; }
.sub-total-bdr { border-top: 1px solid rgba(0,0,0,.15) !important; }

.section-header {
    border-left: 5px solid var(--hdr-color);
    padding-left: 8px;
}

.field-label { font-size: 0.75rem; color: #8a8a8a; margin-bottom: 2px; }
.field-value { font-size: 0.875rem; font-weight: 500; }

/* Approval card */
.approval-card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,.08); border-radius: 12px; overflow: hidden; }

.approval-card-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    padding: 14px 18px;
    border: none;
}

.approval-row {
    padding: 14px 18px;
}
.approval-row-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #9ca3af;
    margin-bottom: 4px;
}
.approval-row-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
}
.approval-time {
    display: block;
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 400;
}
.approval-amount {
    font-size: 1.1rem;
    font-weight: 700;
    color: #7239ea;
}
.approval-divider {
    height: 1px;
    background: #f3f4f6;
    margin: 0 18px;
}

.approval-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-approved  { background: #d1fae5; color: #065f46; }
.status-requested { background: #fef3c7; color: #92400e; }
.status-rejected,
.status-declined  { background: #fee2e2; color: #991b1b; }
.status-pending   { background: #e0e7ff; color: #3730a3; }

.approval-card-footer {
    padding: 14px 18px;
    background: #fafafa;
    border-top: 1px solid #f3f4f6;
}

.approval-back-btn {
    background: #f3f4f6;
    color: #374151;
    border: none;
}
</style>

<style>
[data-bs-theme="dark"] .deposit-view-page .card {
    background-color: #212529;
    border-color: #495057 !important;
    color: #dee2e6;
}

[data-bs-theme="dark"] .deposit-view-page .card-body {
    background-color: #212529;
    color: #dee2e6;
}

[data-bs-theme="dark"] .deposit-view-page .card-header {
    background-color: #2b3035;
    border-color: #495057 !important;
    color: #f8f9fa;
}

[data-bs-theme="dark"] .deposit-view-page .agent-logo-wrap,
[data-bs-theme="dark"] .deposit-view-page .reference-file-wrap {
    background: #2b3035;
    border-color: #495057;
}

[data-bs-theme="dark"] .deposit-view-page .field-label {
    color: #adb5bd;
}

[data-bs-theme="dark"] .deposit-view-page .field-value {
    color: #e9ecef;
}

[data-bs-theme="dark"] .deposit-view-page .table-purple {
    --bs-table-bg: #3a3364;
    --bs-table-color: #f1ecfd;
    --bs-table-border-color: #495057;
}

[data-bs-theme="dark"] .deposit-view-page .sub-total-bdr {
    border-top-color: rgba(255, 255, 255, .15) !important;
}

[data-bs-theme="dark"] .deposit-view-page .approval-card {
    box-shadow: 0 2px 12px rgba(0, 0, 0, .3);
}

[data-bs-theme="dark"] .deposit-view-page .approval-row-label {
    color: #adb5bd;
}

[data-bs-theme="dark"] .deposit-view-page .approval-row-value {
    color: #e9ecef;
}

[data-bs-theme="dark"] .deposit-view-page .approval-time {
    color: #9ca3af;
}

[data-bs-theme="dark"] .deposit-view-page .approval-divider {
    background: #495057;
}

[data-bs-theme="dark"] .deposit-view-page .approval-card-footer {
    background: #2b3035;
    border-top-color: #495057;
}

[data-bs-theme="dark"] .deposit-view-page .approval-back-btn {
    background: #343a40;
    color: #dee2e6;
}

[data-bs-theme="dark"] .deposit-view-page .approval-status-badge.status-approved {
    background: rgba(16, 185, 129, .18);
    color: #34d399;
}

[data-bs-theme="dark"] .deposit-view-page .approval-status-badge.status-requested {
    background: rgba(245, 158, 11, .18);
    color: #fbbf24;
}

[data-bs-theme="dark"] .deposit-view-page .approval-status-badge.status-rejected,
[data-bs-theme="dark"] .deposit-view-page .approval-status-badge.status-declined {
    background: rgba(239, 68, 68, .18);
    color: #f87171;
}

[data-bs-theme="dark"] .deposit-view-page .approval-status-badge.status-pending {
    background: rgba(99, 102, 241, .18);
    color: #a5b4fc;
}
</style>
