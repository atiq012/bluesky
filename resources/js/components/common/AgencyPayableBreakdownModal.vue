<script setup>
import { computed } from 'vue';
import AppModal from './AppModal.vue';
import AgencyPricingBreakdown from './AgencyPricingBreakdown.vue';

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    pricing: { type: Object, default: null },
    currency: { type: String, default: 'BDT' },
    title: { type: String, default: 'Payable Fare Breakdown' },
    subtitle: { type: String, default: '' },
    grossPayment: { type: Number, default: null },
});

defineEmits(['close']);

const headerRuleName = computed(() => props.subtitle || props.pricing?.rule_name || '');
</script>

<template>
    <AppModal
        :is-open="isOpen"
        :show-header="false"
        size="md"
        max-width="420px"
        @close="$emit('close')"
    >
        <div class="apbm-shell">
            <div class="apbm-header">
                <div class="apbm-header-main">
                    <div class="apbm-header-icon" aria-hidden="true">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div class="apbm-header-text">
                        <h2 class="apbm-title">{{ title }}</h2>
                        <p v-if="headerRuleName" class="apbm-subtitle">
                            <i class="fa-solid fa-tag apbm-subtitle-icon" aria-hidden="true"></i>
                            {{ headerRuleName }}
                        </p>
                    </div>
                </div>
                <button type="button" class="apbm-close" aria-label="Close" @click="$emit('close')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="apbm-body">
                <AgencyPricingBreakdown
                    v-if="pricing"
                    :pricing="pricing"
                    :currency="currency"
                    :gross-payment="grossPayment"
                    :show-rule-name="false"
                />
            </div>
        </div>
    </AppModal>
</template>

<style scoped>
.apbm-shell {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 2rem);
    overflow: hidden;
}

.apbm-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 1.1rem 1.15rem 1rem;
    /* Same Bluesky tint as FlightPricePanel header */
    background: linear-gradient(90deg, #d2f4f2 0%, #d6eef9 35%, #e2e0f8 70%, #ebe4fc 100%);
    color: #0f172a;
    border-bottom: 1px solid rgba(124, 58, 237, 0.12);
}

.apbm-header-main {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
}

.apbm-header-icon {
    flex-shrink: 0;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(26, 158, 181, 0.14);
    color: #1a9eb5;
    font-size: 1.05rem;
}

.apbm-header-text {
    min-width: 0;
}

.apbm-header-text .apbm-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.3;
    letter-spacing: -0.01em;
    color: #0f172a;
}

.apbm-subtitle {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 4px 0 0;
    font-size: 12px;
    line-height: 1.4;
    color: #64748b;
    font-weight: 500;
}

.apbm-subtitle-icon {
    font-size: 10px;
    opacity: 0.9;
    color: #7c3aed;
}

.apbm-close {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.75);
    color: #1a9eb5;
    cursor: pointer;
    transition: background 0.15s ease;
}

.apbm-close:hover {
    background: rgba(255, 255, 255, 0.95);
}

.apbm-body {
    padding: 0.55rem 1rem 0.85rem;
    overflow-y: auto;
}
</style>

<style>
html[data-bs-theme='dark'] .apbm-header {
    background: linear-gradient(90deg, #1a2f35 0%, #1a2838 35%, #24204a 70%, #2a1f3c 100%);
    border-bottom-color: rgba(124, 58, 237, 0.22);
    color: #e2e8f0;
}

html[data-bs-theme='dark'] .apbm-header-text .apbm-title {
    color: #f1f5f9;
}

html[data-bs-theme='dark'] .apbm-subtitle {
    color: #94a3b8;
}

html[data-bs-theme='dark'] .apbm-subtitle-icon {
    color: #a78bfa;
}

html[data-bs-theme='dark'] .apbm-header-icon {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.12);
    color: #7dd3fc;
}

html[data-bs-theme='dark'] .apbm-close {
    background: rgba(255, 255, 255, 0.1);
    color: #7dd3fc;
}

html[data-bs-theme='dark'] .apbm-close:hover {
    background: rgba(255, 255, 255, 0.18);
}
</style>
