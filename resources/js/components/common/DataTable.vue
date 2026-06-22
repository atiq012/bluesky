<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import Vue3Datatable from '@bhplugin/vue3-datatable';
import '@bhplugin/vue3-datatable/dist/style.css';
import { useDatatableColumnsStore } from '../../stores/datatableColumns';
import LoadingSpinner from './LoadingSpinner.vue';
import SearchInput from './SearchInput.vue';
import AppButton from './AppButton.vue';
import AppTooltip from './AppTooltip.vue';
import { filterRowsBySearch } from '../../utils/datatableSearch';

const props = defineProps({
    tableId: { type: String, default: 'datatable' },
    rows: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: 'Search by anything' },
    sortable: { type: Boolean, default: false },
    sortColumn: { type: String, default: '' },
    sortDirection: { type: String, default: 'asc' },
    columnFilter: { type: Boolean, default: false },
    pagination: { type: Boolean, default: true },
    paginationInfo: { type: String, default: 'Showing {0} to {1} of {2} entries' },
    pageSizeOptions: { type: Array, default: () => [5, 10, 20, 50, 100] },
    pageSize: { type: Number, default: 5 },
    showPageSize: { type: Boolean, default: true },
    showNumbersCount: { type: Number, default: 3 },
    striped: { type: Boolean, default: true },
    skin: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    refreshLoading: { type: Boolean, default: false },
    disableInternalSearch: { type: Boolean, default: false },
    showCreateButton: { type: Boolean, default: false },
    createButtonText: { type: String, default: 'Create' },
    createButtonLoading: { type: Boolean, default: false },
    emptyStateText: { type: String, default: 'No data' },
    noMatchText: { type: String, default: 'No matching records' },
});

const emit = defineEmits(['search', 'create', 'refresh']);

function toInitCap(str) {
    if (!str) return str;
    return str.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}

const normalizedColumns = computed(() =>
    props.columns.map((column) => ({
        ...column,
        title: toInitCap(column.title ?? column.label),
    })),
);

const columnOptions = computed(() =>
    normalizedColumns.value
        .map((column) => {
            const field = column.field ?? column.key ?? column.title ?? column.label;
            return {
                field,
                label: column.title ?? column.label ?? column.field ?? column.key,
            };
        })
        .filter((column) => !!column.field),
);

const datatableColumnsStore = useDatatableColumnsStore();
const showColumnMenu = ref(false);
const columnMenuRef = ref(null);
const columnSearchQuery = ref('');
const localSearchQuery = ref('');
const localPageSize = ref(props.pageSize || props.pageSizeOptions[0] || 10);
const visibleFields = ref([]);

const fallbackFields = computed(() => columnOptions.value.map((c) => c.field));

const filteredColumnOptions = computed(() => {
    const query = columnSearchQuery.value.trim().toLowerCase();
    if (!query) return columnOptions.value;
    return columnOptions.value.filter((column) => column.label.toLowerCase().includes(query));
});

const visibleColumns = computed(() =>
    normalizedColumns.value.filter((column) => {
        const field = column.field ?? column.key ?? column.title ?? column.label;
        if (!field) return true;
        return visibleFields.value.includes(field);
    }),
);

const datatableKey = computed(() => `${props.tableId}-${localPageSize.value}-${visibleFields.value.join(',')}`);

const tableSkin = computed(() => {
    if (props.skin) return props.skin;
    return props.striped ? 'bh-table-striped bh-table-bordered' : 'bh-table-bordered';
});

watch(
    () => props.pageSize,
    (value) => {
        if (typeof value === 'number' && value > 0) {
            localPageSize.value = value;
        }
    },
);

const loadVisibleFields = () => {
    const fallback = fallbackFields.value;
    if (!fallback || !fallback.length) return;
    visibleFields.value = [...datatableColumnsStore.initTable(props.tableId, fallback)];
};

function toggleColumnField(field) {
    if (visibleFields.value.includes(field)) {
        visibleFields.value = visibleFields.value.filter((f) => f !== field);
        return;
    }
    visibleFields.value = [...visibleFields.value, field];
}

