<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { ref, computed, onMounted, onUnmounted, provide } from 'vue'
import axiosInstance from '../../../axiosInstance'
import { runAction } from '../../../utils/runAction'
import { buildReceiptFromAttemptDetail } from '../../../utils/buildReceiptFromCommit'
import { useTpV2Ticket } from '../../../composables/useTpV2Ticket'
import { useTpV2Cancel } from '../../../composables/useTpV2Cancel'
import { useTpV2Void } from '../../../composables/useTpV2Void'
import { useRealtimeList } from '../../../composables/useRealtimeList'
import AppTooltip from '../../common/AppTooltip.vue'
import BookingReceiptModal from './BookingReceiptModal.vue'
import TicketResultModal from './TicketResultModal.vue'
import CancelResultModal from './CancelResultModal.vue'
import CancelConfirmModal from './CancelConfirmModal.vue'
import IssueTicketConfirmModal from './IssueTicketConfirmModal.vue'
import VoidConfirmModal from './VoidConfirmModal.vue'
import VoidResultModal from './VoidResultModal.vue'
import BookingHistoryModal from './BookingHistoryModal.vue'
import TicketErrorModal from './TicketErrorModal.vue'
import SearchWingsBuildLoader from '../../search/SearchWingsBuildLoader.vue'

const rows = ref([])
const loading = ref(false)
const refreshLoading = ref(false)
const loadingItemId = ref(null)
const loadingAction = ref(null)
const issueTicketOverlay = ref(false)
const showReceiptModal = ref(false)
const receiptData = ref(null)

const showTicketModal = ref(false)
const ticketModalData = ref({ ticketNumbers: [], ticketedAt: null, pnr: null })
// Full booking receipt for the ticket modal's print buttons — null while unavailable,
// which just hides "Print All Ticket" / "Print Single Page" rather than erroring.
const ticketPrintReceipt = ref(null)

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

const showTicketErrorModal = ref(false)
const ticketErrorData = ref({ pnr: null, message: null })

const showIssueTicketConfirmModal = ref(false)
const issueTicketTargetRow = ref(null)
const issueTicketConfirmLoading = ref(false)

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
    { field: 'code_name', title: 'Booking ID & Agency', sort: false },
    { field: 'sector', title: 'Sector', sort: false },
    { field: 'date', title: 'Date', sort: false },
    { field: 'pax', title: 'No. of PAX', sort: false },
    { field: 'pnr', title: 'PNR', sort: false,headerClass: 'text-uppercase' },
    { field: 'total_fare', title: 'Total Fare', sort: false },
    { field: 'ticketing', title: 'Ticketing', sort: false },
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
    return (Number(row?.pax_adt) || 0) + (Number(row?.pax_cnn) || 0) + (Number(row?.pax_kid) || 0)
        + (Number(row?.pax_inf) || 0) + (Number(row?.pax_ins) || 0)
}

