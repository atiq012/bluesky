<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { ref, computed } from "vue";
import { useRouter } from 'vue-router';
import axiosInstance from "../../../axiosInstance";
import { useAuthStore } from '../../../stores/authStore';
import AppDataTable from '../../common/DataTable.vue';
import ActionButtons from '../../common/ActionButtons.vue';
import CreatedInfo from '../../common/CreatedInfo.vue';
import AppTooltip from '../../common/AppTooltip.vue';
import moment from "moment";

const authStore = useAuthStore();
const router = useRouter();
const rData = ref([]);
const loading = ref(false);
const filterDate = ref("");
const filterAuthor = ref("");
const filterStatus = ref("");
const showNoteModal = ref(false);
const selectedGroupItem = ref(null);
const declineNote = ref(null);
const statistics = computed(() => {
    const total = rData.value.length;
    const active = rData.value.filter(i => (i.status || '').toLowerCase() === 'active').length;
    const inactive = rData.value.filter(i => (i.status || '').toLowerCase() === 'inactive').length;
    const expired = rData.value.filter(i => (i.status || '').toLowerCase() === 'expired').length;
    return { total, active, inactive, expired };
});

const columns = [
    { field: 'sl', title: 'Sl.' },
    { field: 'group_info', title: 'Group Details' },
    { field: 'group_n_way_type', title: 'Group and Way Type' },
    { field: 'airline', title: 'Airline' },
    { field: 'sector', title: 'Sector & Class' },
    { field: 'dates', title: 'Departure & Return Date' },
    { field: 'seats', title: 'No. Of PAX' },
    { field: 'fare', title: 'Total Fare and Payment Sequence' },
    { field: 'paid', title: 'Total Paid & Due' },
    { field: 'kam', title: 'KAM' },
    { field: 'status', title: 'Status' },
    { field: 'created_col', title: 'Created By', sort: false },
    // { field: 'updated_col', title: 'Updated By', sort: false },
    { field: 'action', title: 'Action' },
];

const rows = computed(() =>
    rData.value.map((item, index) => ({ ...item, sl: index + 1 }))
);

function formatAmount(value) {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? n.toLocaleString('en-BD', { maximumFractionDigits: 0 }) : '0';
}

function groupIdDisplay(row) {
    return row.idd || 'GRP-' + String(Math.floor(Math.random() * 10000)).padStart(4, '0');
}

function wayTypeConfig(wayType) {
    const type = String(wayType || "One Way").toLowerCase();
    if (type.includes("round")) {
        return { cls: 'way-badge way-round', label: wayType || 'One Way' };
    }
    return { cls: 'way-badge way-one', label: wayType || 'One Way' };
}

function statusConfig(status) {

    switch ((status || '')) {
        case 'New Request':
            return { cls: 'status-pill status-inactive', icon: 'fa-solid fa-circle', label: 'New Request' };
        case 'On Process':
            return { cls: 'status-pill status-inactive', icon: 'fa-solid fa-circle', label: 'On Process' };
        case 'Price offer':
            return { cls: 'status-pill status-price-offer', icon: 'fa-solid fa-circle', label: 'Price Offer' };
        case 'Offer confirmed':
            return { cls: 'status-pill status-price-offer', icon: 'fa-solid fa-circle', label: 'Offer Confirmed' };
        case 'Decline':
            return { cls: 'status-pill status-expired', icon: 'fa-solid fa-circle', label: 'Decline' };
        case 'Confirmed':
            return { cls: 'status-pill status-active', icon: 'fa-solid fa-circle', label: 'Confirmed' };
        case 'Approved':
            return { cls: 'status-pill status-active', icon: 'fa-solid fa-circle', label: 'Approved' };
        case 'Request Cancelled':
            return { cls: 'status-pill status-expired', icon: 'fa-solid fa-circle', label: 'Request Cancelled' };
        default:
            return { cls: 'status-pill status-active', icon: 'fa-solid fa-circle', label: 'Active' };
    }
}

