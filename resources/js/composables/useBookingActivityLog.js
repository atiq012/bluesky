import { ref } from 'vue'
import axiosInstance from '../axiosInstance'

export function useBookingActivityLog() {
    const logs = ref([])
    const loading = ref(false)
    const error = ref(null)

    async function fetchLogs(attemptId) {
        loading.value = true
        error.value = null
        try {
            const res = await axiosInstance.get(`v2/booking-attempts/${attemptId}/activity-log`)
            logs.value = res.data?.data ?? []
        } catch (e) {
            error.value = e.response?.data?.message || 'Failed to load activity log.'
            logs.value = []
        } finally {
            loading.value = false
        }
    }

    return { logs, loading, error, fetchLogs }
}
