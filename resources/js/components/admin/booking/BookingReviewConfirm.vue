<script setup>
import { computed, reactive } from 'vue'
import { storeToRefs } from 'pinia'
import { useBookingStore } from '../../../stores/bookingStore'
import { formatFareAmount } from '../../../utils/dynamicRulePricingDisplay'

const props = defineProps({
    snapshot: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const bookingStore = useBookingStore()
const { flight, priceData, travelerForms, fareRulesSegments, form } = storeToRefs(bookingStore)

// Same ADT/CNN/KID/INF/INS ordering used to build the traveler list in create.vue's booking wizard
const paxTypeAbbrevByIndex = computed(() => {
    const f = form.value ?? {}
    const adt = Number(f.ADT ?? 1)
    const cnn = Number(f.CNN ?? 0)
    const kid = Number(f.KID ?? 0)
    const inf = Number(f.INF ?? 0)
    const ins = Number(f.INS ?? 0)
    const list = []
    for (let i = 0; i < adt; i++) list.push('ADT')
    for (let i = 0; i < cnn; i++) list.push('CHD')
    for (let i = 0; i < kid; i++) list.push('CHD')
    for (let i = 0; i < inf; i++) list.push('INF')
    for (let i = 0; i < ins; i++) list.push('INS')
    return list
})

const price = computed(() => props.snapshot?.price ?? priceData.value)
const currency = computed(() => price.value?.currency ?? 'BDT')

function fmtMoney(n, spaced = true) {
    const num = Number(n)
    if (Number.isNaN(num)) return '—'
    const val = formatFareAmount(num)
    return spaced ? `${currency.value} ${val}` : `${currency.value}${val}`
}

// ── Collapsible sections — all open by default (matches reference design) ──
const open = reactive({
    flight: true,
    passengers: true,
    fareDetails: true,
    fareRules: true,
})
function toggleSection(key) {
    open[key] = !open[key]
}

function formatTime(date, time) {
    if (!time) return ''
    const raw = String(time)
    if (/AM|PM/i.test(raw)) return raw
    const ts = new Date(`${date || '1970-01-01'}T${raw}`)
    if (Number.isNaN(ts.getTime())) return raw
    return ts.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })
}

// "19 July, Thursday" — segment departure/arrival line (matches SS3)
function formatReviewDate(date) {
    if (!date) return ''
    const d = new Date(`${date}T00:00:00`)
    if (Number.isNaN(d.getTime())) return date
    const day = d.getDate()
    const month = d.toLocaleDateString('en-US', { month: 'long' })
    const weekday = d.toLocaleDateString('en-US', { weekday: 'long' })
    return `${day} ${month}, ${weekday}`
}

// "19 Jul,2026, Thursday" — leg header date (matches SS3)
function formatLegDate(date) {
    if (!date) return ''
    const d = new Date(`${date}T00:00:00`)
    if (Number.isNaN(d.getTime())) return date
    const day = d.getDate()
    const month = d.toLocaleDateString('en-US', { month: 'short' })
    const year = d.getFullYear()
    const weekday = d.toLocaleDateString('en-US', { weekday: 'long' })
    return `${day} ${month},${year}, ${weekday}`
}

function formatDob(iso) {
    if (!iso) return '—'
    const d = new Date(`${iso}T00:00:00`)
    if (Number.isNaN(d.getTime())) return iso
    const parts = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).split(' ')
    return parts.length >= 3 ? `${parts[0]}-${parts[1]}-${parts[2]}` : iso
}

function titleCaseCity(name) {
    const s = String(name ?? '').trim()
    if (!s) return ''
    if (s.length <= 3 && s === s.toUpperCase()) return s
    return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
}

// "CODE | City" format (matches SS2)
function codeCity(city, code) {
    const cityLabel = titleCaseCity(city)
    const airport = String(code ?? '').trim().toUpperCase()
    if (cityLabel && airport && cityLabel.toUpperCase() !== airport) {
        return `${airport} | ${cityLabel}`
    }
    return airport || cityLabel || '—'
}

function baggageLabel(leg) {
    const bags = leg?.baggage_allowance ?? []
    const checked = bags.find((b) => b.type === 'checked')
    if (checked?.weight) return checked.weight.toUpperCase().includes('KG') ? checked.weight : `${checked.weight}KG`
    if (checked?.quantity) return `${checked.quantity}PC (${checked.weight ?? '—'})`
    return '—'
}

function refundableLabel(leg) {
    const r = leg?.refundable ?? leg?.is_refundable
    if (r === true || r === 1 || r === 'Yes' || r === 'yes') return 'Yes'
    if (r === false || r === 0 || r === 'No' || r === 'no') return 'No'
    return '—'
}

function legOriginCity(leg) {
    const seg = leg.segments[0]
    return titleCaseCity(seg?.Origin_City_Name || seg?.departure_code)
}

function legDestCity(leg) {
    const seg = leg.segments[leg.segments.length - 1]
    return titleCaseCity(seg?.Destination_City_Name || seg?.arrival_code)
}

