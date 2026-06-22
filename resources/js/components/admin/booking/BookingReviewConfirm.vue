<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useBookingStore } from '../../../stores/bookingStore'

const props = defineProps({
    snapshot: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    confirming: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['back', 'confirm'])

const bookingStore = useBookingStore()
const { flight, priceData, travelerForms } = storeToRefs(bookingStore)

const price = computed(() => props.snapshot?.price ?? priceData.value)
const currency = computed(() => price.value?.currency ?? 'BDT')

function fmtMoney(n, spaced = true) {
    const num = Number(n)
    if (Number.isNaN(num)) return '—'
    const val = num.toLocaleString(undefined, { maximumFractionDigits: 0 })
    return spaced ? `${currency.value} ${val}` : `${currency.value}${val}`
}

const fareSummary = computed(() => {
    const p = price.value ?? {}
    return {
        grossFare: p.base_fare ?? 0,
        tax: p.total_taxes ?? 0,
        ait: p.ait ?? 0,
        serviceCharge: p.service_charge ?? p.total_fees ?? 0,
        discount: p.discount ?? 0,
        totalPayable: p.total_price ?? 0,
    }
})

const fareRows = computed(() => [
    { key: 'gross',   label: 'Gross Fare',     value: fmtMoney(fareSummary.value.grossFare, false) },
    { key: 'tax',     label: 'Taxes & Fees',   value: fmtMoney(fareSummary.value.tax, false) },
    { key: 'ait',     label: 'AIT',            value: fmtMoney(fareSummary.value.ait, false) },
    { key: 'service', label: 'Service Charge', value: fmtMoney(fareSummary.value.serviceCharge, false) },
])

function formatTime(date, time) {
    if (!time) return ''
    const raw = String(time)
    if (/AM|PM/i.test(raw)) return raw
    const ts = new Date(`${date || '1970-01-01'}T${raw}`)
    if (Number.isNaN(ts.getTime())) return raw
    return ts.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })
}

function formatReviewDate(date) {
    if (!date) return ''
    const d = new Date(`${date}T00:00:00`)
    if (Number.isNaN(d.getTime())) return date
    const weekday = d.toLocaleDateString('en-US', { weekday: 'long' })
    const day = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    return `${weekday}, ${day}`
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
            baggage: baggageLabel(f.outbound),
            refundable: refundableLabel(f.outbound),
            segments: legSegmentsFromFlight(f.outbound),
        })
    }

    if (f?.inbound?.segments?.length) {
        legs.push({
            key: 'inbound',
            label: 'Return',
            icon: 'fa-solid fa-plane-arrival',
            theme: 'inbound',
            baggage: baggageLabel(f.inbound),
            refundable: refundableLabel(f.inbound),
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
                baggage: '—',
                refundable: '—',
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
                baggage: '—',
                refundable: '—',
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
        return {
            name:     t.name || '—',
            gender:   t.gender ?? f?.gender ?? '—',
            dob:      formatDob(t.dob ?? f?.dob),
            passport: t.passport_no ?? f?.passportNo ?? '—',
            contact,
        }
    })
})

function classLabel(seg) {
    const cabin = seg.cabin_class || seg.cabin || 'Economy'
    const code  = seg.booking_code || seg.class_of_service || ''
    return code ? `${cabin}(${code})` : cabin
}

function legRoute(leg) {
    const segs = leg.segments
    if (!segs.length) return ''
    const from = titleCaseCity(segs[0].Origin_City_Name || segs[0].departure_code)
    const to   = titleCaseCity(segs[segs.length - 1].Destination_City_Name || segs[segs.length - 1].arrival_code)
    return from && to ? `${from} to ${to}` : ''
}

function layoverText(seg) {
    const city = codeCity(seg.Destination_City_Name, seg.arrival_code)
    const time = seg.layover_time ? ` - ${seg.layover_time}` : ''
    return `Layover at ${city}${time}`
}

function destinationText(seg) {
    const city    = codeCity(seg.Destination_City_Name, seg.arrival_code)
    const airport = seg.Destination_Airport_Name || ''
    return { city, airport }
}

function paxInitials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean)
    if (!parts.length) return '?'
    return parts.map((p) => p[0]).join('').slice(0, 2).toUpperCase()
}

function segSubtitle(seg) {
    const parts = [
        seg.flight_number || seg.flight,
        seg.equipment || seg.aircraft_name,
        classLabel(seg),
    ].filter(Boolean)
    return parts.join(' • ')
}
</script>