watch(
    visibleFields,
    (val) => {
        datatableColumnsStore.setVisibleFields(props.tableId, val);
    },
    { deep: true },
);

watch(columnOptions, () => {
    loadVisibleFields();
});

watch(localSearchQuery, (newValue) => {
    emit('search', newValue);
});

const columnBarColors = ['#4361ee', '#e7515a', '#00ab55', '#e2a03f', '#2196f3', '#805dca', '#009688', '#ff5722'];
const getColumnBarColor = (index) => columnBarColors[index % columnBarColors.length];

const useGlobalRowSearch = computed(() => props.searchable && !props.disableInternalSearch);

const displayRows = computed(() => {
    if (!useGlobalRowSearch.value) {
        return props.rows;
    }
    return filterRowsBySearch(props.rows, localSearchQuery.value);
});

const pluginSearchQuery = computed(() => {
    if (useGlobalRowSearch.value) {
        return '';
    }
    return localSearchQuery.value;
});

const hasNoSourceRows = computed(() => !props.loading && props.rows.length === 0);
const hasNoMatchingRows = computed(() => !props.loading && props.rows.length > 0 && displayRows.value.length === 0);

function toggleColumnMenu() {
    showColumnMenu.value = !showColumnMenu.value;
}

function hideAllColumns() {
    visibleFields.value = [];
}

function showAllColumns() {
    visibleFields.value = [...fallbackFields.value];
}

function handleOutsideClick(event) {
    const target = event.target;
    if (!target || !columnMenuRef.value) return;
    if (!columnMenuRef.value.contains(target)) {
        showColumnMenu.value = false;
    }
}

