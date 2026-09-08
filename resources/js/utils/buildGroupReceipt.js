import dayjs from 'dayjs'
import axiosInstance from '../axiosInstance'

const DEFAULT_AGENCY = {
    name: 'BlueSky Travel',
    address: 'Dhaka, Bangladesh',
    email: 'info@bluesky.com',
    phone: '+880 1XXX-XXXXXX',
    logo: null,
}

const DEFAULT_AIRLINE_LOGO = '/uploads/airlines/default.svg'

async function resolveAgency() {
    try {
        const res = await axiosInstance.get('getActiveCompany')
        const company = res.data?.data ?? null
        if (!company) return DEFAULT_AGENCY
        return {
            name: company.name || DEFAULT_AGENCY.name,
            address: company.address || DEFAULT_AGENCY.address,
            email: company.email || DEFAULT_AGENCY.email,
            phone: company.phone || DEFAULT_AGENCY.phone,
            logo: company.logo || null,
        }
    } catch {
        return DEFAULT_AGENCY
    }
}

// Same directory the airline-settings admin (AirlineLogoController) writes to —
// matched by code first, then by display name, since assigned_group.airline /
// segment metadata may hold either depending on how the PNR was assigned.
async function resolveAirlineIndex() {
    try {
        const res = await axiosInstance.get('getAllAirlines')
        const list = Array.isArray(res.data) ? res.data : []
        const byCode = new Map()
        const byName = new Map()
        for (const a of list) {
            const rawLogo = a?.logo ? String(a.logo) : ''
            const logo = rawLogo ? (rawLogo.startsWith('/') ? rawLogo : `/${rawLogo}`) : null
            if (!logo) continue
            if (a.code) byCode.set(String(a.code).trim().toUpperCase(), logo)
            if (a.a_name) byName.set(String(a.a_name).trim().toUpperCase(), logo)
        }
        return { byCode, byName }
    } catch {
        return { byCode: new Map(), byName: new Map() }
    }
}

function resolveAirlineLogo(identifier, airlineIndex) {
    const key = String(identifier ?? '').trim().toUpperCase()
    if (!key) return DEFAULT_AIRLINE_LOGO
    return airlineIndex.byCode.get(key) || airlineIndex.byName.get(key) || DEFAULT_AIRLINE_LOGO
}

function minutesBetween(from, to) {
    const a = dayjs(from)
    const b = dayjs(to)
    if (!a.isValid() || !b.isValid()) return 0
    return Math.max(b.diff(a, 'minute'), 0)
}

// "07 hr 55 min" — leg header total + layover chips
function longDuration(mins) {
    if (!mins) return '—'
    const h = Math.floor(mins / 60)
    const m = mins % 60
    return `${String(h).padStart(2, '0')} hr ${String(m).padStart(2, '0')} min`
}

// "5h 00m" — per-segment mid-track duration
function shortDuration(mins) {
    if (!mins) return '—'
    const h = Math.floor(mins / 60)
    const m = mins % 60
    return `${h}h ${String(m).padStart(2, '0')}m`
}

function formatTimeOfDay(value) {
    const d = dayjs(value)
    return d.isValid() ? d.format('hh:mm A') : '—'
}

function formatDob(value) {
    const d = dayjs(value)
    return d.isValid() ? d.format('DD-MMM-YYYY') : '—'
}

function formatBookedOn(value) {
    const d = dayjs(value)
    return d.isValid() ? d.format('DD MMM YYYY | hh:mm A') : '—'
}

function formatGeneratedAt() {
    return dayjs().format('DD MMMM YYYY | hh:mmA')
}

function titleCase(name) {
    const s = String(name ?? '').trim()
    if (!s) return ''
    if (s.length <= 3 && s === s.toUpperCase()) return s
    return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
}

// Matches allFlightInfo.vue's segmentBaggage() formatting
function baggageLabel(segment) {
    const cabin = segment.cabin_baggage ? `Cabin-${segment.cabin_baggage}${segment.cabin_baggage_unit || ''}` : null
    const checkIn = segment.check_in_baggage ? `Check In-${segment.check_in_baggage}${segment.check_in_baggage_unit || ''} /person` : null
    return [cabin, checkIn].filter(Boolean).join(', ') || '—'
}