<template>
    <div class="review-confirm">
        <div v-if="loading" class="review-confirm__state">
            <div class="review-confirm__spinner" />
            <p>Preparing your booking summary…</p>
        </div>

        <template v-else-if="snapshot">
            <div class="review-stack">

                <!-- ── Flight Details ─────────────────────────────────────── -->
                <section class="rc-card">
                    <div class="rc-card__head">
                        <i class="fa-solid fa-plane-departure rc-card__head-icon" aria-hidden="true" />
                        <span class="rc-card__head-title">Flight Details</span>
                    </div>

                    <div v-if="!hasFlights" class="rc-empty">
                        Flight details unavailable.
                    </div>

                    <div
                        v-for="leg in journeyLegs"
                        :key="leg.key"
                        class="rc-leg"
                        :class="`rc-leg--${leg.theme}`"
                    >
                        <!-- Leg heading: badge + route -->
                        <div class="rc-leg__head">
                            <span class="rc-leg__badge" :class="`rc-leg__badge--${leg.theme}`">
                                <i :class="leg.icon" aria-hidden="true" />
                                {{ leg.label }}
                            </span>
                            <span class="rc-leg__route">{{ legRoute(leg) }}</span>
                        </div>

                        <!-- Column labels -->
                        <div class="rc-seg-cols d-none d-lg-flex">
                            <span class="rc-seg-cols__flight">Flight</span>
                            <span class="rc-seg-cols__dep">Departure</span>
                            <span class="rc-seg-cols__dur">Duration</span>
                            <span class="rc-seg-cols__arr">Arrival</span>
                        </div>

                        <!-- Segments -->
                        <template v-for="(seg, idx) in leg.segments" :key="`${leg.key}-${idx}`">
                            <article class="rc-seg">
                                <!-- Airline -->
                                <div class="rc-seg__airline">
                                    <div class="rc-seg__logo-wrap">
                                        <img v-if="seg.logo_path" :src="seg.logo_path" class="rc-seg__logo" alt="" />
                                        <div v-else class="rc-seg__logo rc-seg__logo--ph">
                                            <i class="fa-solid fa-plane" aria-hidden="true" />
                                        </div>
                                    </div>
                                    <div>
                                        <div class="rc-seg__airline-name">{{ seg.airline_name }}</div>
                                        <div class="rc-seg__airline-sub">{{ segSubtitle(seg) }}</div>
                                    </div>
                                </div>

                                <!-- Departure -->
                                <div class="rc-seg__point">
                                    <div class="rc-seg__time">{{ seg.departure_time || '—' }}</div>
                                    <div class="rc-seg__city">{{ codeCity(seg.Origin_City_Name, seg.departure_code) }}</div>
                                    <div class="rc-seg__date">{{ formatReviewDate(seg.departure_date) }}</div>
                                    <div class="rc-seg__terminal">Terminal {{ seg.originTerminal || '—' }}</div>
                                </div>

                                <!-- Duration / Track -->
                                <div class="rc-seg__mid">
                                    <div class="rc-seg__dur-text">{{ seg.flightTime1 || seg.duration || '—' }}</div>
                                    <div class="rc-seg__track">
                                        <span class="rc-seg__dot" />
                                        <span class="rc-seg__line">
                                            <i class="fa-solid fa-plane rc-seg__plane-ico" aria-hidden="true" />
                                        </span>
                                        <span class="rc-seg__dot rc-seg__dot--arr" />
                                    </div>
                                    <div class="rc-seg__stoptype">{{ seg.stop_count ? `${seg.stop_count} stop` : 'Non-stop' }}</div>
                                </div>

                                <!-- Arrival -->
                                <div class="rc-seg__point rc-seg__point--arr">
                                    <div class="rc-seg__time">{{ seg.arrival_time || '—' }}</div>
                                    <div class="rc-seg__city">{{ codeCity(seg.Destination_City_Name, seg.arrival_code) }}</div>
                                    <div class="rc-seg__date">{{ formatReviewDate(seg.arrival_date) }}</div>
                                    <div class="rc-seg__terminal">Terminal {{ seg.destinationTerminal || '—' }}</div>
                                </div>
                            </article>

                            <!-- Layover / reached-destination chip -->
                            <div v-if="!seg.lastitem" class="rc-chip rc-chip--layover">
                                <i class="fa-solid fa-clock" aria-hidden="true" />
                                <span>{{ layoverText(seg) }}</span>
                                <span class="rc-chip__sep">|</span>
                                <span>{{ seg.Destination_Airport_Name }}</span>
                            </div>
                            <div v-else class="rc-chip rc-chip--dest">
                                <i class="fa-solid fa-location-dot" aria-hidden="true" />
                                <span>Reached Destination at {{ destinationText(seg).city }}</span>
                                <span class="rc-chip__sep">|</span>
                                <span>{{ destinationText(seg).airport }}</span>
                            </div>
                        </template>

                        <!-- Leg footer: baggage + refundable -->
                        <div class="rc-leg__footer">
                            <span class="rc-leg__footer-item">
                                <i class="fa-solid fa-suitcase-rolling" aria-hidden="true" />
                                Baggage: {{ leg.baggage }}
                            </span>
                            <span class="rc-leg__footer-sep">|</span>
                            <span class="rc-leg__footer-item">
                                <i class="fa-solid fa-rotate-left" aria-hidden="true" />
                                Refundable: {{ leg.refundable }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- ── Passenger + Fare side by side ──────────────────── -->
                <div class="rc-bottom-row">

                    <!-- Passenger Details -->
                    <section class="rc-card rc-card--pax">
                        <div class="rc-card__head">
                            <i class="fa-solid fa-users rc-card__head-icon" aria-hidden="true" />
                            <span class="rc-card__head-title">Passenger Details</span>
                        </div>
                        <div class="table-responsive">
                            <table class="rc-pax-table">
                                <thead>
                                    <tr>
                                        <th>Traveler</th>
                                        <th>Gender</th>
                                        <th>DOB</th>
                                        <th>Passport No.</th>
                                        <th>Contact Info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(p, pi) in passengers" :key="pi">
                                        <td>
                                            <div class="rc-pax">
                                                <span class="rc-pax__avatar">{{ paxInitials(p.name) }}</span>
                                                <strong>{{ p.name }}</strong>
                                            </div>
                                        </td>
                                        <td>{{ p.gender }}</td>
                                        <td>{{ p.dob }}</td>
                                        <td>{{ p.passport }}</td>
                                        <td class="rc-pax__contact">{{ p.contact }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Fare Summary -->
                    <section class="rc-card rc-card--fare">
                        <div class="rc-card__head">
                            <i class="fa-solid fa-receipt rc-card__head-icon" aria-hidden="true" />
                            <span class="rc-card__head-title">Fare Summary</span>
                        </div>
                        <div class="rc-fare">
                            <div v-for="row in fareRows" :key="row.key" class="rc-fare__row">
                                <span class="rc-fare__label">{{ row.label }}</span>
                                <span class="rc-fare__val">{{ row.value }}</span>
                            </div>
                            <div v-if="fareSummary.discount" class="rc-fare__row rc-fare__row--discount">
                                <span class="rc-fare__label">Discount</span>
                                <span class="rc-fare__val rc-fare__val--discount">- {{ fmtMoney(fareSummary.discount) }}</span>
                            </div>
                            <div class="rc-fare__total">
                                <div class="rc-fare__total-label">Total Amount</div>
                                <div class="rc-fare__total-row">
                                    <span class="rc-fare__total-val">{{ fmtMoney(fareSummary.totalPayable) }}</span>
                                    <span class="rc-fare__incl">Incl. all taxes</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>

            <footer class="rc-actions">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-lg rc-actions__back"
                    @click="emit('back')"
                >
                    <i class="fa-solid fa-arrow-left me-1" aria-hidden="true" />
                    Back to Previous
                </button>
                <button
                    type="button"
                    class="btn btn-lg rc-actions__confirm"
                    :disabled="confirming"
                    @click="emit('confirm')"
                >
                    <span v-if="confirming"><i class="fa-solid fa-spinner fa-spin me-2" aria-hidden="true" /></span>
                    <i v-else class="fa-solid fa-lock me-2" aria-hidden="true" />
                    Confirm &amp; Book Flight
                </button>
            </footer>
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
    --rc-outbound:   #027de2;
    --rc-inbound:    #00ab55;
    --rc-teal:       #0fb3a6;
    --rc-surface:    #ffffff;
    --rc-border:     #e2e8f0;
    --rc-soft:       #f8fafc;
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

/* ── Stack ──────────────────────────────────────────────────────── */
.review-stack {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ── Card ───────────────────────────────────────────────────────── */
.rc-card {
    background: var(--rc-surface);
    border: 1px solid var(--rc-border);
    border-radius: var(--rc-radius);
    box-shadow: var(--rc-shadow);
    overflow: hidden;
}

.rc-card__head {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--rc-border);
}

.rc-card__head-icon {
    color: var(--rc-primary);
    font-size: 0.9rem;
}

.rc-card__head-title {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--rc-text);
}

/* ── Leg ────────────────────────────────────────────────────────── */
.rc-leg:not(:last-child) {
    border-bottom: 1px solid var(--rc-border);
}

.rc-leg__head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 1.25rem;
    border-bottom: 1px solid var(--rc-border);
}

