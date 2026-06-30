<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import { runAction } from '../../../utils/runAction';

const router = useRouter();

const externalRows = ref([]);
const externalLoading = ref(false);
const externalRefreshLoading = ref(false);
const loadingItemId = ref(null);
const loadingAction = ref(null);

const statusModalOpen = ref(false);
const statusSubmitting = ref(false);
const deleteModalOpen = ref(false);
const deleteLoading = ref(false);
const deleteTarget = ref(null);

const statusForm = reactive({
    status: '',
    useridStatus: '',
});

const planePlaceholder = new URL('../../../../../public/theme/appimages/Plane_origin.svg', import.meta.url).href;
const apiBaseUrl = axiosInstance.defaults.baseURL || '';
const apiOrigin = apiBaseUrl ? new URL(apiBaseUrl, window.location.origin).origin : window.location.origin;

const columns = [
    { field: 'sl', title: 'SL', sort: false, width: '60px' },
    { field: 'staff_info', title: 'Staff Info', sort: false },
    { field: 'login_info', title: 'User Contact', sort: false },
    { field: 'created_by_info', title: 'Created', sort: false, cellClass: 'user-meta-cell' },
    { field: 'updated_by_info', title: 'Updated', sort: false, cellClass: 'user-meta-cell' },
    { field: 'status', title: 'Status', sort: false, width: '120px', cellClass: 'user-status-cell' },
    { field: 'action', title: 'Action', sort: false, width: '130px' },
];

const USER_STATUS_META = {
    1: { class: 'user-status-badge user-status-badge--active', label: 'Active' },
    2: { class: 'user-status-badge user-status-badge--hold', label: 'On Hold' },
    3: { class: 'user-status-badge user-status-badge--locked', label: 'Locked' },
    4: { class: 'user-status-badge user-status-badge--deactivated', label: 'Deactivated' },
};

const totalUsers = computed(() => externalRows.value.length);
const activeUsers = computed(() => externalRows.value.filter((u) => Number(u.status) === 1).length);
const holdUsers = computed(() => externalRows.value.filter((u) => Number(u.status) === 2).length);
const lockedUsers = computed(() => externalRows.value.filter((u) => Number(u.status) === 3).length);

const tableRows = computed(() => externalRows.value);
const tableLoading = computed(() => externalLoading.value);
const tableRefreshLoading = computed(() => externalRefreshLoading.value);

function refreshActiveTable() {
    loadExternal(true);
}

function normalizeRows(data) {
    return (data ?? []).map((row) => ({
        ...row,
        id: row.idd ?? row.id,
    }));
}

function resolveImageUrl(path) {
    if (!path) return '';
    const cleanPath = String(path).trim();
    if (cleanPath.startsWith('http://') || cleanPath.startsWith('https://')) return cleanPath;
    return `${apiOrigin}${cleanPath.startsWith('/') ? cleanPath : `/${cleanPath}`}`;
}

function displayValue(value, fallback = '—') {
    if (value == null || value === '') return fallback;
    return value;
}

function statusMeta(row) {
    const key = Number(row?.status);
    return USER_STATUS_META[key] || {
        class: 'user-status-badge user-status-badge--default',
        label: 'Unknown',
    };
}

async function loadExternal(isRefresh = false) {
    if (isRefresh) externalRefreshLoading.value = true;
    else externalLoading.value = true;

    try {
        const response = await axiosInstance.get('getAgentExternalUsers');
        externalRows.value = normalizeRows(response.data?.data);
    } catch (error) {
        console.log(error);
    } finally {
        externalLoading.value = false;
        externalRefreshLoading.value = false;
    }
}

async function reloadAll() {
    await loadExternal(true);
}

function onShowStatusModal(row) {
    statusForm.useridStatus = row.id;
    statusForm.status = String(row.status ?? '');
    statusModalOpen.value = true;
}

async function updateStatus() {
    await runAction(async () => {
        const response = await axiosInstance.post('/user-status/update', statusForm);
        await reloadAll();
        statusModalOpen.value = false;
        Notification.showToast('s', response.data.message);
    }, { setLoading: (val) => { statusSubmitting.value = val; } });
}

async function onEdit(row) {
    await runAction(async () => {
        await router.push({ name: 'EditUser', params: { id: row.id } });
    }, {
        setLoading: (val) => {
            loadingItemId.value = val ? row.id : null;
            loadingAction.value = val ? 'edit' : null;
        },
    });
}

async function onHistory(row) {
    await runAction(async () => {
        await router.push({ name: 'UserLog', params: { id: row.id } });
    }, {
        setLoading: (val) => {
            loadingItemId.value = val ? row.id : null;
            loadingAction.value = val ? 'history' : null;
        },
    });
}

function onDelete(row) {
    deleteTarget.value = row;
    deleteModalOpen.value = true;
}

