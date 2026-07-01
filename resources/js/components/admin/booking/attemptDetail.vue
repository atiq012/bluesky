<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axiosInstance from '../../../axiosInstance'
import { runAction } from '../../../utils/runAction'
import { formatDateTime } from '../../../utils/dateUtils'

const route = useRoute()
const router = useRouter()
const attempt = ref(null)
const timeline = ref([])
const loading = ref(true)
const refreshLoading = ref(false)
const error = ref(null)
const loadingItemId = ref(null)
const loadingAction = ref(null)

const attemptBreadcrumbs = computed(() => [
    { label: 'Dashboard', to: { name: 'Home' } },
    { label: 'Booking attempts', to: { name: 'bookingAttemptList' } },
    { label: attempt.value?.id || route.params.id },
])

const timelineLabels = {
    search: 'Search',
    price: 'Price',
    reservation_workbench: 'Reservation workbench',
    add_offer: 'Add offer',
    add_traveler: 'Add traveler',
    add_travel_agency: 'Travel agency',
    add_ssr_meal: 'SSR meal',
    add_ssr_wheelchair: 'SSR wheelchair',
    pre_commit_snapshot: 'Pre-commit snapshot',
    commit: 'Commit',
    post_commit_snapshot: 'Post-commit snapshot',
    add_ancillary: 'Add ancillary',
    fare_rules_outbound: 'Fare Rules',
    fare_rules_inbound: 'Fare Rules (Return)',
}

const TYPE_META = {
    search: { icon: 'fa-solid fa-magnifying-glass', class: 'timeline-type--search' },
    price: { icon: 'fa-solid fa-tag', class: 'timeline-type--price' },
    reservation_workbench: { icon: 'fa-solid fa-briefcase', class: 'timeline-type--workbench' },
    add_offer: { icon: 'fa-solid fa-ticket', class: 'timeline-type--offer' },
    add_traveler: { icon: 'fa-solid fa-user', class: 'timeline-type--traveler' },
    add_travel_agency: { icon: 'fa-solid fa-building', class: 'timeline-type--agency' },
    add_ssr_meal: { icon: 'fa-solid fa-utensils', class: 'timeline-type--ssr' },
    add_ssr_wheelchair: { icon: 'fa-solid fa-wheelchair', class: 'timeline-type--ssr' },
    pre_commit_snapshot: { icon: 'fa-solid fa-camera', class: 'timeline-type--snapshot' },
    commit: { icon: 'fa-solid fa-check-double', class: 'timeline-type--commit' },
    post_commit_snapshot: { icon: 'fa-solid fa-camera', class: 'timeline-type--snapshot' },
    add_ancillary:      { icon: 'fa-solid fa-suitcase',       class: 'timeline-type--ancillary' },
    fare_rules_outbound: { icon: 'fa-solid fa-file-contract', class: 'timeline-type--fare-rules' },
    fare_rules_inbound:  { icon: 'fa-solid fa-file-contract', class: 'timeline-type--fare-rules' },
}

const STAGE_BADGE_CLASS = {
    search: 'attempt-stage-badge attempt-stage-badge--search',
    price: 'attempt-stage-badge attempt-stage-badge--price',
    workbench: 'attempt-stage-badge attempt-stage-badge--workbench',
    add_offer: 'attempt-stage-badge attempt-stage-badge--offer',
    travelers: 'attempt-stage-badge attempt-stage-badge--travelers',
    travel_agency: 'attempt-stage-badge attempt-stage-badge--agency',
    ssr: 'attempt-stage-badge attempt-stage-badge--ssr',
    ancillary: 'attempt-stage-badge attempt-stage-badge--ancillary',
    review: 'attempt-stage-badge attempt-stage-badge--review',
    confirmed: 'attempt-stage-badge attempt-stage-badge--confirmed',
    commit: 'attempt-stage-badge attempt-stage-badge--commit',
    committed: 'attempt-stage-badge attempt-stage-badge--committed',
    closed_search: 'attempt-stage-badge attempt-stage-badge--closed',
    closed_price: 'attempt-stage-badge attempt-stage-badge--closed',
}

const API_BADGE_CLASS = {
    success: 'attempt-api-badge attempt-api-badge--success',
    error: 'attempt-api-badge attempt-api-badge--error',
}

const STATUS_BADGE_CLASS = {
    success: 'timeline-status-badge timeline-status-badge--success',
    error: 'timeline-status-badge timeline-status-badge--error',
}

