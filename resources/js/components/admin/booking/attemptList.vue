<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import axiosInstance from '../../../axiosInstance'
import { runAction } from '../../../utils/runAction'
import AppTooltip from '../../common/AppTooltip.vue'

const router = useRouter()
const rows = ref([])
const loading = ref(false)
const refreshLoading = ref(false)
const loadingItemId = ref(null)
const loadingAction = ref(null)

const columns = [
    { field: 'attempt_ref', title: 'Id', sort: false },
    { field: 'journey', title: 'Sector', sort: false },
    { field: 'workbench_identifier', title: 'Workbench', sort: false },
    { field: 'status', title: 'Status', sort: false, cellClass: 'attempt-status-cell' },
    { field: 'created_at', title: 'Created', sort: false, cellClass: 'attempt-created-cell' },
    { field: 'action', title: 'Action', sort: false, width: '100px' },
]

async function load(isRefresh = false) {
    if (isRefresh) {
        refreshLoading.value = true
    } else {
        loading.value = true
    }

    try {
        const res = await axiosInstance.get('v2/booking-attempts')
        rows.value = res.data?.data ?? []
    } finally {
        loading.value = false
        refreshLoading.value = false
    }
}

async function onView(row) {
    await runAction(async () => {
        if (!row?.id) return
        await router.push({ name: 'bookingAttemptDetail', params: { id: row.id } })
    }, {
        setLoading: (val) => {
            loadingItemId.value = val ? row?.id ?? null : null
            loadingAction.value = val ? 'view' : null
        },
    })
}

function sectorFrom(row) {
    if (row?.from_airport) return row.from_airport
    const part = (row?.route ?? '').split('→')[0]?.trim()
    return part || '—'
}

function sectorTo(row) {
    if (row?.to_airport) return row.to_airport
    const part = (row?.route ?? '').split('→')[1]?.trim()
    return part || '—'
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

function stageBadgeClass(row) {
    const raw = String(row?.stage_raw ?? '').toLowerCase()
    return STAGE_BADGE_CLASS[raw] || 'attempt-stage-badge attempt-stage-badge--default'
}

function apiBadgeClass(row) {
    const raw = String(row?.last_api_raw ?? '').toLowerCase()
    return API_BADGE_CLASS[raw] || 'attempt-api-badge attempt-api-badge--default'
}

const WAY_TYPE_META = {
    'Round Way': { icon: 'fa-solid fa-arrows-rotate', class: 'attempt-way--round' },
    'One Way': { icon: 'fa-solid fa-arrow-right', class: 'attempt-way--one' },
    'Multi City': { icon: 'fa-solid fa-route', class: 'attempt-way--multi' },
}

function wayTypeMeta(row) {
    const label = row?.way_type || 'One Way'
    return WAY_TYPE_META[label] || WAY_TYPE_META['One Way']
}

function paxTotal(row) {
    if (row?.pax_count != null) return Number(row.pax_count) || 0
    return (Number(row?.pax_adt) || 0) + (Number(row?.pax_cnn) || 0) + (Number(row?.pax_inf) || 0)
}

function paxTooltipHtml(row) {
    const adt = Number(row?.pax_adt) || 0
    const cnn = Number(row?.pax_cnn) || 0
    const inf = Number(row?.pax_inf) || 0
    return [
        '<div class="attempt-pax-tooltip">',
        `<span class="attempt-pax-tooltip__item attempt-pax-tooltip__item--adt"><i class="fa-solid fa-user"></i> ${adt}</span>`,
        '<span class="attempt-pax-tooltip__sep">|</span>',
        `<span class="attempt-pax-tooltip__item attempt-pax-tooltip__item--cnn"><i class="fa-solid fa-child"></i> ${cnn}</span>`,
        '<span class="attempt-pax-tooltip__sep">|</span>',
        `<span class="attempt-pax-tooltip__item attempt-pax-tooltip__item--inf"><i class="fa-solid fa-baby"></i> ${inf}</span>`,
        '</div>',
    ].join('')
}

onMounted(() => load())
</script>

<template>
        <AppBreadcrumbs
        title="Flight Management"
        :back-to="{ name: 'Home' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Booking attempts' },
        ]"
    />

    <div class="card attempt-list-card">
        <div class="card-header">Booking attempts (support audit)</div>
        <div class="card-body">
            <DataTable
                table-id="booking-attempt-list"
                :rows="rows"
                :columns="columns"
                :striped="false"
                :loading="loading"
                :refresh-loading="refreshLoading"
                :page-size="10"
                :page-size-options="[10, 25, 50, 100]"
                :sortable="false"
                search-placeholder="Search by anything"
                empty-state-text="No booking attempts"
                no-match-text="No matching attempts"
                @refresh="load(true)"
            >
                <template #journey="{ value: row }">
                    <div class="attempt-journey">
                        <div class="attempt-journey__line">
                            <i class="fa-solid fa-plane-departure attempt-journey__ico attempt-journey__ico--dep" aria-hidden="true" />
                            <span class="attempt-journey__code">{{ sectorFrom(row) }}</span>
                            <span class="attempt-journey__date">{{ row?.dep_date || '—' }}</span>
                        </div>
                        <div class="attempt-journey__line">
                            <i class="fa-solid fa-plane-arrival attempt-journey__ico attempt-journey__ico--arr" aria-hidden="true" />
                            <span class="attempt-journey__code">{{ sectorTo(row) }}</span>
                            <span v-if="row?.arrival_date" class="attempt-journey__date">{{ row.arrival_date }}</span>
                        </div>
                    </div>
                </template>
                <template #workbench_identifier="{ value: row }">
                    <div class="attempt-workbench">
                        <div class="attempt-workbench__line">
                            <i class="fa-solid fa-briefcase attempt-workbench__ico" aria-hidden="true" />
                            <span class="attempt-workbench__value">{{ row?.workbench_identifier || '—' }}</span>
                        </div>
                        <div class="attempt-workbench__line attempt-workbench__way" :class="wayTypeMeta(row).class">
                            <i :class="wayTypeMeta(row).icon" class="attempt-workbench__way-ico" aria-hidden="true" />
                            <span>{{ row?.way_type || 'One Way' }}</span>
                            <span class="attempt-workbench__sep">|</span>
                            <AppTooltip
                                :content="paxTooltipHtml(row)"
                                allow-html
                                placement="top"
                            >
                                <span class="attempt-workbench__pax">Pax {{ paxTotal(row) }}</span>
                            </AppTooltip>
                        </div>
                    </div>
                </template>
                <template #status="{ value: row }">
                    <div class="attempt-outcome">
                        <AppTooltip
                            v-if="row?.last_api_error"
                            :content="row.last_api_error"
                            placement="top"
                        >
                            <span :class="stageBadgeClass(row)">{{ row?.stage || row?.status || '—' }}</span>
                        </AppTooltip>
                        <span v-else :class="stageBadgeClass(row)">{{ row?.stage || row?.status || '—' }}</span>
                        <span :class="apiBadgeClass(row)">{{ row?.last_api_status || '—' }}</span>
                    </div>
                </template>
                <template #created_at="{ value: row }">
                    <CreatedInfo
                        :name="row?.created_by"
                        :date="row?.created_at_iso || row?.created_at"
                        :image-path="row?.created_by_avatar || ''"
                    />
                </template>
                <template #action="{ value: row }">
                    <ActionButtons
                        :item="row"
                        :show-view="true"
                        :show-edit="false"
                        :show-delete="false"
                        :loading-item-id="loadingItemId"
                        :loading-action="loadingAction"
                        @view="onView"
                    />
                </template>
            </DataTable>
        </div>
    </div>
