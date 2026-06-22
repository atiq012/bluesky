import dayjs from 'dayjs'
import axiosInstance from '../axiosInstance'
import { formatDuration, formatTime } from './dateUtils'
import { loadAirportMap, resolveAirport, mergeAirportHintsFromFlight } from './airportLookup'

export function formatBookingCode(attemptId) {
    const id = Number(attemptId)
    if (!id) return 'BLU000000'
    return `BLU${String(id).padStart(6, '0')}`
}

const REFUND_LABELS = {
    refundable: 'Refundable',
    partial: 'Partially Refundable',
    non_refundable: 'Non Refundable',
}

const DEFAULT_AGENCY = {
    name: 'BlueSky Travel',
    address: 'Dhaka, Bangladesh',
    email: 'info@bluesky.com',
    phone: '+880 1XXX-XXXXXX',
    logo: null,
}

let activeCompanyPromise = null

async function loadActiveCompany() {
    if (!activeCompanyPromise) {
        activeCompanyPromise = axiosInstance.get('getActiveCompany')
            .then((res) => res.data?.data ?? null)
            .catch(() => null)
    }
    return activeCompanyPromise
}

function resolveAgency(company) {
    if (!company) return DEFAULT_AGENCY
    return {
        name: company.name || DEFAULT_AGENCY.name,
        address: company.address || DEFAULT_AGENCY.address,
        email: company.email || DEFAULT_AGENCY.email,
        phone: company.phone || DEFAULT_AGENCY.phone,
        logo: company.logo || null,
    }
}

const TERMS = [
    'All bookings are subject to airline rules, fare conditions, and regulatory requirements.',
    'Passengers must carry valid travel documents and arrive at the airport within the recommended check-in time.',
    'Schedule, terminal, and baggage information may change without prior notice until ticket issuance.',
    'Refund, reissue, and cancellation charges follow airline and fare-rule policies shown at booking time.',
    'BlueSky acts as an agent and is not liable for airline operational changes beyond standard support obligations.',
]

function extractLocators(reservation) {
    const receipts = reservation?.Receipt ?? []
    const list = Array.isArray(receipts) ? receipts : [receipts]
    let gdsPnr = null
    let airlinePnr = null

    for (const receipt of list) {
        const loc = receipt?.Confirmation?.Locator
        if (!loc?.value) continue
        const val = String(loc.value).trim().toUpperCase()
        const source = String(loc.source ?? '')
        if (source === '1G') gdsPnr = val
        else if (!airlinePnr) airlinePnr = val
    }

    return { gdsPnr, airlinePnr }
}

function formatReviewDate(date) {
    if (!date) return ''
    const d = dayjs(`${date}T00:00:00`)
    if (!d.isValid()) return date
    return `${d.format('DD MMM')}, ${d.format('dddd')}`
}

function formatSegmentDateTime(date, time, fallbackDate, fallbackTime) {
    const d = date ?? fallbackDate
    const t = formatTime(d, time) || fallbackTime || '—'
    const dateLine = formatReviewDate(d)
    return dateLine ? `${t} | ${dateLine}` : t
}

function formatDob(iso) {
    if (!iso) return '—'
    const d = dayjs(iso.length > 10 ? iso : `${iso}T00:00:00`)
    return d.isValid() ? d.format('DD-MMM-YYYY') : iso
}

function parseIsoMinutes(iso) {
    if (!iso) return 0
    const m = String(iso).match(/PT(?:(\d+)H)?(?:(\d+)M)?/)
    if (!m) return 0
    return (parseInt(m[1] ?? 0, 10) * 60) + parseInt(m[2] ?? 0, 10)
}

function minutesToLabel(mins) {
    if (!mins || mins <= 0) return ''
    const h = Math.floor(mins / 60)
    const m = mins % 60
    if (h && m) return `${h}hr ${m} min`
    if (h) return `${h}hr 0 min`
    return `${m} min`
}

function computeLayover(prevArrivalDate, prevArrivalTime, nextDepartureDate, nextDepartureTime) {
    const a = dayjs(`${prevArrivalDate}T${prevArrivalTime}`)
    const b = dayjs(`${nextDepartureDate}T${nextDepartureTime}`)
    if (!a.isValid() || !b.isValid()) return ''
    const diff = b.diff(a, 'minute')
    return diff > 0 ? minutesToLabel(diff) : ''
}

function findStoreSegment(flight, carrier, number, depCode) {
    const legs = [flight?.outbound, flight?.inbound].filter(Boolean)
    for (const leg of legs) {
        for (const seg of leg?.segments ?? []) {
            const fn = `${seg.airline_code ?? seg.carrier ?? ''}${seg.flight_number ?? seg.number ?? ''}`
            const target = `${carrier ?? ''}${number ?? ''}`
            if (fn && target && fn.replace(/\s/g, '') === target.replace(/\s/g, '')) return seg
            if ((seg.departure_code ?? seg.origin) === depCode) return seg
        }
    }
    return null
}