function stopLabel(leg) {
    const n = Math.max(leg.segments.length - 1, 0)
    return n === 0 ? 'Non-stop' : `${n} Stop${n > 1 ? 's' : ''}`
}

function refundPillLabel(leg) {
    return leg.refundable === 'Yes' ? 'Refundable' : 'Non Refundable'
}

function operatorText(seg) {
    const name = seg.is_codeshare && seg.codeshare_info?.operating_airline_name
        ? seg.codeshare_info.operating_airline_name
        : seg.airline_name
    return `Operated By ${name}`
}

function carryOnLabel(allowance) {
    const bag = (allowance ?? []).find((b) => b.type === 'carry_on')
    return bag ? `Cabin-${bag.quantity ?? 1}pc` : '—'
}

function checkedLabel(allowance) {
    const bag = (allowance ?? []).find((b) => b.type === 'checked')
    return bag?.weight ? `Check In- ${bag.weight}/person` : '—'
}

function productsToSegments(products) {
    if (!Array.isArray(products)) return []
    return products.map((p, i, list) => {
        const f = p.flight ?? {}
        const dep = f.departure ?? {}
        const arrival = f.arrival ?? {}
        const isLast = i === list.length - 1
        return {
            airline_name: f.carrier ?? '—',
            logo_path: null,
            flight_number: `${f.carrier ?? ''}${f.number ?? ''}`,
            equipment: f.equipment ?? '—',
            cabin_class: p.cabin ?? '',
            booking_code: p.class_of_service ?? '',
            departure_code: dep.location ?? '—',
            arrival_code: arrival.location ?? '—',
            departure_time: formatTime(dep.date, dep.time),
            departure_date: dep.date ?? '',
            arrival_time: formatTime(arrival.date, arrival.time),
            arrival_date: arrival.date ?? '',
            originTerminal: dep.terminal ?? '—',
            destinationTerminal: arrival.terminal ?? '—',
            Origin_City_Name: dep.location ?? '—',
            Origin_Airport_Name: dep.location ?? '—',
            Destination_City_Name: arrival.location ?? '—',
            Destination_Airport_Name: arrival.location ?? '—',
            flightTime1: p.total_duration ?? '—',
            layover_time: '',
            lastitem: isLast,
            baggage: baggageLabel(null),
        }
    })
}

function legSegmentsFromFlight(leg) {
    const bag = baggageLabel(leg)
    return (leg.segments ?? []).map((seg, i, arr) => ({
        ...seg,
        baggage: bag,
        lastitem: i === arr.length - 1,
    }))
}

function productsByDirection(products) {
    const outbound = []
    const inbound  = []
    products.forEach((p, i) => {
        if (p.direction === 'inbound') inbound.push(p)
        else if (p.direction === 'outbound') outbound.push(p)
        else if (i === 0) outbound.push(p)
        else inbound.push(p)
    })
    return { outbound, inbound }
}

const journeyLegs = computed(() => {
    const legs = []
    const f = flight.value

    if (f?.outbound?.segments?.length) {
        legs.push({
            key: 'outbound',
            label: 'Outbound',
            icon: 'fa-solid fa-plane-departure',
            theme: 'outbound',
            duration: f.outbound.total_flight_time ?? '',
            refundable: refundableLabel(f.outbound),
            fareBasisCode: f.outbound.fareBasisCode ?? '',
            baggageAllowance: f.outbound.baggage_allowance ?? [],
            segments: legSegmentsFromFlight(f.outbound),
        })
    }

    if (f?.inbound?.segments?.length) {
        legs.push({
            key: 'inbound',
            label: 'Return',
            icon: 'fa-solid fa-plane-arrival',
            theme: 'inbound',
            duration: f.inbound.total_flight_time ?? '',
            refundable: refundableLabel(f.inbound),
            fareBasisCode: f.inbound.fareBasisCode ?? '',
            baggageAllowance: f.inbound.baggage_allowance ?? [],
            segments: legSegmentsFromFlight(f.inbound),
        })
    }

    if (!legs.length && price.value?.products?.length) {
        const { outbound, inbound } = productsByDirection(price.value.products)
        if (outbound.length) {
            legs.push({
                key: 'outbound',
                label: 'Outbound',
                icon: 'fa-solid fa-plane-departure',
                theme: 'outbound',
                duration: '',
                refundable: '—',
                fareBasisCode: outbound[0]?.fare_basis_code ?? '',
                baggageAllowance: outbound[0]?.baggage ?? [],
                segments: productsToSegments(outbound).map((seg, i, arr) => ({
                    ...seg,
                    lastitem: i === arr.length - 1,
                })),
            })
        }
        if (inbound.length) {
            legs.push({
                key: 'inbound',
                label: 'Return',
                icon: 'fa-solid fa-plane-arrival',
                theme: 'inbound',
                duration: '',
                refundable: '—',
                fareBasisCode: inbound[0]?.fare_basis_code ?? '',
                baggageAllowance: inbound[0]?.baggage ?? [],
                segments: productsToSegments(inbound).map((seg, i, arr) => ({
                    ...seg,
                    lastitem: i === arr.length - 1,
                })),
            })
        }
    }

    return legs
})