function paxTooltipHtml(row) {
    const adt = Number(row?.pax_adt) || 0
    const cnn = Number(row?.pax_cnn) || 0
    const kid = Number(row?.pax_kid) || 0
    const inf = Number(row?.pax_inf) || 0
    const ins = Number(row?.pax_ins) || 0
    const items = [
        `<span class="bl-pax-tooltip__item bl-pax-tooltip__item--adt"><i class="fa-solid fa-user"></i> ADT ${adt}</span>`,
        `<span class="bl-pax-tooltip__item bl-pax-tooltip__item--cnn"><i class="fa-solid fa-child"></i> CNN ${cnn}</span>`,
    ]
    if (kid) items.push(`<span class="bl-pax-tooltip__item bl-pax-tooltip__item--cnn"><i class="fa-solid fa-child-reaching"></i> KID ${kid}</span>`)
    items.push(`<span class="bl-pax-tooltip__item bl-pax-tooltip__item--inf"><i class="fa-solid fa-baby"></i> INF ${inf}</span>`)
    if (ins) items.push(`<span class="bl-pax-tooltip__item bl-pax-tooltip__item--inf"><i class="fa-solid fa-baby"></i> INS ${ins}</span>`)
    return ['<div class="bl-pax-tooltip">', ...items, '</div>'].join('')
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

// Ticks every second so the seconds digit reads as a live clock rather than a static figure
const countdownTick = ref(Date.now())
let countdownTimer = null

onMounted(() => {
    countdownTimer = setInterval(() => { countdownTick.value = Date.now() }, 1000)
})
onUnmounted(() => {
    if (countdownTimer) clearInterval(countdownTimer)
})

function buildCountdown(iso, now) {
    if (!iso) return null
    const target = new Date(iso).getTime()
    if (Number.isNaN(target)) return null

    const diffMs = target - now
    if (diffMs <= 0) {
        return { head: 'Expired', seconds: null, severity: 'expired', label: 'Ticketing deadline has expired' }
    }

    const totalSeconds = Math.floor(diffMs / 1000)
    const days = Math.floor(totalSeconds / 86400)
    const hours = Math.floor((totalSeconds % 86400) / 3600)
    const minutes = Math.floor((totalSeconds % 3600) / 60)
    const seconds = totalSeconds % 60

    // Larger units drop off once they hit zero so the chip stays compact
    const head = days > 0
        ? `${days}D-${hours}H-${minutes}M`
        : hours > 0
            ? `${hours}H-${minutes}M`
            : `${minutes}M`

    const spoken = [
        days ? `${days} day${days > 1 ? 's' : ''}` : null,
        hours ? `${hours} hour${hours > 1 ? 's' : ''}` : null,
        minutes ? `${minutes} minute${minutes > 1 ? 's' : ''}` : null,
    ].filter(Boolean).join(' ')

    // Tiers track how an agent actually triages: plenty of time, due today, or act now
    const severity = totalSeconds < 10800
        ? 'critical'
        : totalSeconds < 86400
            ? 'soon'
            : 'calm'

    return {
        head,
        seconds: String(seconds).padStart(2, '0'),
        severity,
        label: `${spoken} left to issue the ticket`,
    }
}

// Computed once per tick instead of per template reference, so a 1s interval stays cheap
const countdowns = computed(() => {
    const now = countdownTick.value
    const map = {}
    for (const row of rows.value ?? []) {
        if (row?.ticket_no || !row?.payment_deadline) continue
        map[row.id] = buildCountdown(row.payment_deadline, now)
    }
    return map
})

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
        console.log('Booking attempts loaded:', rows.value)
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

// Flame/view button is one action but its target differs by row status —
// Ticketed rows print the ticket, everything else prints the booking confirmation.
function onViewAction(row) {
    if (isTicketed(row)) return onViewTicket(row)
    return onView(row)
}

async function onViewTicket(row) {
    if (!row?.ticket_no) return
    ticketModalData.value = {
        ticketNumbers: row.ticket_numbers ?? [],
        ticketedAt:    row.ticket_date ?? null,
        pnr:           row.gds_pnr ?? row.pnr ?? null,
    }
    // Clear first — otherwise a previous row's receipt could flash on this one while it loads
    ticketPrintReceipt.value = null
    showTicketModal.value = true

    if (row.id) {
        ticketPrintReceipt.value = await buildTicketPrintReceipt(row.id, row.ticket_date)
    }
}

// Best-effort — the "View" flow already builds this same receipt shape from the same
// endpoint, so a failure here just hides the print buttons rather than the ticket modal itself.
async function buildTicketPrintReceipt(attemptId, ticketedAt) {
    try {
        const res = await axiosInstance.get(`v2/booking-attempts/${attemptId}`)
        const attempt = res.data?.data?.attempt
        const snapshot = attempt?.snapshot_json ?? attempt?.pre_commit_snapshot ?? null
        const commitResponse = attempt?.commit_response ?? null
        if (!commitResponse?.ReservationResponse) return null

        const receipt = await buildReceiptFromAttemptDetail({ attempt, snapshot, commitResponse })
        receipt.status = 'Ticketed'
        // Ticketed already — the "issue by" deadline notice no longer applies
        receipt.paymentDeadline = null
        receipt.paymentDeadlineLong = null
        if (ticketedAt) {
            receipt.ticketDate = new Date(ticketedAt).toLocaleString('en-GB', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true,
            })
        }
        return receipt
    } catch {
        return null
    }
}

function sectorLabel(row) {
    const lines = journeyLines(row)
    if (lines.length) {
        return lines.map((l) => l.sector).filter(Boolean).join(' · ') || null
    }
    return row?.sector || null
}

function onIssueTicket(row) {
    if (!row?.id) return
    issueTicketTargetRow.value = row
    showIssueTicketConfirmModal.value = true
}

