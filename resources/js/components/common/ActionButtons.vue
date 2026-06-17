<script setup>
import { computed } from 'vue';
import DropdownMenu from './DropdownMenu.vue';
import ActionIconButton from './ActionIconButton.vue';

const props = defineProps({
    item: { type: Object, required: true },
    showMore: { type: Boolean, default: false },
    showView: { type: Boolean, default: false },
    showEdit: { type: Boolean, default: true },
    showPrint: { type: Boolean, default: false },
    showLogs: { type: Boolean, default: false },
    showDelete: { type: Boolean, default: true },
    showStatusModal: { type: Boolean, default: false },
    showStatusChange: { type: Boolean, default: false },
    showReverse: { type: Boolean, default: false },
    showComing: { type: Boolean, default: false },
    showFundRequest: { type: Boolean, default: false },
    fundRequestDisabled: { type: Boolean, default: false },
    showPlaceOrder: { type: Boolean, default: false },
    placeOrderDisabled: { type: Boolean, default: false },
    showReceive: { type: Boolean, default: false },
    receiveDisabled: { type: Boolean, default: false },
    receiveLabel: { type: String, default: '' },
    showFundProcessing: { type: Boolean, default: false },
    showFundsReady: { type: Boolean, default: false },
    showGiveAdvance: { type: Boolean, default: false },
    showAuthorize: { type: Boolean, default: false },
    showPaymentHistory: { type: Boolean, default: false },
    showReturn: { type: Boolean, default: false },
    returnLabel: { type: String, default: '' },
    showIssueTicket: { type: Boolean, default: false },
    issueTicketDisabled: { type: Boolean, default: false },
    showDownloadRequest: { type: Boolean, default: false },
    showDownloadResponse: { type: Boolean, default: false },
    downloadRequestLabel: { type: String, default: 'Request' },
    downloadResponseLabel: { type: String, default: 'Response' },
    moreLabel: { type: String, default: '' },
    viewLabel: { type: String, default: '' },
    editLabel: { type: String, default: '' },
    printLabel: { type: String, default: '' },
    logsLabel: { type: String, default: '' },
    deleteLabel: { type: String, default: '' },
    statusModalLabel: { type: String, default: '' },
    statusChangeLabel: { type: String, default: 'Status' },
    reverseLabel: { type: String, default: '' },
    comingLabel: { type: String, default: '' },
    fundRequestLabel: { type: String, default: '' },
    placeOrderLabel: { type: String, default: '' },
    fundProcessingLabel: { type: String, default: '' },
    fundsReadyLabel: { type: String, default: '' },
    giveAdvanceLabel: { type: String, default: '' },
    authorizeLabel: { type: String, default: '' },
    statusChangeOptions: {
        type: Array,
        default: () => [
            { key: '1', label: 'Active', icon: 'active' },
            { key: '0', label: 'Inactive', icon: 'inactive' },
        ],
    },
    loadingItemId: { type: [Number, String], default: null },
    loadingAction: { type: String, default: null },
    printDropdownOptions: { type: Array, default: () => null },
});

const emit = defineEmits([
    'more', 'view', 'edit', 'delete', 'print', 'logs', 'showStatusModal', 'statusChange', 'reverse', 'coming',
    'fund-request', 'place-order', 'receive', 'fund-processing', 'funds-ready', 'give-advance', 'authorize',
    'payment-history', 'return', 'download-request', 'download-response', 'issue-ticket',
]);

const itemId = computed(() => (props.item?.id != null ? String(props.item.id) : ''));
const isLoad = (action) =>
    props.loadingItemId != null
    && props.loadingAction === action
    && itemId.value === String(props.loadingItemId);

const hasPrintDropdown = computed(() => Array.isArray(props.printDropdownOptions) && props.printDropdownOptions.length > 0);

const filteredStatusChangeOptions = computed(() => {
    if (!Array.isArray(props.statusChangeOptions) || !props.statusChangeOptions.length) return [];
    const statusId = props.item?.status_s_id;
    if (statusId === 1 || statusId === '1') {
        return props.statusChangeOptions.filter((opt) => String(opt.key) !== '1');
    }
    if (statusId === 0 || statusId === '0') {
        return props.statusChangeOptions.filter((opt) => String(opt.key) !== '0');
    }
    return props.statusChangeOptions;
});

const hasStatusChangeOptions = computed(() => filteredStatusChangeOptions.value.length > 0);