const hasFlights = computed(() => journeyLegs.value.some((leg) => leg.segments.length > 0))

const passengers = computed(() => {
    const snap = props.snapshot?.travelers ?? []
    return snap.map((t, i) => {
        const f = travelerForms.value[i]
        const phone = t.phone ?? f?.phone
        const email = t.email ?? f?.email
        let contact = '—'
        if (phone && email) contact = `${email}\n${phone}`
        else if (phone) contact = phone
        else if (email) contact = email
        const fullName = [f?.title, f?.firstName, f?.middleName, f?.lastName]
            .filter(Boolean)
            .join(' ')
        return {
            name:     fullName || t.name || '—',
            gender:   t.gender ?? f?.gender ?? '—',
            paxType:  paxTypeAbbrevByIndex.value[i] ?? 'ADT',
            dob:      formatDob(t.dob ?? f?.dob),
            passport: t.passport_no ?? f?.passportNo ?? '—',
            contact,
        }
    })
})

function destinationText(seg) {
    const city    = codeCity(seg.Destination_City_Name, seg.arrival_code)
    const airport = seg.Destination_Airport_Name || ''
    return { city, airport }
}

// ── Fare Details — per passenger-type breakdown ─────────────────────────
const paxBreakdown = computed(() => {
    const rows = price.value?.price_breakdown ?? []
    return rows.map((bd) => ({
        type: bd.type,
        quantity: Number(bd.quantity ?? 1),
        baseFare: Number(bd.base_fare ?? 0) * Number(bd.quantity ?? 1),
        tax: Number(bd.total_taxes ?? 0) * Number(bd.quantity ?? 1),
        total: Number(bd.total_price ?? 0) * Number(bd.quantity ?? 1),
    }))
})

const fareTotals = computed(() => ({
    baseFare: Number(price.value?.base_fare ?? 0),
    tax: Number(price.value?.total_taxes ?? 0),
    fees: Number(price.value?.total_fees ?? 0),
    grossFare: Number(price.value?.total_price ?? 0),
}))

function paxTone(type) {
    if (type === 'Child') return 'child'
    if (type === 'Infant') return 'infant'
    return 'adult'
}

// ── Fare Rules — structured cancellation/change tables per segment ─────
function formatRuleTiming(timing) {
    if (!timing) return '—'
    return String(timing).replace(/([a-z])([A-Z])/g, '$1 $2').replace(/_/g, ' ')
}

function formatRuleAmount(amount) {
    if (!amount) return '—'
    const code = amount.code ?? ''
    const value = Number(amount.value ?? 0)
    return `${code} ${value.toLocaleString()}`.trim()
}

</script>