</template>

<style scoped>
.attempt-journey {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 9rem;
    white-space: nowrap;
}

.attempt-journey__line {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    line-height: 1.2;
}

.attempt-journey__ico {
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.8rem;
}

.attempt-journey__ico--dep {
    color: #027de2;
}

.attempt-journey__ico--arr {
    color: #00ab55;
}

.attempt-journey__code {
    font-weight: 600;
    color: #334155;
    min-width: 2.25rem;
}

.attempt-journey__date {
    font-size: 0.8rem;
    color: #64748b;
}

.attempt-workbench {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 8rem;
    white-space: nowrap;
}

.attempt-workbench__line {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    line-height: 1.2;
}

.attempt-workbench__ico {
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.8rem;
    color: #7c3aed;
}

.attempt-workbench__value {
    font-weight: 600;
    color: #334155;
}

.attempt-workbench__way {
    font-size: 0.8rem;
    font-weight: 500;
}

.attempt-workbench__way-ico {
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.attempt-way--round {
    color: #027de2;
}

.attempt-way--one {
    color: #64748b;
}

.attempt-way--multi {
    color: #0891b2;
}

.attempt-workbench__sep {
    color: #cbd5e1;
    font-weight: 400;
    margin: 0 0.1rem;
}

.attempt-workbench__pax {
    color: #e85d8a;
    font-weight: 600;
    cursor: pointer;
}
</style>

<style>
.attempt-list-card .attempt-created-cell {
    white-space: normal !important;
}

.attempt-list-card .attempt-status-cell {
    text-align: center !important;
}

.attempt-outcome {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    min-width: 9rem;
}

.attempt-stage-badge,
.attempt-api-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 9.5rem;
    padding: 0.3rem 0.45rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.2;
    text-align: center;
    white-space: nowrap;
}

.attempt-stage-badge--search { background: #f0f9ff; color: #0369a1; }
.attempt-stage-badge--price { background: #fef9c3; color: #a16207; }
.attempt-stage-badge--workbench { background: #e8f4fd; color: #027de2; }
.attempt-stage-badge--offer { background: #ede9fe; color: #7c3aed; }
.attempt-stage-badge--travelers { background: #ecfdf5; color: #059669; }
.attempt-stage-badge--agency { background: #f5f3ff; color: #6d28d9; }
.attempt-stage-badge--ssr { background: #fff7ed; color: #c2410c; }
.attempt-stage-badge--ancillary { background: #fdf4ff; color: #a21caf; }
.attempt-stage-badge--review { background: #fef3c7; color: #d97706; }
.attempt-stage-badge--confirmed { background: #ede9fe; color: #7c3aed; }
.attempt-stage-badge--commit { background: #d1fae5; color: #047857; }
.attempt-stage-badge--committed { background: #d1fae5; color: #059669; }
.attempt-stage-badge--closed { background: #f1f5f9; color: #475569; }
.attempt-stage-badge--default { background: #f1f5f9; color: #64748b; }

.attempt-api-badge--success { background: #dcfce7; color: #15803d; }
.attempt-api-badge--error { background: #fee2e2; color: #dc2626; }
.attempt-api-badge--default { background: #f8fafc; color: #94a3b8; }

.attempt-pax-tooltip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.attempt-pax-tooltip__sep {
    color: rgba(255, 255, 255, 0.45);
    font-weight: 400;
}

.attempt-pax-tooltip__item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.attempt-pax-tooltip__item--adt {
    color: #7dd3fc;
}

.attempt-pax-tooltip__item--cnn {
    color: #fcd34d;
}

.attempt-pax-tooltip__item--inf {
    color: #f9a8d4;
}
</style>
