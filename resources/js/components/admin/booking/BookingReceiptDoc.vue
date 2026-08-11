<script setup>
import A4PrintDoc from '../../common/print/A4PrintDoc.vue'
import axiosInstance from '../../../axiosInstance'
import { ref, computed, watch } from 'vue'

//changes

const props = defineProps({
    receipt: { type: Object, default: null },
    // Optional — renders an "E-Ticket Issued" block instead of the ticketing-deadline
    // notice. Left empty, this component behaves exactly as the plain booking receipt.
    ticketNumbers: { type: Array, default: () => [] },
    // Optional — restricts the passenger table to just this one traveler (single-ticket
    // print pages). Left null, every passenger on the booking is listed as normal.
    passengerIndex: { type: Number, default: null },
})

const a4 = ref(null)
// Public path — works in on-screen + print iframe + html2pdf (Vite import.meta URL can break off-screen)
const DEFAULT_BLUESKY_LOGO = '/theme/appimages/blueskywings.png'

// Pagination, footer pinning and the print/PDF plumbing all live in A4PrintDoc now —
// this component only describes what a booking voucher looks like.
const downloading = computed(() => a4.value?.downloading ?? false)

const hasReceipt = computed(() => !!props.receipt)
const hasTicketNumbers = computed(() => props.ticketNumbers.length > 0)

// Once ticketed, the amber box switches from the reservation warning to the
// admin-configured Ticket Policy — fetched once, on the transition into ticketed state.
const ticketPolicyPoints = ref([])
const ticketPolicyLoaded = ref(false)

async function loadTicketPolicy() {
    if (ticketPolicyLoaded.value) return
    ticketPolicyLoaded.value = true
    try {
        const response = await axiosInstance.post('editPolicy', { type: 'ticket' })
        ticketPolicyPoints.value = (response.data || []).filter((p) => Number(p.status) === 1)
    } catch {
        ticketPolicyPoints.value = []
    }
}

watch(hasTicketNumbers, (ticketed) => { if (ticketed) loadTicketPolicy() }, { immediate: true })

const printTitle = computed(() => `Voucher ${props.receipt?.bookingId ?? ''}`)
const pdfFileName = computed(() => `Voucher-${props.receipt?.bookingId ?? 'booking'}.pdf`)
// Blocks the page engine must never slice through
const KEEP_SELECTOR = '.voucher-keep'
const PDF_AVOID = ['.voucher-seg', '.voucher-leg', '.voucher-fare', '.voucher-notes', '.voucher-notice']

// No per-passenger ticket mapping comes back from Travelport — pair by position, and fall
// back to the first ticket number when there are fewer tickets than passengers.
function passengerTicketNumber(pi) {
    return props.ticketNumbers[pi] ?? props.ticketNumbers[0] ?? '—'
}

const displayPassengers = computed(() => {
    const all = props.receipt?.passengers ?? []
    if (props.passengerIndex == null) return all
    const one = all[props.passengerIndex]
    return one ? [one] : all
})
const agencyLogo = computed(() => {
    const logo = String(props.receipt?.agency?.logo ?? '').trim()
    return logo || DEFAULT_BLUESKY_LOGO
})

function onAgencyLogoError(e) {
    if (e.target.src.includes('blueskywings')) return
    e.target.src = DEFAULT_BLUESKY_LOGO
}
const legs = computed(() => {
    if (props.receipt?.legs?.length) return props.receipt.legs
    // Legacy flat segments → single outbound leg
    if (props.receipt?.segments?.length) {
        return [{
            key: 'outbound',
            label: 'Outbound Flight',
            routeLabel: props.receipt.route?.label ?? '',
            dateLabel: '',
            duration: props.receipt.totalFlightTime ?? '',
            stopLabel: '',
            metaBar: '',
            segments: props.receipt.segments,
        }]
    }
    return []
})

const itineraryTitle = computed(() => {
    const way = props.receipt?.route?.wayTitle || 'ONE WAY'
    return `FLIGHT ITINERARY - ${way}`
})

const noticePnr = computed(() => {
    const gds = String(props.receipt?.gdsPnr ?? '').trim()
    const air = String(props.receipt?.airlinePnr ?? '').trim()
    if (gds && gds !== '—') return gds
    if (air && air !== '—') return air
    return '—'
})

// Sample style: "09 Jul 2026" (date only from payment deadline)
const ticketByShort = computed(() => {
    const raw = props.receipt?.paymentDeadlineLong || props.receipt?.paymentDeadline || ''
    if (!raw || raw === '—') return '—'
    const m = String(raw).match(/(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})/)
    return m ? m[1] : String(raw).split(',')[0].trim() || '—'
})

const outboundRbd = computed(() => {
    const leg = (props.receipt?.legs || []).find((l) => l.key === 'outbound') || props.receipt?.legs?.[0]
    return leg?.bookingCode || leg?.segments?.[0]?.booking_code || ''
})

const returnRbd = computed(() => {
    const leg = (props.receipt?.legs || []).find((l) => l.key === 'inbound')
    if (!leg) return ''
    return leg.bookingCode || leg.segments?.[0]?.booking_code || ''
})

const outboundFareBasis = computed(() => {
    const leg = (props.receipt?.legs || []).find((l) => l.key === 'outbound') || props.receipt?.legs?.[0]
    return leg?.fareBasisCode || ''
})

const returnFareBasis = computed(() => {
    const leg = (props.receipt?.legs || []).find((l) => l.key === 'inbound')
    return leg?.fareBasisCode || ''
})