<template>
    <div class="review-confirm">
        <div v-if="loading" class="review-confirm__state">
            <div class="review-confirm__spinner" />
            <p>Preparing your booking summary…</p>
        </div>

        <template v-else-if="snapshot">
            <div class="rc-scroll">

                        <!-- ── Flight Details ─────────────────────────────── -->
                        <section class="rc-section">
                            <button type="button" class="rc-section__head" @click="toggleSection('flight')">
                                <span>Flight Details</span>
                                <i class="fa-solid" :class="open.flight ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true" />
                            </button>

                            <div v-show="open.flight" class="rc-section__body rc-section__body--flush">
                                <div v-if="!hasFlights" class="rc-empty">Flight details unavailable.</div>

                                <div
                                    v-for="leg in journeyLegs"
                                    :key="leg.key"
                                    class="rc-leg"
                                    :class="`rc-leg--${leg.theme}`"
                                >
                                    <div class="rc-leg__head">
                                        <span class="rc-leg__title">
                                            <i :class="leg.icon" aria-hidden="true" />
                                            {{ leg.label }} Flight
                                        </span>
                                        <span class="rc-leg__sep">|</span>
                                        <span class="rc-leg__route">
                                            {{ legOriginCity(leg) }}
                                            <i class="fa-solid fa-arrow-right" aria-hidden="true" />
                                            {{ legDestCity(leg) }}
                                        </span>
                                        <span class="rc-leg__sep">|</span>
                                        <span class="rc-leg__meta">
                                            <i class="fa-solid fa-calendar" aria-hidden="true" />
                                            {{ formatLegDate(leg.segments[0]?.departure_date) }}
                                        </span>
                                        <span class="rc-leg__sep">|</span>
                                        <span class="rc-leg__meta">
                                            <i class="fa-solid fa-clock" aria-hidden="true" />
                                            {{ leg.duration || '—' }}
                                        </span>
                                        <span class="rc-leg__nonref">{{ refundPillLabel(leg) }}</span>
                                    </div>

                                    <template v-for="(seg, idx) in leg.segments" :key="`${leg.key}-${idx}`">
                                        <article class="rc-seg">
                                            <div class="rc-seg__airline">
                                                <div class="rc-seg__logo-wrap">
                                                    <img v-if="seg.logo_path" :src="seg.logo_path" class="rc-seg__logo" alt="" />
                                                    <div v-else class="rc-seg__logo rc-seg__logo--ph">
                                                        <i class="fa-solid fa-plane" aria-hidden="true" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="rc-seg__airline-name">{{ seg.airline_name }}</div>
                                                    <div class="rc-seg__flightno">{{ seg.flight_number || seg.flight }}</div>
                                                    <div class="rc-seg__aircraft">{{ seg.aircraft_name || seg.equipment || '—' }}</div>
                                                    <div class="rc-seg__operator">{{ operatorText(seg) }}</div>
                                                </div>
                                            </div>

                                            <div class="rc-seg__point">
                                                <div class="rc-seg__code">{{ seg.departure_code }}</div>
                                                <div class="rc-seg__airport">{{ seg.Origin_Airport_Name }}</div>
                                                <div class="rc-seg__time">
                                                    <span class="rc-seg__time-val">{{ seg.departure_time || '—' }}</span>
                                                    <span class="rc-seg__time-sep">|</span>
                                                    <span class="rc-seg__time-date">{{ formatReviewDate(seg.departure_date) }}</span>
                                                </div>
                                                <div class="rc-seg__terminal">Terminal: {{ seg.originTerminal || '—' }}</div>
                                            </div>

                                            <div class="rc-seg__mid">
                                                <div class="rc-seg__dur-text">{{ seg.flightTime1 || seg.duration || '—' }}</div>
                                                <div class="rc-seg__track">
                                                    <span class="rc-seg__dot" />
                                                    <span class="rc-seg__line">
                                                        <i class="fa-solid fa-plane rc-seg__plane-ico" aria-hidden="true" />
                                                    </span>
                                                    <span class="rc-seg__dot rc-seg__dot--arr" />
                                                </div>
                                                <div class="rc-seg__stoptype">{{ stopLabel(leg) }}</div>
                                            </div>

                                            <div class="rc-seg__point rc-seg__point--arr">
                                                <div class="rc-seg__code">{{ seg.arrival_code }}</div>
                                                <div class="rc-seg__airport">{{ seg.Destination_Airport_Name }}</div>
                                                <div class="rc-seg__time">
                                                    <span class="rc-seg__time-val">{{ seg.arrival_time || '—' }}</span>
                                                    <span class="rc-seg__time-sep">|</span>
                                                    <span class="rc-seg__time-date">{{ formatReviewDate(seg.arrival_date) }}</span>
                                                </div>
                                                <div class="rc-seg__terminal">Terminal: {{ seg.destinationTerminal || '—' }}</div>
                                            </div>

                                            <div class="rc-seg__info">
                                                <span class="rc-seg__info-cabin">Cabin: {{ seg.cabin_class || seg.cabin || 'Economy' }}, RBD: {{ seg.booking_code || seg.class_of_service || '—' }}</span>
                                                <span class="rc-seg__info-sep">|</span>
                                                <span class="rc-seg__info-fare">Fare Basis: {{ leg.fareBasisCode || '—' }}</span>
                                                <span class="rc-seg__info-sep">|</span>
                                                <span class="rc-seg__info-bag">Baggage: {{ carryOnLabel(leg.baggageAllowance) }}, {{ checkedLabel(leg.baggageAllowance) }}</span>
                                            </div>
                                        </article>

                                        <div v-if="!seg.lastitem" class="rc-chip rc-chip--layover">
                                            <img :src="'/theme/appimages/layover_dstina.svg'" class="rc-chip__icon" alt="" />
                                            <span class="rc-chip__main">
                                                Layover at {{ titleCaseCity(seg.Destination_City_Name) }}<template v-if="seg.layover_time"> - {{ seg.layover_time }}</template>
                                            </span>
                                            <span class="rc-chip__sep">|</span>
                                            <span class="rc-chip__airport">{{ seg.Destination_Airport_Name }}</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </section>

                        <!-- ── Passenger Details ──────────────────────────── -->
                        <section class="rc-section">
                            <button type="button" class="rc-section__head" @click="toggleSection('passengers')">
                                <span>Passenger Details</span>
                                <i class="fa-solid" :class="open.passengers ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true" />
                            </button>

                            <div v-show="open.passengers" class="rc-section__body rc-section__body--flush">
                                <div class="table-responsive">
                                    <table class="rc-pax-table">
                                        <colgroup>
                                            <col style="width: 26%">
                                            <col style="width: 15%">
                                            <col style="width: 15%">
                                            <col style="width: 44%">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Passenger</th>
                                                <th>Date of Birth</th>
                                                <th>Passport</th>
                                                <th>Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(p, pi) in passengers" :key="pi">
                                                <td>
                                                    <div class="rc-pax">
                                                        <div class="rc-pax__info">
                                                            <span>{{ p.name }}</span>
                                                            <span class="rc-pax__gender">
                                                                <span class="rc-pax__type-badge" :class="`rc-pax__type-badge--${p.paxType.toLowerCase()}`">{{ p.paxType }}</span>
                                                                {{ p.gender }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ p.dob }}</td>
                                                <td>{{ p.passport }}</td>
                                                <td class="rc-pax__contact">{{ p.contact }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <!-- ── Fare Details ────────────────────────────────── -->
                        <section class="rc-section">
                            <button type="button" class="rc-section__head" @click="toggleSection('fareDetails')">
                                <span>Fare Details</span>
                                <i class="fa-solid" :class="open.fareDetails ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true" />
                            </button>

                            <div v-show="open.fareDetails" class="rc-section__body rc-section__body--flush">
                                <div class="rc-fd">
                                    <div class="table-responsive rc-fd__table-wrap">
                                        <table class="rc-fd-table">
                                            <thead>
                                                <tr>
                                                    <th>PAX</th>
                                                    <th>Base Fare</th>
                                                    <th>Tax</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="bd in paxBreakdown" :key="bd.type" :class="`rc-fd-row--${paxTone(bd.type)}`">
                                                    <td>{{ bd.type }} × {{ bd.quantity }}</td>
                                                    <td>{{ fmtMoney(bd.baseFare) }}</td>
                                                    <td>{{ fmtMoney(bd.tax) }}</td>
                                                    <td>{{ fmtMoney(bd.total) }}</td>
                                                </tr>
                                                <tr v-if="!paxBreakdown.length">
                                                    <td colspan="4" class="rc-empty">Fare breakdown unavailable.</td>
                                                </tr>
                                            </tbody>
                                            <tfoot v-if="paxBreakdown.length">
                                                <tr>
                                                    <td>Total</td>
                                                    <td>{{ fmtMoney(fareTotals.baseFare) }}</td>
                                                    <td>{{ fmtMoney(fareTotals.tax) }}</td>
                                                    <td>{{ fmtMoney(fareTotals.grossFare) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="rc-fd__gross">
                                        <span class="rc-fd__gross-label">Gross Fare</span>
                                        <span class="rc-fd__gross-val">{{ fmtMoney(fareTotals.grossFare) }}</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- ── Fare Rules ──────────────────────────────────── -->
                        <section class="rc-section">
                            <button type="button" class="rc-section__head" @click="toggleSection('fareRules')">
                                <span>Fare Rules</span>
                                <i class="fa-solid" :class="open.fareRules ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true" />
                            </button>

                            <div v-show="open.fareRules" class="rc-section__body">
                                <div class="rc-rules-note">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true" />
                                    <span>Based on the fare rule policy, the pricing and schedule of the flight can be changed.</span>
                                </div>

                                <div v-if="!fareRulesSegments.length" class="rc-empty">
                                    No structured fare rules for this offer.
                                </div>

                                <article
                                    v-for="(seg, sIdx) in fareRulesSegments"
                                    :key="`${seg.direction}-${seg.flightRef}-${sIdx}`"
                                    class="rc-rule-card"
                                >
                                    <header class="rc-rule-card__head">
                                        <i class="fa-solid fa-plane" aria-hidden="true" />
                                        <span>{{ seg.displayLabel || seg.flightRef || 'Segment' }}</span>
                                        <span class="rc-rule-dir">{{ seg.direction === 'inbound' ? 'Return' : 'Outbound' }}</span>
                                    </header>

                                    <div class="rc-rule-tables">
                                        <div class="rc-rule-block">
                                            <div class="rc-rule-block__title">
                                                <i class="fa-solid fa-ban" aria-hidden="true" /> Cancellation
                                            </div>
                                            <div v-if="!seg.cancellation?.length" class="rc-rule-empty">No data</div>
                                            <table v-else class="rc-rule-table">
                                                <thead>
                                                    <tr><th>Condition</th><th>Status</th><th>Charge</th></tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(c, ci) in seg.cancellation" :key="ci">
                                                        <td>{{ formatRuleTiming(c.timing) }}</td>
                                                        <td>
                                                            <span class="rc-rule-chip" :class="c.permitted ? 'rc-rule-chip--ok' : 'rc-rule-chip--no'">
                                                                {{ c.permitted ? 'Permitted' : 'Not Permitted' }}
                                                            </span>
                                                        </td>
                                                        <td>{{ formatRuleAmount(c.amount) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="rc-rule-block">
                                            <div class="rc-rule-block__title">
                                                <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true" /> Changes
                                            </div>
                                            <div v-if="!seg.changes?.length" class="rc-rule-empty">No data</div>
                                            <table v-else class="rc-rule-table">
                                                <thead>
                                                    <tr><th>Condition</th><th>Status</th><th>Charge</th></tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(c, ci) in seg.changes" :key="ci">
                                                        <td>{{ formatRuleTiming(c.timing) }}</td>
                                                        <td>
                                                            <span class="rc-rule-chip" :class="c.permitted ? 'rc-rule-chip--ok' : 'rc-rule-chip--no'">
                                                                {{ c.permitted ? 'Permitted' : 'Not Permitted' }}
                                                            </span>
                                                        </td>
                                                        <td>{{ formatRuleAmount(c.amount) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </section>

            </div>

            <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
        </template>

        <div v-else class="review-confirm__state">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
            <p>No booking summary yet. Complete add-ons and continue to review.</p>
        </div>
    </div>
</template>

<style scoped>
/* ── Design tokens ──────────────────────────────────────────────── */
.review-confirm {
    --rc-primary:    #027de2;
    --rc-teal:       #0fb3a6;
    --rc-purple:     #7239ea;
    --rc-surface:    #ffffff;
    --rc-border:     #e2e8f0;
    --rc-soft:       #eef1f6;
    --rc-header:     #e2e8f0;
    --rc-seg-tint:   #f7f9fe;
    --rc-muted:      #64748b;
    --rc-text:       #0f172a;
    --rc-sub:        #475569;
    --rc-radius:     12px;
    --rc-shadow:     0 1px 3px rgba(15, 23, 42, 0.06), 0 6px 20px rgba(2, 125, 226, 0.07);
    --rc-transition: 0.18s ease;
}

/* ── Loading / empty state ──────────────────────────────────────── */
.review-confirm__state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--rc-muted);
}
.review-confirm__state > i {
    font-size: 2rem;
    color: #cbd5e1;
    margin-bottom: 0.75rem;
}
.review-confirm__spinner {
    width: 44px; height: 44px;
    margin: 0 auto 1rem;
    border: 3px solid #e2e8f0;
    border-top-color: var(--rc-primary);
    border-radius: 50%;
    animation: rc-spin 0.8s linear infinite;
}
@keyframes rc-spin { to { transform: rotate(360deg); } }

/* ── Scrollable section stack ─────────────────────────────────── */
.rc-scroll {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    margin-bottom: 1rem;
}

/* ── Collapsible section ───────────────────────────────────────── */
.rc-section {
    background: var(--rc-surface);
    border: 1px solid var(--rc-border);
    border-radius: var(--rc-radius);
    box-shadow: var(--rc-shadow);
    overflow: hidden;
}

.rc-section__head {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--rc-header);
    border: none;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--rc-text);
    cursor: pointer;
}

.rc-section__head i {
    color: var(--rc-muted);
    font-size: 0.8rem;
}

.rc-section__body {
    padding: 1rem 1.25rem;
}

.rc-section__body--flush {
    padding: 0;
}

.rc-empty {
    padding: 1.5rem 1rem;
    text-align: center;
    color: var(--rc-muted);
    font-size: 0.9rem;
}

/* ── Leg ────────────────────────────────────────────────────────── */
.rc-leg:not(:last-child) {
    border-bottom: 1px solid var(--rc-border);
}

.rc-leg__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    row-gap: 0.35rem;
    column-gap: 0.6rem;
    padding: 0.65rem 1.25rem;
    border-bottom: 1px solid var(--rc-border);
}

.rc-leg__title {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--rc-primary);
    flex-shrink: 0;
}
.rc-leg__title i { position: relative; top: 1px; }

.rc-leg__sep {
    color: var(--rc-border);
    flex-shrink: 0;
}

.rc-leg__route {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--rc-text);
}
.rc-leg__route i { font-size: 0.7rem; color: var(--rc-muted); position: relative; top: 1px; }