const simpleActions = computed(() => {
    const defs = [
        { key: 'more', show: props.showMore, label: props.moreLabel || 'More Actions', icon: 'fa-solid fa-ellipsis-vertical', btnClass: 'action-btn-more' },
        { key: 'view', show: props.showView, label: props.viewLabel || 'View', icon: 'fa-solid fa-fire-flame-curved', btnClass: 'action-btn-view' },
        { key: 'edit', show: props.showEdit, label: props.editLabel || 'Edit', icon: 'fa-solid fa-pencil', btnClass: 'action-btn-edit' },
        { key: 'delete', show: props.showDelete, label: props.deleteLabel || 'Delete', icon: 'fa-solid fa-trash', btnClass: 'action-btn-delete' },
        { key: 'logs', show: props.showLogs, label: props.logsLabel || 'Logs', icon: 'fa-solid fa-file-lines', btnClass: 'action-btn-logs' },
        { key: 'statusModal', emit: 'showStatusModal', show: props.showStatusModal, label: props.statusModalLabel || 'Status', icon: 'fa-solid fa-circle-check', btnClass: 'action-btn-status-modal' },
        { key: 'reverse', show: props.showReverse, label: props.reverseLabel || 'Reverse', icon: 'fa-solid fa-rotate-left', btnClass: 'action-btn-reverse' },
        { key: 'coming', show: props.showComing, label: props.comingLabel || 'Coming Soon', icon: 'fa-solid fa-clock', btnClass: 'action-btn-coming', disabled: true },
        { key: 'fund-request', show: props.showFundRequest, label: props.fundRequestLabel || 'Fund Request', icon: 'fa-solid fa-coins', btnClass: 'action-btn-fund-request', disabled: props.fundRequestDisabled },
        { key: 'place-order', show: props.showPlaceOrder, label: props.placeOrderLabel || 'Place Order', icon: 'fa-solid fa-cart-shopping', btnClass: 'action-btn-place-order', disabled: props.placeOrderDisabled },
        { key: 'receive', show: props.showReceive, label: props.receiveLabel || 'Order Receive', icon: 'fa-solid fa-box-open', btnClass: 'action-btn-receive', disabled: props.receiveDisabled },
        { key: 'payment-history', show: props.showPaymentHistory, label: 'Payment History', icon: 'fa-solid fa-money-check-dollar', btnClass: 'action-btn-payment-history' },
        { key: 'fund-processing', show: props.showFundProcessing, label: props.fundProcessingLabel || 'Fund Processing', icon: 'fa-solid fa-arrow-right', btnClass: 'action-btn-fund-processing' },
        { key: 'funds-ready', show: props.showFundsReady, label: props.fundsReadyLabel || 'Funds Ready', icon: 'fa-solid fa-check', btnClass: 'action-btn-funds-ready' },
        { key: 'give-advance', show: props.showGiveAdvance, label: props.giveAdvanceLabel || 'Give Advance', icon: 'fa-solid fa-hand-holding-dollar', btnClass: 'action-btn-give-advance' },
        { key: 'authorize', show: props.showAuthorize, label: props.authorizeLabel || 'Authorize', icon: 'fa-solid fa-bolt', btnClass: 'action-btn-authorize' },
        { key: 'return', show: props.showReturn, label: props.returnLabel || 'Return', icon: 'fa-solid fa-arrow-left', btnClass: 'action-btn-return' },
        { key: 'download-request', show: props.showDownloadRequest, label: props.downloadRequestLabel, icon: 'fa-solid fa-file-arrow-up', btnClass: 'action-btn-download-request' },
        { key: 'download-response', show: props.showDownloadResponse, label: props.downloadResponseLabel, icon: 'fa-solid fa-file-arrow-down', btnClass: 'action-btn-download-response' },
        { key: 'issue-ticket', show: props.showIssueTicket, label: 'Issue Ticket', icon: 'fa-solid fa-ticket', btnClass: 'action-btn-issue-ticket', disabled: props.issueTicketDisabled },
    ];
    return defs.filter((action) => action.show);
});

function emitAction(action) {
    emit(action.emit || action.key, props.item);
}
</script>