// Penalty line built from real fare-rule data (priceData.penalties) — only shown when
// the API actually returned a penalty; never fabricated per route.
function penaltyLine(label, penalty) {
    if (!penalty) return null
    const appliesTo = String(penalty.applies_to || 'PerTicket').replace(/([a-z])([A-Z])/g, '$1 $2')
    const amount = Number(penalty.amount ?? 0)
    const chargeText = amount > 0 ? `${fmtMoney(amount)} charge (${appliesTo})` : `Permitted, no charge (${appliesTo})`
    return [
        { bold: true, text: `${label}:` },
        { text: ` ${chargeText}, subject to fare rules on file.` },
    ]
}

// Yellow notes — bold labels + dynamic PNR / deadline / RBD / real penalty data
const voucherNotes = computed(() => {
    const pnr = noticePnr.value
    const ticketBy = ticketByShort.value
    const outRbd = outboundRbd.value || '—'
    const retRbd = returnRbd.value
    const penalties = props.receipt?.penalties

    const notes = [
        [
            { bold: true, text: 'This is a reservation, not a ticket-' },
            { text: ' Seats are held under ' },
            { bold: true, text: `PNR ${pnr}` },
            { text: ' but travel is only valid once ticketed.' },
        ],
        [
            { bold: true, text: `Ticket by ${ticketBy} —` },
            { text: ' after the deadline the airline automatically cancels the held segments without notice.' },
        ],
        [
            { bold: true, text: 'Seat numbers:' },
            { text: ' Numbers are assigned at ticketing or during online check-in; they are not held at this stage.' },
        ],
    ]

    const cancelLine = penaltyLine('Cancellation', penalties?.cancel)
    if (cancelLine) notes.push(cancelLine)

    const changeLine = penaltyLine('Date/flight change', penalties?.change)
    if (changeLine) notes.push(changeLine)

    // Round-trip only, and only when outbound/return fare basis actually differ
    if (retRbd && outboundFareBasis.value && returnFareBasis.value && outboundFareBasis.value !== returnFareBasis.value) {
        notes.push([
            { bold: true, text: `Return fare basis is ${returnFareBasis.value} —` },
            { text: ` different from the outbound (${outboundFareBasis.value}, RBD ${outRbd}/${retRbd}); change and refund rules may differ for each direction.` },
        ])
    }

    return notes
})

// Sample: "Due by 15 Dec, 2026 | 10:20 AM"
const dueByLabel = computed(() => {
    const raw = props.receipt?.paymentDeadlineLong || props.receipt?.paymentDeadline
    if (!raw || raw === '—') return ''
    const m = String(raw).match(/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})(?:,?\s*(\d{1,2}):(\d{2}))?/)
    if (!m) return `Due by ${String(raw).replace(/\s*\(GMT[^)]*\)/i, '').trim()}`
    let timePart = ''
    if (m[4] != null) {
        const h24 = parseInt(m[4], 10)
        const min = m[5]
        const ampm = h24 >= 12 ? 'PM' : 'AM'
        const h12 = h24 % 12 || 12
        timePart = ` | ${String(h12).padStart(2, '0')}:${min} ${ampm}`
    }
    return `Due by ${m[1]} ${m[2]}, ${m[3]}${timePart}`
})

function countWord(n) {
    const map = { 1: 'one', 2: 'two', 3: 'three', 4: 'four', 5: 'five', 6: 'six' }
    return map[n] || String(n)
}

// Sample bottom notice — flight count / travel dates dynamic
const beforePayRest = computed(() => {
    const legs = props.receipt?.legs || []
    const segCount = legs.reduce((n, leg) => n + (leg.segments?.length || 0), 0)
        || (props.receipt?.segments?.length || 0)
    const legCount = legs.length || (segCount ? 1 : 0)
    const timingPhrase = segCount > 0
        ? `all ${countWord(segCount)} flight timing${segCount === 1 ? '' : 's'}`
        : 'all flight timings'
    const datePhrase = legCount >= 2 ? 'both travel dates' : 'the travel date'
    return (
        ` check every passenger name against their passport — names cannot be changed after ticketing.`
        + ` Verify ${timingPhrase} and ${datePhrase}.`
        + ` Once payment is confirmed we will issue tickets and send an updated E-Ticket Voucher with ticket numbers for each passenger.`
    )
})

function fmtMoney(amount, { signed = false, spaced = true } = {}) {
    const n = Number(amount)
    if (Number.isNaN(n)) return '—'
    const cur = props.receipt?.fare?.currency ?? 'BDT'
    const abs = Math.abs(n).toLocaleString(undefined, { maximumFractionDigits: 0 })
    const body = spaced ? `${cur} ${abs}` : `${cur}${abs}`
    if (signed && n < 0) return `-${body}`
    if (signed && n > 0 && props.receipt?.fare?.discount) return `-${body}`
    return body
}

function fmtDiscount(amount) {
    const n = Number(amount)
    if (!n) return null
    const cur = props.receipt?.fare?.currency ?? 'BDT'
    const abs = Math.abs(n).toLocaleString(undefined, { maximumFractionDigits: 0 })
    return `-${cur} ${abs}`
}

function paxTypeClass(type) {
    const t = String(type || 'ADT').toUpperCase()
    if (t === 'CHD' || t === 'CNN') return 'voucher-badge--chd'
    if (t === 'INF' || t === 'INS') return 'voucher-badge--inf'
    return 'voucher-badge--adt'
}