.rc-leg__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    color: var(--rc-sub);
}
.rc-leg__meta i { color: var(--rc-muted); font-size: 0.68rem; position: relative; top: -1px; }

.rc-leg__nonref {
    margin-left: auto;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--rc-primary);
    background: rgba(2, 125, 226, 0.08);
    border: 1px solid rgba(2, 125, 226, 0.2);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    flex-shrink: 0;
}

/* ── Segment row ────────────────────────────────────────────────── */
.rc-seg {
    padding: 1rem 1.25rem 5px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
    transition: background var(--rc-transition);
}
.rc-seg:hover { background: #fafbff; }

@media (min-width: 992px) {
    .rc-seg {
        grid-template-columns: 165px 1fr 150px 1fr;
        grid-template-rows: auto auto;
        row-gap: 0.6rem;
        column-gap: 0.75rem;
        align-items: start;
    }
}

.rc-seg__airline {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.5rem;
}
@media (min-width: 992px) {
    /* Spans both rows so its bottom lands exactly on the info bar's bottom, not the DAC/DXB row alone.
       Top/left bleed to .rc-seg's true edges (bottom stays untouched so the row-2 alignment holds).
       Left pad kept tight so "Operated By …" fits one line inside the narrow airline col. */
    .rc-seg__airline {
        grid-column: 1;
        grid-row: 1 / 3;
        align-self: stretch;
        margin: -1rem 0 0 -1.25rem;
        background: var(--rc-seg-tint);
        border-radius: 4px;
        padding: 0.75rem 0.65rem 1rem 1rem;
    }
    /* Segments after a layover chip keep breathing room instead of bleeding flush against it */
    .rc-chip + .rc-seg .rc-seg__airline {
        margin-top: -11px;
        padding-top: 1rem;
    }
}
.rc-seg__logo-wrap { flex-shrink: 0; }
.rc-seg__logo {
    width: 50px; height: 50px;
    border-radius: 8px;
    border: 1px solid var(--rc-border);
    background: var(--rc-soft);
    object-fit: contain;
    padding: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.rc-seg__logo--ph {
    color: var(--rc-primary);
    font-size: 1.1rem;
}
.rc-seg__airline-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--rc-text);
    line-height: 1.3;
}
.rc-seg__flightno {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--rc-primary);
    margin-top: 0.15rem;
}
.rc-seg__aircraft {
    font-size: 0.70rem;
    font-weight: 700;
    color: var(--rc-sub);
    margin-top: 0.1rem;
}
.rc-seg__operator {
    font-size: 0.72rem;
    color: var(--rc-muted);
    margin-top: 0.1rem;
    white-space: nowrap;
}

