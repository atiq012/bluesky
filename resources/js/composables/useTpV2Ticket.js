import { ref } from 'vue'
import axiosInstance from '../axiosInstance'

export function useTpV2Ticket() {
    const ticketLoading = ref(false)
    const ticketError = ref(null)

    async function issueTicket(attemptId) {
        ticketLoading.value = true
        ticketError.value = null

        try {
            const res = await axiosInstance.post(`v2/booking-attempts/${attemptId}/issue-ticket`)
            return res.data
        } catch (e) {
            ticketError.value = e.response?.data?.message || 'Ticketing failed. Please try again.'
            throw e
        } finally {
            ticketLoading.value = false
        }
    }

    return { ticketLoading, ticketError, issueTicket }
}
