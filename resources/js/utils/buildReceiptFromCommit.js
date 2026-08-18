import dayjs from 'dayjs'
import axiosInstance from '../axiosInstance'
import { formatDuration, formatTime, formatTicketingDeadline } from './dateUtils'
import { loadAirportMap, resolveAirport, mergeAirportHintsFromFlight } from './airportLookup'

export function formatBookingCode(attemptId) {
    const id = Number(attemptId)
    if (!id) return 'BATMP000000000'
    return `BATMP${String(id).padStart(9, '0')}`
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

const DEFAULT_AIRLINE_LOGO = '/uploads/airlines/default.svg'

const VOUCHER_NOTES = []

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

function formatLegHeaderDate(date) {
    if (!date) return ''
    const d = dayjs(`${date}T00:00:00`)
    if (!d.isValid()) return date
    return d.format('DD MMM YYYY, dddd')
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

function formatPhoneDisplay(phone) {
    if (!phone) return ''
    const raw = String(phone).trim()
    if (!raw || raw.startsWith('+') || raw.startsWith('00') || raw.startsWith('0')) return raw
    const digits = raw.replace(/[\s\-()]/g, '')
    // BD mobile without leading 0 → 017XXXXXXXX
    if (/^1\d{9}$/.test(digits)) return `0${digits}`
    return raw
}

function formatBookedOn(value) {
    if (!value) return dayjs().format('DD MMM YYYY | hh:mm A')
    const d = dayjs(value)
    return d.isValid() ? d.format('DD MMM YYYY | hh:mm A') : String(value)
}

function formatGeneratedAt(value) {
    if (!value) return dayjs().format('DD MMMM YYYY | hh:mmA')
    const d = dayjs(value)
    return d.isValid() ? d.format('DD MMMM YYYY | hh:mmA') : String(value)
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
    if (h && m) return `${String(h).padStart(2, '0')} hr ${m} min`
    if (h) return `${String(h).padStart(2, '0')} hr 0 min`
    return `${m} min`
}

function computeLayover(prevArrivalDate, prevArrivalTime, nextDepartureDate, nextDepartureTime) {
    const a = dayjs(`${prevArrivalDate}T${prevArrivalTime}`)
    const b = dayjs(`${nextDepartureDate}T${nextDepartureTime}`)
    if (!a.isValid() || !b.isValid()) return ''
    const diff = b.diff(a, 'minute')
    return diff > 0 ? minutesToLabel(diff) : ''
}

function titleCaseCity(name) {
    const s = String(name ?? '').trim()
    if (!s) return ''
    if (s.length <= 3 && s === s.toUpperCase()) return s
    return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
}

function airlineLogoPath(storeSeg, carrier) {
    if (storeSeg?.logo_path) return storeSeg.logo_path
    const code = String(carrier || storeSeg?.airline_code || storeSeg?.carrier || '').trim().toUpperCase()
    if (code) return `/uploads/airlines/${code}.svg`
    return DEFAULT_AIRLINE_LOGO
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

function baggageBarLabel(leg, storeLeg, priceProduct) {
    const allowance = storeLeg?.baggage_allowance
        ?? priceProduct?.baggage
        ?? []
    const bags = Array.isArray(allowance) ? allowance : []
    const cabin = bags.find((b) => b.type === 'carry_on' || b.type === 'cabin')
    const checked = bags.find((b) => b.type === 'checked' || b.type === 'check_in')
    const cabinPart = cabin
        ? `Cabin-${cabin.quantity ?? 1}pc`
        : null
    let checkPart = null
    if (checked?.weight) {
        const w = String(checked.weight).toUpperCase().includes('KG')
            ? checked.weight
            : `${checked.weight}`
        checkPart = `Check In- ${w} /person`
    } else if (checked?.quantity) {
        checkPart = `Check In- ${checked.quantity}pc /person`
    }
    const parts = [cabinPart, checkPart].filter(Boolean)
    if (parts.length) return parts.join(', ')
    const firstSegBag = leg?.[0]?.baggage
    return firstSegBag && firstSegBag !== '—' ? firstSegBag : '—'
}

function mapRawSegment(fs, pf, flight, airportMap, airportHints) {
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

    return {
        airline_name: storeSeg?.airline_name ?? carrier,
        airline_code: carrier || storeSeg?.airline_code || '',
        logo_path: airlineLogoPath(storeSeg, carrier),
        flight_number: `${carrier}${number}`,
        equipment: f?.equipment ?? storeSeg?.equipment ?? storeSeg?.aircraft_name ?? '—',
        cabin_class: pf?.cabin ?? storeSeg?.cabin_class ?? 'Economy',
        booking_code: pf?.classOfService ?? storeSeg?.booking_code ?? storeSeg?.class_of_service ?? '',
        fare_basis: pf?.fareBasisCode ?? storeSeg?.fare_basis_code ?? '',
        departure_code: depAp.code,
        departure_city: titleCaseCity(depAp.city) || depAp.code,
        departure_airport: depAp.airport,
        departure_time: formatTime(dep.date, dep.time) || storeSeg?.departure_time || '—',
        departure_date: dep.date ?? storeSeg?.departure_date ?? '',
        departure_time_raw: dep.time,
        arrival_code: arrAp.code,
        arrival_city: titleCaseCity(arrAp.city) || arrAp.code,
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
    }
}

function attachLayovers(segments, flightLeg) {
    for (let i = 0; i < segments.length - 1; i++) {
        const cur = segments[i]
        const next = segments[i + 1]
        cur.layover_time = computeLayover(
            cur.arrival_date,
            cur.arrival_time_raw,
            next.departure_date,
            next.departure_time_raw,
        )
        if (!cur.layover_time && flightLeg?.segments?.[i]?.layover_time) {
            cur.layover_time = flightLeg.segments[i].layover_time
        }
        cur.layover_airport = cur.arrival_airport || cur.arrival_city
        cur.lastitem = false
    }
    if (segments.length) segments[segments.length - 1].lastitem = true
    return segments
}

function stopLabel(segments) {
    const n = Math.max((segments?.length ?? 1) - 1, 0)
    if (n === 0) return 'Non-stop'
    return `${n} Stop${n > 1 ? 's' : ''}`
}

function totalDurationLabel(segments, storeLeg) {
    if (storeLeg?.total_flight_time) return storeLeg.total_flight_time
    let total = 0
    for (const s of segments) total += parseIsoMinutes(s.duration_iso)
    return total ? minutesToLabel(total) : (segments[0]?.duration || '—')
}

function buildLegMeta(key, segments, storeLeg, priceProduct) {
    const first = segments[0]
    const last = segments[segments.length - 1]
    const cabin = first?.cabin_class || 'Economy'
    const rbd = first?.booking_code || ''
    const fareBasis = storeLeg?.fareBasisCode
        ?? storeLeg?.fare_basis_code
        ?? priceProduct?.fare_basis_code
        ?? first?.fare_basis
        ?? ''
    const bag = baggageBarLabel(segments, storeLeg, priceProduct)

    return {
        key,
        label: key === 'inbound' ? 'Return Flight' : 'Outbound Flight',
        routeLabel: `${first?.departure_city || first?.departure_code || '—'} → ${last?.arrival_city || last?.arrival_code || '—'}`,
        dateLabel: formatLegHeaderDate(first?.departure_date),
        duration: totalDurationLabel(segments, storeLeg),
        stopLabel: stopLabel(segments),
        cabin,
        bookingCode: rbd,
        fareBasisCode: fareBasis,
        baggageLabel: bag,
        metaBar: [
            `Cabin: ${cabin}${rbd ? `, RBD: ${rbd}` : ''}`,
            fareBasis ? `Fare Basis: ${fareBasis}` : null,
            bag && bag !== '—' ? `Baggage: ${bag}` : null,
        ].filter(Boolean).join(' | '),
        segments,
    }
}

function parseSegmentsFromProducts(reservation, flight, airportMap, airportHints) {
    const offer = Array.isArray(reservation?.Offer) ? reservation.Offer[0] : reservation?.Offer
    const products = offer?.Product ?? []
    const productList = Array.isArray(products) ? products : [products]
    const all = []

    for (const product of productList) {
        const rawSegs = product?.FlightSegment ?? []
        const segList = Array.isArray(rawSegs) ? rawSegs : [rawSegs]
        const pf = product?.PassengerFlight?.[0]?.FlightProduct?.[0] ?? {}
        for (const fs of segList) {
            if (!fs) continue
            all.push(mapRawSegment(fs, pf, flight, airportMap, airportHints))
        }
    }

    return attachLayovers(all, flight?.outbound)
}

function parseLegs(reservation, flight, airportMap, airportHints, priceData) {
    const offer = Array.isArray(reservation?.Offer) ? reservation.Offer[0] : reservation?.Offer
    const products = offer?.Product ?? []
    const productList = Array.isArray(products) ? products : [products]
    const priceProducts = Array.isArray(priceData?.products) ? priceData.products : []

    // Prefer store flight legs so outbound/return split matches search UI
    if (flight?.outbound?.segments?.length || flight?.inbound?.segments?.length) {
        const legs = []
        const outCount = flight?.outbound?.segments?.length ?? 0
        const allFromTp = []

        for (const product of productList) {
            const rawSegs = product?.FlightSegment ?? []
            const segList = Array.isArray(rawSegs) ? rawSegs : [rawSegs]
            const pf = product?.PassengerFlight?.[0]?.FlightProduct?.[0] ?? {}
            for (const fs of segList) {
                if (!fs) continue
                allFromTp.push(mapRawSegment(fs, pf, flight, airportMap, airportHints))
            }
        }

        if (flight?.outbound?.segments?.length) {
            const segs = attachLayovers(
                allFromTp.slice(0, outCount).length
                    ? allFromTp.slice(0, outCount)
                    : allFromTp.filter((_, i) => i < outCount),
                flight.outbound,
            )
            // If TP slice empty, rebuild from store only via TP match already done
            const finalSegs = segs.length ? segs : attachLayovers(
                (flight.outbound.segments ?? []).map((s) => ({
                    airline_name: s.airline_name ?? s.carrier ?? '—',
                    airline_code: s.airline_code ?? s.carrier ?? '',
                    logo_path: airlineLogoPath(s, s.airline_code ?? s.carrier),
                    flight_number: `${s.airline_code ?? s.carrier ?? ''}${s.flight_number ?? s.number ?? ''}`,
                    equipment: s.equipment ?? s.aircraft_name ?? '—',
                    cabin_class: s.cabin_class ?? 'Economy',
                    booking_code: s.booking_code ?? s.class_of_service ?? '',
                    fare_basis: s.fare_basis_code ?? '',
                    departure_code: s.departure_code ?? s.origin ?? '—',
                    departure_city: titleCaseCity(s.Origin_City_Name) || (s.departure_code ?? '—'),
                    departure_airport: s.Origin_Airport_Name ?? '',
                    departure_time: s.departure_time ?? '—',
                    departure_date: s.departure_date ?? '',
                    departure_time_raw: s.departure_time,
                    arrival_code: s.arrival_code ?? s.destination ?? '—',
                    arrival_city: titleCaseCity(s.Destination_City_Name) || (s.arrival_code ?? '—'),
                    arrival_airport: s.Destination_Airport_Name ?? '',
                    arrival_time: s.arrival_time ?? '—',
                    arrival_date: s.arrival_date ?? '',
                    arrival_time_raw: s.arrival_time,
                    origin_terminal: s.originTerminal ?? '—',
                    destination_terminal: s.destinationTerminal ?? '—',
                    baggage: s.baggage ?? '—',
                    duration: s.flightTime1 ?? s.duration ?? '—',
                    duration_iso: '',
                    datetime_departure: s.departure_time ?? '—',
                    datetime_arrival: s.arrival_time ?? '—',
                    layover_time: s.layover_time ?? '',
                })),
                flight.outbound,
            )
            const priceProd = priceProducts.find((p) => String(p.direction || '').toLowerCase() === 'outbound')
                ?? priceProducts[0]
            legs.push(buildLegMeta('outbound', finalSegs, flight.outbound, priceProd))
        }

        if (flight?.inbound?.segments?.length) {
            const inSegs = allFromTp.slice(outCount)
            let finalSegs = attachLayovers(inSegs.length ? inSegs : [], flight.inbound)
            if (!finalSegs.length) {
                // TP product split missed inbound — rebuild from store selection
                finalSegs = attachLayovers(
                    (flight.inbound.segments ?? []).map((s) => ({
                        airline_name: s.airline_name ?? s.carrier ?? '—',
                        airline_code: s.airline_code ?? s.carrier ?? '',
                        logo_path: airlineLogoPath(s, s.airline_code ?? s.carrier),
                        flight_number: `${s.airline_code ?? s.carrier ?? ''}${s.flight_number ?? s.number ?? ''}`,
                        equipment: s.equipment ?? s.aircraft_name ?? '—',
                        cabin_class: s.cabin_class ?? 'Economy',
                        booking_code: s.booking_code ?? s.class_of_service ?? '',
                        fare_basis: s.fare_basis_code ?? '',
                        departure_code: s.departure_code ?? s.origin ?? '—',
                        departure_city: titleCaseCity(s.Origin_City_Name) || (s.departure_code ?? '—'),
                        departure_airport: s.Origin_Airport_Name ?? '',
                        departure_time: s.departure_time ?? '—',
                        departure_date: s.departure_date ?? '',
                        departure_time_raw: s.departure_time,
                        arrival_code: s.arrival_code ?? s.destination ?? '—',
                        arrival_city: titleCaseCity(s.Destination_City_Name) || (s.arrival_code ?? '—'),
                        arrival_airport: s.Destination_Airport_Name ?? '',
                        arrival_time: s.arrival_time ?? '—',
                        arrival_date: s.arrival_date ?? '',
                        arrival_time_raw: s.arrival_time,
                        origin_terminal: s.originTerminal ?? '—',
                        destination_terminal: s.destinationTerminal ?? '—',
                        baggage: s.baggage ?? '—',
                        duration: s.flightTime1 ?? s.duration ?? '—',
                        duration_iso: '',
                        datetime_departure: s.departure_time ?? '—',
                        datetime_arrival: s.arrival_time ?? '—',
                        layover_time: s.layover_time ?? '',
                    })),
                    flight.inbound,
                )
            }
            const priceProd = priceProducts.find((p) => {
                const d = String(p.direction || '').toLowerCase()
                return d === 'inbound' || d === 'return' || d === 'in'
            }) ?? priceProducts[1]
            if (finalSegs.length) {
                legs.push(buildLegMeta('inbound', finalSegs, flight.inbound, priceProd))
            }
        }

        if (legs.length) return legs
    }

    // Fallback: one product = one leg when multiple products, else single outbound
    if (productList.length > 1) {
        return productList.map((product, idx) => {
            const rawSegs = product?.FlightSegment ?? []
            const segList = Array.isArray(rawSegs) ? rawSegs : [rawSegs]
            const pf = product?.PassengerFlight?.[0]?.FlightProduct?.[0] ?? {}
            const segs = attachLayovers(
                segList.filter(Boolean).map((fs) => mapRawSegment(fs, pf, flight, airportMap, airportHints)),
                idx === 0 ? flight?.outbound : flight?.inbound,
            )
            const key = idx === 0 ? 'outbound' : 'inbound'
            return buildLegMeta(key, segs, idx === 0 ? flight?.outbound : flight?.inbound, priceProducts[idx])
        }).filter((leg) => leg.segments.length)
    }

    const flat = parseSegmentsFromProducts(reservation, flight, airportMap, airportHints)
    if (!flat.length) return []
    return [buildLegMeta('outbound', flat, flight?.outbound, priceProducts[0])]
}

function paxTypeListFromForm(form = {}, snapshot = null) {
    const f = form ?? snapshot?.search ?? {}
    const adt = Number(f.ADT ?? f.Adult ?? snapshot?.search?.ADT ?? 1)
    const cnn = Number(f.CNN ?? f.Child ?? snapshot?.search?.CNN ?? 0)
    const kid = Number(f.KID ?? snapshot?.search?.KID ?? 0)
    const inf = Number(f.INF ?? f.Infant ?? snapshot?.search?.INF ?? 0)
    const ins = Number(f.INS ?? snapshot?.search?.INS ?? 0)
    const list = []
    for (let i = 0; i < adt; i++) list.push('ADT')
    for (let i = 0; i < cnn; i++) list.push('CHD')
    for (let i = 0; i < kid; i++) list.push('CHD')
    for (let i = 0; i < inf; i++) list.push('INF')
    for (let i = 0; i < ins; i++) list.push('INS')
    return list
}

function parseTravelers(reservation, travelerForms = [], snapshotTravelers = [], form = null, snapshot = null) {
    const raw = reservation?.Traveler ?? []
    const list = Array.isArray(raw) ? raw : [raw]
    const types = paxTypeListFromForm(form, snapshot)

    return list.map((t, i) => {
        const snap = snapshotTravelers[i] ?? {}
        const formRow = travelerForms[i] ?? {}
        const given = t?.PersonName?.Given ?? formRow.firstName ?? ''
        const surname = t?.PersonName?.Surname ?? formRow.lastName ?? ''
        const title = formRow.title ?? snap.title ?? ''
        const name = snap.name || [title, given, surname].filter(Boolean).join(' ').trim() || '—'
        const phone = t?.Telephone?.[0]?.phoneNumber ?? formRow.phone ?? snap.phone ?? ''
        const email = t?.Email?.[0]?.value ?? formRow.email ?? snap.email ?? ''
        let contact = '—'
        if (phone) contact = formatPhoneDisplay(phone)
        else if (email) contact = email

        const doc = t?.TravelDocument?.[0] ?? {}
        return {
            name,
            type: types[i] ?? 'ADT',
            gender: t?.gender ?? formRow.gender ?? snap.gender ?? '—',
            dob: formatDob(t?.birthDate ?? formRow.dob ?? snap.dob),
            passport: doc?.docNumber ?? formRow.passportNo ?? snap.passport_no ?? '—',
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

function resolveFareBreakdown(priceData, form) {
    const rows = Array.isArray(priceData?.price_breakdown) ? priceData.price_breakdown : []
    if (!rows.length) return []

    const grouped = new Map()
    for (const bd of rows) {
        const type = bd.type || bd.passenger_type_code || 'Adult'
        const key = String(bd.passenger_type_code || type).toUpperCase()
        const qty = Number(bd.quantity ?? 1) || 1
        const existing = grouped.get(key)
        if (existing) {
            existing.qty += qty
            existing.base += Number(bd.base_fare ?? 0) * qty
            existing.tax += Number(bd.total_taxes ?? 0) * qty
            continue
        }
        grouped.set(key, {
            type,
            qty,
            base: Number(bd.base_fare ?? 0) * qty,
            tax: Number(bd.total_taxes ?? 0) * qty,
        })
    }

    return [...grouped.values()].map((row) => {
        const t = String(row.type).toLowerCase()
        let label = row.type
        if (t.startsWith('adu') || t === 'adt') label = `Adult X ${row.qty}`
        else if (t.startsWith('chi') || t === 'cnn' || t === 'chd') label = `Child X ${row.qty}`
        else if (t.startsWith('inf')) label = `Infant X ${row.qty}`
        else label = `${row.type} X ${row.qty}`
        return { ...row, label }
    })
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

    return {
        currency,
        grossFare,
        tax,
        ait,
        serviceCharge,
        discount,
        totalPayable,
        breakdown: resolveFareBreakdown(pd.price_breakdown ? pd : snapPrice, null),
    }
}

function resolvePaymentDeadline(offer) {
    const terms = offer?.TermsAndConditionsFull ?? []
    const list = Array.isArray(terms) ? terms : [terms]
    for (const t of list) {
        const raw = t?.PaymentTimeLimit || t?.ExpiryDate
        if (!raw) continue
        const formatted = formatTicketingDeadline(raw, 'DD MMM YYYY, HH:mm')
        if (formatted) return formatted
    }
    return '—'
}

function resolvePaymentDeadlineLong(offer) {
    const terms = offer?.TermsAndConditionsFull ?? []
    const list = Array.isArray(terms) ? terms : [terms]
    for (const t of list) {
        const raw = t?.PaymentTimeLimit || t?.ExpiryDate
        if (!raw) continue
        const formatted = formatTicketingDeadline(raw, 'DD MMM YYYY, HH:mm')
        // The GMT+6 label is only truthful now that the value is pinned to agency time
        if (formatted) return `${formatted} (GMT+6)`
    }
    return null
}

function resolveRouteLabel(form, flight, legs) {
    const first = legs[0]?.segments?.[0]
    const lastOut = legs[0]?.segments?.[(legs[0]?.segments?.length ?? 1) - 1]
    const from = form?.from ?? flight?.outbound?.origin ?? first?.departure_city ?? '—'
    const to = form?.to ?? flight?.outbound?.destination ?? lastOut?.arrival_city ?? '—'
    const isRound = Number(form?.Way) === 2 || !!flight?.inbound || legs.some((l) => l.key === 'inbound')
    const way = isRound ? 'Round Trip' : 'One Way'
    const wayTitle = isRound ? 'ROUND WAY' : 'ONE WAY'
    return { from, to, label: `${from} - ${to} (${way})`, way, wayTitle }
}

function totalFlightMinutes(legs) {
    let total = 0
    for (const leg of legs) {
        for (const s of leg.segments ?? []) total += parseIsoMinutes(s.duration_iso)
    }
    return total || 0
}

function primaryAirlineName(legs, flight) {
    return legs[0]?.segments?.[0]?.airline_name
        || flight?.outbound?.first_airline_name
        || 'the airline'
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
            ? { from: search.from, to: search.to, Way: search.way, ADT: search.ADT, CNN: search.CNN, KID: search.KID, INF: search.INF, INS: search.INS }
            : null,
        travelerForms: [],
        bookingAttemptId: attempt?.attempt_ref ?? snapshot?.attempt_id ?? null,
        bookedBy: attempt?.created_by ?? null,
        bookedOn: attempt?.created_at ?? attempt?.booked_at ?? null,
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
    bookedBy = null,
    bookedOn = null,
}) {
    const reservation = travelportResponse?.ReservationResponse?.Reservation ?? {}
    const offer = Array.isArray(reservation?.Offer) ? reservation.Offer[0] : reservation?.Offer
    const { gdsPnr, airlinePnr } = extractLocators(reservation)

    const [airportMap, activeCompany] = await Promise.all([
        loadAirportMap(),
        loadActiveCompany(),
    ])
    const airportHints = mergeAirportHintsFromFlight(flight)
    const legs = parseLegs(reservation, flight, airportMap, airportHints, priceData ?? snapshot?.price)
    const segments = legs.flatMap((leg) => leg.segments ?? [])
    const passengers = parseTravelers(
        reservation,
        travelerForms,
        snapshot?.travelers ?? [],
        form,
        snapshot,
    )
    const fare = resolveFare(offer, priceData, snapshot)
    const route = resolveRouteLabel(form, flight, legs)
    const totalMins = totalFlightMinutes(legs)
    const totalFlightTime = totalMins ? minutesToLabel(totalMins) : '—'
    const paymentDeadline = resolvePaymentDeadline(offer)
    const paymentDeadlineLong = resolvePaymentDeadlineLong(offer)
    const agency = resolveAgency(activeCompany)
    const firstPax = passengers[0]?.name && passengers[0].name !== '—' ? passengers[0].name : null

    return {
        bookingId: formatBookingCode(bookingAttemptId),
        gdsPnr: gdsPnr ?? '—',
        airlinePnr: airlinePnr ?? '—',
        refundStatus: resolveRefundStatus(flight, priceData, snapshot),
        status: 'Booking Confirmed',
        bookedOn: formatBookedOn(bookedOn),
        bookedBy: bookedBy || firstPax || '—',
        route,
        totalFlightTime,
        segments,
        legs,
        passengers,
        fare,
        paymentDeadline,
        paymentDeadlineLong,
        penalties: priceData?.penalties ?? snapshot?.price?.penalties ?? null,
        paymentStatus: 'PAYMENT PENDING',
        airlineName: primaryAirlineName(legs, flight),
        agency,
        notes: VOUCHER_NOTES,
        terms: VOUCHER_NOTES,
        ticketNo: '—',
        ticketDate: '—',
        authorizedBy: agency.name || 'Bluesky NDC Travel Ltd.',
        generatedAt: formatGeneratedAt(),
    }
}