function canDelete(row) {
    if (row.status == 'Request Cancelled' || row.status == 'Approved' || row.status == 'Confirmed' || row.status == 'Decline' || row.status == 'On Process' ||  row.status == 'Offer confirmed') {
        return false
    }
}

function groupAssignDetails(row) {
    if (row.status == 'Request Cancelled' || row.status == 'Approved' || row.status == 'Confirmed' || row.status == 'Decline' || row.status == 'On Process' ||  row.status == 'New Request') {
        return false;
    } else {
        return true;
    }
}

function availableSeats(row) {
    return (row.total_seat || 50) - (row.assigned_pax || 0);
}

function clearFilters() {
    filterDate.value = "";
    filterAuthor.value = "";
    filterStatus.value = "";
}

async function getListValues() {
    loading.value = true;
    try {
        const response = await axiosInstance.get("get-groups");
        rData.value = response.data.data;
    } catch (error) {
        // console.log(error);
    }
    loading.value = false;
}

function handleView(item) {

    router.push({ name: 'requestGroupView', params: { id: item.id } });
}

function handleGroup(item) {

    router.push({ name: 'viewOfferPrice', params: { id: item.id } });
}

function handleCopy(item) {
    // router.push({ name: 'copyGroupPnr', params: { id: item.id } });
}

function handlePnr(item) {
    // router.push({ name: 'pnrGroupPnr', params: { id: item.id } });
}

async function declineGroupReq() {

    if (!declineNote.value || !declineNote.value) return;

    try {
        await axiosInstance.post("CancelGroup", {
            id: selectedGroupItem.value,
            note: declineNote.value
        });

        getListValues();

        if (typeof Notification !== 'undefined' && Notification?.showToast) {
            Notification.showToast('s', 'Decline successfully!');
        }

        closeDeclineModal();
    } catch (error) {
        if (typeof Notification !== 'undefined' && Notification?.showToast) {
            Notification.showToast('e', 'Failed to decline.');
        }
    }
}
function handleDelete(item) {

    showNoteModal.value = true;
    selectedGroupItem.value = item.id;
    declineNote.value = "";
}
function closeDeclineModal() {
    showNoteModal.value = false;
    selectedGroupItem.value = null;
    declineNote.value = "";
}

getListValues();
</script>

