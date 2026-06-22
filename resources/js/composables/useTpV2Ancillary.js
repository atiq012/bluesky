import { ref } from 'vue'
import axiosInstance from '../axiosInstance'
import { useBookingStore } from '../stores/bookingStore'

const NO_ANCILLARY_MESSAGE = 'No ancillary options found.'

function applyEmptyAncillaryShop(bookingStore) {
    bookingStore.setAncillaryShopData({
        items: [],
        travelport_response: null,
        no_ancillaries: true,
        message: NO_ANCILLARY_MESSAGE,
    })
}

export function useTpV2Ancillary() {
    const isShoppingAncillaries = ref(false)
    const isBookingAncillary = ref(false)
    const shopError = ref(null)
    const bookError = ref(null)

    const bookingStore = useBookingStore()

    function componentsForCoverage(item, coverage = 'both') {
        const components = Array.isArray(item?.merged_components) && item.merged_components.length > 0
            ? item.merged_components
            : [item]

        if (coverage === 'both') return components

        return components.filter(component => component?.component_direction === coverage)
    }

    function itemOfferingIds(item, coverage = 'both') {
        const components = componentsForCoverage(item, coverage)
        if (components.length > 0) {
            return components
                .map(component => component?.catalog_offering_id)
                .filter(Boolean)
        }

        return [item?.catalog_offering_id].filter(Boolean)
    }

    function isAncillaryBooked(item, coverage = 'both') {
        const ids = itemOfferingIds(item, coverage)
        if (ids.length === 0) return false
        return ids.every(id => bookingStore.bookedAncillaryIds.includes(id))
    }

    async function shopAncillaries() {
        if (!bookingStore.workbenchIdentifier || !bookingStore.sessionId) return

        isShoppingAncillaries.value = true
        shopError.value = null

        try {
            const res = await axiosInstance.post('v2/reservation/ancillary/shop', {
                workbench_identifier: bookingStore.workbenchIdentifier,
                session_id: bookingStore.sessionId,
            })

            const items = res.data.ancillary_items ?? []
            const noAncillaries = res.data.no_ancillaries ?? items.length === 0

            bookingStore.setAncillaryShopData({
                items,
                travelport_response: res.data.travelport_response ?? null,
                no_ancillaries: noAncillaries,
                message: noAncillaries ? NO_ANCILLARY_MESSAGE : (res.data.message ?? null),
            })
        } catch {
            applyEmptyAncillaryShop(bookingStore)
        } finally {
            isShoppingAncillaries.value = false
        }
    }

    async function bookAncillary(ancillaryItem, coverage = 'both') {
        isBookingAncillary.value = true
        bookError.value = null

        const travelerRefIds = bookingStore.travelportTravelerRefIds ?? []
        const components = componentsForCoverage(ancillaryItem, coverage)
        const targetComponents = components.length > 0
            ? components
            : componentsForCoverage(ancillaryItem, 'both')
        const payloadRows = []

        for (const component of targetComponents) {
            const base = {
                catalog_offerings_group_id: component.catalog_offerings_group_id,
                catalog_offerings_identifier_value: component.catalog_offerings_identifier_value,
                catalog_offerings_identifier_authority: component.catalog_offerings_identifier_authority ?? 'Travelport',
                catalog_offering_id: component.catalog_offering_id,
                product_id: component.product_id,
                quantity: 1,
            }

            if (travelerRefIds.length > 0) {
                for (const refId of travelerRefIds.filter(Boolean)) {
                    payloadRows.push({ ...base, traveler_ref_id: refId })
                }
            } else {
                payloadRows.push({ ...base, traveler_ref_id: 'travelerRefId_1' })
            }
        }

        try {
            await axiosInstance.post('v2/reservation/ancillary/book', {
                workbench_identifier: bookingStore.workbenchIdentifier,
                session_id: bookingStore.sessionId,
                ancillaries: payloadRows,
            })

            itemOfferingIds(ancillaryItem, coverage).forEach(id => bookingStore.addBookedAncillaryId(id))
        } catch (e) {
            bookError.value = e?.response?.data?.message ?? 'Failed to add ancillary.'
            throw e
        } finally {
            isBookingAncillary.value = false
        }
    }

    return {
        isShoppingAncillaries,
        isBookingAncillary,
        shopError,
        bookError,
        shopAncillaries,
        bookAncillary,
        isAncillaryBooked,
    }
}