.rc-leg__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.28rem 0.75rem;
    border-radius: 999px;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    flex-shrink: 0;
}

.rc-leg__badge--outbound {
    background: rgba(2, 125, 226, 0.10);
    color: var(--rc-outbound);
    border: 1px solid rgba(2, 125, 226, 0.22);
}

.rc-leg__badge--inbound {
    background: rgba(0, 171, 85, 0.10);
    color: var(--rc-inbound);
    border: 1px solid rgba(0, 171, 85, 0.22);
}

.rc-leg__route {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--rc-text);
}

/* ── Column labels ──────────────────────────────────────────────── */
.rc-seg-cols {
    display: flex;
    align-items: center;
    gap: 0;
    padding: 0.5rem 1.25rem;
    background: var(--rc-soft);
    border-bottom: 1px solid var(--rc-border);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--rc-muted);
}
.rc-seg-cols__flight { flex: 0 0 200px; }
.rc-seg-cols__dep    { flex: 1 1 0; }
.rc-seg-cols__dur    { flex: 0 0 150px; text-align: center; }
.rc-seg-cols__arr    { flex: 1 1 0; }

.rc-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--rc-muted);
    font-size: 0.9rem;
}

/* ── Segment row ────────────────────────────────────────────────── */
.rc-seg {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--rc-border);
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    transition: background var(--rc-transition);
}
.rc-seg:hover { background: #fafbff; }

@media (min-width: 992px) {
    .rc-seg {
        grid-template-columns: 200px 1fr 150px 1fr;
        align-items: center;
        gap: 0.75rem;
    }
}

/* Airline col */
.rc-seg__airline {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}
.rc-seg__logo-wrap { flex-shrink: 0; }
.rc-seg__logo {
    width: 50px; height: 50px;
    border-radius: 8px;
    border: 1px solid var(--rc-border);
    background: var(--rc-soft);
    object-fit: contain;
    padding: 4px;
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
.rc-seg__airline-sub {
    font-size: 0.75rem;
    color: var(--rc-sub);
    margin-top: 0.2rem;
    line-height: 1.4;
}

/* Departure / Arrival cols */
.rc-seg__point {}
.rc-seg__time {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--rc-text);
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}
.rc-seg__city {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--rc-teal);
    margin-top: 0.2rem;
}
.rc-seg__date {
    font-size: 0.76rem;
    color: var(--rc-sub);
    margin-top: 0.15rem;
}
.rc-seg__terminal {
    font-size: 0.74rem;
    color: var(--rc-muted);
    margin-top: 0.1rem;
}
.rc-seg__point--arr .rc-seg__time {
    text-align: right;
}
.rc-seg__point--arr .rc-seg__city,
.rc-seg__point--arr .rc-seg__date,
.rc-seg__point--arr .rc-seg__terminal {
    text-align: right;
}