const columns = [
    { field: 'log_ref', title: 'Id', sort: false },
    { field: 'session_type', title: 'Type', sort: false },
    { field: 'status', title: 'Status', sort: false, cellClass: 'timeline-status-cell' },
    { field: 'http_status', title: 'HTTP', sort: false, cellClass: 'timeline-http-cell' },
    { field: 'created_at', title: 'Time', sort: false },
    { field: 'action', title: 'Action', sort: false, width: '100px' },
]

const timelineRows = computed(() =>
    timeline.value.map((row) => ({
        ...row,
        log_id: row.id,
        log_ref: row.log_ref ?? row.id,
        id: `${row.source}-${row.id}`,
        created_at: formatDateTime(row.created_at) || row.created_at,
    })),
)

function timelineLabel(row) {
    if (row?.type_label) return row.type_label
    const type = row?.session_type ?? row
    return timelineLabels[type] || type
}

function typeMeta(type) {
    return TYPE_META[type] || { icon: 'fa-solid fa-circle-nodes', class: 'timeline-type--default' }
}

function stageBadgeClass(row) {
    const raw = String(row?.stage_raw ?? '').toLowerCase()
    return STAGE_BADGE_CLASS[raw] || 'attempt-stage-badge attempt-stage-badge--default'
}

function apiBadgeClass(row) {
    const raw = String(row?.last_api_raw ?? '').toLowerCase()
    return API_BADGE_CLASS[raw] || 'attempt-api-badge attempt-api-badge--default'
}

function timelineStatusClass(status) {
    const raw = String(status ?? '').toLowerCase()
    return STATUS_BADGE_CLASS[raw] || 'timeline-status-badge timeline-status-badge--default'
}

function httpBadgeClass(code) {
    if (code == null || code === '' || code === '—') return 'timeline-http-badge timeline-http-badge--na'
    const n = Number(code)
    if (n >= 200 && n < 300) return 'timeline-http-badge timeline-http-badge--ok'
    if (n >= 400) return 'timeline-http-badge timeline-http-badge--err'
    return 'timeline-http-badge timeline-http-badge--default'
}

function formatHttp(code) {
    return code == null || code === '' ? '—' : code
}

async function load(isRefresh = false) {
    if (isRefresh) {
        refreshLoading.value = true
    } else {
        loading.value = true
    }
    error.value = null

    try {
        const res = await axiosInstance.get(`v2/booking-attempts/${route.params.id}`)
        attempt.value = res.data?.data?.attempt ?? null
        timeline.value = res.data?.data?.timeline ?? []
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load attempt.'
    } finally {
        loading.value = false
        refreshLoading.value = false
    }
}

function downloadPath(row, kind) {
    const logId = row.log_id ?? row.id
    const base = {
        search: `v2/booking/search-logs/${logId}`,
        price: `v2/booking/price-logs/${logId}`,
        session: `v2/booking/sessions/${logId}`,
    }
    return `${base[row.source] ?? base.session}/${kind}-download`
}

function downloadFilename(row, kind) {
    const attemptId = attempt.value?.attempt_ref ?? attempt.value?.id ?? route.params.id
    if (row.source === 'search' || row.source === 'price') {
        return `${attemptId}-${row.source}-${kind}.json`
    }
    if (row.source === 'session') {
        const sessionType = row.session_type ?? 'session'
        return `${attemptId}-${sessionType}-${kind}.json`
    }
    return `${row.source}-${kind}.json`
}

async function downloadPayload(row, kind) {
    try {
        const res = await axiosInstance.get(downloadPath(row, kind), { responseType: 'blob' })
        const raw = await res.data.text()
        let content = raw
        try {
            content = `${JSON.stringify(JSON.parse(raw), null, 4)}\n`
        } catch {
            // keep raw when response is not JSON
        }
        const url = window.URL.createObjectURL(new Blob([content], { type: 'application/json' }))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', downloadFilename(row, kind))
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
    } catch {
        error.value = 'Download failed.'
    }
}

async function onDownloadRequest(row) {
    await runAction(() => downloadPayload(row, 'request'), {
        setLoading: (val) => {
            loadingItemId.value = val ? row.id : null
            loadingAction.value = val ? 'download-request' : null
        },
    })
}

async function onDownloadResponse(row) {
    await runAction(() => downloadPayload(row, 'response'), {
        setLoading: (val) => {
            loadingItemId.value = val ? row.id : null
            loadingAction.value = val ? 'download-response' : null
        },
    })
}

async function onBack() {
    await runAction(() => router.push({ name: 'bookingAttemptList' }))
}

onMounted(() => load())
</script>

