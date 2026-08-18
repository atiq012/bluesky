<script setup>
import { ref, computed } from 'vue'
import BookingReceiptDoc from './BookingReceiptDoc.vue'

const props = defineProps({
    visible: { type: Boolean, default: false },
    receipt: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const docRef = ref(null)
const hasReceipt = computed(() => !!props.receipt)
const downloading = computed(() => docRef.value?.downloading ?? false)

function handleClose() {
    emit('close')
}

function handlePrint() {
    docRef.value?.print()
}

function handleDownload() {
    docRef.value?.download()
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="visible && hasReceipt"
            class="receipt-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="receipt-modal-title"
        >
            <div class="receipt-modal__toolbar no-print">
                <button type="button" class="receipt-modal__close" @click="handleClose">
                    <i class="fa-solid fa-xmark" aria-hidden="true" />
                    Close
                </button>
                <div class="receipt-modal__toolbar-actions">
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        :disabled="downloading"
                        @click="handleDownload"
                    >
                        <i class="fa-solid fa-download me-1" aria-hidden="true" />
                        {{ downloading ? 'Downloading…' : 'Download this Receipt' }}
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" @click="handlePrint">
                        <i class="fa-solid fa-print me-1" aria-hidden="true" />
                        Print
                    </button>
                </div>
            </div>

            <BookingReceiptDoc ref="docRef" :receipt="receipt" />
        </div>
    </Teleport>
</template>

<style scoped>
.receipt-modal {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(15, 23, 42, 0.55);
    overflow: auto;
    padding: 1rem;
}

.receipt-modal__toolbar {
    position: sticky;
    top: 0;
    z-index: 2;
    max-width: 210mm;
    margin: 0 auto 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 0.85rem;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.14);
}

.receipt-modal__close {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: none;
    background: #fff;
    color: #dc2626;
    font-weight: 600;
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.receipt-modal__toolbar-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

@media print {
    :global(html),
    :global(body) {
        overflow: visible !important;
        height: auto !important;
    }

    :global(.no-print) {
        display: none !important;
    }

    :global(body *) {
        visibility: hidden;
    }

    :global(.receipt-modal),
    :global(.receipt-modal *) {
        visibility: visible;
    }

    :global(.receipt-modal) {
        position: absolute;
        inset: 0;
        background: #fff !important;
        padding: 0;
        overflow: visible !important;
    }

    :global(.receipt-modal__body),
    :global(.voucher-doc),
    :global(.receipt-doc),
    :global(.table-responsive) {
        max-width: none;
        margin: 0;
        overflow: visible !important;
        max-height: none !important;
    }
}
</style>