function parseSegments(reservation, flight, airportMap, airportHints) {
    const offer = Array.isArray(reservation?.Offer) ? reservation.Offer[0] : reservation?.Offer
    const products = offer?.Product ?? []
    const productList = Array.isArray(products) ? products : [products]
    const segments = []

    for (const product of productList) {
        const rawSegs = product?.FlightSegment ?? []
        const segList = Array.isArray(rawSegs) ? rawSegs : [rawSegs]
        const pf = product?.PassengerFlight?.[0]?.FlightProduct?.[0] ?? {}

        for (const fs of segList) {
            const f = fs?.Flight ?? {}
            const dep = f?.Departure ?? {}
            const arr = f?.Arrival ?? {}
            const carrier = f?.carrier ?? ''
            const number = f?.number ?? ''
            const storeSeg = findStoreSegment(flight, carrier, number, dep.location)
            const depAp = resolveAirport(dep.location, airportMap, airportHints[dep.location] ?? {
                city: storeSeg?.Origin_City_Name,
                airport: storeSeg?.Origin_Airport_Name,
            })
            const arrAp = resolveAirport(arr.location, airportMap, airportHints[arr.location] ?? {
                city: storeSeg?.Destination_City_Name,
                airport: storeSeg?.Destination_Airport_Name,
            })

            segments.push({
                airline_name: storeSeg?.airline_name ?? carrier,
                flight_number: `${carrier}${number}`,
                equipment: f?.equipment ?? storeSeg?.equipment ?? storeSeg?.aircraft_name ?? '—',
                cabin_class: pf?.cabin ?? storeSeg?.cabin_class ?? 'Economy',
                booking_code: pf?.classOfService ?? storeSeg?.booking_code ?? storeSeg?.class_of_service ?? '',
                departure_code: depAp.code,
                departure_city: depAp.city?.toUpperCase?.() ? depAp.city.toUpperCase() : depAp.city,
                departure_airport: depAp.airport,
                departure_time: formatTime(dep.date, dep.time) || storeSeg?.departure_time || '—',
                departure_date: dep.date ?? storeSeg?.departure_date ?? '',
                departure_time_raw: dep.time,
                arrival_code: arrAp.code,
                arrival_city: arrAp.city?.toUpperCase?.() ? arrAp.city.toUpperCase() : arrAp.city,
                arrival_airport: arrAp.airport,
                arrival_time: formatTime(arr.date, arr.time) || storeSeg?.arrival_time || '—',
                arrival_date: arr.date ?? storeSeg?.arrival_date ?? '',
                arrival_time_raw: arr.time,
                origin_terminal: storeSeg?.originTerminal ?? dep.terminal ?? '—',
                destination_terminal: storeSeg?.destinationTerminal ?? arr.terminal ?? '—',
                baggage: storeSeg?.baggage ?? '—',
                duration: formatDuration(f.duration) || storeSeg?.flightTime1 || storeSeg?.duration || '—',
                duration_iso: f.duration,
                datetime_departure: formatSegmentDateTime(dep.date, dep.time, storeSeg?.departure_date, storeSeg?.departure_time),
                datetime_arrival: formatSegmentDateTime(arr.date, arr.time, storeSeg?.arrival_date, storeSeg?.arrival_time),
            })
        }
    }

    for (let i = 0; i < segments.length - 1; i++) {
        const cur = segments[i]
        const next = segments[i + 1]
        cur.layover_time = computeLayover(
            cur.arrival_date,
            cur.arrival_time_raw,
            next.departure_date,
            next.departure_time_raw,
        )
        if (!cur.layover_time && flight?.outbound?.segments?.[i]?.layover_time) {
            cur.layover_time = flight.outbound.segments[i].layover_time
        }
        cur.lastitem = false
    }
    if (segments.length) segments[segments.length - 1].lastitem = true

    return segments
}

function parseTravelers(reservation, travelerForms = [], snapshotTravelers = []) {
    const raw = reservation?.Traveler ?? []
    const list = Array.isArray(raw) ? raw : [raw]

    return list.map((t, i) => {
        const snap = snapshotTravelers[i] ?? {}
        const form = travelerForms[i] ?? {}
        const given = t?.PersonName?.Given ?? form.firstName ?? ''
        const surname = t?.PersonName?.Surname ?? form.lastName ?? ''
        const title = form.title ?? snap.title ?? ''
        const name = snap.name || [title, given, surname].filter(Boolean).join(' ').trim() || '—'
        const phone = t?.Telephone?.[0]?.phoneNumber ?? form.phone ?? snap.phone ?? ''
        const email = t?.Email?.[0]?.value ?? form.email ?? snap.email ?? ''
        let contact = '—'
        if (phone && email) contact = `${email} / ${phone}`
        else if (phone) contact = phone
        else if (email) contact = email

        const doc = t?.TravelDocument?.[0] ?? {}
        return {
            name,
            gender: t?.gender ?? form.gender ?? snap.gender ?? '—',
            dob: formatDob(t?.birthDate ?? form.dob ?? snap.dob),
            passport: doc?.docNumber ?? form.passportNo ?? snap.passport_no ?? '—',
            contact,
        }
    })
}