<template>
        <AppBreadcrumbs
        title="Flight Management"
        :back-to="{ name: 'bookingAttemptList' }"
        :breadcrumbs="attemptBreadcrumbs"
    />

    <div v-if="loading" class="attempt-detail-loading">
        <LoadingSpinner />
    </div>
    <div v-else-if="error && !attempt" class="alert alert-danger">{{ error }}</div>
    <template v-else-if="attempt">
        <div v-if="error" class="alert alert-danger mb-3">{{ error }}</div>

        <div class="card attempt-detail-card mb-3">
            <div class="card-header attempt-detail-card__header">
                <div class="attempt-detail-card__title">
                    <i class="fa-solid fa-clipboard-list attempt-detail-card__title-ico" aria-hidden="true" />
                    <span>Attempt {{ attempt.id }}</span>
                </div>
                <div class="attempt-detail-outcome">
                    <span :class="stageBadgeClass(attempt)">{{ attempt.stage || attempt.status }}</span>
                    <span :class="apiBadgeClass(attempt)">{{ attempt.last_api_status || '—' }}</span>
                </div>
            </div>
            <div class="card-body attempt-detail-card__body">
                <div class="attempt-detail-grid">
                    <div class="attempt-detail-item">
                        <div class="attempt-detail-item__label">
                            <i class="fa-solid fa-briefcase attempt-detail-item__ico attempt-detail-item__ico--workbench" aria-hidden="true" />
                            <span>Workbench</span>
                        </div>
                        <div class="attempt-detail-item__value">{{ attempt.workbench_identifier || '—' }}</div>
                    </div>
                    <div class="attempt-detail-item">
                        <div class="attempt-detail-item__label">
                            <i class="fa-solid fa-ticket attempt-detail-item__ico attempt-detail-item__ico--pnr" aria-hidden="true" />
                            <span>PNR</span>
                        </div>
                        <div class="attempt-detail-item__value">{{ attempt.gds_pnr || attempt.pnr || '—' }}</div>
                    </div>
                    <div class="attempt-detail-item">
                        <div class="attempt-detail-item__label">
                            <i class="fa-solid fa-calendar-check attempt-detail-item__ico attempt-detail-item__ico--created" aria-hidden="true" />
                            <span>Created</span>
                        </div>
                        <div class="attempt-detail-item__value">{{ formatDateTime(attempt.created_at) || '—' }}</div>
                    </div>
                    <div v-if="attempt.confirmed_at" class="attempt-detail-item">
                        <div class="attempt-detail-item__label">
                            <i class="fa-solid fa-circle-check attempt-detail-item__ico attempt-detail-item__ico--confirmed" aria-hidden="true" />
                            <span>Confirmed</span>
                        </div>
                        <div class="attempt-detail-item__value">{{ formatDateTime(attempt.confirmed_at) }}</div>
                    </div>
                </div>
                <div v-if="attempt.last_api_error || attempt.commit_error" class="attempt-detail-error">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
                    <span>{{ attempt.last_api_error || attempt.commit_error }}</span>
                </div>
            </div>
        </div>

        <div class="card attempt-detail-card">
            <div class="card-header attempt-detail-card__header attempt-detail-card__header--table">
                <div class="attempt-detail-card__title">
                    <i class="fa-solid fa-timeline attempt-detail-card__title-ico attempt-detail-card__title-ico--timeline" aria-hidden="true" />
                    <span>API timeline</span>
                </div>
            </div>
            <div class="card-body">
                <DataTable
                    table-id="booking-attempt-timeline"
                    :rows="timelineRows"
                    :columns="columns"
                    :striped="false"
                    :loading="false"
                    :refresh-loading="refreshLoading"
                    :page-size="50"
                    :page-size-options="[10, 25, 50, 100]"
                    :sortable="false"
                    search-placeholder="Search timeline"
                    empty-state-text="No API calls recorded"
                    no-match-text="No matching entries"
                    @refresh="load(true)"
                >
                    <template #session_type="{ value: row }">
                        <div class="timeline-type" :class="typeMeta(row?.session_type).class">
                            <i :class="typeMeta(row?.session_type).icon" class="timeline-type__ico" aria-hidden="true" />
                            <span>{{ timelineLabel(row) }}</span>
                        </div>
                    </template>
                    <template #status="{ value: row }">
                        <span :class="timelineStatusClass(row?.status)">{{ row?.status || '—' }}</span>
                    </template>
                    <template #http_status="{ value: row }">
                        <span :class="httpBadgeClass(row?.http_status)">{{ formatHttp(row?.http_status) }}</span>
                    </template>
                    <template #action="{ value: row }">
                        <ActionButtons
                            :item="row"
                            :show-edit="false"
                            :show-delete="false"
                            :show-download-request="!!row?.has_request_payload"
                            :show-download-response="!!row?.has_response_payload"
                            :loading-item-id="loadingItemId"
                            :loading-action="loadingAction"
                            @download-request="onDownloadRequest"
                            @download-response="onDownloadResponse"
                        />
                    </template>
                </DataTable>
            </div>
        </div>

        <div class="mt-3">
            <AppButton variant="return" label="Back to list" @click="onBack" />
        </div>
    </template>