function mapSegment(raw, assignedGroup, airlineIndex) {
    const durationMin = minutesBetween(raw.departure_datetime, raw.arrival_datetime)
    const airlineIdentifier = raw.metadata?.airline || assignedGroup?.airline

    return {
        airline_name: airlineIdentifier || '—',
        flight_number: raw.flight_no || '—',
        equipment: raw.metadata?.aircraft || raw.metadata?.aircraft_model || '—',
        logo_path: resolveAirlineLogo(airlineIdentifier, airlineIndex),
        cabin_class: raw.class_type || assignedGroup?.class_type || 'Economy',
        booking_code: raw.booking_class || assignedGroup?.code_rbd || '',
        fare_basis: raw.fare_basis || '',
        departure_code: raw.departure_from || '—',
        departure_city: titleCase(raw.departure_from) || raw.departure_from || '—',
        departure_airport: '',
        departure_time: formatTimeOfDay(raw.departure_datetime),
        departure_date: raw.departure_datetime,
        departure_time_raw: raw.departure_datetime,
        arrival_code: raw.arrival_to || '—',
        arrival_city: titleCase(raw.arrival_to) || raw.arrival_to || '—',
        arrival_airport: '',
        arrival_time: formatTimeOfDay(raw.arrival_datetime),
        arrival_date: raw.arrival_datetime,
        arrival_time_raw: raw.arrival_datetime,
        origin_terminal: raw.metadata?.terminal || '—',
        destination_terminal: '—',
        baggage: baggageLabel(raw),
        duration: shortDuration(durationMin),
        duration_minutes: durationMin,
    }
}

function attachLayovers(segments) {
    for (let i = 0; i < segments.length - 1; i++) {
        const cur = segments[i]
        const next = segments[i + 1]
        const gap = minutesBetween(cur.arrival_time_raw, next.departure_time_raw)
        cur.layover_time = gap > 0 ? longDuration(gap) : ''
        cur.layover_airport = cur.arrival_city
        cur.lastitem = false
    }
    if (segments.length) segments[segments.length - 1].lastitem = true
    return segments
}

function buildLeg(key, rawSegments, assignedGroup, airlineIndex, legLabel) {
    const segments = attachLayovers(rawSegments.map((s) => mapSegment(s, assignedGroup, airlineIndex)))
    const totalMin = segments.reduce((n, s) => n + (s.duration_minutes || 0), 0)
    const stops = Math.max(segments.length - 1, 0)
    const first = segments[0]

    return {
        key,
        label: legLabel ?? (key === 'inbound' ? 'Return Flight' : 'Outbound Flight'),
        duration: longDuration(totalMin),
        stopLabel: stops === 0 ? 'Non-stop' : `${stops} Stop${stops > 1 ? 's' : ''}`,
        cabin: first?.cabin_class,
        bookingCode: first?.booking_code,
        fareBasisCode: first?.fare_basis,
        baggageLabel: first?.baggage,
        segments,
    }
}

function paxTypeCode(type) {
    const t = String(type || '').toUpperCase()
    if (t.startsWith('CH') || t === 'CNN') return 'CHD'
    if (t.startsWith('IN') || t === 'INS') return 'INF'
    return 'ADT'
}

function countByPaxType(ticketedPax) {
    const counts = { ADT: 0, CHD: 0, INF: 0 }
    for (const p of ticketedPax) counts[paxTypeCode(p.pax_type)]++
    return counts
}

// price_offers carries the per-pax-type base/tax/AIT actually quoted to the agent
// (same source the group list's Total Fare column reads) — this is the real fare,
// unlike group_pnrs.base_fare/tax which is a legacy single figure.
function buildFareFromPriceOffer(priceOffer, counts) {
    const typeMeta = [
        { code: 'ADT', label: 'Adult', base: 'adult_base_fare', tax: 'adult_tax', ait: 'adult_ait' },
        { code: 'CHD', label: 'Child', base: 'child_base_fare', tax: 'child_tax', ait: 'child_ait' },
        { code: 'INF', label: 'Infant', base: 'infant_base_fare', tax: 'infant_tax', ait: 'infant_ait' },
    ]
    let grossFare = 0
    let totalTax = 0
    const rows = []
    for (const t of typeMeta) {
        const qty = counts[t.code] || 0
        if (!qty) continue
        const base = Number(priceOffer?.[t.base] ?? 0) * qty
        const tax = (Number(priceOffer?.[t.tax] ?? 0) + Number(priceOffer?.[t.ait] ?? 0)) * qty
        grossFare += base
        totalTax += tax
        rows.push({ label: `${t.label} X ${qty}`, base, tax })
    }
    return { rows, grossFare, totalTax }
}