function handleIssueTicketConfirmDismiss() {
    if (issueTicketConfirmLoading.value) return
    showIssueTicketConfirmModal.value = false
    issueTicketTargetRow.value = null
}

async function onIssueTicketConfirmed() {
    const row = issueTicketTargetRow.value
    if (!row?.id) return

    issueTicketConfirmLoading.value = true
    loadingItemId.value = row.id
    loadingAction.value = 'issue-ticket'
    issueTicketOverlay.value = true

    try {
        const res = await issueTicket(row.id)

        showIssueTicketConfirmModal.value = false
        issueTicketTargetRow.value = null

        ticketModalData.value = {
            ticketNumbers: res.ticket_numbers ?? [],
            ticketedAt: res.ticketed_at ?? null,
            pnr: row.gds_pnr ?? row.pnr ?? null,
        }
        showTicketModal.value = true

        const [receipt] = await Promise.all([
            buildTicketPrintReceipt(row.id, res.ticketed_at),
            load(),
        ])
        ticketPrintReceipt.value = receipt
        // Wallet settled on issue — Topbar skips own Pusher actor, so refresh local header now
        window.dispatchEvent(new Event('balance:refresh'))
    } catch (e) {
        const msg = e?.response?.data?.message || 'Ticketing failed. Please try again.'
        ticketErrorData.value = { pnr: row?.gds_pnr ?? row?.pnr ?? null, message: msg }
        showTicketErrorModal.value = true
        showIssueTicketConfirmModal.value = false
        issueTicketTargetRow.value = null
    } finally {
        issueTicketConfirmLoading.value = false
        loadingItemId.value = null
        loadingAction.value = null
        issueTicketOverlay.value = false
    }
}

function handleTicketErrorModalClose() {
    showTicketErrorModal.value = false
}

