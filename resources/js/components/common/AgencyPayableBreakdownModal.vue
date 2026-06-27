<script setup>
import { computed } from 'vue';
import AppModal from './AppModal.vue';
import AgencyPricingBreakdown from './AgencyPricingBreakdown.vue';

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    pricing: { type: Object, default: null },
    currency: { type: String, default: 'BDT' },
    title: { type: String, default: 'Payable Price Breakdown' },
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
        max-width="520px"
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

            <div class="apbm-footer">
                <button type="button" class="apbm-btn-close" @click="$emit('close')">
                    <i class="fa-solid fa-xmark me-1"></i>
                    Close
                </button>
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
    background: linear-gradient(135deg, #0f766e 0%, #0891b2 45%, #1565c0 100%);
    color: #fff;
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
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.22);
    font-size: 1.05rem;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
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
    color: #fff;
}

.apbm-subtitle {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 4px 0 0;
    font-size: 12px;
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.92);
    font-weight: 500;
}

.apbm-subtitle-icon {
    font-size: 10px;
    opacity: 0.9;
}

.apbm-close {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    cursor: pointer;
    transition: background 0.15s ease;
}

.apbm-close:hover {
    background: rgba(255, 255, 255, 0.24);
}

.apbm-body {
    padding: 0.55rem 1rem 0.2rem;
    overflow-y: auto;
}

.apbm-footer {
    display: flex;
    justify-content: flex-end;
    padding: 0.55rem 1rem 0.7rem;
    border-top: 1px solid var(--bs-border-color-translucent, rgba(0, 0, 0, 0.08));
    background: var(--bs-tertiary-bg, #f8fafc);
}

.apbm-btn-close {
    display: inline-flex;
    align-items: center;
    padding: 0.42rem 0.95rem;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.apbm-btn-close:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}
</style>

<style>
html[data-bs-theme='dark'] .apbm-footer {
    background: #1e2227;
    border-color: #495057;
}

html[data-bs-theme='dark'] .apbm-btn-close {
    color: #cbd5e1;
    background: #2b3035;
    border-color: #495057;
}

html[data-bs-theme='dark'] .apbm-btn-close:hover {
    background: #343a40;
    border-color: #64748b;
}
</style>
