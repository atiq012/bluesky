<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import moment from 'moment';
import ActionButtons from '../../../components/common/ActionButtons.vue';
import AppModal from '../../../components/common/AppModal.vue';
import AppButton from '../../../components/common/AppButton.vue';
import FinancialHistoryModal from './FinancialHistoryModal.vue';
import { fetchFinancialHistory } from './financialHistoryApi';
import { runAction } from '../../../utils/runAction';

const router = useRouter();

const rData = ref([]);
const loading = ref(false);
const showCancelModal = ref(false);
const selectedDeposit = ref(null);
const cancelLoading = ref(false);
const showHistoryModal = ref(false);
const historyLoading = ref(false);
const historyBalance = ref({});
const historyRows = ref([]);

const columns = [
    { field: 'index', title: 'SL' },
    { field: 'name', title: 'Payment Term' },
    { field: 'bank', title: 'Payment Account' },
    { field: 'reference_no', title: 'Reference No & Date' },
    { field: 'agent', title: 'Requested By' },
    { field: 'total', title: 'Total Amount' },
    { field: 'remarks', title: 'Remarks' },
    { field: 'created_by', title: 'Created By' },
    { field: 'updated_by', title: 'Updated By' },
    { field: 'status', title: 'Status' },
    { field: 'action', title: 'Action' },
];

const tableRows = computed(() =>
    rData.value.map((row, i) => ({ ...row, index: i + 1 }))
);

async function getListValues() {
    try {
        loading.value = true;
        const response = await axiosInstance.get('getDeposit');
        rData.value = Array.isArray(response.data.data) ? response.data.data : [];
    } catch {
        rData.value = [];
    } finally {
        loading.value = false;
    }
}

function openCancelModal(item) {
    selectedDeposit.value = item;
    showCancelModal.value = true;
}

function closeCancelModal() {
    if (cancelLoading.value) return;
    showCancelModal.value = false;
    selectedDeposit.value = null;
}

async function confirmCancel() {
    if (!selectedDeposit.value) return;
    try {
        cancelLoading.value = true;
        await axiosInstance.post('/deposit/cancel', { id: selectedDeposit.value.idd });
        closeCancelModal();
        getListValues();
        Notification.showToast('s', 'Deposit request cancelled successfully.');
    } catch {
        Notification.showToast('e', 'Failed to cancel deposit request.');
    } finally {
        cancelLoading.value = false;
    }
}

async function openHistoryModal() {
    await runAction(async () => {
        const data = await fetchFinancialHistory();
        historyBalance.value = data.balance;
        historyRows.value = data.rows;
        showHistoryModal.value = true;
    }, { setLoading: (v) => { historyLoading.value = v; } });
}

function closeHistoryModal() {
    if (historyLoading.value) return;
    showHistoryModal.value = false;
}

function openDepositDetails(item) {
    if (!item?.idd) return;
    router.push({ name: 'depoDetails', params: { id: item.idd } });
}

onMounted(getListValues);
</script>