<template>
    <div class="action-buttons-grid">
        <ActionIconButton
            v-for="action in simpleActions"
            :key="action.key"
            :loading="isLoad(action.key)"
            :disabled="action.disabled"
            :icon="action.icon"
            :btn-class="action.btnClass"
            :tooltip="action.label"
            @click="emitAction(action)"
        />

        <div v-if="showPrint" class="action-buttons-cell">
            <DropdownMenu
                v-if="hasPrintDropdown"
                :options="printDropdownOptions"
                placement="bottom-start"
                @select="(key) => $emit('print', item, key)"
            >
                <template #trigger>
                    <ActionIconButton
                        :loading="isLoad('print')"
                        icon="fa-solid fa-print"
                        btn-class="action-btn-print"
                        :tooltip="printLabel || 'Print'"
                    />
                </template>
            </DropdownMenu>
            <ActionIconButton
                v-else
                :loading="isLoad('print')"
                icon="fa-solid fa-print"
                btn-class="action-btn-print"
                :tooltip="printLabel || 'Print'"
                @click="$emit('print', item)"
            />
        </div>

        <div v-if="showStatusChange && hasStatusChangeOptions" class="action-buttons-cell">
            <DropdownMenu
                :options="filteredStatusChangeOptions"
                placement="bottom-start"
                @select="(key) => $emit('statusChange', item, key)"
            >
                <template #trigger>
                    <ActionIconButton
                        :loading="isLoad('statusChange')"
                        icon="fa-solid fa-arrows-up-down"
                        btn-class="action-btn-status-change"
                        :tooltip="statusChangeLabel || 'Status'"
                    />
                </template>
            </DropdownMenu>
        </div>
    </div>
</template>

<style scoped>
.action-buttons-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.35rem;
    justify-items: center;
    width: max-content;
    min-width: 7rem;
}

.action-buttons-cell {
    display: contents;
}

:deep(.action-btn-more) { --action-btn-color: #19c4db; --action-btn-bg: #e8f9fc; }
:deep(.action-btn-view) { --action-btn-color: #0d9488; --action-btn-bg: #e6f5f3; }
:deep(.action-btn-edit) { --action-btn-color: #e85d8a; --action-btn-bg: #fdeef4; }
:deep(.action-btn-delete) { --action-btn-color: #dc2626; --action-btn-bg: #fdecec; }
:deep(.action-btn-logs) { --action-btn-color: #1ba3f0; --action-btn-bg: #e8f4fe; }
:deep(.action-btn-status-modal) { --action-btn-color: #eab308; --action-btn-bg: #fef9c3; }
:deep(.action-btn-status-change) { --action-btn-color: #059669; --action-btn-bg: #e6f7f0; }
:deep(.action-btn-reverse) { --action-btn-color: #e11d48; --action-btn-bg: #fde8ef; }
:deep(.action-btn-coming) { --action-btn-color: #64748b; --action-btn-bg: #f1f5f9; opacity: 0.65; }
:deep(.action-btn-fund-request) { --action-btn-color: #f59e0b; --action-btn-bg: #fef5e6; }
:deep(.action-btn-place-order) { --action-btn-color: #4f46e5; --action-btn-bg: #eeeeff; }
:deep(.action-btn-receive) { --action-btn-color: #0f766e; --action-btn-bg: #e6f3f1; }
:deep(.action-btn-payment-history) { --action-btn-color: #7239ea; --action-btn-bg: #f1ebfd; }
:deep(.action-btn-fund-processing) { --action-btn-color: #2563eb; --action-btn-bg: #eaf1fe; }
:deep(.action-btn-funds-ready) { --action-btn-color: #10b981; --action-btn-bg: #e7f9f3; }
:deep(.action-btn-give-advance) { --action-btn-color: #8b5cf6; --action-btn-bg: #f0ebfe; }
:deep(.action-btn-authorize) { --action-btn-color: #16a34a; --action-btn-bg: #e9f7ed; }
:deep(.action-btn-return) { --action-btn-color: #ea580c; --action-btn-bg: #fef0e8; }
:deep(.action-btn-print) { --action-btn-color: #f1892a; --action-btn-bg: #fef3e8; }
:deep(.action-btn-download-request) { --action-btn-color: #6366f1; --action-btn-bg: #eef2ff; }
:deep(.action-btn-download-response) { --action-btn-color: #027de2; --action-btn-bg: #e8f4fd; }
:deep(.action-btn-issue-ticket) { --action-btn-color: #7c3aed; --action-btn-bg: #ede9fe; }
</style>
