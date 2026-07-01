<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import { useAuthStore } from '../../../stores/authStore';
import AppDataTable from '../../common/DataTable.vue';
import moment from 'moment';
import { runAction } from '../../../utils/runAction';

const authStore = useAuthStore();
const router = useRouter();
const rData = ref([]);
const loading = ref(false);
const loadingItemId = ref(null);
const loadingAction = ref(null);

function paxTypeLabel(type) {
    if (type === 1 || type === '1' || type === 'Adult') return 'Adult';
    if (type === 2 || type === '2' || type === 'Child') return 'Child';
    if (type === 3 || type === '3' || type === 'Infant') return 'Infant';
    return type || '-';
}

const statistics = computed(() => {
    const total = rData.value.length;
    const adults = rData.value.filter(i => paxTypeLabel(i.pax_type) === 'Adult').length;
    const child = rData.value.filter(i => paxTypeLabel(i.pax_type) === 'Child').length;
    const infant = rData.value.filter(i => paxTypeLabel(i.pax_type) === 'Infant').length;
    return { total, adults, child, infant };
});

const columns = [
    { field: 'sl', title: 'SL' },
    { field: 'traveler', title: 'Traveler Info' },
    { field: 'passport', title: 'Passport Info' },
    { field: 'contact', title: 'Contact Info' },
    { field: 'usage', title: 'Total Usage & Ticketed' },
    { field: 'created_by', title: 'Created' },
    { field: 'updated_by', title: 'Updated' },
    { field: 'action', title: 'Action' },
];

const rows = computed(() =>
    rData.value.map((item, index) => ({
        ...item,
        sl: index + 1,
        pax_type_label: paxTypeLabel(item.pax_type),
    }))
);

async function getListValues() {
    loading.value = true;
    try {
        const response = await axiosInstance.get('getTraveler');
        rData.value = response.data.data;
    } catch (error) {
        console.log(error);
    }
    loading.value = false;
}

function handleEdit(item) {
    router.push({ name: 'TravelerEdit', params: { ids: item.idd } });
}

async function handleView(row) {
    // router.push({ name: 'TravelerView', params: { ids: item.idd } });
    await runAction(async () => {
        if (!row?.idd) return;
        await router.push({ name: 'TravelerView', params: { ids: row.idd } });
    }, {
        setLoading: (val) => {
            loadingItemId.value = val ? row?.idd ?? null : null;
            loadingAction.value = val ? 'view' : null;
        },
    });
}

function handleHistory(item) {
    router.push({ name: 'UserLog', params: { id: item.idd } });
}

function handleDelete(item) {
    iziToast.question({
        timeout: 100000,
        pauseOnHover: false,
        close: false,
        overlay: true,
        displayMode: 'once',
        id: 'question',
        zindex: 999,
        message: 'Want to delete this traveler?',
        position: 'center',
        buttons: [
            ['<button><b>No</b></button>', (instance, toast) => {
                instance.hide({ transitionOut: 'fadeOut' }, toast, 'no');
            }, true],
            ['<button><b>Yes</b></button>', (instance, toast) => {
                instance.hide({ transitionOut: 'fadeOut' }, toast, 'yes');
            }, true],
        ],
        onClosed: async (instance, toast, closedBy) => {
            if (closedBy === 'yes') {
                await axiosInstance.post('deleteTraveler', { id: item.idd });
                getListValues();
                Notification.showToast('s', 'Successfully Traveler Deleted.');
            }
        },
    });
}

getListValues();
</script>