@media (min-width: 992px) {
    .rc-seg__point { grid-column: 2; grid-row: 1; }
    .rc-seg__point--arr { grid-column: 4; grid-row: 1; }
}
.rc-seg__code {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--rc-text);
    line-height: 1.2;
}
.rc-seg__airport {
    font-size: 0.72rem;
    color: var(--rc-muted);
    margin-top: 0.15rem;
    line-height: 1.3;
}
.rc-seg__time {
    font-size: 0.82rem;
    margin-top: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.rc-seg__time-val {
    font-weight: 700;
    color: var(--rc-text);
}
.rc-seg__time-sep {
    color: var(--rc-border);
    margin: 0 0.15rem;
}
.rc-seg__time-date {
    color: var(--rc-sub);
}
.rc-seg__terminal {
    font-size: 0.74rem;
    color: var(--rc-muted);
    margin-top: 0.15rem;
}
.rc-seg__mid {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0;
}
@media (min-width: 992px) {
    .rc-seg__mid { grid-column: 3; grid-row: 1; }
}
.rc-seg__dur-text {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--rc-sub);
    white-space: nowrap;
}
.rc-seg__track {
    display: flex;
    align-items: center;
    width: 100%;
    max-width: 110px;
}
.rc-seg__dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--rc-primary);
    flex-shrink: 0;
}
.rc-seg__dot--arr { background: #94a3b8; }
.rc-seg__line {
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, var(--rc-primary), #94a3b8);
    position: relative;
    margin: 0 2px;
}
.rc-seg__plane-ico {
    position: absolute;
    left: 50%; top: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.6rem;
    color: var(--rc-primary);
    background: var(--rc-surface);
    padding: 0 3px;
}
.rc-seg__stoptype {
    font-size: 0.7rem;
    color: var(--rc-muted);
    white-space: nowrap;
}

/* ── Per-segment info bar (cabin / RBD / fare basis / baggage) ──── */
.rc-seg__info {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    background: var(--rc-soft);
    border-radius: 2px;
    font-size: 0.7rem;
}
@media (min-width: 992px) {
    /* Sits in row 2, right of the logo column; bottom lands flush with the logo card's bottom.
       Right edge bled to .rc-seg's true edge, padding grows to compensate (mirrors the logo card's bleed). */
    .rc-seg__info {
        grid-column: 2 / 5;
        grid-row: 2;
        align-self: end;
        margin-right: -1.25rem;
        padding-right: 2rem;
    }
}
.rc-seg__info-cabin { color: var(--rc-teal); font-weight: 600; }
.rc-seg__info-fare  { color: var(--rc-purple); font-weight: 600; }
.rc-seg__info-bag    { color: #6059D8; font-weight: 600; }
.rc-seg__info-sep    { color: var(--rc-muted); }

/* ── Layover chip ───────────────────────────────────────────────── */
.rc-chip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.5rem 1.25rem;
    font-size: 0.8rem;
    border-top: 1px solid var(--rc-border);
    border-bottom: 1px solid var(--rc-border);
}
.rc-chip i { font-size: 0.8rem; }
.rc-chip__icon { width: 18px; height: 18px; flex-shrink: 0; }
.rc-chip__sep { color: var(--rc-border); }
.rc-chip--layover {
    color: var(--rc-primary);
}
.rc-chip--layover i { color: var(--rc-purple); }
.rc-chip__main { font-weight: 700; }
.rc-chip__airport { font-weight: 400; }

/* ── Passenger table ────────────────────────────────────────────── */
.rc-pax-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 0.84rem;
    margin-top: 0.5rem;
}
.rc-pax-table thead th {
    background: var(--rc-soft);
    color: var(--rc-muted);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid var(--rc-border);
    white-space: nowrap;
}
.rc-pax-table tbody td {
    padding: 0.8rem 1rem;
    border-bottom: 1px solid var(--rc-border);
    vertical-align: middle;
    color: var(--rc-text);
    font-size: 0.84rem;
}
.rc-pax-table tbody tr:last-child td { border-bottom: none; }
.rc-pax-table tbody tr:hover td { background: #fafbff; }

.rc-pax {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.rc-pax__info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.rc-pax__gender {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 400;
    color: var(--rc-muted);
}
.rc-pax__type-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.5rem;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.rc-pax__type-badge--adt { background: rgba(2, 125, 226, 0.12); color: var(--rc-primary); }
.rc-pax__type-badge--chd { background: rgba(114, 57, 234, 0.12); color: var(--rc-purple); }
.rc-pax__type-badge--inf { background: rgba(15, 179, 166, 0.12); color: var(--rc-teal); }
.rc-pax__type-badge--ins { background: rgba(15, 179, 166, 0.12); color: var(--rc-teal); }
.rc-pax__contact {
    font-size: 0.78rem;
    word-break: break-word;
    white-space: pre-line;
}

/* ── Fare Details ───────────────────────────────────────────────── */
.rc-fd {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0;
}
.rc-fd__table-wrap {
    flex: 1 1 380px;
}
.rc-fd-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
    margin-top: 0.5rem;
}
.rc-fd-table thead th {
    background: var(--rc-soft);
    color: var(--rc-muted);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.65rem 1.25rem;
    border-bottom: 1px solid var(--rc-border);
    text-align: left;
}
.rc-fd-table tbody td,
.rc-fd-table tfoot td {
    padding: 0.7rem 1.25rem;
    border-bottom: 1px solid var(--rc-border);
    color: var(--rc-text);
}
.rc-fd-table tfoot td {
    font-weight: 700;
    background: var(--rc-soft);
    border-bottom: none;
}

.rc-fd__gross {
    flex: 0 0 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    text-align: center;
    padding: 1rem 1.25rem;
    border-left: 1px solid var(--rc-border);
    background: var(--rc-seg-tint);
}
.rc-fd__gross-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--rc-muted);
}
.rc-fd__gross-val {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--rc-primary);
}