<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Deposit Management</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Deposit List</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <router-link :to="{ name: 'CreateDeposit' }" class="btn btn-primary btn-sm">
                <i class="fa fa-circle-plus"></i> Deposit Request
            </router-link>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total</span>
                    <span class="info-agency-number">1200</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="active-agency mb-3">
                <span class="active-agency-icon bg-success elevation-1 text-white"><i class="fa-solid fa-circle-check"></i></span>
                <div class="active-agency-content">
                    <span class="active-agency-text">Approved</span>
                    <span class="active-agency-number">760</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fa fa-pause"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Requested</span>
                    <span class="info-box-number">5</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa fa-clock"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">Decline</span>
                    <span class="pending-agnt-number">20</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card rounded rounded-2 shadow-none p-3">
                <div class="row">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm">
                            <option>Select Payment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm">
                            <option>Select Bank</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm">
                            <option>Select Status</option>
                        </select>
                    </div>
                    <div class="col-md-1 mt-2">
                        <i class="fa fa-times text-danger"></i> Clear
                    </div>
                    <div class="col-md d-flex justify-content-end align-items-center mt-2 mt-md-0">
                        <AppButton
                            variant="browse"
                            label="History"
                            :loading="historyLoading"
                            @click="openHistoryModal"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card rounded rounded-2 shadow-none p-3">
                <DataTable
                    table-id="deposit-list"
                    :rows="tableRows"
                    :columns="columns"
                    :loading="loading"
                    search-placeholder="Search by anything"
                    @refresh="getListValues"
                >
                    <template #name="{ value: row }">
                        <span>
                            <i
                                v-if="row.name === 'Cash'"
                                class="fa-solid fa-money-bill-wave me-1"
                                style="color: #00ab55;"
                            ></i>
                            <i
                                v-else-if="row.name === 'Bank Transfer'"
                                class="fa-solid fa-building-columns me-1"
                                style="color: #027DE2;"
                            ></i>
                            <i v-else class="fa-solid fa-credit-card me-1" style="color: #805dca;"></i>
                            {{ row.name }}
                        </span>
                    </template>

                    <template #bank="{ value: row }">
                        <div>
                            <i class="fa-solid fa-landmark me-1 text-muted"></i>
                            {{ row.bank }}
                            <br />
                            <small class="text-primary">
                                <i class="fa-solid fa-hashtag" style="font-size: 0.65rem;"></i>
                                Acount No: {{ row.acct_no }}
                            </small>
                        </div>
                    </template>

                    <template #reference_no="{ value: row }">
                        <div>
                            <i class="fa-solid fa-receipt me-1 text-muted"></i>
                            {{ row.reference_no }}
                            <br />
                            <small class="text-primary">
                                <i class="fa-regular fa-calendar me-1" style="font-size: 0.65rem;"></i>
                                {{ moment(row.reference_date).format('DD-MMM-YYYY') }}
                            </small>
                        </div>
                    </template>

                    <template #agent="{ value: row }">
                        <div>
                            <i class="fa-solid fa-user me-1" style="color: #027DE2;"></i>
                            {{ row.agent }}
                            <br />
                            <small class="text-primary">
                                <i class="fa-regular fa-clock me-1" style="font-size: 0.65rem;"></i>
                                {{ moment(row.created_at).format('DD-MMM-YYYY') }} |
                                {{ moment(row.created_at).format('h:mm') }}
                            </small>
                        </div>
                    </template>

                    <template #total="{ value: row }">
                        <span>
                            <i class="fa-solid fa-coins me-1" style="color: #00ab55;"></i>
                            {{ row.total }}
                        </span>
                    </template>

                    <template #remarks="{ value: row }">
                        <span v-if="row.remarks && row.remarks !== 'null'">
                            <i class="fa-solid fa-comment-dots me-1 text-muted"></i>
                            {{ row.remarks }}
                        </span>
                        <span v-else class="text-muted">—</span>
                    </template>

                    <template #created_by="{ value: row }">
                        <CreatedInfo
                            :name="row?.created_by"
                            :date="row?.created_at"
                            :image-path="row?.created_by_avatar || ''"
                        />
                    </template>

                    <template #updated_by="{ value: row }">
                        <CreatedInfo
                            v-if="row?.updated_by"
                            :name="row.updated_by"
                            :date="row.updated_at"
                            :image-path="row?.updated_by_avatar || ''"
                        />
                        <span v-else class="text-muted">—</span>
                    </template>

                    <template #status="{ value: row }">
                        <div
                            v-if="row.status === 'Requested'"
                            class="badge rounded-pill text-warning bg-light-warning p-2 text-uppercase px-3"
                        >
                            <i class="bx bxs-circle me-1"></i>{{ row.status }}
                        </div>
                        <div
                            v-else-if="row.status === 'Rejected' || row.status === 'Cancelled'"
                            class="badge rounded-pill text-danger bg-light-danger p-2 text-uppercase px-3"
                        >
                            <i class="bx bxs-circle me-1"></i>{{ row.status }}
                        </div>
                        <div v-else class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3">
                            <i class="bx bxs-circle me-1"></i>{{ row.status }}
                        </div>
                    </template>

                    <template #action="{ value: row }">
                        <ActionButtons
                            :item="row"
                            :show-view="true"
                            :show-edit="false"
                            :show-delete="false"
                            :show-cancel-booking="row.status === 'Requested'"
                            cancel-booking-label="Cancel Request"
                            @view="openDepositDetails"
                            @cancel-booking="openCancelModal"
                        />
                    </template>
                </DataTable>
            </div>
        </div>
    </div>

    <AppModal
        :is-open="showCancelModal"
        :show-header="false"
        size="sm"
        max-width="460px"
        :close-on-backdrop="!cancelLoading"
        @close="closeCancelModal"
    >
        <div v-if="selectedDeposit" class="cdm-body">
            <!-- Icon + Title -->
            <div class="cdm-header">
                <span class="cdm-icon-wrap">
                    <i class="fa-solid fa-ban cdm-icon"></i>
                </span>
                <div class="cdm-title-group">
                    <h5 class="cdm-title">Cancel Deposit Request</h5>
                    <p class="cdm-subtitle">This action cannot be undone.</p>
                </div>
                <button class="cdm-close-btn" :disabled="cancelLoading" @click="closeCancelModal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Info rows -->
            <div class="cdm-info-list">
                <div class="cdm-info-row">
                    <span class="cdm-info-label">
                        <i class="fa-solid fa-credit-card cdm-row-icon"></i> Payment Type
                    </span>
                    <span class="cdm-info-value">{{ selectedDeposit.name || '—' }}</span>
                </div>
                <div class="cdm-info-row">
                    <span class="cdm-info-label">
                        <i class="fa-solid fa-landmark cdm-row-icon"></i> Bank / Account
                    </span>
                    <span class="cdm-info-value">
                        {{ selectedDeposit.bank || '—' }}
                        <small v-if="selectedDeposit.acct_no" class="cdm-acct">#{{ selectedDeposit.acct_no }}</small>
                    </span>
                </div>
                <div class="cdm-info-row">
                    <span class="cdm-info-label">
                        <i class="fa-solid fa-receipt cdm-row-icon"></i> Reference No
                    </span>
                    <span class="cdm-info-value">{{ selectedDeposit.reference_no || '—' }}</span>
                </div>
                <div class="cdm-info-row">
                    <span class="cdm-info-label">
                        <i class="fa-regular fa-calendar cdm-row-icon"></i> Reference Date
                    </span>
                    <span class="cdm-info-value">
                        {{ selectedDeposit.reference_date ? moment(selectedDeposit.reference_date).format('DD-MMM-YYYY') : '—' }}
                    </span>
                </div>
                <div class="cdm-info-row">
                    <span class="cdm-info-label">
                        <i class="fa-solid fa-coins cdm-row-icon"></i> Total Amount
                    </span>
                    <span class="cdm-info-value cdm-amount">{{ selectedDeposit.total }}</span>
                </div>
                <div class="cdm-info-row">
                    <span class="cdm-info-label">
                        <i class="fa-solid fa-user cdm-row-icon"></i> Requested By
                    </span>
                    <span class="cdm-info-value">{{ selectedDeposit.agent || '—' }}</span>
                </div>
                <div v-if="selectedDeposit.remarks && selectedDeposit.remarks !== 'null'" class="cdm-info-row">
                    <span class="cdm-info-label">
                        <i class="fa-solid fa-comment-dots cdm-row-icon"></i> Remarks
                    </span>
                    <span class="cdm-info-value">{{ selectedDeposit.remarks }}</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="cdm-footer">
                <AppButton variant="close" :block="true" :disabled="cancelLoading" @click="closeCancelModal" />
                <AppButton
                    variant="delete"
                    label="Cancel Request"
                    loading-text="Cancelling..."
                    :loading="cancelLoading"
                    :block="true"
                    @click="confirmCancel"
                >
                    <template #icon>
                        <i class="fa-solid fa-ban"></i>
                    </template>
                </AppButton>
            </div>
        </div>
    </AppModal>

    <AppModal
        :is-open="showHistoryModal"
        :show-header="false"
        size="xl"
        max-width="1100px"
        :close-on-backdrop="!historyLoading"
        @close="closeHistoryModal"
    >
        <FinancialHistoryModal
            :balance="historyBalance"
            :rows="historyRows"
            @close="closeHistoryModal"
        />
    </AppModal>
