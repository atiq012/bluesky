import axiosInstance from '../axiosInstance'

export async function completeSearchAttempt(attemptId) {
    if (!attemptId) return

    try {
        await axiosInstance.post(`v2/booking-attempts/${attemptId}/complete-on-search`)
    } catch (error) {
        console.warn('complete on search failed', error)
    }
}

export async function completePriceAttempt(attemptId) {
    if (!attemptId) return

    try {
        await axiosInstance.post(`v2/booking-attempts/${attemptId}/complete-on-price`)
    } catch (error) {
        console.warn('complete on price failed', error)
    }
}
