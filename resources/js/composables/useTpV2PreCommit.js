import { ref } from 'vue'
import axiosInstance from '../axiosInstance'
import { useBookingStore } from '../stores/bookingStore'

export function useTpV2PreCommit() {
    const isApplyingSsr = ref(false)
    const ssrError = ref(null)

    const bookingStore = useBookingStore()

    function hasSsrToApply(travelerForms, travelersMeta) {
        return travelerForms.some((f, i) => {
            const meal = (f.meal ?? '').trim()
            const wheelchair = f.wheelchair === 'Yes'
            const hasMealSsr = meal === 'Veg'
            return hasMealSsr || wheelchair
        })
    }

    function buildSsrPreviewRows(travelerForms, travelersMeta) {
        return travelerForms.map((f, i) => {
            const meta = travelersMeta[i] ?? {}
            const name = [f.title, f.firstName, f.lastName].filter(Boolean).join(' ').trim() || `Traveler ${i + 1}`
            const meal = (f.meal ?? '').trim()
            const wheelchair = f.wheelchair === 'Yes' ? 'Yes' : (f.wheelchair === 'No' ? 'No' : '—')
            let mealSsr = '—'
            if (meal === 'Veg') mealSsr = 'Vegetarian (SSR)'
            else if (meal === 'Non Veg') mealSsr = 'Standard (no SSR)'
            else if (meal) mealSsr = meal

            return {
                label: `${meta.label ?? 'Traveler'} ${i + 1}`,
                name,
                meal: meal || '—',
                mealSsr,
                wheelchair,
                willApply: meal === 'Veg' || f.wheelchair === 'Yes',
            }
        })
    }

    async function applySsr(travelerForms, travelersMeta) {
        if (!bookingStore.workbenchIdentifier || !bookingStore.sessionId) {
            throw new Error('Workbench session missing. Please restart booking from search.')
        }

        if (bookingStore.ssrSubmitted) {
            return { skipped: bookingStore.ssrSkipped }
        }

        if (!hasSsrToApply(travelerForms, travelersMeta)) {
            bookingStore.setSsrApplied({ skipped: true, response: null })
            return { skipped: true }
        }

        isApplyingSsr.value = true
        ssrError.value = null

        try {
            const res = await axiosInstance.post('v2/reservation/ssr/apply', {
                workbench_identifier: bookingStore.workbenchIdentifier,
                session_id: bookingStore.sessionId,
                travelers: travelerForms.map((f, i) => ({
                    sequence: i + 1,
                    meal_preference: f.meal || null,
                    wheelchair_needed: f.wheelchair === 'Yes',
                })),
            })

            if (!res.data?.status) {
                throw new Error(res.data?.message ?? 'Failed to apply SSR.')
            }

            bookingStore.setSsrApplied({
                skipped: !!res.data.skipped,
                response: res.data.travelport_response ?? null,
            })

            return res.data
        } catch (e) {
            ssrError.value = e?.response?.data?.message ?? e.message ?? 'Failed to apply SSR.'
            throw e
        } finally {
            isApplyingSsr.value = false
        }
    }

    return {
        isApplyingSsr,
        ssrError,
        hasSsrToApply,
        buildSsrPreviewRows,
        applySsr,
    }
}