<template>
    <AppBreadcrumbs title="Group List" :back-to="{ name: 'groupList' }" :breadcrumbs="[
        { label: 'Dashboard', to: { name: 'Home' } },
        { label: 'Group Management', to: { name: 'groupList' } },
        { label: 'Group List' }]">
        <template #actions>
            <div class="btn-group">
                <router-link :to="{ name: 'createGroupRequest' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i> Group Request
                </router-link>
            </div>
        </template>
    </AppBreadcrumbs>


    <!-- Stats Cards -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fa-solid fa-link"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total</span>
                    <span class="info-agency-number">{{ statistics.total }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="active-agency">
                <span class="active-agency-icon bg-success elevation-1 text-white"><i
                        class="fa-solid fa-link"></i></span>
                <div class="active-agency-content">
                    <span class="active-agency-text">Active</span>
                    <span class="active-agency-number">{{ statistics.active }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-danger elevation-1"><i class="fa-solid fa-link"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Inactive</span>
                    <span class="info-box-number">{{ statistics.inactive }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="pending-agnt">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa-solid fa-link"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">Expired</span>
                    <span class="pending-agnt-number">{{ statistics.expired }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <!-- <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white">
                            <i class="fa-regular fa-calendar"></i>
                        </span>
                        <input v-model="filterDate" type="text" class="form-control"
                            placeholder="01-Aug-2024 - 22-Aug-2024">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select v-model="filterAuthor" class="form-select form-select-sm">
                        <option value="">Author</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select v-model="filterStatus" class="form-select form-select-sm">
                        <option value="">Status</option>
                        <option value="1">Active</option>
                        <option value="2">Expired</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none px-0" @click="clearFilters">
                        <i class="fa-solid fa-xmark me-1"></i>Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-2 p-3">
                <AppDataTable table-id="group-pnr-list" :rows="rows" :columns="columns" :loading="loading"
                    :page-size="30" search-placeholder="Search by anything..." @refresh="getListValues">
                    <!-- SL -->
                    <template #sl="{ value: row }">
                        <span class="text-muted fw-semibold">
                            {{ row.sl }}
                        </span>
                    </template>

                    <!-- Group ID & Received Date -->
                    <template #group_info="{ value: row }">
                        <div class="group-info-cell">

                            <div class="cell-main">
                                {{ row.group_code }}
                            </div>
                            <div class="cell-sub">
                                <i class="fa-regular fa-calendar me-1" style="font-size: 0.65rem;"></i>
                                {{ row.created_at ? moment(row.created_at).format('DD-MMM-YYYY') : 'N/A' }}
                            </div>
                        </div>
                    </template>

                    <!-- Group & Way Type -->
                    <template #group_n_way_type="{ value: row }">
                        <div class="way-type-cell">
                            <div class="cell-main">{{ row.group_type || 'Hajj' }}</div>
                            <span :class="wayTypeConfig(row.request_type).cls">
                                {{ wayTypeConfig(row.request_type).label }}
                            </span>
                        </div>
                    </template>

                    <!-- Airlines -->
                    <template #airline="{ value: row }">
                        <div class="airline-cell">
                            <div class="cell-main">
                                <i class="fa-solid fa-plane-departure me-1 table-icon"></i>
                                {{ row.airline || 'Qatar Airline' }}
                            </div>
                            <div class="cell-link">PNR : {{ row.pnr || '-' }}</div>

                        </div>
                    </template>

                    <!-- Sector & Class -->
                    <template #sector="{ value: row }">
                        <div class="sector-cell">
                            <div class="cell-main">

                                <div v-if="row.request_type == 'multicity'"
                                    v-html="row.route_display.replaceAll(' | ', '<br>').replaceAll('|', '<br>')"></div>

                                <div v-else-if="row.request_type == 'oneway'">
                                    {{ row.origin }} - {{ row.destination }}
                                </div>
                                <div v-else>
                                    {{ row.origin }} - {{ row.destination }} <br>
                                    {{ row.return_origin }} - {{ row.return_destination }}
                                </div>
                            </div>
                            <div class="cell-link">{{ row.class_type }} ({{ row.class_code }})</div>
                        </div>
                    </template>

                    <!-- Departure & Return Date -->
                    <template #dates="{ value: row }">
                        <div class="cell-main" v-if="row.request_type == 'multicity'">
                            <div v-if="row.request_type == 'multicity'"
                                v-html="`<i class='fa-regular fa-calendar me-1' style='font-size: 0.65rem;'></i>${row.route_date_display.replaceAll(' | ', `<br> <i class='fa-regular fa-calendar me-1' style='font-size: 0.65rem;'></i>`).replaceAll('|', `<br> <i class='fa-regular fa-calendar me-1' style='font-size: 0.65rem;'></i>`)}`">
                            </div>
                        </div>
                        <div class="cell-main" v-else-if="row.request_type == 'oneway'">
                            <i class="fa-regular fa-calendar me-1" style="font-size: 0.65rem;">
                            </i> {{ row.departure_date ? moment(row.departure_date).format('DD-MMM-YYYY h:mm A') : '-'
                            }}
                        </div>
                        <div class="cell-main" v-else>
                            <i class="fa-regular fa-calendar me-1" style="font-size: 0.65rem;"></i>
                            {{ row.departure_date ? moment(row.departure_date).format('DD-MMM-YYYY h:mm A') : '-' }}
                            <br>
                            <i class="fa-regular fa-calendar me-1" style="font-size: 0.65rem;"></i>
                            {{ row.return_date ? moment(row.return_date).format('DD-MMM-YYYY h:mm A') : '-' }}
                        </div>
                    </template>

                    <!-- no of pax -->
                    <template #seats="{ value: row }">
                        <div class="fare-cell">
                            <div class="cell-main amount-text">
                                <i class="fa fa-users me-1"></i>
                                {{ row.adult_traveler + row.child_traveler + row.infant_traveler }}
                            </div>
                            <div class="cell-main cell-link">
                                <i class="fa fa-person me-1"></i> {{ row.adult_traveler }} | <i
                                    class="fa fa-child me-1"></i>
                                {{ row.child_traveler }} | <i class="fa fa-baby me-1"></i>
                                {{ row.infant_traveler }}
                            </div>
                        </div>
                    </template>
                    <!-- Estimate Fare & Payment Sequence -->
                    <template #fare="{ value: row }">
                        <div class="fare-cell" v-if="row.status != 'New Request'">
                            <div class="cell-main amount-text">
                                {{ row.currency }} {{ formatAmount(row.per_person_fare) }}
                            </div>
                        </div>
                        <div v-else>
                            -
                        </div>
                    </template>

                    <!-- Paid Amount -->
                    <template #paid="{ value: row }">
                        <div class="paid-cell" v-if="row.status != 'New Request'">
                            <div class="cell-main amount-text">
                                {{ row.currency }} {{ formatAmount(row.total_paid_from_sequences) }}
                            </div>
                        </div>
                        <div v-else>
                            -
                        </div>
                    </template>
                    <!-- KAM -->
                    <template #kam="{ value: row }">

                        <CreatedInfo :name="row?.assigned_to_kam" :date="row?.assigned_date" />

                    </template>
                    <!-- Status -->
                    <template #status="{ value: row }">
                        <div class="status-cell">
                            <span :class="['rounded-pill', statusConfig(row.status).cls]">
                                <i :class="[statusConfig(row.status).icon, 'me-1 tiny']"></i>
                                {{ statusConfig(row.status).label }}
                            </span>
                        </div>
                    </template>

                    <!-- created by -->
                    <!-- Created By -->
                    <template #created_col="{ value: row }">
                        <CreatedInfo :name="row?.createdby" :date="row?.created_at" />
                    </template>

                    <!-- Updated By -->
                    <!-- <template #updated_col="{ value: row }">
                        <CreatedInfo :name="row?.updatedby || row?.updatedby" :date="row?.updated_at" />
                    </template> -->

                    <!-- Action -->
                    <template #action="{ value: row }">
                        <ActionButtons :item="row"
                        :show-edit="false"                            :showGroupAssign="groupAssignDetails(row)"
                        :show-view="true" :show-copy="true"
                        :show-delete="canDelete(row)"
                        :show-authorize="false" copy-label="PNR"
                        @view="handleView"
                        @copy="handlePnr"
                        @view-group="handleGroup"
                        @delete="handleDelete" />
                    </template>
                </AppDataTable>
            </div>
        </div>
    </div>

    <!-- decline note -->
    <div v-if="showNoteModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-pencil me-2"></i>Cancel Note
                    </h5>
                    <button type="button" class="btn-close" @click="closeDeclineModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cancel Note <span class="text-danger">*</span></label>
                        <textarea class="form-control" v-model="declineNote" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary btn-sm" @click="closeDeclineModal">
                        <i class="fa-solid fa-xmark me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn-action btn-next btn-sm" @click="declineGroupReq">
                        <i class="fa-solid fa-check me-1"></i>Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.btn-action {
    padding: 0.5rem 1.75rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 300;
    cursor: pointer;
    border: none;
    transition: all 0.18s;
}

.btn-next {
    background: #3b82f6;
    color: #fff;
    box-shadow: 0 1px 3px rgba(59, 130, 246, 0.25);
}
/* Stats card styles */
.info-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #cbdff4, #bcd6f1, #aecced, #8eb6e4, #6da1dc, #4a8bd2, #1576c9);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

.info-agency .info-agency-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    width: 70px;
}

.info-agency .info-agency-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
}

.info-agency .info-agency-text {
    font-size: 19px;
    color: #838587;
}

.info-agency .info-agency-number {
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
    width: 100%;
}

.active-agency .active-agency-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    width: 70px;
}

.active-agency .active-agency-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
}

