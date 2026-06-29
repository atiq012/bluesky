<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { ref, computed, onMounted, provide } from 'vue'
import axiosInstance from '../../../axiosInstance'
import { runAction } from '../../../utils/runAction'
import { buildReceiptFromAttemptDetail } from '../../../utils/buildReceiptFromCommit'
import { useTpV2Ticket } from '../../../composables/useTpV2Ticket'
import { useTpV2Cancel } from '../../../composables/useTpV2Cancel'
import { useTpV2Void } from '../../../composables/useTpV2Void'
import AppTooltip from '../../common/AppTooltip.vue'
import BookingReceiptModal from './BookingReceiptModal.vue'
import TicketResultModal from './TicketResultModal.vue'
import CancelResultModal from './CancelResultModal.vue'
import CancelConfirmModal from './CancelConfirmModal.vue'
import VoidConfirmModal from './VoidConfirmModal.vue'
import VoidResultModal from './VoidResultModal.vue'
import BookingHistoryModal from './BookingHistoryModal.vue'

const rows = ref([])
const loading = ref(false)
const refreshLoading = ref(false)
const loadingItemId = ref(null)
const loadingAction = ref(null)
const showReceiptModal = ref(false)
const receiptData = ref(null)

const showTicketModal = ref(false)
const ticketModalData = ref({ ticketNumbers: [], ticketedAt: null, pnr: null })

const showCancelModal = ref(false)
const cancelModalData = ref({ pnr: null, cancelledAt: null })

const showCancelConfirmModal = ref(false)
const cancelTargetRow = ref(null)
const cancelConfirmLoading = ref(false)

const showHistoryModal = ref(false)
const historyTargetRow = ref(null)

const showVoidConfirmModal = ref(false)
const voidTargetRow = ref(null)
const voidConfirmLoading = ref(false)
const showVoidModal = ref(false)
const voidModalData = ref({ pnr: null, voidedAt: null, voidedTickets: [] })

const { issueTicket } = useTpV2Ticket()
const { cancelBooking } = useTpV2Cancel()
const { voidTicket } = useTpV2Void()

const tableRows = computed(() =>
    rows.value.map(row => ({
        ...row,
        _loadingAction: row.id === loadingItemId.value ? loadingAction.value : null,
    }))
)

const columns = [
    { field: 'code_name', title: 'Code & Name', sort: false },
    { field: 'sector', title: 'Sector', sort: false },
    { field: 'date', title: 'Date', sort: false },
    { field: 'pax', title: 'No. of PAX', sort: false },
    { field: 'pnr', title: 'PNR', sort: false },
    { field: 'total_fare', title: 'Total Fare', sort: false },
    { field: 'last_ticketing', title: 'Last Ticketing', sort: false },
    { field: 'tickets', title: 'Tickets', sort: false },
    { field: 'airline', title: 'Airline', sort: false },
    { field: 'status', title: 'Status', sort: false },
    { field: 'created_by', title: 'Created by', sort: false },
    { field: 'action', title: 'Action', sort: false, width: '100px' },
]

const listStats = computed(() => {
    const list = rows.value ?? []
    const total = list.length
    const confirmed = list.filter((r) =>
        ['confirmed', 'booking_confirmed'].includes(r.legacy_status_raw)
        || ['Confirmed', 'Booking Confirmed'].includes(r.legacy_status)
    ).length
    const failed = list.filter((r) =>
        r.legacy_status_raw === 'booking_failed' || r.legacy_status === 'Booking Failed'
    ).length
    const cancelled = list.filter((r) => r.attempt_status === 'cancelled').length
    return { total, confirmed, failed, cancelled }
})

const WAY_TYPE_META = {
    'Round Way': { icon: 'fa-solid fa-arrows-rotate', class: 'bl-way--round' },
    'One Way': { icon: 'fa-solid fa-arrow-right', class: 'bl-way--one' },
    'Multi City': { icon: 'fa-solid fa-route', class: 'bl-way--multi' },
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
        '<div class="bl-pax-tooltip">',
        `<span class="bl-pax-tooltip__item bl-pax-tooltip__item--adt"><i class="fa-solid fa-user"></i> ADT ${adt}</span>`,
        `<span class="bl-pax-tooltip__item bl-pax-tooltip__item--cnn"><i class="fa-solid fa-child"></i> CNN ${cnn}</span>`,
        `<span class="bl-pax-tooltip__item bl-pax-tooltip__item--inf"><i class="fa-solid fa-baby"></i> INF ${inf}</span>`,
        '</div>',
    ].join('')
}