@media (max-width: 767px) {
    .rc-fd__gross { border-left: none; border-top: 1px solid var(--rc-border); flex: 1 1 100%; }
}

/* ── Fare Rules ─────────────────────────────────────────────────── */
.rc-rules-note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    margin-bottom: 1rem;
    background: #fff8e6;
    border: 1px solid rgba(240, 180, 27, 0.35);
    border-radius: 8px;
    font-size: 0.8rem;
    color: #7a5a12;
}
.rc-rules-note i { color: #f0b41b; }

.rc-rule-card {
    border: 1px solid var(--rc-border);
    border-radius: 10px;
    margin-bottom: 0.85rem;
    overflow: hidden;
}
.rc-rule-card:last-child { margin-bottom: 0; }
.rc-rule-card__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.9rem;
    background: var(--rc-soft);
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--rc-text);
}
.rc-rule-card__head i { color: var(--rc-primary); }
.rc-rule-dir {
    margin-left: auto;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--rc-muted);
    text-transform: uppercase;
}
.rc-rule-tables {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0;
}
@media (min-width: 768px) {
    .rc-rule-tables { grid-template-columns: 1fr 1fr; }
    .rc-rule-block:first-child { border-right: 1px solid var(--rc-border); }
}
.rc-rule-block { padding: 0.75rem 0.9rem; }
.rc-rule-block__title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--rc-sub);
    margin-bottom: 0.5rem;
}
.rc-rule-empty {
    font-size: 0.78rem;
    color: var(--rc-muted);
}
.rc-rule-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
}
.rc-rule-table th {
    text-align: left;
    color: var(--rc-muted);
    font-weight: 600;
    padding: 0.3rem 0.4rem;
    border-bottom: 1px solid var(--rc-border);
}
.rc-rule-table td {
    padding: 0.35rem 0.4rem;
    border-bottom: 1px solid var(--rc-border);
    color: var(--rc-text);
}
.rc-rule-chip {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
}
.rc-rule-chip--ok { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.rc-rule-chip--no { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

/* ── Dark mode ──────────────────────────────────────────────────── */
[data-bs-theme="dark"] .review-confirm {
    --rc-surface:  var(--bs-card-bg);
    --rc-border:   var(--bs-border-color);
    --rc-soft:     rgba(255, 255, 255, 0.05);
    --rc-header:   rgba(255, 255, 255, 0.09);
    --rc-seg-tint: rgba(2, 125, 226, 0.028);
    --rc-muted:    var(--bs-secondary-color);
    --rc-text:     var(--bs-body-color);
    --rc-sub:      var(--bs-secondary-color);
    --rc-shadow:   0 1px 3px rgba(0, 0, 0, 0.25), 0 6px 20px rgba(0, 0, 0, 0.18);
}

[data-bs-theme="dark"] .rc-seg:hover          { background: rgba(255, 255, 255, 0.03); }
[data-bs-theme="dark"] .rc-seg__plane-ico     { background: var(--bs-card-bg); }
[data-bs-theme="dark"] .rc-chip--layover      { color: #7dd3fc; }
[data-bs-theme="dark"] .rc-leg__nonref        { background: rgba(2, 125, 226, 0.15); }
[data-bs-theme="dark"] .rc-pax-table tbody tr:hover td { background: rgba(255, 255, 255, 0.04); }
[data-bs-theme="dark"] .rc-fd-table tfoot td  { background: rgba(255, 255, 255, 0.05); }
[data-bs-theme="dark"] .rc-rules-note         { background: rgba(240, 180, 27, 0.1); color: #fcd34d; }
[data-bs-theme="dark"] .review-confirm__spinner {
    border-color: rgba(255, 255, 255, 0.08);
    border-top-color: var(--rc-primary);
}

@media (prefers-reduced-motion: reduce) {
    .review-confirm__spinner { animation: none; }
    .rc-seg { transition: none; }
}
</style>