/* Duration / Track col */
.rc-seg__mid {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0;
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

/* ── Layover / Dest chips ───────────────────────────────────────── */
.rc-chip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-top: 1px solid var(--rc-border);
}
.rc-chip i { font-size: 0.8rem; opacity: 0.85; }
.rc-chip__sep { opacity: 0.4; }
.rc-chip--layover {
    background: #e8f4fc;
    color: #066bb8;
}
.rc-chip--dest {
    background: #e8f4fc;
    color: #066bb8;
}

/* ── Leg footer ─────────────────────────────────────────────────── */
.rc-leg__footer {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 1.25rem;
    background: var(--rc-soft);
    border-top: 1px solid var(--rc-border);
    font-size: 0.78rem;
    color: var(--rc-sub);
}
.rc-leg__footer-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.rc-leg__footer-item i { color: var(--rc-muted); font-size: 0.8rem; }
.rc-leg__footer-sep { opacity: 0.4; }

/* ── Bottom row ─────────────────────────────────────────────────── */
.rc-bottom-row {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
}
.rc-card--pax  { flex: 1 1 0; min-width: 0; }
.rc-card--fare { flex: 0 0 270px; }

@media (max-width: 767px) {
    .rc-bottom-row { flex-direction: column; }
    .rc-card--fare { flex: 1 1 auto; width: 100%; }
}

