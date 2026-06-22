import { ref } from 'vue'
import axiosInstance from '../axiosInstance'

export function useTpV2Cancel() {
    const cancelLoading = ref(false)
    const cancelError = ref(null)

    async function cancelBooking(attemptId) {
        cancelLoading.value = true
        cancelError.value = null

        try {
            const res = await axiosInstance.post(`v2/booking-attempts/${attemptId}/cancel`)
            return res.data
        } catch (e) {
            cancelError.value = e.response?.data?.message || 'Cancellation failed. Please try again.'
            throw e
        } finally {
            cancelLoading.value = false
        }
    }

    return { cancelLoading, cancelError, cancelBooking }
}
