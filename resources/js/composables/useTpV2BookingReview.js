import { ref } from 'vue'
import axiosInstance from '../axiosInstance'
import { useBookingStore } from '../stores/bookingStore'

export function useTpV2BookingReview() {
    const isLoading = ref(false)
    const isConfirming = ref(false)
    const error = ref(null)
    const snapshot = ref(null)

    const bookingStore = useBookingStore()

    async function prepareReview() {
        if (!bookingStore.bookingAttemptId) {
            throw new Error('Booking attempt missing. Restart from search.')
        }

        isLoading.value = true
        error.value = null

        try {
            const res = await axiosInstance.post(
                `v2/booking-attempts/${bookingStore.bookingAttemptId}/prepare-review`
            )
            snapshot.value = res.data?.data?.snapshot ?? res.data?.snapshot ?? null
            bookingStore.setReviewSnapshot(snapshot.value)
            return snapshot.value
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Failed to prepare review.'
            throw e
        } finally {
            isLoading.value = false
        }
    }

    async function loadSummary() {
        if (!bookingStore.bookingAttemptId) return null

        isLoading.value = true
        error.value = null

        try {
            const res = await axiosInstance.get(
                `v2/booking-attempts/${bookingStore.bookingAttemptId}/summary`
            )
            snapshot.value = res.data?.data?.snapshot ?? res.data?.snapshot ?? null
            bookingStore.setReviewSnapshot(snapshot.value)
            return snapshot.value
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Failed to load summary.'
            throw e
        } finally {
            isLoading.value = false
        }
    }

    function applyCommitPayload(payload) {
        const err = payload.commit_error ?? payload.message ?? null
        const expired = !!payload.workbench_expired
            || (typeof err === 'string' && (
                err.includes('WORKBENCH_EXPIRED:')
                || err.toUpperCase().includes('SESSION IDENTIFIER IS INVALID')
            ))

        bookingStore.setCommitResult({
            pnr: payload.pnr ?? null,
            reservation_identifier: payload.reservation_identifier ?? null,
            reservation_status: payload.reservation_status ?? null,
            commit_pending: !!payload.commit_pending,
            workbench_expired: expired,
            commit_error: err,
            status: payload.status ?? null,
            travelport_response: payload.travelport_response ?? null,
        })
    }

    async function confirmBooking() {
        if (!bookingStore.bookingAttemptId) {
            throw new Error('Booking attempt missing.')
        }

        isConfirming.value = true
        error.value = null

        try {
            const res = await axiosInstance.post(
                `v2/booking-attempts/${bookingStore.bookingAttemptId}/confirm`
            )
            bookingStore.setReviewConfirmed(true)
            const payload = res.data?.data ?? res.data ?? {}
            applyCommitPayload(payload)
            return payload
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Confirmation failed.'
            throw e
        } finally {
            isConfirming.value = false
        }
    }

    async function retryCommit() {
        if (!bookingStore.bookingAttemptId) {
            throw new Error('Booking attempt missing.')
        }

        isConfirming.value = true
        error.value = null

        try {
            const res = await axiosInstance.post(
                `v2/booking-attempts/${bookingStore.bookingAttemptId}/retry-commit`
            )
            const payload = res.data?.data ?? res.data ?? {}
            applyCommitPayload(payload)
            return payload
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Retry commit failed.'
            throw e
        } finally {
            isConfirming.value = false
        }
    }

    return {
        isLoading,
        isConfirming,
        error,
        snapshot,
        prepareReview,
        loadSummary,
        confirmBooking,
        retryCommit,
    }
}
