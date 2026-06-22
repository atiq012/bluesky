import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axiosInstance from '../axiosInstance'
import { useBookingStore } from '../stores/bookingStore'
import { useSearchStore } from '../stores/searchStore'
import { buildSelectionJson } from '../utils/bookingSelectionJson'

export function useTpV2Workbench() {
    const isInitiating = ref(false)
    const error = ref(null)

    const router = useRouter()
    const bookingStore = useBookingStore()
    const searchStore = useSearchStore()

    async function initiateAndNavigate({ priceLogId, offerId, priceData, flight, selectedBrand, form }) {
        isInitiating.value = true
        error.value = null

        try {
            const selectionJson = buildSelectionJson({
                flight,
                selectedBrand,
                form,
            })

            const res = await axiosInstance.post('v2/reservation/workbench/initiate', {
                price_log_id: priceLogId ?? null,
                offer_identifier: offerId ?? null,
                selection_json: selectionJson,
            })

            const { workbench_identifier, session_id, booking_attempt_id, travelport_response } = res.data

            bookingStore.setBookingSession({
                workbenchIdentifier: workbench_identifier,
                sessionId: session_id,
                bookingAttemptId: booking_attempt_id,
                offerId,
                priceData,
                flight,
                selectedBrand,
                form,
                workbenchApiResponse: {
                    status: res.data.status,
                    message: res.data.message,
                    workbench_identifier,
                    session_id,
                    travelport_response: travelport_response ?? null,
                },
            })

            const addOfferRes = await axiosInstance.post('v2/reservation/workbench/addoffer', {
                workbench_identifier: workbench_identifier,
                session_id: session_id,
            })

            bookingStore.setAddOfferApiResponse({
                status: addOfferRes.data.status,
                message: addOfferRes.data.message,
                travelport_response: addOfferRes.data.travelport_response ?? null,
            })

            const contentSource = addOfferRes.data.content_source ?? 'GDS'
            bookingStore.setContentSource(contentSource)
            if (selectionJson) {
                selectionJson.content_source = contentSource
            }

            searchStore.activeSearchAttemptId = null
            router.push({ name: 'bookingCreate' })

        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Failed to initiate reservation. Please try again.'
        } finally {
            isInitiating.value = false
        }
    }

    return { isInitiating, error, initiateAndNavigate }
}
