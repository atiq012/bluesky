import { ref } from 'vue'
import axiosInstance from '../axiosInstance'
import { useBookingStore } from '../stores/bookingStore'

export function useTpV2AddTraveler() {
    const isSubmitting = ref(false)
    const error = ref(null)

    const bookingStore = useBookingStore()

    function mapUiPaxType(travelerMeta) {
        if (travelerMeta?.type === 'Child') return 'CNN'
        if (travelerMeta?.type === 'Infant') return 'INF'
        return 'ADT'
    }

    function sanitizeTravelportName(name) {
        return String(name ?? '')
            .trim()
            .replace(/[^a-zA-Z\s\-']/g, '')
            .replace(/\s+/g, ' ')
            .trim()
    }

    function mapTravelerPreferences(travelerForms) {
        return travelerForms.map((f, i) => ({
            sequence: i + 1,
            meal_preference: f.meal || null,
            wheelchair_needed: f.wheelchair === 'Yes',
        }))
    }

    async function syncTravelerPreferences(travelerForms) {
        if (!bookingStore.workbenchIdentifier || !bookingStore.sessionId) {
            throw new Error('Workbench session missing. Please restart booking from search.')
        }
        if (!bookingStore.paxIds?.length) {
            return { updated: 0 }
        }

        try {
            const res = await axiosInstance.post('v2/reservation/pax/sync-preferences', {
                workbench_identifier: bookingStore.workbenchIdentifier,
                session_id: bookingStore.sessionId,
                travelers: mapTravelerPreferences(travelerForms),
            })

            if (!res.data?.status) {
                throw new Error(res.data?.message ?? 'Failed to sync traveler preferences.')
            }

            return res.data
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Failed to sync traveler preferences.'
            throw e
        }
    }

    async function submitTravelers(travelerForms, travelerFiles, travelersMeta) {
        isSubmitting.value = true
        error.value = null

        try {
            if (!bookingStore.workbenchIdentifier || !bookingStore.sessionId) {
                throw new Error('Workbench session missing. Please restart booking from search.')
            }

            if (bookingStore.paxesSubmitted) {
                return {
                    pax_ids: bookingStore.paxIds,
                    travelport_traveler_ids: bookingStore.travelportTravelerIds,
                }
            }

            const travelers = travelerForms.map((f, i) => {
                const firstName = sanitizeTravelportName(f.firstName)
                const lastName = sanitizeTravelportName(f.lastName)
                const middleName = sanitizeTravelportName(f.middleName)
                const title = sanitizeTravelportName(f.title)

                if (!firstName || !lastName) {
                    throw new Error(
                        'Passenger name must use letters only (A–Z). Remove numbers and special characters.'
                    )
                }

                return {
                    pax_type: mapUiPaxType(travelersMeta[i]),
                    sequence: i + 1,
                    is_primary_contact: !!f.isPrimaryContact,
                    title: title || null,
                    first_name: firstName,
                    middle_name: middleName || null,
                    last_name: lastName,
                    dob: f.dob,
                    gender: f.gender,
                    nationality: f.nationality || null,
                    passport_number: f.passportNo || null,
                    passport_expiry_date: f.expiryDate || null,
                    email: f.email || null,
                    phone: f.phone || null,
                    meal_preference: f.meal || null,
                    wheelchair_needed: f.wheelchair === 'Yes',
                    frequent_flyer_number: f.frequentFlyer || null,
                }
            })

            if (travelers.length > 9) {
                throw new Error('Maximum 9 travelers allowed per booking.')
            }

            const totalPax = (bookingStore.form?.ADT ?? 0)
                + (bookingStore.form?.CNN ?? 0)
                + (bookingStore.form?.INF ?? 0)

            if (totalPax > 0 && travelers.length !== totalPax) {
                throw new Error('Traveler count does not match search passengers.')
            }

            const res = await axiosInstance.post('v2/reservation/pax', {
                workbench_identifier: bookingStore.workbenchIdentifier,
                session_id: bookingStore.sessionId,
                travelers,
            })

            if (!res.data?.status) {
                throw new Error(res.data?.message ?? 'Failed to save travelers')
            }

            const paxIds = res.data.pax_ids ?? []

            bookingStore.setPaxes({
                paxIds,
                travelportTravelerIds: res.data.travelport_traveler_ids ?? [],
                travelportTravelerRefIds: res.data.travelport_traveler_ref_ids ?? [],
            })

            bookingStore.setAddTravelerApiResponse({
                status: res.data.status,
                message: res.data.message,
                pax_ids: res.data.pax_ids,
                travelport_traveler_ids: res.data.travelport_traveler_ids,
                travelport_response: res.data.travelport_response ?? null,
            })

            for (let i = 0; i < paxIds.length; i++) {
                const files = travelerFiles[i]
                const passport = files?.passportFiles?.[0]?.file
                const visa = files?.visaFiles?.[0]?.file
                if (!passport && !visa) continue

                const fd = new FormData()
                if (passport) fd.append('passport_image', passport)
                if (visa) fd.append('visa_image', visa)

                await axiosInstance.post(`v2/reservation/pax/${paxIds[i]}/files`, fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                })
            }

            return res.data
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Failed to save travelers'
            throw e
        } finally {
            isSubmitting.value = false
        }
    }

    return { submitTravelers, syncTravelerPreferences, isSubmitting, error, mapUiPaxType }
}