/* ── Passenger table ────────────────────────────────────────────── */
.rc-pax-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
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
.rc-pax__avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--rc-primary), #3b9eff);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.rc-pax__contact {
    font-size: 0.78rem;
    max-width: 200px;
    word-break: break-word;
    white-space: pre-line;
}

/* ── Fare summary ───────────────────────────────────────────────── */
.rc-fare {
    display: flex;
    flex-direction: column;
}
.rc-fare__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid var(--rc-border);
    transition: background var(--rc-transition);
}
.rc-fare__row:hover { background: var(--rc-soft); }
.rc-fare__label {
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--rc-sub);
}
.rc-fare__val {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--rc-text);
    text-align: right;
}
.rc-fare__row--discount .rc-fare__label { color: #16a34a; }
.rc-fare__val--discount { color: #16a34a; }

.rc-fare__total {
    padding: 0.85rem 1rem 0.8rem;
    background: linear-gradient(180deg, rgba(2, 125, 226, 0.06) 0%, rgba(2, 125, 226, 0.11) 100%);
    border-top: 1px solid rgba(2, 125, 226, 0.18);
}
.rc-fare__total-label {
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--rc-muted);
    margin-bottom: 0.4rem;
}
.rc-fare__total-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}
.rc-fare__total-val {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--rc-primary);
    line-height: 1;
}
.rc-fare__incl {
    font-size: 0.7rem;
    color: var(--rc-muted);
    white-space: nowrap;
    font-style: italic;
}

/* ── Actions ────────────────────────────────────────────────────── */
.rc-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--rc-border);
}
.rc-actions__back {
    cursor: pointer;
    transition: border-color var(--rc-transition), color var(--rc-transition);
}
.rc-actions__back:hover {
    border-color: var(--rc-primary);
    color: var(--rc-primary);
}
.rc-actions__confirm {
    cursor: pointer;
    background: linear-gradient(135deg, #0880e1, #3b9eff);
    border: none;
    color: #fff;
    font-weight: 700;
    padding-left: 2rem;
    padding-right: 2rem;
    box-shadow: 0 6px 20px rgba(2, 125, 226, 0.30);
    transition: transform var(--rc-transition), box-shadow var(--rc-transition);
}
.rc-actions__confirm:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 28px rgba(2, 125, 226, 0.38);
    color: #fff;
}
.rc-actions__confirm:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
.rc-actions__back:focus-visible,
.rc-actions__confirm:focus-visible {
    outline: 2px solid var(--rc-primary);
    outline-offset: 2px;
}

/* ── Dark mode ──────────────────────────────────────────────────── */
[data-bs-theme="dark"] .review-confirm {
    --rc-surface:  var(--bs-card-bg);
    --rc-border:   var(--bs-border-color);
    --rc-soft:     rgba(255, 255, 255, 0.04);
    --rc-muted:    var(--bs-secondary-color);
    --rc-text:     var(--bs-body-color);
    --rc-sub:      var(--bs-secondary-color);
    --rc-shadow:   0 1px 3px rgba(0, 0, 0, 0.25), 0 6px 20px rgba(0, 0, 0, 0.18);
}

[data-bs-theme="dark"] .rc-seg:hover          { background: rgba(255, 255, 255, 0.03); }
[data-bs-theme="dark"] .rc-seg__plane-ico     { background: var(--bs-card-bg); }
[data-bs-theme="dark"] .rc-chip--layover,
[data-bs-theme="dark"] .rc-chip--dest         { background: rgba(2, 125, 226, 0.10); color: #7dd3fc; }
[data-bs-theme="dark"] .rc-leg__badge--outbound { background: rgba(2, 125, 226, 0.15); border-color: rgba(2, 125, 226, 0.3); }
[data-bs-theme="dark"] .rc-leg__badge--inbound  { background: rgba(0, 171, 85, 0.15);  border-color: rgba(0, 171, 85, 0.3); }
[data-bs-theme="dark"] .rc-pax-table tbody tr:hover td { background: rgba(255, 255, 255, 0.04); }
[data-bs-theme="dark"] .rc-fare__total {
    background: rgba(2, 125, 226, 0.10);
    border-top-color: rgba(2, 125, 226, 0.22);
}
[data-bs-theme="dark"] .review-confirm__spinner {
    border-color: rgba(255, 255, 255, 0.08);
    border-top-color: var(--rc-primary);
}

@media (prefers-reduced-motion: reduce) {
    .review-confirm__spinner { animation: none; }
    .rc-seg, .rc-fare__row, .rc-actions__confirm, .rc-actions__back { transition: none; }
}
</style>