</template>

<style scoped>
.attempt-detail-loading {
    min-height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.attempt-detail-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.attempt-detail-outcome {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.3rem;
}

.attempt-detail-card__header--table {
    border-bottom: 1px solid var(--bs-border-color);
}

.attempt-detail-card__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.attempt-detail-card__title-ico {
    color: #027de2;
    font-size: 1rem;
}

.attempt-detail-card__title-ico--timeline {
    color: #7c3aed;
}

.attempt-detail-card__body {
    padding-top: 1rem;
}

.attempt-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 1rem 1.5rem;
}

.attempt-detail-item__label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    margin-bottom: 0.35rem;
}

.attempt-detail-item__ico {
    width: 1rem;
    text-align: center;
    font-size: 0.8rem;
}

.attempt-detail-item__ico--workbench { color: #7c3aed; }
.attempt-detail-item__ico--pnr { color: #00ab55; }
.attempt-detail-item__ico--created { color: #027de2; }
.attempt-detail-item__ico--confirmed { color: #059669; }

.attempt-detail-item__value {
    font-weight: 600;
    color: #334155;
    word-break: break-all;
}

.attempt-detail-error {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    background: #fef2f2;
    color: #dc2626;
    font-size: 0.875rem;
}

.attempt-detail-error i {
    margin-top: 0.1rem;
    flex-shrink: 0;
}

.timeline-type {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-weight: 500;
    white-space: nowrap;
}

.timeline-type__ico {
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.8rem;
}

.timeline-type--search { color: #027de2; }
.timeline-type--price { color: #00ab55; }
.timeline-type--workbench { color: #7c3aed; }
.timeline-type--offer { color: #f59e0b; }
.timeline-type--traveler { color: #0891b2; }
.timeline-type--agency { color: #4f46e5; }
.timeline-type--ssr { color: #e11d48; }
.timeline-type--snapshot { color: #64748b; }
.timeline-type--commit { color: #059669; }
.timeline-type--ancillary { color: #ea580c; }
.timeline-type--default { color: #94a3b8; }
</style>

<style>
.attempt-detail-card .attempt-stage-badge,
.attempt-detail-card .attempt-api-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 7.5rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
}

.attempt-detail-card .attempt-stage-badge--search { background: #f0f9ff; color: #0369a1; }
.attempt-detail-card .attempt-stage-badge--price { background: #fef9c3; color: #a16207; }
.attempt-detail-card .attempt-stage-badge--workbench { background: #e8f4fd; color: #027de2; }
.attempt-detail-card .attempt-stage-badge--offer { background: #ede9fe; color: #7c3aed; }
.attempt-detail-card .attempt-stage-badge--closed { background: #f1f5f9; color: #475569; }
.attempt-detail-card .attempt-stage-badge--committed { background: #d1fae5; color: #059669; }
.attempt-detail-card .attempt-stage-badge--default { background: #f1f5f9; color: #64748b; }

.attempt-detail-card .attempt-api-badge--success { background: #dcfce7; color: #15803d; }
.attempt-detail-card .attempt-api-badge--error { background: #fee2e2; color: #dc2626; }
.attempt-detail-card .attempt-api-badge--default { background: #f8fafc; color: #94a3b8; }

.attempt-detail-card .timeline-status-cell,
.attempt-detail-card .timeline-http-cell {
    text-align: center !important;
}

.attempt-detail-card .timeline-status-badge,
.attempt-detail-card .timeline-http-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 5.5rem;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
}

.attempt-detail-card .timeline-status-badge--success {
    background: #d1fae5;
    color: #059669;
}

.attempt-detail-card .timeline-status-badge--error {
    background: #fee2e2;
    color: #dc2626;
}

.attempt-detail-card .timeline-status-badge--default {
    background: #f1f5f9;
    color: #64748b;
}

.attempt-detail-card .timeline-http-badge--ok {
    background: #e8f4fd;
    color: #027de2;
}

.attempt-detail-card .timeline-http-badge--err {
    background: #fee2e2;
    color: #dc2626;
}

.attempt-detail-card .timeline-http-badge--na {
    background: #f8fafc;
    color: #94a3b8;
}

.attempt-detail-card .timeline-http-badge--default {
    background: #f1f5f9;
    color: #64748b;
}
</style>
