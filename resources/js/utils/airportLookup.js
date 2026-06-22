import axiosInstance from '../axiosInstance'

let airportCache = null
let airportLoadPromise = null

export async function loadAirportMap() {
    if (airportCache) return airportCache
    if (airportLoadPromise) return airportLoadPromise

    airportLoadPromise = axiosInstance.get('airports')
        .then((res) => {
            const rows = Array.isArray(res.data) ? res.data : (res.data?.data ?? [])
            const map = {}
            for (const row of rows) {
                const code = row.code ?? row.Code
                if (!code) continue
                map[code] = {
                    city: row.City_name ?? row.City_Name ?? row.city ?? code,
                    airport: row.Airport_Name ?? row.airport_name ?? code,
                    country: row.Country_name ?? row.country ?? '',
                }
            }
            airportCache = map
            return map
        })
        .catch(() => ({}))
        .finally(() => { airportLoadPromise = null })

    return airportLoadPromise
}

export function resolveAirport(code, airportMap = {}, hints = {}) {
    const iata = String(code ?? '').trim().toUpperCase()
    const fromDb = airportMap[iata]
    return {
        code: iata || '—',
        city: hints.city ?? hints.City_name ?? fromDb?.city ?? iata,
        airport: hints.airport ?? hints.Airport_Name ?? fromDb?.airport ?? iata,
    }
}

export function mergeAirportHintsFromFlight(flight) {
    const hints = {}
    const legs = [flight?.outbound, flight?.inbound].filter(Boolean)
    for (const leg of legs) {
        for (const seg of leg?.segments ?? []) {
            const dep = seg.departure_code ?? seg.origin
            const arr = seg.arrival_code ?? seg.destination
            if (dep) {
                hints[dep] = {
                    city: seg.Origin_City_Name,
                    airport: seg.Origin_Airport_Name,
                }
            }
            if (arr) {
                hints[arr] = {
                    city: seg.Destination_City_Name,
                    airport: seg.Destination_Airport_Name,
                }
            }
        }
    }
    return hints
}