.active-agency .active-agency-text {
    font-size: 19px;
    color: #838587;
}

.active-agency .active-agency-number {
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
    width: 100%;
}

.info-box .info-box-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    width: 70px;
}

.info-box .info-box-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
}

.info-box .info-box-text {
    font-size: 19px;
    color: #838587;
}

.info-box .info-box-number {
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
    width: 100%;
}

.pending-agnt .pending-agnt-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    width: 70px;
}

.pending-agnt .pending-agnt-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
}

.pending-agnt .pending-agnt-text {
    font-size: 19px;
    color: #838587;
}

.pending-agnt .pending-agnt-number {
    font-weight: 700;
    font-size: 22px;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-info {
    background-color: #0880e1 !important;
    color: #fff !important;
}

.bg-success {
    background-color: #05cc61 !important;
    color: #fff !important;
}

.bg-warning {
    background-color: #fb8e28 !important;
    color: #fff !important;
}

.bg-danger {
    background-color: #efb51d !important;
    color: #fff !important;
}

/* Table cell styles */
.cell-main {
    font-weight: 600;
    color: #33415c;
    line-height: 1.35;
}

.cell-sub {
    margin-top: 3px;
    font-size: 11px;
    color: #7c8ba5;
    line-height: 1.3;
}

.cell-link {
    margin-top: 3px;
    font-size: 11px;
    color: #3f7dd8;
    font-weight: 600;
    line-height: 1.3;
}

.cell-tagline {
    margin-top: 3px;
    font-size: 11px;
    color: #4d8dd8;
    font-weight: 600;
}

.table-icon {
    font-size: 10px;
    color: #4f82d8;
}

.table-icon.warning {
    color: #f59e0b;
}

.table-icon.violet {
    color: #7c3aed;
}

.table-icon.primary {
    color: #1d4ed8;
}

.way-badge {
    margin-top: 3px;
    display: inline-block;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.2;
}

.way-round {
    background: #fff2df;
    color: #d97706;
}

.way-one {
    background: #3269d6;
    color: #e8f0ff;
}

.amount-text {
    color: #2f557f;
}

.tiny {
    font-size: 7px;
}

.status-pill {
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    text-transform: none;
}

.status-active {
    color: #0f9a59;
    background: #ecfdf3;
    border: 1px solid #b5e9cc;
}

.status-inactive {
    color: #9c6b00;
    background: #fff8e8;
    border: 1px solid #f2dea6;
}

.status-expired {
    color: #c84545;
    background: #fff2f2;
    border: 1px solid #f4c5c5;
}
.status-price-offer {
    color: #b0c845;
    background: #f2fffb;
    border: 1px solid #e8f4c5;
}
.status-other {
    color: #586c8f;
    background: #f1f5fb;
    border: 1px solid #d7e0ef;
}

.seat-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.fare-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.paid-cell {
    display: flex;
    flex-direction: column;
}

.status-cell {
    display: inline-flex;
    align-items: center;
}

.segment-item {
    margin-bottom: 8px;
}

.segment-item:last-child {
    margin-bottom: 0;
}

/* Dark mode support */
[data-bs-theme="dark"] .cell-main {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .cell-sub {
    color: #94a3b8;
}

[data-bs-theme="dark"] .cell-link {
    color: #60a5fa;
}

[data-bs-theme="dark"] .cell-tagline {
    color: #60a5fa;
}

[data-bs-theme="dark"] .amount-text {
    color: #93c5fd;
}

[data-bs-theme="dark"] .status-active {
    color: #4ade80;
    background: rgba(34, 197, 94, 0.15);
    border-color: rgba(34, 197, 94, 0.3);
}

[data-bs-theme="dark"] .status-inactive {
    color: #fbbf24;
    background: rgba(251, 191, 36, 0.15);
    border-color: rgba(251, 191, 36, 0.3);
}

[data-bs-theme="dark"] .status-expired {
    color: #f87171;
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
}

[data-bs-theme="dark"] .status-price-offer {
    color: #d2f871;
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
}
</style>