<template>
        <AppBreadcrumbs
        title="Traveller Management"
        :back-to="{ name: 'Home' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Traveller List' },
        ]"
    >
        <template #actions>
            <div class="btn-group">
                <router-link :to="{ name: 'CreateTraveller' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i> Traveller
                </router-link>

            </div>
        </template>
    </AppBreadcrumbs>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total Traveller</span>
                    <span class="info-agency-number">{{ statistics.total }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="active-agency mb-3">
                <span class="active-agency-icon bg-success elevation-1 text-white"><i
                        class="fa-solid fa-circle-check"></i></span>
                <div class="active-agency-content">
                    <span class="active-agency-text">Total Adult</span>
                    <span class="active-agency-number">{{ statistics.adults }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fa fa-pause"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Children</span>
                    <span class="info-box-number">{{ statistics.child }}</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa fa-clock"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">Total Infant</span>
                    <span class="pending-agnt-number">{{ statistics.infant }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="row">
        <div class="col-12">
            <div class="card rounded rounded-2 shadow-none p-3">
                <AppDataTable table-id="traveler-list" :rows="rows" :columns="columns" :loading="loading"
                    :page-size="30" search-placeholder="Search by anything.." @refresh="getListValues">
                    <!-- SL -->
                    <template #sl="{ value: row }">
                        <span class="text-muted fw-semibold">{{ row.sl }}</span>
                    </template>

                    <!-- Traveler Info -->
                    <template #traveler="{ value: row }">
                        <span class="d-flex flex-column">
                            <span class="fw-semibold">{{ row.full_name || '-' }}</span>
                            <small class="text-primary">
                                <i class="fa-regular fa-calendar me-1" style="font-size:0.65rem"></i>
                                {{ row.dob ? moment(row.dob).format('DD-MMM-YYYY') : '-' }}
                            </small>
                            <small class="text-primary">
                                {{ row.gender || '-' }} | {{ row.pax_type_label }}
                            </small>
                        </span>
                    </template>

                    <!-- Passport Info -->
                    <template #passport="{ value: row }">
                        <span class="d-flex flex-column">
                            <span class="fw-semibold">
                                <i class="fa-solid fa-id-card text-info me-1" style="font-size:0.75rem"></i>
                                {{ row.passport_number || '-' }}
                            </span>
                            <small class="text-primary">
                                <i class="fa-regular fa-calendar me-1" style="font-size:0.65rem"></i>
                                {{ row.passport_issue_date ? moment(row.passport_issue_date).format('DD-MMM-YYYY') : '-'
                                }}
                            </small>
                            <small class="text-primary">{{ row.nationality || '-' }}</small>
                        </span>
                    </template>

                    <!-- Contact Info -->
                    <template #contact="{ value: row }">
                        <span class="d-flex flex-column">
                            <span class="fw-semibold">
                                <i class="fa-solid fa-envelope text-secondary me-1" style="font-size:0.75rem"></i>
                                {{ row.email || '-' }}
                            </span>
                            <small class="text-primary">
                                <i class="fa-solid fa-phone me-1" style="font-size:0.65rem"></i>
                                {{ row.phone || '-' }}
                            </small>
                        </span>
                    </template>

                    <!-- Total Usage & Ticketed -->
                    <template #usage="{ value: row }">
                        <span class="d-flex flex-column">
                            <span class="fw-bold text-success">
                                <i class="fa-solid fa-bangladeshi-taka-sign me-1" style="font-size:0.75rem"></i>
                                {{ row.total_usage ?? '100000' }}
                            </span>
                            <small class="text-primary">
                                <i class="fa fa-ticket me-1"></i>{{ row.ticket_count ?? '10' }}
                            </small>
                        </span>
                    </template>

                    <!-- Created -->
                    <template #created_by="{ value: row }">
                        <span class="d-flex flex-column">
                            <span class="fw-semibold">
                                <i class="fa-solid fa-user-pen text-secondary me-1" style="font-size:0.75rem"></i>
                                {{ row.created_by || '-' }}
                            </span>
                            <small class="text-primary">
                                <i class="fa-regular fa-calendar me-1" style="font-size:0.65rem"></i>
                                {{ moment(row.created_at).format('MMM DD, YYYY [at] h:mm A') }}
                            </small>
                        </span>
                    </template>

                    <!-- Updated -->
                    <template #updated_by="{ value: row }">
                        <span class="d-flex flex-column">
                            <span class="fw-semibold">
                                <i class="fa-solid fa-user-check text-secondary me-1" style="font-size:0.75rem"></i>
                                {{ row.updated_by || '-' }}
                            </span>
                            <small v-if="row.updated_by" class="text-primary">
                                <i class="fa-regular fa-calendar me-1" style="font-size:0.65rem"></i>
                                {{ moment(row.updated_at).format('MMM DD, YYYY [at] h:mm A') }}
                            </small>
                        </span>
                    </template>

                    <!-- Action -->
                    <template #action="{ value: row }">
                        <!-- <div class="d-flex">
                            <button type="button" class="btn btn-outline-only-edit rounded-circle" style="width:30px;height:30px" @click="handleEdit(row)">
                                <i class="fa-solid fa-pencil" style="font-size:14px"></i>
                            </button>
                            <button type="button" class="btn btn-outline-only-edit rounded-circle ms-1" style="width:30px;height:30px" @click="handleView(row)">
                                <i class="fa-solid fa-eye" style="font-size:14px"></i>
                            </button>
                            <button type="button" class="btn btn-outline-timer rounded-circle ms-1" style="width:30px;height:30px" @click="handleHistory(row)">
                                <i class="fa-solid fa-clock-rotate-left" style="font-size:14px"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger rounded-circle ms-1" style="width:30px;height:30px" @click="handleDelete(row)">
                                <i class="fa-solid fa-trash" style="font-size:14px"></i>
                            </button>
                        </div> -->
                        <!-- :show-view="true" -->
                        <ActionButtons :item="row" :show-edit="true" :show-delete="true" :show-view="true"
                            :show-authorize="true" authorize-label="Approve" @view="handleView"
                            :loading-item-id="loadingItemId" :loading-action="loadingAction"
                            @authorize="handleApproval" />
                    </template>
                </AppDataTable>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Stats card styles preserved from original */
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

.btn-outline-only-edit {
    --bs-btn-color: #027de2;
    --bs-btn-border-color: #027de2;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #027de2;
    --bs-btn-hover-border-color: #027de2;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #027de2;
    --bs-btn-active-border-color: #027de2;
    --bs-btn-disabled-color: #027de2;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #027de2;
}

.btn-outline-timer {
    --bs-btn-color: #1ba3f0;
    --bs-btn-border-color: #1ba3f0;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #1ba3f0;
    --bs-btn-hover-border-color: #1ba3f0;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #1ba3f0;
    --bs-btn-active-border-color: #1ba3f0;
    --bs-btn-disabled-color: #1ba3f0;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #1ba3f0;
}
</style>