function journeyLines(row) {
    return row?.journey_lines?.length ? row.journey_lines : []
}

function journeyDatetime(line) {
    if (line?.departure_at_fmt) return line.departure_at_fmt
    const leg = line?.legs?.[0]
    if (!leg) return '—'
    const date = leg.dep_date_fmt ?? ''
    const time = leg.dep_time_fmt ?? ''
    return [date, time].filter(Boolean).join(' ') || '—'
}

function sectorLineMeta(idx) {
    if (idx === 0) {
        return { icon: 'fa-solid fa-plane-departure', class: 'bl-sector--out' }
    }
    return { icon: 'fa-solid fa-plane-arrival', class: 'bl-sector--in' }
}

function splitDateTime(fmt) {
    if (!fmt || fmt === '—') return { date: '—', time: '—' }
    const match = String(fmt).match(/^(\d{1,2}-[A-Za-z]{3}-\d{4})\s+(.+)$/)
    if (match) return { date: match[1], time: match[2] }
    return { date: fmt, time: '—' }
}

function ticketParts(row) {
    return splitDateTime(row?.ticket_at_fmt)
}

function ticketList(row) {
    if (Array.isArray(row?.ticket_numbers) && row.ticket_numbers.length) return row.ticket_numbers
    if (row?.ticket_no) return row.ticket_no.split(',').map(t => t.trim()).filter(Boolean)
    return []
}

const STATUS_LABELS = { 'Cancelled': 'Booking Cancelled' }
function statusLabel(row) {
    const s = row?.legacy_status || row?.status || '—'
    return STATUS_LABELS[s] ?? s
}

function statusBadgeClass(row) {
    const raw = row?.legacy_status_raw ?? ''
    const s = row?.legacy_status ?? ''
    if (raw === 'ticketed'          || s === 'Ticketed')          return 'bl-status bl-status--ticketed'
    if (raw === 'booking_confirmed' || s === 'Booking Confirmed') return 'bl-status bl-status--confirmed'
    if (raw === 'booking_failed'    || s === 'Booking Failed')    return 'bl-status bl-status--failed'
    if (raw === 'ticketing'         || s === 'Ticketing')         return 'bl-status bl-status--ticketing'
    if (raw === 'confirmed'         || s === 'Confirmed')         return 'bl-status bl-status--primary'
    if (raw === 'cancelled'         || s === 'Cancelled')         return 'bl-status bl-status--cancelled'
    if (raw === 'voided'            || s === 'Voided')            return 'bl-status bl-status--voided'
    return 'bl-status bl-status--default'
}

async function load(isRefresh = false) {
    if (isRefresh) {
        refreshLoading.value = true
    } else {
        loading.value = true
    }

    try {
        const response = await axiosInstance.get('v2/booking-attempts', { params: { scope: 'booking' } })
        rows.value = response.data?.data ?? []
    } catch (error) {
        console.log(error)
        rows.value = []
    } finally {
        loading.value = false
        refreshLoading.value = false
    }
}

async function onView(row) {
    await runAction(async () => {
        if (!row?.id) return

        const res = await axiosInstance.get(`v2/booking-attempts/${row.id}`)
        const attempt = res.data?.data?.attempt
        const snapshot = attempt?.snapshot_json ?? attempt?.pre_commit_snapshot ?? null
        const commitResponse = attempt?.commit_response ?? null

        if (!commitResponse?.ReservationResponse) {
            Notification.showToast('e', 'Booking receipt is not available for this record.')
            return
        }

        receiptData.value = await buildReceiptFromAttemptDetail({
            attempt,
            snapshot,
            commitResponse,
        })
        showReceiptModal.value = true
    }, {
        setLoading: (val) => {
            loadingItemId.value = val ? row?.id ?? null : null
            loadingAction.value = val ? 'view' : null
        },
    })
}

