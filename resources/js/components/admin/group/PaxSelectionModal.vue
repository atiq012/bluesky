<script setup>
import { ref, computed, watch,onMounted } from "vue";
import axiosInstance from "../../../axiosInstance";

const props = defineProps({
    visible: { type: Boolean, default: false },
    groupId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'generated']);

const loadingPax = ref(false);
const paxList = ref([]);
const selectedIds = ref(new Set());
const generating = ref(false);
const pnr = ref('');

watch(() => props.visible, (v) => {
    if (v) {
        selectedIds.value = new Set();
        loadPaxList();
    }
});

async function loadPaxList() {
    if (!props.groupId) return;
    loadingPax.value = true;
    try {
        const response = await axiosInstance.get(`group-pax-upload/${props.groupId}`);
        paxList.value = response.data?.data ?? [];

        selectedIds.value = new Set(paxList.value.map((pax) => pax.id));
    } catch (error) {
        Notification.showToast('e', error.response?.data?.message || 'Failed to load PAX list.');
    } finally {
        loadingPax.value = false;
    }
}

function paxName(pax) {
    return pax.ticket_no;
}

function toggleSelected(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}

function isSelected(id) {
    return selectedIds.value.has(id);
}

const selectedCount = computed(() => selectedIds.value.size);

const allSelected = computed(() =>
    paxList.value.length > 0 && selectedIds.value.size === paxList.value.length
);

function toggleSelectAll() {
    selectedIds.value = allSelected.value
        ? new Set()
        : new Set(paxList.value.map((pax) => pax.id));
}

const canGenerate = computed(() => !generating.value && selectedCount.value > 0);

const generateLabel = computed(() => {
    if (generating.value) return 'Generating...';
    if (allSelected.value) return 'Generate E Ticket';
    return `Generate E Ticket (${String(selectedCount.value).padStart(2, '0')})`;
});

function handleClose() {
    if (generating.value) return;
    emit('close');
}

async function handleGenerate() {
    if (!canGenerate.value || !props.groupId) return;
    generating.value = true;
    try {
        const payload = {};
        if (!allSelected.value) {
            payload.pax_ids = [...selectedIds.value];
        }
        const response = await axiosInstance.post(`group-eticket/${props.groupId}`, payload);
        emit('generated', response.data?.data);
    } catch (error) {
        Notification.showToast('e', error.response?.data?.message || 'Failed to generate e-ticket.');
    } finally {
        generating.value = false;
    }
}

onMounted(() => {
    fetchGroupPnr();
});


