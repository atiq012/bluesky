<script setup>
import AppModal from '../common/AppModal.vue';

defineProps({
    isOpen: { type: Boolean, default: false },
    title: { type: String, default: '' },
    effectiveDate: { type: String, default: '' },
    intro: { type: String, default: '' },
    sections: { type: Array, default: () => [] },
});

defineEmits(['close']);
</script>

<template>
    <AppModal
        :is-open="isOpen"
        :title="title"
        size="lg"
        max-width="720px"
        align="top"
        @close="$emit('close')"
    >
        <div class="legal-modal-body">
            <p v-if="effectiveDate" class="legal-effective-date">Effective date: {{ effectiveDate }}</p>
            <p v-if="intro" class="legal-intro">{{ intro }}</p>

            <section
                v-for="(section, index) in sections"
                :key="`${section.heading}-${index}`"
                class="legal-section"
            >
                <h6 class="legal-section-title">{{ section.heading }}</h6>
                <p
                    v-for="(paragraph, paragraphIndex) in section.paragraphs"
                    :key="`${index}-${paragraphIndex}`"
                    class="legal-paragraph"
                >
                    {{ paragraph }}
                </p>
            </section>
        </div>

        <div class="legal-modal-footer">
            <button type="button" class="legal-close-btn" @click="$emit('close')">Close</button>
        </div>
    </AppModal>
</template>

<style scoped>
.legal-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 0 1rem 1rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: #334155;
    background: #fff;
}

.legal-effective-date {
    margin: 0 0 0.75rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
}

.legal-intro {
    margin: 0 0 1.25rem;
    font-size: 0.92rem;
    line-height: 1.65;
    color: #475569;
}

.legal-section + .legal-section {
    margin-top: 1.1rem;
    padding-top: 1.1rem;
    border-top: 1px solid #e2e8f0;
}

.legal-section-title {
    margin: 0 0 0.55rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
}

.legal-paragraph {
    margin: 0 0 0.65rem;
    font-size: 0.88rem;
    line-height: 1.65;
    color: #475569;
}

.legal-paragraph:last-child {
    margin-bottom: 0;
}

.legal-modal-footer {
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
    padding: 0.75rem 1rem 1rem;
    border-top: 1px solid #e2e8f0;
    background: #fff;
}

.legal-close-btn {
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0.55rem 1.25rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease;
}

.legal-close-btn:hover {
    background: #1d4ed8;
}
</style>
