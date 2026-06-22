import { ref } from 'vue'
import axiosInstance from '../axiosInstance'

export function useTpV2Void() {
    const voidLoading = ref(false)
    const voidError = ref(null)

    async function voidTicket(attemptId, ticketNumbers) {
        voidLoading.value = true
        voidError.value = null

        try {
            const res = await axiosInstance.post(`v2/booking-attempts/${attemptId}/void-ticket`, {
                ticket_numbers: ticketNumbers,
            })
            return res.data
        } catch (e) {
            voidError.value = e.response?.data?.message || 'Ticket void failed. Please try again.'
            throw e
        } finally {
            voidLoading.value = false
        }
    }

    return { voidLoading, voidError, voidTicket }
}