function wayTitleFromType(wayType) {
    const w = String(wayType || '').toLowerCase()
    if (w.includes('multi')) return 'MULTI CITY'
    if (w.includes('round')) return 'ROUND WAY'
    if (w.includes('one')) return 'ONE WAY'
    return null
}

// Maps the /group-eticket/{id} response (group + assigned PNR/segments + selected PAX)
// onto the same `receipt` shape BookingReceiptDoc.vue already knows how to render —
// so group e-tickets get the exact same voucher layout as individual bookings.
export async function buildGroupReceipt({ group, paxList, bookedBy, priceOffer }) {
    const assignedGroup = group?.assigned_group ?? null
    const allSegments = [...(assignedGroup?.segments ?? [])]
        .sort((a, b) => (a.segment_order ?? 0) - (b.segment_order ?? 0))

    const [agency, airlineIndex] = await Promise.all([resolveAgency(), resolveAirlineIndex()])

    const departureSegs = allSegments.filter((s) => s.segment_type === 'departure')
    const returnSegs = allSegments.filter((s) => s.segment_type === 'return')
    const multiCitySegs = allSegments.filter((s) => s.segment_type === 'multi_city')

    const legs = []
    if (departureSegs.length) legs.push(buildLeg('outbound', departureSegs, assignedGroup, airlineIndex))
    if (returnSegs.length) legs.push(buildLeg('inbound', returnSegs, assignedGroup, airlineIndex))
    // Each multi-city segment is its own unrelated city-pair, not a connecting flight of
    // one leg, so every one gets its own leg card instead of being lumped together.
    multiCitySegs.forEach((seg, idx) => {
        legs.push(buildLeg(`multicity-${idx}`, [seg], assignedGroup, airlineIndex, `Flight ${idx + 1}`))
    })

    const ticketedPax = (paxList ?? []).filter((p) => !!p.ticket_no)
    if (!ticketedPax.length) {
        throw new Error('Selected passenger(s) are not ticketed yet.')
    }

    const passengers = ticketedPax.map((p) => ({
        name: [p.title, p.first_name, p.last_name].filter(Boolean).join(' ') || '—',
        type: paxTypeCode(p.pax_type),
        gender: p.gender || '—',
        dob: formatDob(p.dob),
        passport: p.passport_no || '—',
        contact: p.phone || p.email || '—',
    }))
    const ticketNumbers = ticketedPax.map((p) => p.ticket_no)

    const currency = priceOffer?.currency || group?.currency || assignedGroup?.currency || 'BDT'

    let breakdown = []
    let grossFare = 0
    let totalTax = 0
    let totalPayable = 0

    if (priceOffer) {
        const counts = countByPaxType(ticketedPax)
        const built = buildFareFromPriceOffer(priceOffer, counts)
        breakdown = built.rows
        grossFare = built.grossFare
        totalTax = built.totalTax
        totalPayable = grossFare + totalTax
    } else {
        // No price offer on record — fall back to the group PNR's own base_fare/tax.
        const baseUnit = Number(assignedGroup?.base_fare ?? 0)
        const taxUnit = Number(assignedGroup?.tax ?? 0)
        grossFare = baseUnit * ticketedPax.length
        totalTax = taxUnit * ticketedPax.length
        totalPayable = grossFare + totalTax
        if (baseUnit || taxUnit) {
            breakdown = [{ label: `Passenger X ${ticketedPax.length}`, base: grossFare, tax: totalTax }]
        }
    }

    const receipt = {
        bookingId: group?.group_code || '—',
        gdsPnr: assignedGroup?.pnr || '—',
        airlinePnr: assignedGroup?.airline_pnr || '—',
        refundStatus: '',
        status: 'Ticketed',
        bookedOn: formatBookedOn(group?.created_at),
        bookedBy: bookedBy || '—',
        route: {
            wayTitle: wayTitleFromType(assignedGroup?.way_type)
                ?? (multiCitySegs.length ? 'MULTI CITY' : returnSegs.length ? 'ROUND WAY' : 'ONE WAY'),
        },
        legs,
        passengers,
        fare: {
            currency,
            grossFare,
            tax: totalTax,
            discount: 0,
            totalPayable,
            breakdown,
        },
        paymentDeadline: '—',
        paymentDeadlineLong: null,
        penalties: null,
        paymentStatus: '',
        airlineName: assignedGroup?.airline || 'the airline',
        agency,
        notes: [],
        authorizedBy: agency.name,
        generatedAt: formatGeneratedAt(),
    }

    return { receipt, ticketNumbers }
}