function formatSegDate(date) {
    if (!date) return ''
    const raw = String(date)
    if (/[A-Za-z]/.test(raw) && !/^\d{4}-/.test(raw)) return raw
    const d = new Date(`${raw.slice(0, 10)}T00:00:00`)
    if (Number.isNaN(d.getTime())) return raw
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Review page date line: "16 August, Sunday"
function formatReviewDate(date) {
    if (!date) return ''
    const d = new Date(`${String(date).slice(0, 10)}T00:00:00`)
    if (Number.isNaN(d.getTime())) return date
    const day = d.getDate()
    const month = d.toLocaleDateString('en-US', { month: 'long' })
    const weekday = d.toLocaleDateString('en-US', { weekday: 'long' })
    return `${day} ${month}, ${weekday}`
}

function formatLegDate(date) {
    if (!date) return ''
    const d = new Date(`${String(date).slice(0, 10)}T00:00:00`)
    if (Number.isNaN(d.getTime())) return date
    const day = d.getDate()
    const month = d.toLocaleDateString('en-US', { month: 'short' })
    const year = d.getFullYear()
    const weekday = d.toLocaleDateString('en-US', { weekday: 'long' })
    return `${day} ${month},${year}, ${weekday}`
}

function titleCaseCity(name) {
    const s = String(name ?? '').trim()
    if (!s) return ''
    if (s.length <= 3 && s === s.toUpperCase()) return s
    return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
}

function legOriginCity(leg) {
    const seg = leg?.segments?.[0]
    return titleCaseCity(seg?.departure_city || seg?.departure_code)
}

function legDestCity(leg) {
    const segs = leg?.segments ?? []
    const seg = segs[segs.length - 1]
    return titleCaseCity(seg?.arrival_city || seg?.arrival_code)
}

function legIcon(leg) {
    return leg?.key === 'inbound' ? 'fa-solid fa-plane-arrival' : 'fa-solid fa-plane-departure'
}

function refundPillLabel() {
    const r = String(props.receipt?.refundStatus || '')
    if (/non/i.test(r)) return 'Non Refundable'
    if (/partial/i.test(r)) return 'Partially Refundable'
    if (r && r !== '—') return r
    return 'Refundable'
}

// BD local mobile often stored without leading 0 (17XXXXXXXX) — pad for display
function formatContact(contact) {
    if (!contact || contact === '—') return contact || '—'
    return String(contact)
        .split(/[\n,/|]+/)
        .map((part) => {
            const raw = part.trim()
            if (!raw) return raw
            // Keep international / already-zeroed numbers
            if (raw.startsWith('+') || raw.startsWith('00') || raw.startsWith('0')) return raw
            const digits = raw.replace(/[\s\-()]/g, '')
            if (/^1\d{9}$/.test(digits)) return `0${digits}`
            return raw
        })
        .filter(Boolean)
        .join('\n')
}

function onLogoError(e) {
    e.target.src = '/uploads/airlines/default.svg'
}

// Thin wrappers over the shared A4 shell — kept so callers (modals, ticket printing)
// keep the same API they always had.
async function print() {
    await a4.value?.print()
}

// Lets a caller combine this doc's markup with others into one print job (e.g. one
// ticket per A4 page) instead of opening its own print window.
async function getPrintHtml() {
    return (await a4.value?.getPrintHtml()) ?? ''
}

async function download() {
    await a4.value?.download()
}

function onDownloadError() {
    Notification?.showToast?.('e', 'Failed to download voucher PDF.')
}

defineExpose({ print, download, downloading, getPrintHtml })

</script>

<template>
    <A4PrintDoc
        v-if="hasReceipt"
        ref="a4"
        class="receipt-modal__body voucher-doc"
        :keep-selector="KEEP_SELECTOR"
        :avoid-selectors="PDF_AVOID"
        :print-title="printTitle"
        :file-name="pdfFileName"
        @error="onDownloadError"
    >
        <template #header>
            <div class="voucher-header">
                <div class="voucher-header__left">
                    <img
                        :src="agencyLogo"
                        alt="Agency"
                        class="voucher-header__logo"
                        @error="onAgencyLogoError"
                    />
                    <div class="voucher-header__agency">
                        <div class="voucher-header__line voucher-header__line--name">
                            <svg class="voucher-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01M9 14h.01M15 14h.01" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <strong>{{ receipt.agency.name }}</strong>
                        </div>
                        <div v-if="receipt.agency.address" class="voucher-header__line">
                            <svg class="voucher-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.2" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                            <span>{{ receipt.agency.address }}</span>
                        </div>
                        <div
                            v-if="(receipt.agency.email && receipt.agency.email !== '—') || (receipt.agency.phone && receipt.agency.phone !== '—')"
                            class="voucher-header__line voucher-header__line--contact"
                        >
                            <template v-if="receipt.agency.email && receipt.agency.email !== '—'">
                                <svg class="voucher-ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m4 7 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span>{{ receipt.agency.email }}</span>
                            </template>
                            <span
                                v-if="receipt.agency.email && receipt.agency.email !== '—' && receipt.agency.phone && receipt.agency.phone !== '—'"
                                class="voucher-header__sep"
                            >|</span>
                            <template v-if="receipt.agency.phone && receipt.agency.phone !== '—'">
                                <svg class="voucher-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 3.5h3.2l1.1 3.3-2 1.2a12.5 12.5 0 0 0 5.2 5.2l1.2-2 3.3 1.1v3.2a2 2 0 0 1-2.1 2A15.5 15.5 0 0 1 3.5 5.6a2 2 0 0 1 2-2.1z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span>{{ receipt.agency.phone }}</span>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="voucher-header__right">
                    <div class="voucher-status" :class="{ 'voucher-status--ticketed': hasTicketNumbers }">{{ receipt.status }}</div>
                    <div class="voucher-header__meta">Booking On - {{ receipt.bookedOn }}</div>
                    <div class="voucher-header__meta">Booked By - {{ receipt.bookedBy }}</div>
                </div>
            </div>
        </template>

            <div
                class="voucher-summary voucher-keep"
                :class="{ 'voucher-summary--solo': !hasTicketNumbers && !(receipt.paymentDeadlineLong || (receipt.paymentDeadline && receipt.paymentDeadline !== '—')) }"
            >
                <div class="voucher-pnr">
                    <div class="voucher-pnr__cell">
                        <span>GDS PNR</span>
                        <strong>{{ receipt.gdsPnr }}</strong>
                    </div>
                    <div class="voucher-pnr__cell">
                        <span>Airline PNR</span>
                        <strong>{{ receipt.airlinePnr }}</strong>
                    </div>
                    <div class="voucher-pnr__cell">
                        <span>Booking Ref</span>
                        <strong>{{ receipt.bookingId }}</strong>
                    </div>
                </div>

                <div v-if="hasTicketNumbers" class="voucher-ticket-issued">
                    <div class="voucher-ticket-issued__label">
                        <i class="fa-solid fa-circle-check" aria-hidden="true" />
                        E-Ticket Issued
                    </div>
                    <p class="voucher-ticket-issued__desc">
                        Your ticket has been successfully issued. The reservation is confirmed, and no
                        further action is required.
                    </p>
                </div>
                <div
                    v-else-if="receipt.paymentDeadlineLong || (receipt.paymentDeadline && receipt.paymentDeadline !== '—')"
                    class="voucher-deadline"
                >
                    <p>
                        Ticketing deadline
                        – your booking held but <strong>not yet Ticketed</strong>.
                        Issue the ticket by
                        <strong>{{ receipt.paymentDeadlineLong || receipt.paymentDeadline }}</strong>
                        or {{ receipt.airlineName }} will automatically cancel this booking.
                        Fare is not guaranteed until ticketed.
                    </p>
                </div>
            </div>

            <!-- Passenger details -->
            <section class="voucher-section voucher-keep">
                <div class="voucher-section__title">PASSENGER DETAILS</div>
                <table class="voucher-table">
                    <thead>
                        <tr>
                            <th>Passenger</th>
                            <th>Type</th>
                            <th v-if="hasTicketNumbers">Ticket</th>
                            <th>Date of Birth</th>
                            <th>Passport No.</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, pi) in displayPassengers" :key="pi">
                            <td class="voucher-table__name">{{ p.name }}</td>
                            <td>
                                <span class="voucher-badge" :class="paxTypeClass(p.type)">{{ p.type }}</span>
                            </td>
                            <td v-if="hasTicketNumbers">{{ passengerTicketNumber(pi) }}</td>
                            <td>{{ p.dob }}</td>
                            <td>{{ p.passport }}</td>
                            <td class="voucher-table__contact">{{ formatContact(p.contact) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Flight itinerary — same layout as BookingReviewConfirm -->
            <section class="voucher-section voucher-itin">
                <div class="voucher-section__title voucher-section__title--itinerary">{{ itineraryTitle }}</div>

                <div
                    v-for="leg in legs"
                    :key="leg.key"
                    class="voucher-leg voucher-keep"
                >
                    <div class="voucher-leg__head">
                        <span class="voucher-leg__title">
                            <i :class="legIcon(leg)" aria-hidden="true" />
                            {{ leg.label }}
                        </span>
                        <span class="voucher-leg__sep">|</span>
                        <span class="voucher-leg__route">
                            {{ legOriginCity(leg) }}
                            <i class="fa-solid fa-arrow-right" aria-hidden="true" />
                            {{ legDestCity(leg) }}
                        </span>
                        <span class="voucher-leg__sep">|</span>
                        <span class="voucher-leg__meta">
                            <i class="fa-regular fa-calendar" aria-hidden="true" />
                            {{ leg.dateLabel || formatLegDate(leg.segments?.[0]?.departure_date) }}
                        </span>
                        <span class="voucher-leg__sep">|</span>
                        <span class="voucher-leg__meta">
                            <i class="fa-regular fa-clock" aria-hidden="true" />
                            {{ leg.duration || '—' }}
                        </span>
                        <span class="voucher-leg__nonref">{{ refundPillLabel() }}</span>
                    </div>

                    <template v-for="(seg, idx) in leg.segments" :key="`${leg.key}-${idx}`">
                        <article class="voucher-seg voucher-keep">
                            <div class="voucher-seg__airline">
                                <div class="voucher-seg__logo-wrap">
                                    <img
                                        :src="seg.logo_path || '/uploads/airlines/default.svg'"
                                        alt=""
                                        class="voucher-seg__logo"
                                        @error="onLogoError"
                                    />
                                </div>
                                <div>
                                    <div class="voucher-seg__airline-name">{{ seg.airline_name }}</div>
                                    <div class="voucher-seg__fn">{{ seg.flight_number }}</div>
                                    <div class="voucher-seg__eq">{{ seg.equipment || '—' }}</div>
                                </div>
                            </div>

                            <div class="voucher-seg__point">
                                <div class="voucher-seg__code">{{ seg.departure_code }}</div>
                                <div class="voucher-seg__airport">{{ seg.departure_airport }}</div>
                                <div class="voucher-seg__time">
                                    <span class="voucher-seg__time-val">{{ seg.departure_time || '—' }}</span>
                                    <span class="voucher-seg__time-sep">|</span>
                                    <span class="voucher-seg__time-date">{{ formatReviewDate(seg.departure_date) || formatSegDate(seg.departure_date) }}</span>
                                </div>
                                <div class="voucher-seg__term">Terminal: {{ seg.origin_terminal || '—' }}</div>
                            </div>

                            <div class="voucher-seg__mid">
                                <div class="voucher-seg__dur">{{ seg.duration || '—' }}</div>
                                <div class="voucher-seg__track">
                                    <span class="voucher-seg__dot" />
                                    <span class="voucher-seg__line">
                                        <i class="fa-solid fa-plane voucher-seg__plane" aria-hidden="true" />
                                    </span>
                                    <span class="voucher-seg__dot voucher-seg__dot--arr" />
                                </div>
                                <div class="voucher-seg__stops">{{ leg.stopLabel || '—' }}</div>
                            </div>

                            <div class="voucher-seg__point voucher-seg__point--arr">
                                <div class="voucher-seg__code">{{ seg.arrival_code }}</div>
                                <div class="voucher-seg__airport">{{ seg.arrival_airport }}</div>
                                <div class="voucher-seg__time">
                                    <span class="voucher-seg__time-val">{{ seg.arrival_time || '—' }}</span>
                                    <span class="voucher-seg__time-sep">|</span>
                                    <span class="voucher-seg__time-date">{{ formatReviewDate(seg.arrival_date) || formatSegDate(seg.arrival_date) }}</span>
                                </div>
                                <div class="voucher-seg__term">Terminal: {{ seg.destination_terminal || '—' }}</div>
                            </div>

                            <div class="voucher-seg__info">
                                <span class="voucher-seg__info-cabin">
                                    Cabin: {{ leg.cabin || seg.cabin_class || 'Economy' }}<template v-if="leg.bookingCode || seg.booking_code">, RBD: {{ leg.bookingCode || seg.booking_code }}</template>
                                </span>
                                <span class="voucher-seg__info-sep">|</span>
                                <span class="voucher-seg__info-fare">
                                    Fare Basis: {{ leg.fareBasisCode || seg.fare_basis || '—' }}
                                </span>
                                <span class="voucher-seg__info-sep">|</span>
                                <span class="voucher-seg__info-bag">
                                    Baggage: {{ leg.baggageLabel && leg.baggageLabel !== '—' ? leg.baggageLabel : (seg.baggage || '—') }}
                                </span>
                            </div>
                        </article>

                        <div
                            v-if="!seg.lastitem && seg.layover_time"
                            class="voucher-chip voucher-chip--layover"
                        >
                            <img :src="'/theme/appimages/layover_dstina.svg'" class="voucher-chip__icon" alt="" />
                            <span class="voucher-chip__main">
                                Layover at {{ titleCaseCity(seg.arrival_city) }} - {{ seg.layover_time }}
                            </span>
                            <span class="voucher-chip__sep">|</span>
                            <span class="voucher-chip__airport">{{ seg.layover_airport || seg.arrival_airport }}</span>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Fare summary + notes -->
            <section class="voucher-fare voucher-keep">
                <div class="voucher-fare__left">
                    <div class="voucher-section__title">FARE SUMMARY</div>
                    <div class="voucher-fare__body">
                        <div class="voucher-fare__group">
                            <div class="voucher-fare__group-title">Base Fare</div>
                            <template v-if="receipt.fare.breakdown?.length">
                                <div
                                    v-for="(row, ri) in receipt.fare.breakdown"
                                    :key="`base-${ri}`"
                                    class="voucher-fare__row"
                                >
                                    <span>{{ row.label }}</span>
                                    <strong>{{ fmtMoney(row.base) }}</strong>
                                </div>
                            </template>
                            <div v-else class="voucher-fare__row">
                                <span>Base Fare</span>
                                <strong>{{ fmtMoney(receipt.fare.grossFare) }}</strong>
                            </div>
                        </div>

                        <div class="voucher-fare__group">
                            <div class="voucher-fare__group-title">TAX</div>
                            <template v-if="receipt.fare.breakdown?.length">
                                <div
                                    v-for="(row, ri) in receipt.fare.breakdown"
                                    :key="`tax-${ri}`"
                                    class="voucher-fare__row"
                                >
                                    <span>{{ row.label }}</span>
                                    <strong>{{ fmtMoney(row.tax) }}</strong>
                                </div>
                            </template>
                            <div v-else class="voucher-fare__row">
                                <span>TAX</span>
                                <strong>{{ fmtMoney(receipt.fare.tax) }}</strong>
                            </div>
                        </div>

                        <div v-if="Number(receipt.fare.discount) > 0" class="voucher-fare__group">
                            <div class="voucher-fare__group-title">Discount</div>
                            <div class="voucher-fare__row voucher-fare__row--discount">
                                <span>Discounted Amount</span>
                                <strong>{{ fmtDiscount(receipt.fare.discount) }}</strong>
                            </div>
                        </div>

                        <div class="voucher-fare__due">
                            <span>Gross Fare</span>
                            <strong>{{ fmtMoney(receipt.fare.totalPayable) }}</strong>
                        </div>

                        <p class="voucher-fare__hint">
                            Fare quoted, not guaranteed. The airline may reprice fares, taxes or surcharges until the ticket is issued. Amount confirmed only on ticketing.
                        </p>

                        <div v-if="!hasTicketNumbers" class="voucher-pay-status">
                            <div class="voucher-pay-status__left">
                                <i class="fa-regular fa-clock" aria-hidden="true" />
                                <strong>{{ receipt.paymentStatus || 'PAYMENT PENDING' }}</strong>
                            </div>
                            <span v-if="dueByLabel" class="voucher-pay-status__due">{{ dueByLabel }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="!hasTicketNumbers || ticketPolicyPoints.length" class="voucher-fare__right">
                    <div class="voucher-notes voucher-keep">
                        <template v-if="hasTicketNumbers">
                            <div class="voucher-notes__title">Ticket Policy</div>
                            <ul>
                                <li v-for="p in ticketPolicyPoints" :key="p.id">{{ p.point }}</li>
                            </ul>
                        </template>
                        <ul v-else>
                            <li v-for="(parts, ni) in voucherNotes" :key="ni">
                                <template v-for="(part, pi) in parts" :key="pi">
                                    <b v-if="part.bold">{{ part.text }}</b>
                                    <template v-else>{{ part.text }}</template>
                                </template>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <div v-if="!hasTicketNumbers" class="voucher-notice voucher-keep">
                <b>Before you pay:</b>{{ beforePayRest }}
            </div>

        <template #footer>
            <div class="voucher-footer">
                <span>Authorized By : <strong>{{ receipt.authorizedBy }}</strong></span>
                <span class="voucher-footer__generated">Generated On : {{ receipt.generatedAt }}</span>
            </div>
        </template>
    </A4PrintDoc>
</template>

<style scoped>
.receipt-modal__body {
    margin: 0 auto 2rem;
}

/* Voucher typography only — the A4 canvas (size, margins, page flow) belongs to A4PrintDoc.
   These live on the sheet root so they inherit into every slot, on screen and in print. */
.voucher-doc {
    color: #1e3a5f;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 11px;
    line-height: 1.4;
}

.voucher-keep {
    break-inside: avoid;
    page-break-inside: avoid;
}

.voucher-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 35px;
    page-break-inside: avoid;
    break-inside: avoid;
}

.voucher-header__left {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    min-width: 0;
}

.voucher-header__logo {
    width: 52px;
    height: 52px;
    object-fit: contain;
    flex-shrink: 0;
}

.voucher-header__agency {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 10px;
    color: #475569;
    line-height: 1.45;
}

.voucher-header__line {
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.voucher-header__line--contact {
    align-items: center;
    flex-wrap: wrap;
}

.voucher-header__line--contact .voucher-ico {
    margin-top: 0;
}

.voucher-header__sep {
    margin: 0 2px;
    color: #94a3b8;
}

.voucher-ico {
    width: 12px;
    height: 12px;
    margin-top: 2px;
    flex-shrink: 0;
    color: #64748b;
}

.voucher-header__line--name {
    align-items: center;
}

.voucher-header__line--name .voucher-ico {
    margin-top: 0;
    width: 13px;
    height: 13px;
    color: #0f172a;
}

.voucher-header__line--name strong {
    font-size: 13px;
    color: #0f172a;
}

.voucher-header__right {
    display: flex;
    flex-direction: column;
    gap: 5px;
    text-align: left;
    flex-shrink: 0;
}

.voucher-status {
    font-size: 16px;
    font-weight: 800;
    color: #d97706;
}

/* Matches the E-Ticket Issued banner's green — same "done" state, same color */
.voucher-status--ticketed {
    color: #047857;
}

.voucher-header__meta {
    font-size: 10px;
    color: #475569;
}

.voucher-summary {
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    gap: 8px;
    align-items: stretch;
    margin-bottom: 10px;
    page-break-inside: avoid;
    break-inside: avoid;
}

.voucher-summary--solo {
    grid-template-columns: 1fr;
}

.voucher-pnr {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: #e8f3fc;
    border-radius: 6px;
    overflow: hidden;
}

.voucher-pnr__cell {
    padding: 8px 6px;
    text-align: center;
    border-right: 1px solid rgba(37, 99, 235, 0.12);
    min-width: 0;
}

.voucher-pnr__cell:last-child {
    border-right: none;
}

.voucher-pnr__cell span {
    display: block;
    font-size: 8px;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0.02em;
    margin-bottom: 2px;
}

.voucher-pnr__cell strong {
    display: block;
    font-size: 10px;
    color: #1e3a8a;
    letter-spacing: 0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.voucher-deadline {
    background: #fff7e6;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 9.5px;
    color: #7c4a03;
    line-height: 1.45;
}

.voucher-deadline p {
    margin: 0;
    display: block;
    white-space: normal;
    word-break: normal;
    overflow-wrap: break-word;
}

.voucher-deadline strong {
    color: #0f172a;
    font-weight: 800;
}

.voucher-ticket-issued {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 6px;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.voucher-ticket-issued__label {
    font-size: 10px;
    font-weight: 800;
    color: #047857;
    display: flex;
    align-items: center;
    gap: 5px;
}

.voucher-ticket-issued__desc {
    margin: 0;
    font-size: 9px;
    color: #065f46;
    line-height: 1.5;
}

.voucher-ticket-issued__numbers {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.voucher-ticket-chip {
    font-size: 9px;
    font-weight: 700;
    color: #065f46;
    background: #fff;
    border: 1px solid #a7f3d0;
    border-radius: 4px;
    padding: 2px 6px;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
}

.voucher-section {
    margin-bottom: 10px;
}

.voucher-section__title {
    background: #eef1f4;
    color: #334155;
    font-weight: 800;
    font-size: 11px;
    letter-spacing: 0.04em;
    padding: 6px 10px;
    text-align: left;
}

.voucher-section__title--itinerary {
    margin-bottom: 8px;
}

.voucher-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10.5px;
}

.voucher-table thead th {
    text-align: left;
    font-weight: 600;
    color: #64748b;
    padding: 7px 8px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 10px;
}

.voucher-table tbody td {
    padding: 7px 8px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.voucher-table__contact {
    white-space: pre-line;
}

.voucher-table__name {
    font-weight: 700;
    color: #0f172a;
}

.voucher-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.03em;
}

.voucher-badge--adt {
    background: #dbeafe;
    color: #1d4ed8;
}

.voucher-badge--chd {
    background: #dcfce7;
    color: #15803d;
}

.voucher-badge--inf {
    background: #ede9fe;
    color: #6d28d9;
}

/* Itinerary — mirrors BookingReviewConfirm rc-leg / rc-seg (A4 compact) */
.voucher-itin {
    --v-primary: #027de2;
    --v-teal: #0fb3a6;
    --v-purple: #7239ea;
    --v-border: #e2e8f0;
    --v-soft: #eef1f6;
    --v-seg-tint: #f7f9fe;
    --v-muted: #64748b;
    --v-text: #0f172a;
    --v-sub: #475569;
    border: 1px solid var(--v-border);
    border-radius: 4px;
    overflow: hidden;
}

.voucher-itin .voucher-section__title {
    border-bottom: 1px solid var(--v-border);
}

.voucher-leg:not(:last-child) {
    border-bottom: 1px solid var(--v-border);
}

.voucher-leg__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    row-gap: 4px;
    column-gap: 6px;
    padding: 7px 10px;
    border-bottom: 1px solid var(--v-border);
    page-break-inside: avoid;
    break-inside: avoid;
}

.voucher-leg__title {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    color: var(--v-primary);
    flex-shrink: 0;
}

.voucher-leg__sep {
    color: var(--v-border);
    flex-shrink: 0;
}

.voucher-leg__route {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10.5px;
    font-weight: 600;
    color: var(--v-text);
}

.voucher-leg__route i {
    font-size: 8px;
    color: var(--v-muted);
}

.voucher-leg__meta {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 9px;
    color: var(--v-sub);
}

.voucher-leg__meta i {
    color: var(--v-muted);
    font-size: 9px;
}

.voucher-leg__nonref {
    margin-left: auto;
    font-size: 9px;
    font-weight: 600;
    color: var(--v-primary);
    background: rgba(2, 125, 226, 0.08);
    border: 1px solid rgba(2, 125, 226, 0.2);
    border-radius: 999px;
    padding: 2px 8px;
    flex-shrink: 0;
}

.voucher-seg {
    display: grid;
    grid-template-columns: 120px 1fr 100px 1fr;
    grid-template-rows: auto auto;
    column-gap: 8px;
    row-gap: 6px;
    padding: 10px 10px 4px;
    align-items: start;
    page-break-inside: avoid;
    break-inside: avoid;
}

.voucher-seg__airline {
    grid-column: 1;
    grid-row: 1 / 3;
    align-self: stretch;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    margin: -10px 0 0 -10px;
    background: var(--v-seg-tint);
    border-radius: 4px;
    padding: 8px 6px 10px 10px;
}

.voucher-chip + .voucher-seg .voucher-seg__airline {
    margin-top: -6px;
    padding-top: 10px;
}

.voucher-seg__logo-wrap {
    flex-shrink: 0;
}

.voucher-seg__logo {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    border: 1px solid var(--v-border);
    background: #fff;
    object-fit: contain;
    padding: 2px;
}

.voucher-seg__airline-name {
    font-size: 11px;
    font-weight: 700;
    color: var(--v-text);
    line-height: 1.25;
}

.voucher-seg__fn {
    font-size: 10px;
    font-weight: 700;
    color: var(--v-primary);
    margin-top: 1px;
}

.voucher-seg__eq {
    font-size: 9px;
    font-weight: 700;
    color: var(--v-sub);
    margin-top: 1px;
}

.voucher-seg__point {
    grid-column: 2;
    grid-row: 1;
}

.voucher-seg__point--arr {
    grid-column: 4;
    grid-row: 1;
}

.voucher-seg__code {
    font-size: 13px;
    font-weight: 800;
    color: var(--v-text);
    line-height: 1.2;
}

.voucher-seg__airport {
    font-size: 9px;
    color: var(--v-muted);
    margin-top: 2px;
    line-height: 1.3;
}

.voucher-seg__time {
    font-size: 10px;
    margin-top: 3px;
    font-variant-numeric: tabular-nums;
}

.voucher-seg__time-val {
    font-weight: 700;
    color: var(--v-text);
}

.voucher-seg__time-sep {
    color: var(--v-border);
    margin: 0 2px;
}

.voucher-seg__time-date {
    color: var(--v-sub);
}

.voucher-seg__term {
    font-size: 9px;
    color: var(--v-muted);
    margin-top: 2px;
}

.voucher-seg__mid {
    grid-column: 3;
    grid-row: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 2px 0;
}

.voucher-seg__dur {
    font-size: 9.5px;
    font-weight: 600;
    color: var(--v-sub);
    white-space: nowrap;
}

.voucher-seg__track {
    display: flex;
    align-items: center;
    width: 100%;
    max-width: 90px;
}

.voucher-seg__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--v-primary);
    flex-shrink: 0;
}

.voucher-seg__dot--arr {
    background: #94a3b8;
}

.voucher-seg__line {
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, var(--v-primary), #94a3b8);
    position: relative;
    margin: 0 2px;
}

.voucher-seg__plane {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    font-size: 8px;
    color: var(--v-primary);
    background: #fff;
    padding: 0 2px;
}

.voucher-seg__stops {
    font-size: 9px;
    color: var(--v-muted);
    white-space: nowrap;
}

.voucher-seg__info {
    grid-column: 2 / 5;
    grid-row: 2;
    align-self: end;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    margin-right: -10px;
    padding: 5px 14px 5px 8px;
    background: var(--v-soft);
    border-radius: 2px;
    font-size: 9px;
}

.voucher-seg__info-cabin {
    color: var(--v-teal);
    font-weight: 600;
}

.voucher-seg__info-fare {
    color: var(--v-purple);
    font-weight: 600;
}

.voucher-seg__info-bag {
    color: #6059d8;
    font-weight: 600;
}

.voucher-seg__info-sep {
    color: var(--v-muted);
}

.voucher-chip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px 10px;
    font-size: 10px;
    border-top: 1px solid var(--v-border);
    border-bottom: 1px solid var(--v-border);
}

.voucher-chip__icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.voucher-chip__sep {
    color: var(--v-border);
}

.voucher-chip--layover {
    color: var(--v-primary);
}

.voucher-chip__main {
    font-weight: 700;
}

.voucher-chip__airport {
    font-weight: 400;
}

.voucher-fare {
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    gap: 10px;
    margin-bottom: 10px;
    page-break-inside: avoid;
    break-inside: avoid;
}

.voucher-fare__body {
    padding: 8px 4px 0;
}

.voucher-fare__group {
    margin-bottom: 8px;
}

.voucher-fare__group-title {
    font-weight: 800;
    font-size: 11px;
    color: #0f172a;
    margin-bottom: 3px;
}

.voucher-fare__row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 2px 0;
    font-size: 10.5px;
    color: #475569;
}