function handleReceiptClose() {
    showReceiptModal.value = false
    receiptData.value = null
}

function isBookingConfirmed(row) {
    return row?.legacy_status_raw === 'booking_confirmed'
        || row?.legacy_status === 'Booking Confirmed'
        || row?.status === 'committed'
}

function isTicketed(row) {
    return row?.legacy_status_raw === 'ticketed'
}

function canVoidTicket(row) {
    if (!isTicketed(row) || !row?.ticket_date) return false
    const ticketDate = new Date(row.ticket_date)
    const today = new Date()
    return ticketDate.toDateString() === today.toDateString()
}

function onViewTicket(row) {
    if (!row?.ticket_no) return
    ticketModalData.value = {
        ticketNumbers: row.ticket_numbers ?? [],
        ticketedAt:    row.ticket_date ?? null,
        pnr:           row.gds_pnr ?? row.pnr ?? null,
    }
    showTicketModal.value = true
}

async function onIssueTicket(row) {
    if (!row?.id) return

    loadingItemId.value = row.id
    loadingAction.value = 'issue-ticket'

    try {
        const res = await issueTicket(row.id)

        ticketModalData.value = {
            ticketNumbers: res.ticket_numbers ?? [],
            ticketedAt: res.ticketed_at ?? null,
            pnr: row.gds_pnr ?? row.pnr ?? null,
        }
        showTicketModal.value = true

        await load()
    } catch (e) {
        Notification.showToast('e', e?.response?.data?.message || 'Ticketing failed. Please try again.')
    } finally {
        loadingItemId.value = null
        loadingAction.value = null
    }
}

function handleTicketModalClose() {
    showTicketModal.value = false
}

function onCancelBooking(row) {
    if (!row?.id) return
    cancelTargetRow.value = row
    showCancelConfirmModal.value = true
}

function handleCancelConfirmDismiss() {
    if (cancelConfirmLoading.value) return
    showCancelConfirmModal.value = false
    cancelTargetRow.value = null
}

async function onCancelConfirmed() {
    const row = cancelTargetRow.value
    if (!row?.id) return

    cancelConfirmLoading.value = true
    loadingItemId.value = row.id
    loadingAction.value = 'cancel-booking'

    try {
        const res = await cancelBooking(row.id)
        showCancelConfirmModal.value = false
        cancelTargetRow.value = null
        cancelModalData.value = {
            pnr:         res.pnr ?? row.gds_pnr ?? null,
            cancelledAt: res.cancelled_at ?? null,
        }
        showCancelModal.value = true
        await load()
    } catch (e) {
        Notification.showToast('e', e?.response?.data?.message || 'Cancellation failed. Please try again.')
    } finally {
        cancelConfirmLoading.value = false
        loadingItemId.value = null
        loadingAction.value = null
    }
}

function handleCancelModalClose() {
    showCancelModal.value = false
}

function onHistory(row) {
    if (!row?.id) return
    historyTargetRow.value = row
    showHistoryModal.value = true
}

function handleHistoryClose() {
    showHistoryModal.value = false
    historyTargetRow.value = null
}

function onVoidTicket(row) {
    if (!row?.id) return
    voidTargetRow.value = row
    showVoidConfirmModal.value = true
}

function handleVoidConfirmDismiss() {
    if (voidConfirmLoading.value) return
    showVoidConfirmModal.value = false
    voidTargetRow.value = null
}

async function onVoidConfirmed(selectedTickets) {
    const row = voidTargetRow.value
    if (!row?.id) return

    voidConfirmLoading.value = true
    loadingItemId.value = row.id
    loadingAction.value = 'void-ticket'

    try {
        const res = await voidTicket(row.id, selectedTickets)
        showVoidConfirmModal.value = false
        voidTargetRow.value = null
        voidModalData.value = {
            pnr:          res.pnr ?? row.gds_pnr ?? null,
            voidedAt:     res.voided_at ?? null,
            voidedTickets: res.voided_tickets ?? selectedTickets,
        }
        showVoidModal.value = true
        await load()
    } catch (e) {
        Notification.showToast('e', e?.response?.data?.message || 'Ticket void failed. Please try again.')
    } finally {
        voidConfirmLoading.value = false
        loadingItemId.value = null
        loadingAction.value = null
    }
}