watch(() => props.groupId, () => {
    fetchGroupPnr();
});
async function fetchGroupPnr() {
    if (!props.groupId) return null;
    try {
        const response = await axiosInstance.get(`group-pnr/${props.groupId}`);

        pnr.value = response.data?.data?.pnr ?? '-';

        return pnr;
    } catch (error) {
        Notification.showToast('e', error.response?.data?.message || 'Failed to fetch group PNR.');
        return null;
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="psm-fade">
            <div v-if="visible" class="psm-overlay" @click.self="handleClose">
                <div class="bp-card">

                    <!-- LEFT PANEL -->
                    <div class="bp-left">
                        <img :src="'/theme/appimages/worlds.png'" class="bp-worlds-bg" aria-hidden="true" />
                        <div class="bp-brand">
                            <img :src="'/theme/appimages/blueskywings.png'" class="bp-logo-img" alt="BlueSky" />
                            <span class="bp-brand-name">BLUESKY</span>
                        </div>

                        <div class="bp-left-body">
                            <div class="bp-issued-badge">
                                <i class="fa-solid fa-ticket"/>
                                Generate e-Ticket
                            </div>

                            <div class="bp-field">
                                <div class="bp-field-label">Group PNR</div>
                                <div class="bp-field-value bp-pnr">{{ pnr }}</div>
                            </div>

                            <div class="bp-field">
                                <div class="bp-field-label">PAX Selected</div>
                                <div class="bp-field-value">{{ selectedCount }} / {{ paxList.length }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- DIVIDER + NOTCHES -->
                    <div class="bp-divider">
                        <div class="bp-notch bp-notch--top" />
                        <div class="bp-notch bp-notch--bottom" />
                    </div>

                    <!-- RIGHT STUB -->
                    <div class="bp-right">
                        <button class="bp-close-x" :disabled="generating" @click="handleClose">
                            <i class="fa-solid fa-xmark" />
                        </button>

                        <div class="psm-stub-row mt-4">
                            <label v-if="paxList.length" class="psm-select-all">
                                <input type="checkbox" class="bp-number-checkbox" :checked="allSelected" @change="toggleSelectAll" />
                                Select All
                            </label>
                            <div class="bp-stub-label">Ticket Numbers</div>
                        </div>

                        <div class="psm-pax-panel">
                            <div v-if="loadingPax" class="psm-pax-loading">
                                <i class="fa-solid fa-spinner fa-spin"></i> Loading PAX...
                            </div>
                            <div v-else-if="!paxList.length" class="psm-pax-empty">
                                No PAX found for this group.
                            </div>
                            <div v-else class="bp-numbers bp-numbers--grid psm-pax-grid">
                                <label v-for="pax in paxList" :key="pax.id" class="bp-number-row">
                                    <input type="checkbox" class="bp-number-checkbox" :checked="isSelected(pax.id)" @change="toggleSelected(pax.id)" />
                                    <span class="bp-number psm-pax-name">{{ paxName(pax) }}</span>
                                </label>
                            </div>
                        </div>

                        <button type="button" class="bp-print-btn psm-generate-btn" :disabled="!canGenerate" @click="handleGenerate">
                            <i v-if="generating" class="fa-solid fa-spinner fa-spin"></i>
                            <i v-else class="fa-solid fa-bolt" />
                            {{ generateLabel }}
                        </button>

                        <button class="bp-close-btn" :disabled="generating" @click="handleClose">Cancel</button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.psm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.72);
    z-index: 1060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

/* ── BOARDING PASS CARD ── */
.bp-card {
    display: flex;
    width: 100%;
    max-width: 700px;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 28px 72px rgba(0, 0, 0, 0.32);
}

/* ── LEFT PANEL ── */
.bp-left {
    flex: 0 0 44%;
    background: linear-gradient(145deg, #1e1b4b 0%, #312e81 55%, #4338ca 100%);
    padding: 1.75rem 1.75rem 1.5rem;
    color: #fff;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}

.bp-worlds-bg {
    position: absolute;
    right: -10%;
    top: 50%;
    transform: translateY(-50%);
    width: 90%;
    height: auto;
    object-fit: contain;
    opacity: 0.08;
    pointer-events: none;
    z-index: 0;
    mix-blend-mode: screen;
    filter: brightness(0) invert(1);
}

.bp-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}

.bp-logo-img {
    height: 25px;
    width: auto;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.bp-brand-name {
    font-size: 0.92rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #fff;
}

.bp-left-body {
    flex: 1;
    padding: 1.75rem 0 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    position: relative;
    z-index: 1;
}

.bp-issued-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 2rem;
    padding: 0.3rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 600;
    width: fit-content;
    color: #c7d2fe;
}

.bp-field-label {
    font-size: 0.63rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #a5b4fc;
    margin-bottom: 0.25rem;
}

.bp-field-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
}

.bp-pnr {
    font-size: 1.95rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

/* ── TEAR DIVIDER ── */
.bp-divider {
    width: 0;
    flex-shrink: 0;
    border-left: 2px dashed rgba(255, 255, 255, 0.22);
    position: relative;
    background: transparent;
}

.bp-notch {
    position: absolute;
    width: 28px;
    height: 28px;
    background: rgba(0, 0, 0, 0.72); /* matches overlay */
    border-radius: 50%;
    left: -14px;
}

.bp-notch--top    { top: -14px; }
.bp-notch--bottom { bottom: -14px; }

/* ── RIGHT STUB ── */
.bp-right {
    flex: 1;
    background: #fff;
    padding: 1.5rem 1.4rem 1.25rem 1.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    position: relative;
    min-width: 0;
}

.bp-close-x {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    width: 28px;
    height: 28px;
    border: none;
    background: #f1f5f9;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 0.85rem;
    transition: background 0.15s;
}

.bp-close-x:hover:not(:disabled) { background: #e2e8f0; }

.bp-close-x:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.bp-stub-label {
    font-size: 0.63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #7c3aed;
    margin-top: 0.4rem;
}

.psm-stub-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.4rem;
}

.psm-stub-row .bp-stub-label {
    margin-top: 0;
}

.psm-select-all {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #7c3aed;
    cursor: pointer;
}

.psm-pax-panel {
    border: 1px solid #ddd6fe;
    border-radius: 0.6rem;
    padding: 0.6rem;
    max-height: 220px;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.psm-pax-grid {
    grid-template-columns: repeat(2, 1fr);
}

.psm-pax-name {
    text-transform: none;
    font-family: inherit;
    letter-spacing: normal;
    font-weight: 600;
}

.psm-pax-loading,
.psm-pax-empty {
    text-align: center;
    color: #94a3b8;
    font-size: 0.82rem;
    padding: 0.75rem 0;
}

/* Numbers grid (reused pattern) */
.bp-numbers {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.bp-numbers--grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
}

.bp-number-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.2rem 0.55rem;
    background: #f5f3ff;
    border-radius: 0.4rem;
    border: 1px solid #ddd6fe;
    min-width: 0;
    cursor: pointer;
}

.bp-number-checkbox {
    flex-shrink: 0;
    width: 0.85rem;
    height: 0.85rem;
    accent-color: #7c3aed;
    cursor: pointer;
}

.bp-number {
    font-size: 0.76rem;
    font-weight: 700;
    color: #312e81;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bp-print-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.5rem;
    border: 1px solid #7c3aed;
    background: #7c3aed;
    color: #fff;
    border-radius: 0.4rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    margin-top: auto;
}

.bp-print-btn:hover:not(:disabled) { background: #6d28d9; }

.bp-print-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.bp-close-btn {
    padding: 0.35rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 0.4rem;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
}

.bp-close-btn:hover:not(:disabled) {
    background: #f5f3ff;
    border-color: #7c3aed;
    color: #7c3aed;
}

.bp-close-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── FADE TRANSITION ── */
.psm-fade-enter-active,
.psm-fade-leave-active { transition: opacity 0.2s ease; }

.psm-fade-enter-from,
.psm-fade-leave-to     { opacity: 0; }

/* ─── Dark mode ───────────────────────────────────── */
[data-bs-theme="dark"] .bp-right {
    background: #1e293b;
}

[data-bs-theme="dark"] .bp-stub-label {
    color: #a78bfa;
}

[data-bs-theme="dark"] .psm-select-all {
    color: #a78bfa;
}

[data-bs-theme="dark"] .psm-pax-panel {
    border-color: #475569;
}

[data-bs-theme="dark"] .bp-number-row {
    background: #312e81;
    border-color: #4c1d95;
}

[data-bs-theme="dark"] .bp-number {
    color: #ddd6fe;
}

[data-bs-theme="dark"] .bp-close-x {
    background: #334155;
    color: #94a3b8;
}

[data-bs-theme="dark"] .bp-close-btn {
    background: #1e293b;
    border-color: #475569;
    color: #cbd5e1;
}
</style>