.voucher-fare__row strong {
    color: #0f172a;
    font-weight: 700;
}

.voucher-fare__row--discount strong {
    color: #dc2626;
}

.voucher-fare__due {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 8px;
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid #e2e8f0;
}

.voucher-fare__due span {
    font-weight: 800;
    font-size: 12px;
    color: #0f172a;
}

.voucher-fare__due strong {
    font-size: 18px;
    font-weight: 800;
    color: #1d4ed8;
}

.voucher-fare__hint {
    margin: 8px 0 8px;
    font-size: 9px;
    color: #78716c;
    line-height: 1.45;
}

.voucher-pay-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 4px;
    padding: 8px 10px;
    color: #92400e;
}

.voucher-pay-status__left {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.voucher-pay-status__left i {
    color: #d97706;
    flex-shrink: 0;
}

.voucher-pay-status__left strong {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: #d97706;
    white-space: nowrap;
}

.voucher-pay-status__due {
    font-size: 10px;
    color: #57534e;
    text-align: right;
    flex-shrink: 0;
}

.voucher-notes {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 4px;
    padding: 10px 12px;
    height: 100%;
    box-sizing: border-box;
}

.voucher-notes__title {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #92400e;
    margin-bottom: 6px;
}

.voucher-notes ul {
    margin: 0;
    padding-left: 18px;
    color: #92400e;
    font-size: 10px;
    line-height: 1.55;
    list-style-type: disc;
    list-style-position: outside;
}

.voucher-notes li {
    margin-bottom: 6px;
    padding-left: 10px;
}

.voucher-notes b {
    font-weight: 700 !important;
    color: inherit;
}

.voucher-notice {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 4px;
    padding: 10px 12px;
    font-size: 10px;
    color: #92400e;
    line-height: 1.55;
    margin-top: 10px;
    page-break-inside: avoid;
    break-inside: avoid;
}

.voucher-notice b {
    font-weight: 700 !important;
    color: inherit;
}

/* Sits flush with the bottom of the printable area on the last page — A4PrintDoc's
   spacer does the pinning, so no negative bleed or bottom margin here. */
.voucher-footer {
    width: 100%;
    box-sizing: border-box;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: #475569;
    color: #f8fafc;
    padding: 8px 12px;
    font-size: 10px;
}

.voucher-footer__generated {
    margin-right: 8px;
}

.voucher-footer strong {
    font-weight: 700;
}

@media print {
    .receipt-modal__body {
        margin: 0;
        box-shadow: none;
    }
}

/* A4 is wider than a phone — the sheet keeps its real size and scrolls sideways */
@media (max-width: 800px) {
    .receipt-modal__body {
        max-width: 100%;
        overflow-x: auto;
    }
}
</style>

<!-- Unscoped — print iframe CSS extract + html2pdf keep bold -->
<style>
.voucher-notes b,
.voucher-notice b {
    font-weight: 700 !important;
    color: inherit !important;
}
</style>
