<script setup>
import AppModal from './AppModal.vue';
import AgencyPricingBreakdown from './AgencyPricingBreakdown.vue';

defineProps({
    isOpen: { type: Boolean, default: false },
    pricing: { type: Object, default: null },
    currency: { type: String, default: 'BDT' },
    title: { type: String, default: 'Payable Price Breakdown' },
    subtitle: { type: String, default: '' },
    grossPayment: { type: Number, default: null },
});

defineEmits(['close']);
</script>

<template>
    <AppModal
        :is-open="isOpen"
        :title="title"
        size="md"
        max-width="480px"
        @close="$emit('close')"
    >
        <div class="apbm-body">
            <p v-if="subtitle" class="apbm-subtitle">{{ subtitle }}</p>
            <AgencyPricingBreakdown
                v-if="pricing"
                :pricing="pricing"
                :currency="currency"
                :gross-payment="grossPayment"
            />
        </div>
        <div class="apbm-footer">
            <button type="button" class="btn btn-secondary btn-sm" @click="$emit('close')">Close</button>
        </div>
    </AppModal>
</template>

<style scoped>
.apbm-body {
    padding: 0 1rem 0.5rem;
}

.apbm-subtitle {
    margin: 0 0 12px;
    font-size: 13px;
    color: var(--bs-secondary-color, #6b7a99);
}

.apbm-footer {
    display: flex;
    justify-content: flex-end;
    padding: 0.75rem 1rem 1rem;
    border-top: 1px solid var(--bs-border-color-translucent, rgba(0, 0, 0, 0.08));
}
</style>