onMounted(() => {
    loadVisibleFields();
    document.addEventListener('click', handleOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
});
</script>

<template>
    <div class="app-data-table min-w-0">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 justify-content-between">
            <div v-if="showPageSize" class="d-none d-sm-flex align-items-center gap-2 small text-muted flex-shrink-0">
                <span>Show</span>
                <select v-model.number="localPageSize" class="form-select form-select-sm app-data-table-page-size">
                    <option v-for="size in pageSizeOptions" :key="size" :value="size">
                        {{ size }}
                    </option>
                </select>
            </div>

            <div
                class="d-flex flex-wrap align-items-center gap-2 flex-fill flex-sm-grow-0 min-w-0 app-data-table-toolbar"
                :class="{ 'app-data-table-toolbar--menu-open': showColumnMenu }"
            >
                <SearchInput
                    v-if="searchable"
                    v-model="localSearchQuery"
                    :placeholder="searchPlaceholder"
                    :input-id="`${tableId}-search`"
                    search-icon-src="/theme/appimages/Search.svg"
                    wrapper-class="app-data-table-search flex-fill flex-sm-grow-0"
                />

                <AppTooltip content="Refresh" placement="top">
                    <button
                        type="button"
                        class="app-data-table-icon-btn app-data-table-icon-btn--primary"
                        :disabled="refreshLoading"
                        @click="$emit('refresh')"
                    >
                        <span v-if="refreshLoading" class="app-data-table-icon-spinner" role="status" aria-hidden="true"></span>
                        <svg
                            v-else
                            class="app-data-table-icon-img"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M15.2404 2.33156C15.1816 2.2684 15.1106 2.21774 15.0317 2.18261C14.9529 2.14747 14.8678 2.12858 14.7814 2.12705C14.6951 2.12553 14.6094 2.14141 14.5293 2.17374C14.4493 2.20607 14.3766 2.2542 14.3155 2.31524C14.2545 2.37629 14.2064 2.449 14.174 2.52905C14.1417 2.6091 14.1258 2.69483 14.1273 2.78115C14.1289 2.86747 14.1478 2.95259 14.1829 3.03145C14.218 3.11031 14.2687 3.18128 14.3318 3.24013L17.5204 6.4287H4.92899C4.75849 6.4287 4.59498 6.49643 4.47442 6.61699C4.35386 6.73755 4.28613 6.90106 4.28613 7.07156C4.28613 7.24206 4.35386 7.40557 4.47442 7.52613C4.59498 7.64669 4.75849 7.71442 4.92899 7.71442H17.5204L14.3318 10.903C14.2687 10.9618 14.218 11.0328 14.1829 11.1117C14.1478 11.1905 14.1289 11.2757 14.1273 11.362C14.1258 11.4483 14.1417 11.534 14.174 11.6141C14.2064 11.6941 14.2545 11.7668 14.3155 11.8279C14.3766 11.8889 14.4493 11.937 14.5293 11.9694C14.6094 12.0017 14.6951 12.0176 14.7814 12.0161C14.8678 12.0145 14.9529 11.9957 15.0317 11.9605C15.1106 11.9254 15.1816 11.8747 15.2404 11.8116L19.5261 7.52585C19.6465 7.40531 19.7141 7.24192 19.7141 7.07156C19.7141 6.9012 19.6465 6.73781 19.5261 6.61727L15.2404 2.33156ZM9.66899 13.0973C9.73215 13.0384 9.78281 12.9674 9.81795 12.8886C9.85308 12.8097 9.87197 12.7246 9.8735 12.6383C9.87502 12.552 9.85914 12.4662 9.82681 12.3862C9.79448 12.3061 9.74635 12.2334 9.68531 12.1724C9.62426 12.1113 9.55155 12.0632 9.4715 12.0309C9.39146 11.9986 9.30572 11.9827 9.2194 11.9842C9.13308 11.9857 9.04796 12.0046 8.9691 12.0397C8.89024 12.0749 8.81927 12.1255 8.76042 12.1887L4.4747 16.4744C4.35432 16.595 4.2867 16.7583 4.2867 16.9287C4.2867 17.0991 4.35432 17.2625 4.4747 17.383L8.76042 21.6687C8.81927 21.7319 8.89024 21.7825 8.9691 21.8177C9.04796 21.8528 9.13308 21.8717 9.2194 21.8732C9.30572 21.8747 9.39146 21.8589 9.4715 21.8265C9.55155 21.7942 9.62426 21.7461 9.68531 21.685C9.74635 21.624 9.79448 21.5513 9.82681 21.4712C9.85914 21.3912 9.87502 21.3054 9.8735 21.2191C9.87197 21.1328 9.85308 21.0477 9.81795 20.9688C9.78281 20.89 9.73215 20.819 9.66899 20.7601L6.48042 17.5716H19.0718C19.2423 17.5716 19.4059 17.5038 19.5264 17.3833C19.647 17.2627 19.7147 17.0992 19.7147 16.9287C19.7147 16.7582 19.647 16.5947 19.5264 16.4741C19.4059 16.3536 19.2423 16.2858 19.0718 16.2858H6.48042L9.66899 13.0973Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                </AppTooltip>

                <div ref="columnMenuRef" class="position-relative">
                    <AppTooltip content="Show / Hide columns" placement="top">
                        <button
                            type="button"
                            class="app-data-table-icon-btn app-data-table-icon-btn--secondary"
                            @click="toggleColumnMenu"
                        >
                            <svg class="app-data-table-icon-img" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 7H14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                <circle cx="17" cy="7" r="2" stroke="currentColor" stroke-width="1.75" />
                                <path d="M4 12H10" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                <circle cx="13" cy="12" r="2" stroke="currentColor" stroke-width="1.75" />
                                <path d="M4 17H16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                <circle cx="19" cy="17" r="2" stroke="currentColor" stroke-width="1.75" />
                            </svg>
                        </button>
                    </AppTooltip>

                    <div
                        v-if="showColumnMenu"
                        class="dropdown-menu show app-data-table-column-menu shadow"
                        @click.stop
                    >
                        <div class="px-3 py-2">
                            <div class="position-relative">
                                <input
                                    v-model="columnSearchQuery"
                                    type="text"
                                    placeholder="Search"
                                    class="form-control form-control-sm pe-4"
                                    autocomplete="off"
                                />
                                <button
                                    v-if="columnSearchQuery"
                                    type="button"
                                    class="btn btn-link btn-sm position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none p-0 me-1"
                                    @click="columnSearchQuery = ''"
                                >
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="app-data-table-column-list-wrap">
                            <div class="app-data-table-column-list">
                                <label
                                    v-for="(column, idx) in filteredColumnOptions"
                                    :key="column.field"
                                    class="app-data-table-column-item"
                                    @click.prevent="toggleColumnField(column.field)"
                                >
                                    <input
                                        class="form-check-input flex-shrink-0"
                                        type="checkbox"
                                        tabindex="-1"
                                        :checked="visibleFields.includes(column.field)"
                                        @click.prevent
                                    />
                                    <span class="app-data-table-column-bar" :style="{ backgroundColor: getColumnBarColor(idx) }" />
                                    <span class="app-data-table-column-label">{{ column.label }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top">
                            <button type="button" class="btn btn-link btn-sm p-0 text-muted text-decoration-none" @click="hideAllColumns">
                                Hide All
                            </button>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" @click="showAllColumns">
                                Show All
                            </button>
                        </div>
                    </div>
                </div>

                <AppButton
                    v-if="showCreateButton"
                    variant="create"
                    :label="createButtonText"
                    :loading="createButtonLoading"
                    @click="$emit('create')"
                />
            </div>
        </div>

        <div class="app-data-table-scroll">
            <template v-if="loading">
                <div class="app-data-table-loading">
                    <LoadingSpinner />
                </div>
            </template>
            <template v-else-if="hasNoSourceRows">
                <div class="app-data-table-empty">
                    <div class="app-data-table-empty-icon text-muted">
                        <i class="fa-solid fa-folder-open fa-3x" aria-hidden="true"></i>
                    </div>
                    <p class="app-data-table-empty-text mb-0">{{ emptyStateText }}</p>
                </div>
            </template>
            <template v-else-if="hasNoMatchingRows">
                <div class="app-data-table-empty">
                    <div class="app-data-table-empty-icon text-muted">
                        <i class="fa-solid fa-folder-open fa-3x" aria-hidden="true"></i>
                    </div>
                    <p class="app-data-table-empty-text mb-0">{{ noMatchText }}</p>
                </div>
            </template>
            <vue3-datatable
                v-else
                :key="datatableKey"
                :rows="displayRows"
                :columns="visibleColumns"
                :sortable="sortable"
                :sort-column="sortColumn"
                :sort-direction="sortDirection"
                :search="pluginSearchQuery"
                :column-filter="columnFilter"
                :pagination="pagination"
                :pagination-info="paginationInfo"
                :page-size-options="pageSizeOptions"
                :page-size="localPageSize"
                :show-page-size="false"
                :show-numbers-count="showNumbersCount"
                :skin="tableSkin"
            >
                <template v-for="(_, name) in $slots" #[name]="slotData">
                    <div v-if="name === 'action' || name === 'actions'" class="d-flex justify-content-center align-items-center w-100">
                        <slot :name="name" v-bind="slotData" />
                    </div>
                    <slot v-else :name="name" v-bind="slotData" />
                </template>
            </vue3-datatable>
        </div>
    </div>
</template>

<style scoped>
.app-data-table-page-size {
    width: 5rem;
}

.app-data-table-search {
    min-width: 0;
}

@media (min-width: 576px) {
    .app-data-table-search {
        width: 18rem;
    }
}

.app-data-table-toolbar {
    position: relative;
    z-index: 1;
    line-height: 0;
}

.app-data-table-toolbar--menu-open {
    z-index: 20;
}

.app-data-table-icon-btn {
    appearance: none;
    box-sizing: border-box;
    width: calc(2.5rem - 1px);
    height: calc(2.5rem - 6px);
    min-width: calc(2.5rem - 1px);
    min-height: calc(2.5rem - 6px);
    margin: 0;
    padding: 0;
    border: 1px solid #C5D5E8;
    border-radius: 5px;
    background: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    vertical-align: middle;
}

.app-data-table-icon-btn--primary {
    border-color: #027DE2;
    color: #027DE2;
}

.app-data-table-icon-btn--secondary {
    border-color: #C5D5E8;
    color: #64748b;
}

.app-data-table-icon-btn:hover:not(:disabled) {
    background: #f8fafc;
}

.app-data-table-icon-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.app-data-table-icon-img {
    width: 1.125rem;
    height: 1.125rem;
    display: block;
    flex-shrink: 0;
}

.app-data-table-icon-spinner {
    width: 0.875rem;
    height: 0.875rem;
    border: 0.15em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: app-data-table-spin 0.75s linear infinite;
}

@keyframes app-data-table-spin {
    to {
        transform: rotate(360deg);
    }
}

.app-data-table-column-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 0.35rem);
    display: block;
    width: min(calc(100vw - 2rem), 14rem);
    z-index: 1050;
    margin: 0;
    line-height: normal;
    pointer-events: auto;
}

