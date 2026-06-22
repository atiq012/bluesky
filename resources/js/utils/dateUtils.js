import dayjs from 'dayjs'

/**
 * Format date string to "03-Jun-2026"
 * @param {string} str - date string (ISO or any dayjs-parseable)
 * @returns {string}
 */
export function formatDate(str) {
    if (!str) return ''
    const d = dayjs(str)
    return d.isValid() ? d.format('DD-MMM-YYYY') : str
}

/**
 * Format datetime string to "01-Jan-2025 12:45 PM"
 * @param {string} str
 * @returns {string}
 */
export function formatDateTime(str) {
    if (!str) return ''
    const d = dayjs(str)
    return d.isValid() ? d.format('DD-MMM-YYYY hh:mm A') : str
}

/**
 * Format time only "HH:mm" → "02:30 PM"
 * @param {string} date - date part (YYYY-MM-DD)
 * @param {string} time - time part (HH:mm:ss)
 * @returns {string}
 */
export function formatTime(date, time) {
    if (!time) return ''
    const d = dayjs(`${date}T${time}`)
    return d.isValid() ? d.format('hh:mm A') : ''
}

/**
 * Parse ISO duration "PT2H30M" → "2h 30m"
 * @param {string} iso
 * @returns {string}
 */
export function formatDuration(iso) {
    if (!iso) return ''
    const m = iso.match(/PT(?:(\d+)H)?(?:(\d+)M)?/)
    if (!m) return iso
    const h = parseInt(m[1] ?? 0)
    const min = String(parseInt(m[2] ?? 0)).padStart(2, '0')
    return `${h}h ${min}m`
}
