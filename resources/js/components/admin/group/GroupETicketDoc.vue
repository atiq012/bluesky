<script setup>
import { ref, watch, nextTick } from "vue";
import BookingReceiptDoc from "../booking/BookingReceiptDoc.vue";
import { buildGroupReceipt } from "../../../utils/buildGroupReceipt";
import { useAuthStore } from "../../../stores/authStore";

const props = defineProps({
    group: { type: Object, default: null },
    paxList: { type: Array, default: () => [] },
});

const authStore = useAuthStore();
const bookingDocRef = ref(null);
const receipt = ref(null);
const ticketNumbers = ref([]);
let buildToken = 0;

async function rebuild() {
    if (!props.group || !props.paxList?.length) {
        receipt.value = null;
        ticketNumbers.value = [];
        return;
    }
    const token = ++buildToken;
    const result = await buildGroupReceipt({
        group: props.group,
        paxList: props.paxList,
        bookedBy: authStore.name,
    });
    if (token !== buildToken) return; // a newer build superseded this one
    receipt.value = result.receipt;
    ticketNumbers.value = result.ticketNumbers;
}

watch(() => [props.group, props.paxList], () => {
    rebuild().catch(() => {
        receipt.value = null;
        ticketNumbers.value = [];
    });
}, { immediate: true });

async function print() {
    await rebuild();
    await nextTick();
    await bookingDocRef.value?.print();
}

defineExpose({ print });
</script>

<template>
    <BookingReceiptDoc ref="bookingDocRef" :receipt="receipt" :ticket-numbers="ticketNumbers" />
</template>