function handleTicketModalClose() {
    showTicketModal.value = false
    ticketPrintReceipt.value = null
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
        // Void refund hits wallet — refresh Topbar Credit/Balance for this tab
        window.dispatchEvent(new Event('balance:refresh'))
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

// Peer portal confirm/ticket/cancel/void → refresh rows + summary cards
useRealtimeList('booking-attempts', () => load(true), { actorIdKey: 'actor_id' })
</script>
<template>
    <Teleport to="body">
        <Transition name="issue-ticket-fade">
            <div v-if="issueTicketOverlay" class="issue-ticket-overlay" aria-hidden="true">
                <SearchWingsBuildLoader />
            </div>
        </Transition>
    </Teleport>

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
                                <div class="bl-name-stack">
                                    <span class="bl-name-text">{{ row?.agency_name || '—' }}</span>
                                    <span class="bl-user">{{ row?.created_by || '—' }}</span>
                                </div>
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

                    <template #ticketing="{ value: row }">
                        <!-- Ticketed and awaiting-ticketing are mutually exclusive, so each state
                             leads with its own labelled pill instead of relying on colour alone -->
                        <div v-if="row?.ticket_no" class="bl-stack">
                            <div
                                class="bl-line bl-ticket-no bl-ticket-no--clickable"
                                role="button"
                                tabindex="0"
                                :aria-label="`Ticket ${ticketList(row)[0]} — view details`"
                                @click="onViewTicket(row)"
                                @keydown.enter.prevent="onViewTicket(row)"
                                @keydown.space.prevent="onViewTicket(row)"
                            >
                                <span class="bl-state-pill bl-state-pill--tkt" title="Ticket issued">TKT</span>
                                <span>{{ ticketList(row)[0] }}</span>
                                <span v-if="ticketList(row).length > 1" class="bl-ticket-more">+{{ ticketList(row).length - 1 }} More</span>
                            </div>
                            <div class="bl-line bl-ticket-at">
                                <i class="fa-regular fa-calendar bl-ico bl-ico-cal" aria-hidden="true" />
                                <span>{{ ticketParts(row).date }}</span>
                                <span class="bl-ticket-at__sep">|</span>
                                <i class="fa-regular fa-clock bl-ico bl-ico-time" aria-hidden="true" />
                                <span>{{ ticketParts(row).time }}</span>
                            </div>
                        </div>

                        <div v-else-if="row?.payment_deadline" class="bl-stack">
                            <div class="bl-line bl-deadline-date">
                                <span class="bl-state-pill bl-state-pill--lt" title="Last Ticketing deadline">LT</span>
                                <span>{{ row?.payment_deadline_date || '—' }} | {{ row?.payment_deadline_time || '—' }}</span>
                            </div>
                            <div v-if="countdowns[row.id]" class="bl-line bl-deadline-time">
                                <span class="bl-countdown-caption">Expires in</span>
                                <span
                                    class="bl-countdown"
                                    :class="`bl-countdown--${countdowns[row.id].severity}`"
                                    :aria-label="countdowns[row.id].label"
                                >
                                    <span>{{ countdowns[row.id].head }}</span>
                                    <template v-if="countdowns[row.id].seconds !== null">
                                        <span aria-hidden="true">-</span>
                                        <!-- Each digit slips independently, matching the search-page
                                             timer; the S is static so only numbers move -->
                                        <span class="bl-sec" aria-hidden="true">
                                            <span class="bl-digit-slot">
                                                <Transition name="bl-digit-slip">
                                                    <span :key="countdowns[row.id].seconds[0]" class="bl-digit-val">{{ countdowns[row.id].seconds[0] }}</span>
                                                </Transition>
                                            </span>
                                            <span class="bl-digit-slot">
                                                <Transition name="bl-digit-slip">
                                                    <span :key="countdowns[row.id].seconds[1]" class="bl-digit-val">{{ countdowns[row.id].seconds[1] }}</span>
                                                </Transition>
                                            </span>
                                            <span>S</span>
                                        </span>
                                    </template>
                                </span>
                            </div>
                        </div>

                        <span v-else class="bl-muted">—</span>
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
                            @view="onViewAction"
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
        :receipt="ticketPrintReceipt"
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

    <IssueTicketConfirmModal
        :visible="showIssueTicketConfirmModal"
        :pnr="issueTicketTargetRow?.gds_pnr ?? issueTicketTargetRow?.pnr ?? null"
        :booking-code="issueTicketTargetRow?.booking_code ?? null"
        :sector="issueTicketTargetRow ? sectorLabel(issueTicketTargetRow) : null"
        :pax-count="issueTicketTargetRow ? paxTotal(issueTicketTargetRow) : null"
        :agency-balance="issueTicketTargetRow?.agency_balance ?? null"
        :ticket-price="issueTicketTargetRow?.total_fare ?? null"
        :loading="issueTicketConfirmLoading"
        @confirm="onIssueTicketConfirmed"
        @cancel="handleIssueTicketConfirmDismiss"
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

    <TicketErrorModal
        :visible="showTicketErrorModal"
        :pnr="ticketErrorData.pnr"
        :message="ticketErrorData.message"
        @close="handleTicketErrorModalClose"
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
    align-items: flex-start;
}

.bl-name-stack {
    display: inline-flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.05rem;
    line-height: 1.1;
    width: fit-content;
    max-width: 100%;
}

.bl-name-text {
    font-weight: 500;
    color: #334155;
}