.app-data-table-column-list-wrap {
    max-height: 15rem;
    overflow: hidden;
}

.app-data-table-column-list {
    max-height: 15rem;
    overflow-y: auto;
}

.app-data-table-column-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    line-height: normal;
    cursor: pointer;
    margin: 0;
}

.app-data-table-column-item:hover {
    background-color: var(--bs-light);
}

.app-data-table-column-bar {
    width: 3px;
    height: 1rem;
    border-radius: 2px;
    flex-shrink: 0;
}

.app-data-table-column-label {
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.app-data-table-loading,
.app-data-table-empty {
    min-height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: var(--bs-light);
    border-radius: 0.5rem;
    border: 1px solid var(--bs-border-color);
}

.app-data-table-empty {
    flex-direction: column;
    gap: 0.75rem;
}

.app-data-table-empty-text {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--bs-secondary-color);
}
</style>

<style>
.app-data-table-scroll {
    position: relative;
    z-index: 0;
}

.app-data-table .bh-table-responsive {
    --app-table-scrollbar-gradient-h: linear-gradient(90deg, #02b9af 0%, #3aa0d8 35%, #4e86f4 65%, #9c54f0 100%);
    --app-table-scrollbar-gradient-v: linear-gradient(180deg, #02b9af 0%, #3aa0d8 35%, #4e86f4 65%, #9c54f0 100%);
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
}

.app-data-table:hover .bh-table-responsive {
    scrollbar-color: #4e86f4 transparent;
}

.app-data-table .bh-table-responsive::-webkit-scrollbar {
    width: 3px;
    height: 3px;
}

.app-data-table .bh-table-responsive::-webkit-scrollbar-button {
    display: none;
    width: 0;
    height: 0;
}

.app-data-table .bh-table-responsive::-webkit-scrollbar-track {
    background: transparent;
}

.app-data-table .bh-table-responsive::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 3px;
}

.app-data-table:hover .bh-table-responsive::-webkit-scrollbar-thumb:horizontal {
    background: var(--app-table-scrollbar-gradient-h);
}

.app-data-table:hover .bh-table-responsive::-webkit-scrollbar-thumb:vertical {
    background: var(--app-table-scrollbar-gradient-v);
}

.app-data-table .bh-datatable {
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}

.app-data-table .bh-table-responsive {
    border-radius: 0;
    margin: 0;
    padding: 0;
}

.app-data-table .bh-datatable table {
    margin: 0 !important;
}

.app-data-table .bh-table-responsive table.bh-table-bordered {
    border: 0 !important;
    border-collapse: collapse !important;
    border-spacing: 0;
    margin-bottom: 0;
}

.app-data-table .bh-table-responsive table.bh-table-bordered tbody tr td,
.app-data-table .bh-table-responsive table.bh-table-bordered thead tr th {
    border-color: var(--bs-border-color);
}

.app-data-table .bh-table-responsive table.bh-table-bordered thead tr:first-child th {
    border-top: 0 !important;
}

.app-data-table .bh-table-responsive table.bh-table-bordered thead tr th:first-child,
.app-data-table .bh-table-responsive table.bh-table-bordered tbody tr td:first-child {
    border-left: 0 !important;
}

.app-data-table .bh-table-responsive table.bh-table-bordered thead tr th:last-child,
.app-data-table .bh-table-responsive table.bh-table-bordered tbody tr td:last-child {
    border-right: 0 !important;
}

.app-data-table .bh-table-responsive table.bh-table-bordered tbody tr:last-child td {
    border-bottom: 0 !important;
}

.app-data-table .bh-table-responsive table thead tr th {
    background-color: var(--bs-light);
    color: var(--bs-body-color);
    -webkit-user-select: text;
    -moz-user-select: text;
    user-select: text;
}

.app-data-table .bh-table-responsive table.bh-table-bordered thead tr:first-child th:first-child {
    border-top-left-radius: 7px;
}

.app-data-table .bh-table-responsive table.bh-table-bordered thead tr:first-child th:last-child {
    border-top-right-radius: 7px;
}

.app-data-table .bh-table-responsive table.bh-table-bordered tbody tr:last-child td:first-child {
    border-bottom-left-radius: 7px;
}

.app-data-table .bh-table-responsive table.bh-table-bordered tbody tr:last-child td:last-child {
    border-bottom-right-radius: 7px;
}

.app-data-table .bh-table-responsive table thead tr th,
.app-data-table .bh-table-responsive table tbody tr td {
    white-space: nowrap;
}

.app-data-table .bh-table-responsive table thead tr th:last-child,
.app-data-table .bh-table-responsive table tbody tr td:last-child {
    position: sticky;
    right: 0;
    z-index: 2;
    box-shadow: -2px 0 6px rgba(0, 0, 0, 0.06);
}

.app-data-table .bh-table-responsive table thead tr th:last-child {
    background-color: #f6f8fa;
    z-index: 3;
    text-align: center !important;
}

.app-data-table .bh-table-responsive table thead tr th:last-child > div {
    justify-content: center !important;
}

.app-data-table .bh-table-responsive table tbody tr td:last-child {
    background-color: #ffffff;
    text-align: center !important;
}

.app-data-table .bh-datatable .bh-pagination {
    margin: 0;
    padding: 0.875rem 1.25rem 1rem !important;
    border-top: 1px solid var(--bs-border-color);
    background: #fff;
    gap: 0.75rem;
}

.app-data-table .bh-datatable .bh-pagination > div {
    width: 100%;
    padding: 0 0.15rem;
}

.app-data-table .bh-datatable .bh-pagination-info {
    font-size: 0.8125rem;
    color: #64748b;
}

.app-data-table .bh-datatable .bh-pagination-number {
    gap: 0.35rem;
}

[data-bs-theme="dark"] .app-data-table-loading,
[data-bs-theme="dark"] .app-data-table-empty {
    background: #2b3035;
    border-color: #495057;
}

[data-bs-theme="dark"] .app-data-table .bh-table-responsive table thead tr th {
    background-color: #343a40;
    color: #f8f9fa !important;
}

[data-bs-theme="dark"] .app-data-table .bh-table-responsive table tbody tr td {
    color: #dee2e6;
}

[data-bs-theme="dark"] .app-data-table .bh-table-responsive table thead tr th:last-child {
    background-color: #343a40;
}

[data-bs-theme="dark"] .app-data-table .bh-table-responsive table tbody tr td:last-child {
    background-color: #212529;
}

[data-bs-theme="dark"] .app-data-table .bh-table-responsive table tbody tr {
    border-color: #495057 !important;
}

[data-bs-theme="dark"] .app-data-table .bh-datatable {
    background: #212529;
    border-color: #495057;
}

[data-bs-theme="dark"] .app-data-table .bh-datatable .bh-pagination {
    background: #212529;
    border-top-color: #495057;
}

[data-bs-theme="dark"] .app-data-table .bh-datatable .bh-pagination-info {
    color: #adb5bd;
}

[data-bs-theme="dark"] .app-data-table-column-item:hover {
    background-color: #343a40;
}
</style>