</template>

<style scoped>
.info-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #cbdff4, #bcd6f1, #aecced, #8eb6e4, #6da1dc, #4a8bd2, #1576c9);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}
.info-agency .info-agency-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}
.info-agency .info-agency-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}
.info-agency .info-agency-text {
    font-size: 19px;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.info-agency .info-agency-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

.active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #c9f1e4, #baf1de, #acf0d7, #8cefc6, #6decb1, #4ce998, #24e57c);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}
.active-agency .active-agency-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}
.active-agency .active-agency-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}
.active-agency .active-agency-text {
    font-size: 19px;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.active-agency .active-agency-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

.pending-agnt {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #f0ded6, #f1d7c9, #f2cfbd, #f3bea2, #f3ac88, #f29b6f, #ef8956);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}
.pending-agnt .pending-agnt-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}
.pending-agnt .pending-agnt-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}
.pending-agnt .pending-agnt-text {
    font-size: 19px;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pending-agnt .pending-agnt-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

.info-box {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #ebf0d6, #e9eeca, #e8ecbe, #e7e7a2, #e8e285, #ebdb66, #efd444);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}
.info-box .info-box-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}
.info-box .info-box-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}
.info-box .info-box-text {
    font-size: 19px;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.info-box .info-box-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}
.bg-info { background-color: #0880e1 !important; }
.bg-success { background-color: #05cc61 !important; }
.bg-warning { background-color: #fb8e28 !important; }
.bg-danger { background-color: #efb51d !important; }

/* ── Cancel Deposit Modal ───────────────────────────── */
.cdm-body {
    padding: 1.25rem 1.25rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.cdm-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.cdm-icon-wrap {
    flex-shrink: 0;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: #fee2e2;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cdm-icon {
    color: #dc2626;
    font-size: 1rem;
}

.cdm-title-group {
    flex: 1;
    min-width: 0;
}
.cdm-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0 0 0.15rem;
    line-height: 1.3;
    color: #0f172a;
}
.cdm-subtitle {
    font-size: 0.78rem;
    color: #94a3b8;
    margin: 0;
}

.cdm-close-btn {
    flex-shrink: 0;
    background: none;
    border: none;
    padding: 0.25rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    border-radius: 4px;
    transition: color 0.15s, background 0.15s;
}
.cdm-close-btn:hover:not(:disabled) { color: #475569; background: #f1f5f9; }
.cdm-close-btn:disabled { opacity: 0.45; cursor: not-allowed; }

.cdm-info-list {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}
.cdm-info-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    font-size: 0.82rem;
}
.cdm-info-row + .cdm-info-row {
    border-top: 1px solid #f1f5f9;
}

.cdm-info-label {
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}
.cdm-row-icon {
    font-size: 0.7rem;
    width: 0.9rem;
    text-align: center;
    color: #94a3b8;
}
.cdm-info-value {
    color: #1e293b;
    font-weight: 500;
    text-align: right;
    word-break: break-all;
}
.cdm-amount { color: #16a34a; }
.cdm-acct {
    display: block;
    font-size: 0.72rem;
    color: #3b82f6;
    font-weight: 400;
}

.cdm-footer {
    display: flex;
    gap: 0.5rem;
}

/* Dark mode */
[data-bs-theme="dark"] .cdm-icon-wrap { background: rgba(220,38,38,0.15); }
[data-bs-theme="dark"] .cdm-title { color: #f1f5f9; }
[data-bs-theme="dark"] .cdm-close-btn:hover:not(:disabled) { color: #cbd5e1; background: rgba(255,255,255,0.06); }
[data-bs-theme="dark"] .cdm-info-list { border-color: #334155; }
[data-bs-theme="dark"] .cdm-info-row + .cdm-info-row { border-color: #1e293b; }
[data-bs-theme="dark"] .cdm-info-label { color: #94a3b8; }
[data-bs-theme="dark"] .cdm-info-value { color: #e2e8f0; }
</style>