.bl-user {
    align-self: flex-end;
    font-size: 0.625rem;
    font-weight: 400;
    color: #94a3b8;
    text-align: right;
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

/* The LT pill alone carries the danger identity; the date itself is plain reference text so a
   whole column of deadlines does not read as a wall of red */
.bl-deadline-date,
.bl-deadline-time {
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
}

.bl-deadline-date {
    color: #334155;
    font-weight: 600;
}

.bl-deadline-clock {
    color: #64748b;
    font-weight: 500;
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

/* Leading state marker — the text itself carries the meaning, so the two states stay
   distinguishable without relying on colour */
.bl-state-pill {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    line-height: 1;
    border-radius: 0.25rem;
    padding: 0.2rem 0.3rem;
    flex-shrink: 0;
    width: 30px;
    text-align: center;
    cursor: default;
}

/* Low-saturation rose: enough to mark this as a deadline, quiet enough to repeat on every row */
.bl-state-pill--lt {
    background: #fff1f2;
    color: #9f1239;
    box-shadow: inset 0 0 0 1px #fecdd3;
}
.bl-state-pill--tkt { background: #ede9fe; color: #5b21b6; }

[data-bs-theme=dark] .bl-state-pill--lt {
    background: #3f0d16;
    color: #fda4af;
    box-shadow: inset 0 0 0 1px #7f1d2e;
}
[data-bs-theme=dark] .bl-state-pill--tkt { background: #2e1065; color: #c4b5fd; }

.bl-countdown-caption {
    font-size: 0.7rem;
    color: #64748b;
}

[data-bs-theme=dark] .bl-countdown-caption { color: #94a3b8; }

.bl-countdown {
    display: inline-flex;
    align-items: baseline;
    gap: 0.05rem;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    white-space: nowrap;
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
}

/* Rolling seconds — same slip mechanic as the search-page booking timer */
.bl-sec {
    display: inline-flex;
    align-items: center;
    gap: 0;
}

.bl-digit-slot {
    position: relative;
    overflow: hidden;
    height: 1.15em;
    width: 0.6em;
    display: inline-block;
    vertical-align: middle;
}

.bl-digit-val {
    display: block;
    line-height: 1.15em;
    text-align: center;
    width: 100%;
}

.bl-digit-slip-enter-active,
.bl-digit-slip-leave-active {
    transition: transform 0.32s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.32s ease;
}

.bl-digit-slip-leave-active {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
}

.bl-digit-slip-enter-from { transform: translateY(-100%); opacity: 0; }
.bl-digit-slip-enter-to   { transform: translateY(0);     opacity: 1; }
.bl-digit-slip-leave-from { transform: translateY(0);     opacity: 1; }
.bl-digit-slip-leave-to   { transform: translateY(100%);  opacity: 0; }

@media (prefers-reduced-motion: reduce) {
    .bl-digit-slip-enter-active,
    .bl-digit-slip-leave-active { transition: none; }
}

/* All four states use the same tinted-badge treatment as the LT pill and escalate by depth of
   tint, so the column never turns into a wall of solid colour */
.bl-countdown--calm {
    background: #f1f5f9;
    color: #475569;
    box-shadow: inset 0 0 0 1px #e2e8f0;
}
.bl-countdown--soon {
    background: #fef3c7;
    color: #92400e;
    box-shadow: inset 0 0 0 1px #fde68a;
}
.bl-countdown--critical {
    background: #ffe4e6;
    color: #9f1239;
    box-shadow: inset 0 0 0 1px #fda4af;
}
.bl-countdown--expired {
    background: #fecdd3;
    color: #881337;
    box-shadow: inset 0 0 0 1px #fb7185;
}

[data-bs-theme=dark] .bl-countdown--calm {
    background: #1e293b;
    color: #cbd5e1;
    box-shadow: inset 0 0 0 1px #334155;
}
[data-bs-theme=dark] .bl-countdown--soon {
    background: #422006;
    color: #fcd34d;
    box-shadow: inset 0 0 0 1px #78350f;
}
[data-bs-theme=dark] .bl-countdown--critical {
    background: #4c0519;
    color: #fda4af;
    box-shadow: inset 0 0 0 1px #9f1239;
}
[data-bs-theme=dark] .bl-countdown--expired {
    background: #6b0a20;
    color: #fecdd3;
    box-shadow: inset 0 0 0 1px #be123c;
}

.bl-muted {
    color: #94a3b8;
}

/* The mid-tone accents in this column sit below 4.5:1 on the dark surface, so lift them */
[data-bs-theme=dark] .bl-ticket-no,
[data-bs-theme=dark] .bl-ticket-no .bl-ico { color: #c4b5fd; }
[data-bs-theme=dark] .bl-deadline-date { color: #e2e8f0; }
[data-bs-theme=dark] .bl-deadline-clock { color: #94a3b8; }
[data-bs-theme=dark] .bl-muted { color: #64748b; }
[data-bs-theme=dark] .bl-ticket-no--clickable:hover {
    background: #2e1065;
    color: #ddd6fe;
}

.bl-ticket-no--clickable:focus-visible {
    outline: 2px solid #7c3aed;
    outline-offset: 2px;
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
.issue-ticket-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(6px);
    pointer-events: auto;
}

html[data-bs-theme="dark"] .issue-ticket-overlay {
    background: rgba(0, 0, 0, 0.65);
}

.issue-ticket-fade-enter-active,
.issue-ticket-fade-leave-active {
    transition: opacity 0.22s ease;
}

.issue-ticket-fade-enter-from,
.issue-ticket-fade-leave-to {
    opacity: 0;
}

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

html[data-bs-theme='dark'] .booking-list-card .bl-name-text {
    color: #cbd5e1;
}

html[data-bs-theme='dark'] .booking-list-card .bl-user {
    color: #64748b;
}
</style>