function handleVoidModalClose() {
    showVoidModal.value = false
}

onMounted(() => load())
</script>
<template>
        <AppBreadcrumbs
        title="Flight Management"
        :back-to="{ name: 'Home' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Booking & Ticketing List' },
        ]"
    >
        <template #actions>
            <div class="btn-group">
                <router-link :to="{ name: 'CreateAgency' }" class="btn btn-outline-primary btn-sm pt-2">
                    <i class="fa fa-file-import"></i> Import PNR
                </router-link>
                &nbsp;
                <router-link :to="{ name: 'manualticketing' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i> Manual Ticketing
                </router-link>

            </div>
        </template>
    </AppBreadcrumbs>

    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fas fa-building"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total</span>
                    <span class="info-agency-number">{{ listStats.total }}</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="active-agency mb-3">
                <span class="active-agency-icon bg-success elevation-1"><i class="fa-solid fa-circle-check"></i></span>
                <div class="active-agency-content">
                    <span class="active-agency-text">Confirm</span>
                    <span class="active-agency-number">{{ listStats.confirmed }}</span>
                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa fa-clock"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">Failed</span>
                    <span class="pending-agnt-number">{{ listStats.failed }}</span>
                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fa fa-pause"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Cancelled</span>
                    <span class="info-box-number">{{ listStats.cancelled }}</span>
                </div>

            </div>

        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12 ">
            <div class="card rounded rounded-2 shadow-none p-3">
                <div class="row">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Agency</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Carrier</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Class</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="single-select-field"
                            data-placeholder="Choose one thing">
                            <option>Select Status</option>
                        </select>
                    </div>

                    <div class="col-md-1 mt-2">
                        <i class="fa fa-times text-danger"> </i> Clear
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card rounded rounded-2 shadow-none p-3 booking-list-card">
                <DataTable
                    table-id="booking-ticketing-list"
                    :rows="tableRows"
                    :columns="columns"
                    :striped="false"
                    :loading="loading"
                    :refresh-loading="refreshLoading"
                    :page-size="10"
                    :page-size-options="[10, 25, 50, 100]"
                    :sortable="false"
                    search-placeholder="Search by anything"
                    empty-state-text="No bookings"
                    no-match-text="No matching bookings"
                    @refresh="load(true)"
                >
                    <template #code_name="{ value: row }">
                        <div class="bl-stack">
                            <div class="bl-line bl-code">
                                <i class="fa-solid fa-barcode bl-ico bl-ico-barcode" aria-hidden="true" />
                                <span>{{ row?.booking_code || '—' }}</span>
                            </div>
                            <div class="bl-line bl-name">
                                <i class="fa-solid fa-building bl-ico bl-ico-building" aria-hidden="true" />
                                <span>{{ row?.agency_name || '—' }}</span>
                            </div>
                        </div>
                    </template>

                    <template #sector="{ value: row }">
                        <div class="bl-stack">
                            <div
                                v-for="(line, idx) in journeyLines(row)"
                                :key="idx"
                                class="bl-line bl-sector"
                                :class="sectorLineMeta(idx).class"
                            >
                                <i :class="sectorLineMeta(idx).icon" class="bl-ico" aria-hidden="true" />
                                <span>{{ line.sector || '—' }}</span>
                            </div>
                            <span v-if="!journeyLines(row).length">{{ row?.sector || '—' }}</span>
                        </div>
                    </template>

                    <template #date="{ value: row }">
                        <div class="bl-stack">
                            <div
                                v-for="(line, idx) in journeyLines(row)"
                                :key="idx"
                                class="bl-line bl-datetime"
                            >
                                <i class="fa-regular fa-calendar bl-ico bl-ico-cal" aria-hidden="true" />
                                <span>{{ journeyDatetime(line) }}</span>
                            </div>
                            <span v-if="!journeyLines(row).length">—</span>
                        </div>
                    </template>

                    <template #pax="{ value: row }">
                        <div class="bl-stack">
                            <AppTooltip :content="paxTooltipHtml(row)" allow-html placement="top">
                                <span class="bl-pax-total">
                                    <i class="fa-solid fa-users bl-ico bl-ico-pax" aria-hidden="true" />
                                    Pax {{ paxTotal(row) }}
                                </span>
                            </AppTooltip>
                            <div class="bl-way" :class="wayTypeMeta(row).class">
                                <i :class="wayTypeMeta(row).icon" class="bl-way__ico" aria-hidden="true" />
                                <span>{{ row?.way_type || 'One Way' }}</span>
                            </div>
                        </div>
                    </template>

                    <template #pnr="{ value: row }">
                        <div class="bl-stack">
                            <div class="bl-line bl-pnr-gds">
                                <i class="fa-solid fa-ticket bl-ico" aria-hidden="true" />
                                <span>{{ row?.gds_pnr || '—' }}</span>
                            </div>
                            <div class="bl-line bl-pnr-airline">
                                <i class="fa-solid fa-plane bl-ico" aria-hidden="true" />
                                <span>{{ row?.airline_pnr || '—' }}</span>
                            </div>
                        </div>
                    </template>

                    <template #total_fare="{ value: row }">
                        <div class="bl-fare">
                            <i class="fa-solid fa-bangladeshi-taka-sign bl-ico bl-ico-fare" aria-hidden="true" />
                            <span>{{ row?.total_fare_label || '—' }}</span>
                        </div>
                    </template>

                    <template #last_ticketing="{ value: row }">
                        <div class="bl-stack">
                            <div class="bl-line bl-deadline-date">
                                <i class="fa-regular fa-calendar bl-ico bl-ico-cal" aria-hidden="true" />
                                <span>{{ row?.payment_deadline_date || '—' }}</span>
                            </div>
                            <div class="bl-line bl-deadline-time">
                                <i class="fa-regular fa-clock bl-ico bl-ico-time" aria-hidden="true" />
                                <span>{{ row?.payment_deadline_time || '—' }}</span>
                            </div>
                        </div>
                    </template>

                    <template #tickets="{ value: row }">
                        <div class="bl-stack">
                            <div
                                class="bl-line bl-ticket-no"
                                :class="{ 'bl-ticket-no--clickable': row?.ticket_no }"
                                @click="row?.ticket_no && onViewTicket(row)"
                            >
                                <i class="fa-solid fa-ticket bl-ico" aria-hidden="true" />
                                <template v-if="row?.ticket_no">
                                    <span>{{ ticketList(row)[0] }}</span>
                                    <span v-if="ticketList(row).length > 1" class="bl-ticket-more">+{{ ticketList(row).length - 1 }} More</span>
                                </template>
                                <span v-else>—</span>
                            </div>
                            <div v-if="row?.ticket_no" class="bl-line bl-ticket-at">
                                <i class="fa-regular fa-calendar bl-ico bl-ico-cal" aria-hidden="true" />
                                <span>{{ ticketParts(row).date }}</span>
                                <span class="bl-ticket-at__sep">|</span>
                                <i class="fa-regular fa-clock bl-ico bl-ico-time" aria-hidden="true" />
                                <span>{{ ticketParts(row).time }}</span>
                            </div>
                        </div>
                    </template>

                    <template #airline="{ value: row }">
                        <div class="bl-stack">
                            <div class="bl-line bl-airline-name">
                                <i class="fa-solid fa-plane bl-ico" aria-hidden="true" />
                                <span>{{ row?.airline_name || row?.airline_code || '—' }}</span>
                            </div>
                            <div v-if="row?.cabin_class" class="bl-line bl-cabin-class">
                                <i class="fa-solid fa-chair bl-ico" aria-hidden="true" />
                                <span>{{ row.cabin_class }}</span>
                            </div>
                        </div>
                    </template>

                    <template #created_by="{ value: row }">
                        <CreatedInfo
                            :name="row?.created_by"
                            :date="row?.created_at_iso || row?.created_at"
                            :image-path="row?.created_by_avatar || ''"
                        />
                    </template>

                    <template #status="{ value: row }">
                        <span :class="statusBadgeClass(row)">
                            <i class="bx bxs-circle me-1" aria-hidden="true" />
                            {{ statusLabel(row) }}
                        </span>
                    </template>

                    <template #action="{ value: row }">
                        <ActionButtons
                            :item="row"
                            :show-view="true"
                            :show-edit="false"
                            :show-delete="false"
                            :show-issue-ticket="isBookingConfirmed(row)"
                            :show-cancel-booking="isBookingConfirmed(row)"
                            :show-void-ticket="canVoidTicket(row)"
                            :show-history="true"
                            :loading-item-id="row._loadingAction ? row.id : null"
                            :loading-action="row._loadingAction"
                            @view="onView"
                            @issue-ticket="onIssueTicket"
                            @cancel-booking="onCancelBooking"
                            @void-ticket="onVoidTicket"
                            @history="onHistory"
                        />
                    </template>
                </DataTable>
            </div>
        </div>
    </div>

    <BookingReceiptModal
        :visible="showReceiptModal"
        :receipt="receiptData"
        @close="handleReceiptClose"
    />

    <TicketResultModal
        :visible="showTicketModal"
        :ticket-numbers="ticketModalData.ticketNumbers"
        :ticketed-at="ticketModalData.ticketedAt"
        :pnr="ticketModalData.pnr"
        @close="handleTicketModalClose"
    />

    <CancelResultModal
        :visible="showCancelModal"
        :pnr="cancelModalData.pnr"
        :cancelled-at="cancelModalData.cancelledAt"
        @close="handleCancelModalClose"
    />

    <CancelConfirmModal
        :visible="showCancelConfirmModal"
        :pnr="cancelTargetRow?.gds_pnr ?? cancelTargetRow?.pnr ?? null"
        :booking-code="cancelTargetRow?.booking_code ?? null"
        :loading="cancelConfirmLoading"
        @confirm="onCancelConfirmed"
        @cancel="handleCancelConfirmDismiss"
    />

    <BookingHistoryModal
        :visible="showHistoryModal"
        :attempt-id="historyTargetRow?.id ?? null"
        :pnr="historyTargetRow?.gds_pnr ?? historyTargetRow?.pnr ?? null"
        @close="handleHistoryClose"
    />

    <VoidConfirmModal
        :visible="showVoidConfirmModal"
        :pnr="voidTargetRow?.gds_pnr ?? voidTargetRow?.pnr ?? null"
        :booking-code="voidTargetRow?.booking_code ?? null"
        :ticket-numbers="voidTargetRow?.ticket_numbers ?? []"
        :ticket-pax-map="voidTargetRow?.ticket_pax_map ?? {}"
        :loading="voidConfirmLoading"
        @confirm="onVoidConfirmed"
        @cancel="handleVoidConfirmDismiss"
    />

    <VoidResultModal
        :visible="showVoidModal"
        :pnr="voidModalData.pnr"
        :voided-at="voidModalData.voidedAt"
        :voided-tickets="voidModalData.voidedTickets"
        @close="handleVoidModalClose"
    />