function resolveRefundStatus(flight, priceData, snapshot) {
    const type = flight?.outbound?.refund_type
        ?? snapshot?.selection?.refund_type
        ?? priceData?.restrictions?.[0]?.type
    if (type && REFUND_LABELS[type]) return REFUND_LABELS[type]
    const penalties = priceData?.penalties
    if (penalties?.cancel?.amount === 0 || penalties?.cancel?.applies_to === 'none') return 'Refundable'
    if (penalties?.cancel) return 'Partially Refundable'
    return 'Refundable'
}

function resolveFare(offer, priceData, snapshot) {
    const price = offer?.Price ?? {}
    const snapPrice = snapshot?.price ?? {}
    const pd = priceData ?? {}
    const currency = price?.CurrencyCode?.value ?? snapPrice.currency ?? pd.currency ?? 'BDT'
    const grossFare = price.Base ?? snapPrice.base_fare ?? pd.base_fare ?? 0
    const tax = price.TotalTaxes ?? snapPrice.total_taxes ?? pd.total_taxes ?? 0
    const ait = snapPrice.ait ?? pd.ait ?? 0
    const serviceCharge = snapPrice.service_charge ?? pd.service_charge ?? pd.total_fees ?? price.TotalFees ?? 0
    const discount = snapPrice.discount ?? pd.discount ?? 0
    const totalPayable = price.TotalPrice ?? snapPrice.total_price ?? pd.total_price ?? (grossFare + tax + ait + serviceCharge - discount)

    return { currency, grossFare, tax, ait, serviceCharge, discount, totalPayable }
}

function resolvePaymentDeadline(offer) {
    const terms = offer?.TermsAndConditionsFull ?? []
    const list = Array.isArray(terms) ? terms : [terms]
    for (const t of list) {
        if (t?.PaymentTimeLimit) return dayjs(t.PaymentTimeLimit).format('DD-MMM-YYYY hh:mm A')
        if (t?.ExpiryDate) return dayjs(t.ExpiryDate).format('DD-MMM-YYYY hh:mm A')
    }
    return '—'
}

function resolveRouteLabel(form, flight, segments) {
    const from = form?.from ?? flight?.outbound?.origin ?? segments[0]?.departure_city ?? '—'
    const to = form?.to ?? flight?.outbound?.destination ?? segments[segments.length - 1]?.arrival_city ?? '—'
    const way = Number(form?.Way) === 2 ? 'Round Trip' : 'One Way'
    return { from, to, label: `${from} - ${to} (${way})`, way }
}

function totalFlightMinutes(segments) {
    let total = 0
    for (const s of segments) total += parseIsoMinutes(s.duration_iso)
    return total || 0
}

export async function buildReceiptFromAttemptDetail({ attempt, snapshot, commitResponse }) {
    if (!commitResponse?.ReservationResponse) {
        throw new Error('Booking receipt is not available for this record.')
    }

    const search = snapshot?.search ?? null

    return buildReceiptFromCommit({
        travelportResponse: commitResponse,
        snapshot,
        priceData: snapshot?.price ?? null,
        flight: attempt?.selection_json ?? snapshot?.selection ?? null,
        form: search
            ? { from: search.from, to: search.to, Way: search.way }
            : null,
        travelerForms: [],
        bookingAttemptId: attempt?.attempt_ref ?? snapshot?.attempt_id ?? null,
    })
}

export async function buildReceiptFromCommit({
    travelportResponse,
    snapshot = null,
    priceData = null,
    flight = null,
    form = null,
    travelerForms = [],
    bookingAttemptId = null,
}) {
    const reservation = travelportResponse?.ReservationResponse?.Reservation ?? {}
    const offer = Array.isArray(reservation?.Offer) ? reservation.Offer[0] : reservation?.Offer
    const { gdsPnr, airlinePnr } = extractLocators(reservation)

    const [airportMap, activeCompany] = await Promise.all([
        loadAirportMap(),
        loadActiveCompany(),
    ])
    const airportHints = mergeAirportHintsFromFlight(flight)
    const segments = parseSegments(reservation, flight, airportMap, airportHints)
    const passengers = parseTravelers(
        reservation,
        travelerForms,
        snapshot?.travelers ?? [],
    )
    const fare = resolveFare(offer, priceData, snapshot)
    const route = resolveRouteLabel(form, flight, segments)
    const totalMins = totalFlightMinutes(segments)
    const totalFlightTime = totalMins ? minutesToLabel(totalMins).replace('hr', 'hr ').replace(' min', 'min') : '—'

    return {
        bookingId: formatBookingCode(bookingAttemptId),
        gdsPnr: gdsPnr ?? '—',
        airlinePnr: airlinePnr ?? '—',
        refundStatus: resolveRefundStatus(flight, priceData, snapshot),
        status: 'Booking Confirmed',
        route,
        totalFlightTime,
        segments,
        passengers,
        fare,
        paymentDeadline: resolvePaymentDeadline(offer),
        agency: resolveAgency(activeCompany),
        terms: TERMS,
        ticketNo: '—',
        ticketDate: '—',
        generatedAt: dayjs().format('DD-MMM-YYYY hh:mm A'),
    }
}