async function confirmDelete() {
    if (!deleteTarget.value?.id) return;

    await runAction(async () => {
        await axiosInstance.post('deleteUser', { id: deleteTarget.value.id });
        await reloadAll();
        deleteModalOpen.value = false;
        deleteTarget.value = null;
        Notification.showToast('s', 'Successfully User Deleted.');
    }, { setLoading: (val) => { deleteLoading.value = val; } });
}

onMounted(() => {
    loadExternal();
});
</script>

<template>
        <AppBreadcrumbs
        title="User Management"
        :back-to="{ name: 'Home' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'User List' },
        ]"
    >
        <template #actions>
            <div class="btn-group">
                <router-link :to="{ name: 'CreateUser' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i> User
                </router-link>
            </div>
        </template>
    </AppBreadcrumbs>

    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total User</span>
                    <span class="info-agency-number">{{ totalUsers }}</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="active-agency mb-3">
                <span class="active-agency-icon bg-success elevation-1 text-white"><i
                        class="fa-solid fa-circle-check"></i></span>
                <div class="active-agency-content">
                    <span class="active-agency-text">Active User</span>
                    <span class="active-agency-number">{{ activeUsers }}</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fa fa-pause"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">On Hold</span>
                    <span class="info-box-number">{{ holdUsers }}</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa fa-clock"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">Locked</span>
                    <span class="pending-agnt-number">{{ lockedUsers }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-none">
        <div class="card-body pt-2">

            <div class="tab-content pt-3">
                <div class="card rounded rounded-2 shadow-none p-3 user-list-card">
                    <DataTable table-id="external-user-list" :rows="tableRows" :columns="columns" :striped="true"
                        :loading="tableLoading" :refresh-loading="tableRefreshLoading" :page-size="10"
                        :page-size-options="[10, 20, 30, 50]" :sortable="false" search-placeholder="Search by anything"
                        empty-state-text="No external users found" no-match-text="No matching users"
                        @refresh="refreshActiveTable">
                        <template #sl="{ value: row }">
                            <span class="user-cell-line">
                                <i class="fa-solid fa-hashtag user-ico user-ico--sl" aria-hidden="true" />
                                {{ row?.DT_RowIndex ?? '—' }}
                            </span>
                        </template>

                        <template #staff_info="{ value: row }">
                            <div class="user-staff-info">
                                <img :src="resolveImageUrl(row?.img) || planePlaceholder" alt=""
                                    class="user-staff-info__avatar">
                                <div class="user-staff-info__body">
                                    <span class="user-staff-info__name">{{ displayValue(row?.name) }}</span>
                                    <span class="user-staff-info__emp">{{ displayValue(row?.emp_id) }}</span>
                                    <span class="user-staff-info__meta">
                                        {{ displayValue(row?.desg) }} | {{ displayValue(row?.dept) }}
                                    </span>
                                </div>
                            </div>
                        </template>

                        <template #login_info="{ value: row }">
                            <div class="user-cell-stack">
                                <span class="user-cell-line user-cell-line--title">
                                    {{ displayValue(row?.phone) }}
                                </span>
                                <span class="user-cell-line user-cell-line--link">
                                    <i class="fa-solid fa-phone user-ico user-ico--phone" aria-hidden="true" />

                                    <i class="fa-solid fa-envelope user-ico user-ico--email" aria-hidden="true" />
                                    {{ displayValue(row?.email) }}
                                </span>
                            </div>
                        </template>

                        <template #created_by_info="{ value: row }">
                            <CreatedInfo :name="row?.created_by" :date="row?.created_at" />
                        </template>

                        <template #updated_by_info="{ value: row }">
                            <CreatedInfo v-if="row?.updated_by" :name="row.updated_by" :date="row.updated_at" />
                            <span v-else class="user-cell-line text-muted">—</span>
                        </template>

                        <template #status="{ value: row }">
                            <span :class="statusMeta(row).class">
                                <i class="fa-solid fa-circle user-status-badge__dot" aria-hidden="true" />
                                {{ statusMeta(row).label }}
                            </span>
                        </template>

                        <template #action="{ value: row }">
                            <ActionButtons :item="row" :show-edit="true" :show-delete="true" :show-status-modal="true"
                                :show-history="true" status-modal-label="Status" :loading-item-id="loadingItemId"
                                :loading-action="loadingAction" @edit="onEdit" @showStatusModal="onShowStatusModal"
                                @history="onHistory" @delete="onDelete" />
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>
    </div>

    <AppModal :is-open="statusModalOpen" title="Change Status" size="sm" @close="statusModalOpen = false">
        <div class="modal-body">
            <div class="card">
                <div class="card-body">
                    <label for="user-status-select" class="form-label">Status</label>
                    <select id="user-status-select" v-model="statusForm.status" class="form-select form-select-sm">
                        <option value="">== Select ==</option>
                        <option value="1">Active</option>
                        <option value="2">On Hold</option>
                        <option value="3">Locked</option>
                        <option value="4">Deactivated</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm mb-2 me-2" @click="statusModalOpen = false">Close</button>
            <button type="button" class="btn btn-sm btn-success mb-2 me-3" :disabled="statusSubmitting" @click="updateStatus">
                <span v-if="statusSubmitting" class="spinner-border spinner-border-sm me-1" role="status"
                    aria-hidden="true" />
                Update
            </button>
        </div>
    </AppModal>

    <DeleteConfirmModal :is-open="deleteModalOpen" title="Delete User" :item-name="deleteTarget?.name || ''"
        message="Want to delete this user?" :loading="deleteLoading" @close="deleteModalOpen = false"
        @confirm="confirmDelete" />
</template>

<style scoped>
.user-cell-stack {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    white-space: nowrap;
}

.user-cell-line {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    line-height: 1.25;
}

.user-cell-line--title {
    font-weight: 600;
    color: #334155;
}

.user-cell-line--link {
    font-size: 0.8125rem;
    color: #027de2;
}

.user-staff-info {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.user-staff-info__avatar {
    width: 3.75rem;
    height: 3.75rem;
    object-fit: cover;
    border-radius: 0.35rem;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.user-staff-info__body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    white-space: nowrap;
}

.user-staff-info__name {
    font-weight: 700;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-staff-info__emp {
    color: #027de2;
    font-size: 0.8125rem;
    font-weight: 600;
}

.user-staff-info__meta {
    color: #64748b;
    font-size: 0.75rem;
}

.user-ico {
    width: 0.875rem;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.user-ico--sl {
    color: #64748b;
}

.user-ico--role {
    color: #805dca;
}

.user-ico--office {
    color: #e67e22;
}

.user-ico--email {
    color: #02b9af;
}

.user-ico--phone {
    color: #00ab55;
}

.user-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

.user-status-badge__dot {
    font-size: 0.45rem;
}

.user-status-badge--active {
    background: #e6f7f0;
    color: #059669;
}

.user-status-badge--hold {
    background: #fef9c3;
    color: #ca8a04;
}

.user-status-badge--locked {
    background: #e8f4fd;
    color: #027de2;
}

.user-status-badge--deactivated {
    background: #fdecec;
    color: #dc2626;
}

.user-status-badge--default {
    background: #f1f5f9;
    color: #64748b;
}
</style>

<style>
.user-list-card .user-meta-cell {
    white-space: normal !important;
}

.user-list-card .user-status-cell {
    text-align: center !important;
}

.user-list-card .bh-table-responsive table tbody tr td:last-child {
    text-align: center !important;
}

.user-list-card .action-buttons-grid {
    margin-inline: auto;
}

[data-bs-theme=dark] .user-staff-info__avatar {
    border-color: #495057;
}

[data-bs-theme=dark] .user-staff-info__name,
[data-bs-theme=dark] .user-cell-line--title {
    color: #e9ecef;
}

[data-bs-theme=dark] .user-staff-info__meta {
    color: #adb5bd;
}

[data-bs-theme=light] body .info-agency {
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

[data-bs-theme=dark] body .info-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

[data-bs-theme=light] body .bg-info,
.info-agency-icon {
    background-color: #0880e1 !important;
    color: #fff !important;
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}

[data-bs-theme=dark] body .bg-info,
.info-agency-icon {
    background-color: #06365d !important;
    color: #4f687c !important;
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

[data-bs-theme=light] body .active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #c9f1e4, #baf1de, #acf0d7, #8cefc6, #6decb1, #4ce998, #24e57c);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

[data-bs-theme=dark] body .active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    background-color: #343a40;
    border-radius: .25rem;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

[data-bs-theme=light] body .bg-success,
.active-agency-icon {
    background-color: #0ea209 !important;
    color: #fff !important;
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.active-agency .active-agency-content,
.pending-agnt .pending-agnt-content,
.info-box .info-box-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.5;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.active-agency .active-agency-text,
.pending-agnt .pending-agnt-text,
.info-box .info-box-text {
    font-size: 19px;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.active-agency .active-agency-number,
.pending-agnt .pending-agnt-number,
.info-box .info-box-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

[data-bs-theme=light] body .pending-agnt {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #f0ded6, #f1d7c9, #f2cfbd, #f3bea2, #f3ac88, #f29b6f, #ef8956);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

[data-bs-theme=dark] body .pending-agnt {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    background-color: #343a40;
    border-radius: .25rem;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

.pending-agnt .pending-agnt-icon,
.info-box .info-box-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}

[data-bs-theme=light] body .bg-warning,
.pending-agnt-icon {
    background-color: #fb8e28 !important;
    color: #fff !important;
}

[data-bs-theme=light] body .info-box {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #ebf0d6, #e9eeca, #e8ecbe, #e7e7a2, #e8e285, #ebdb66, #efd444);
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

[data-bs-theme=dark] body .info-box {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    background-color: #343a40;
    border-radius: .25rem;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    width: 100%;
}

[data-bs-theme=light] body .bg-danger,
.info-box-icon {
    background-color: #99a705 !important;
    color: #fff !important;
}
</style>