</template>

<style scoped>
.booking-list-card {
    min-width: 0;
}

.bl-stack {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    white-space: nowrap;
}

.bl-line {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.bl-ico {
    width: 14px;
    text-align: center;
    flex-shrink: 0;
}

.bl-ico-building { color: #2563eb; }
.bl-ico-barcode { color: #7c3aed; }
.bl-ico-cal { color: #0d9488; }
.bl-ico-time { color: #ea580c; }
.bl-ico-fare { color: #059669; }
.bl-ico-pax { color: #0284c7; }

.bl-code {
    font-weight: 700;
    color: #7c3aed;
}

.bl-name {
    font-weight: 500;
    color: #334155;
}

.bl-sector {
    font-weight: 600;
}

.bl-sector--out {
    color: #027de2;
}

.bl-sector--out .bl-ico {
    color: #027de2;
}

.bl-sector--in {
    color: #00ab55;
}

.bl-sector--in .bl-ico {
    color: #00ab55;
}

.bl-datetime {
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
}

.bl-pax-total {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 600;
    color: #0284c7;
    cursor: default;
}

.bl-way {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.bl-way__ico {
    width: 14px;
    text-align: center;
}

.bl-way--round { color: #027de2; }
.bl-way--one { color: #64748b; }
.bl-way--multi { color: #7c3aed; }

.bl-pnr-gds {
    color: #9333ea;
    font-weight: 600;
}

.bl-pnr-airline {
    color: #0369a1;
    font-weight: 600;
}

.bl-fare {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.bl-deadline-date,
.bl-deadline-time {
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
}

.bl-deadline-date {
    color: #0d9488;
}

.bl-deadline-date .bl-ico {
    color: #0d9488;
}

.bl-deadline-time {
    color: #ea580c;
}

.bl-deadline-time .bl-ico {
    color: #ea580c;
}

.bl-ticket-at {
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
}

.bl-ticket-at__sep {
    opacity: 0.45;
    margin: 0 0.1rem;
}

.bl-ticket-no {
    font-weight: 600;
    color: #9333ea;
}

.bl-ticket-no .bl-ico {
    color: #9333ea;
}

.bl-ticket-more {
    font-size: 0.68rem;
    font-weight: 600;
    color: #7c3aed;
    background: #ede9fe;
    border-radius: 999px;
    padding: 0.05rem 0.4rem;
    white-space: nowrap;
    flex-shrink: 0;
}

.bl-airline-name {
    font-weight: 600;
    color: #0369a1;
}

.bl-airline-name .bl-ico {
    color: #0ea5e9;
}

.bl-cabin-class {
    font-size: 0.82rem;
    color: #059669;
}

.bl-cabin-class .bl-ico {
    color: #10b981;
}

.bl-ticket-no--clickable {
    cursor: pointer;
    border-radius: 0.3rem;
    transition: background 0.12s, color 0.12s;
    padding: 0.1rem 0.3rem;
    margin-left: -0.3rem;
}

.bl-ticket-no--clickable:hover {
    background: #f5f3ff;
    color: #6d28d9;
    text-decoration: underline;
}

.bl-ticket-no--clickable:hover .bl-ico {
    color: #6d28d9;
}

.bl-status {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    white-space: nowrap;
}

.bl-status--confirmed {
    color: #059669;
    background: #e6f7f0;
}

.bl-status--progress {
    color: #d97706;
    background: #fef3c7;
}

.bl-status--primary {
    color: #027de2;
    background: #e8f4fd;
}

.bl-status--default {
    color: #64748b;
    background: #f1f5f9;
}

.bl-status--failed {
    color: #c2410c;
    background: #ffedd5;
}

.bl-status--ticketing {
    color: #7c3aed;
    background: #ede9fe;
}

.bl-status--ticketed {
    color: #065f46;
    background: #d1fae5;
    border-color: #6ee7b7;
}

.bl-status--cancelled {
    color: #991b1b;
    background: #fee2e2;
}

.bl-status--voided {
    color: #881337;
    background: #fff1f2;
    border-color: #fda4af;
}

:deep(.bl-pax-tooltip) {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 600;
}

:deep(.bl-pax-tooltip__item) {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

:deep(.bl-pax-tooltip__item--adt) { color: #0891b2; }
:deep(.bl-pax-tooltip__item--cnn) { color: #16a34a; }
:deep(.bl-pax-tooltip__item--inf) { color: #db2777; }
</style>

<style>
.text-blue {
    color: blue;
}

[data-bs-theme=light] body .info-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #cbdff4, #bcd6f1, #aecced, #8eb6e4, #6da1dc, #4a8bd2, #1576c9);
    display: -ms-flexbox;
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
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;

}

/* dashboard design */

[data-bs-theme=dark] body .bg-info,
.info-agency-icon,
.bg-info>a {
    background-color: #06365d !important;
    color: #4f687c !important;
}

[data-bs-theme=light] body .bg-info,
.info-agency-icon,
.bg-info>a {
    background-color: #0880e1 !important;
    color: #fff !important;

    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}



.info-agency .info-agency-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.info-agency .info-agency-text {
    font-size: 19px;
    letter-spacing: normal;
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

/* active agency */

[data-bs-theme=light] body .active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #c9f1e4, #baf1de, #acf0d7, #8cefc6, #6decb1, #4ce998, #24e57c);
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;

}

[data-bs-theme=dark] body .active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    background-color: #343a40;
    border-radius: .25rem;
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;

}


[data-bs-theme=dark] body .bg-success,
.active-agency-icon,
.bg-success>a {
    background-color: #5b9a59 !important;
    color: #9fbe9e !important;
}

[data-bs-theme=light] body .bg-success,
.active-agency-icon,
.bg-success>a {
    background-color: #0ea209 !important;
    color: #fff !important;

    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.active-agency .active-agency-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.active-agency .active-agency-text {
    font-size: 19px;
    letter-spacing: normal;
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

/* Pending */

.pending-agnt .pending-agnt-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

[data-bs-theme=dark] body .bg-warning,
.pending-agnt-icon,
.bg-warning>a {
    background-color: #562b03 !important;
    color: #d0741d !important;
}

[data-bs-theme=light] body .bg-warning,
.pending-agnt-icon,
.bg-warning>a {
    background-color: #fb8e28 !important;
    color: #fff !important;

    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

[data-bs-theme=light] body .pending-agnt {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #f0ded6, #f1d7c9, #f2cfbd, #f3bea2, #f3ac88, #f29b6f, #ef8956);
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

[data-bs-theme=dark] body .pending-agnt {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    background-color: #343a40;
    border-radius: .25rem;
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.pending-agnt .pending-agnt-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.pending-agnt .pending-agnt-text {
    font-size: 19px;
    letter-spacing: normal;
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


/* On Hold */

[data-bs-theme=light] body .info-box {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #ebf0d6, #e9eeca, #e8ecbe, #e7e7a2, #e8e285, #ebdb66, #efd444);
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

[data-bs-theme=dark] body .info-box {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    background-color: #343a40;
    border-radius: .25rem;
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

[data-bs-theme=dark] body .bg-danger,
.info-box-icon,
.bg-danger>a {
    background-color: #707a03 !important;
    color: #d0d68b !important;
}

[data-bs-theme=light] body .bg-danger,
.info-box-icon,
.bg-danger>a {
    background-color: #99a705 !important;
    color: #fff !important;

    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}


.info-box .info-box-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.info-box .info-box-text {
    font-size: 19px;
    letter-spacing: normal;
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

.odd td {
    background-color: #F5F8FA;
}

.even td {
    background-color: #fff;
}

.btn-outline-user-edit {
    --bs-btn-color: #7239ea;
    --bs-btn-border-color: #7239ea;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #7239ea;
    --bs-btn-hover-border-color: #7239ea;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #7239ea;
    --bs-btn-active-border-color: #7239ea;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #7239ea;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #7239ea;
    --bs-gradient: none;
}

.btn-outline-only-edit {
    --bs-btn-color: #027de2;
    --bs-btn-border-color: #027de2;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #027de2;
    --bs-btn-hover-border-color: #027de2;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #027de2;
    --bs-btn-active-border-color: #027de2;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #027de2;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #027de2;
    --bs-gradient: none;
}

.btn-outline-action-log {
    --bs-btn-color: #f1892a;
    --bs-btn-border-color: #f1892a;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #f1892a;
    --bs-btn-hover-border-color: #f1892a;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #f1892a;
    --bs-btn-active-border-color: #f1892a;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #f1892a;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #f1892a;
    --bs-gradient: none;
}

.btn-outline-purple {
    --bs-btn-color: #7239ea;
    --bs-btn-border-color: #7239ea;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #7239ea;
    --bs-btn-hover-border-color: #7239ea;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #7239ea;
    --bs-btn-active-border-color: #7239ea;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #7239ea;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #7239ea;
    --bs-gradient: none;
}
.btn-outline-timer {
    --bs-btn-color: #1ba3f0;
    --bs-btn-border-color: #1ba3f0;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #1ba3f0;
    --bs-btn-hover-border-color: #1ba3f0;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #1ba3f0;
    --bs-btn-active-border-color: #1ba3f0;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #1ba3f0;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #1ba3f0;
    --bs-gradient: none;
}
</style>
