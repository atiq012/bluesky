<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Array], default: null },
    options: { type: Array, default: () => [] },
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
    placeholder: { type: String, default: '=Select=' },
    multiple: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    controlClass: { type: String, default: 'form-control form-control-sm' },
    controlStyle: { type: [String, Object], default: '' },
});

const emit = defineEmits(['update:modelValue']);

const normalizedOptions = computed(() => {
    const list = props.options || [];
    const vk = props.valueKey;
    const lk = props.labelKey;
    return list.map((item) => ({
        value: item[vk] ?? item.value,
        label: item[lk] ?? item.label ?? String(item[vk] ?? item.value ?? ''),
    }));
});

const isOpen = ref(false);
const searchQuery = ref('');

const filteredOptions = computed(() => {
    const q = (searchQuery.value || '').trim().toLowerCase();
    const list = normalizedOptions.value;
    if (!q) return list;
    return list.filter((opt) => (opt.label || '').toLowerCase().includes(q));
});

const selectedValues = computed(() => {
    const v = props.modelValue;
    if (props.multiple) return Array.isArray(v) ? v : (v != null && v !== '') ? [v] : [];
    // null / undefined / '' all treated as "nothing selected" → shows placeholder
    if (v == null || v === '') return [];
    return [v];
});

const selectedOptions = computed(() => {
    const vals = selectedValues.value;
    const list = normalizedOptions.value;
    return vals.map((val) => list.find((o) => o.value === val)).filter(Boolean);
});

const singleDisplayLabel = computed(() => {
    const sel = selectedOptions.value[0];
    return sel ? sel.label : '';
});

const hasValue = computed(() => selectedValues.value.length > 0);

function openDropdown() {
    if (props.disabled) return;
    isOpen.value = true;
    searchQuery.value = '';
}

function closeDropdown() {
    isOpen.value = false;
    searchQuery.value = '';
}

function selectOption(opt) {
    if (props.multiple) {
        const vals = [...selectedValues.value];
        const idx = vals.indexOf(opt.value);
        if (idx >= 0) vals.splice(idx, 1);
        else vals.push(opt.value);
        emit('update:modelValue', vals);
    } else {
        emit('update:modelValue', opt.value);
        closeDropdown();
    }
}

function clearValue(e) {
    e.stopPropagation();
    if (props.loading || props.disabled) return;
    emit('update:modelValue', props.multiple ? [] : null);
    if (!props.multiple) closeDropdown();
}

function highlightLabel(label) {
    const q = (searchQuery.value || '').trim();
    if (!q || !label) return label;
    const str = String(label);
    const lower = str.toLowerCase();
    const lowerQ = q.toLowerCase();
    const i = lower.indexOf(lowerQ);
    if (i < 0) return str;
    return {
        before: str.slice(0, i),
        match: str.slice(i, i + q.length),
        after: str.slice(i + q.length),
    };
}

const containerRef = ref(null);
function onDocumentClick(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) closeDropdown();
}

const dropdownStyle = ref({ top: 0, left: 0, minWidth: 0 });
function updateDropdownPosition() {
    if (!containerRef.value || !isOpen.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    dropdownStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        minWidth: `${rect.width}px`,
    };
}

let scrollResizeCleanup = null;
watch(isOpen, (open) => {
    if (!open) {
        searchQuery.value = '';
        if (scrollResizeCleanup) {
            scrollResizeCleanup();
            scrollResizeCleanup = null;
        }
        return;
    }
    nextTick(() => {
        updateDropdownPosition();
        const onScrollOrResize = () => updateDropdownPosition();
        window.addEventListener('resize', onScrollOrResize);
        window.addEventListener('scroll', onScrollOrResize, true);
        scrollResizeCleanup = () => {
            window.removeEventListener('resize', onScrollOrResize);
            window.removeEventListener('scroll', onScrollOrResize, true);
        };
    });
});

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    if (scrollResizeCleanup) scrollResizeCleanup();
});
</script>

<template>
    <div ref="containerRef" class="app-select2 position-relative w-100">
        <div
            :class="[
                'app-select2-control',
                controlClass,
                'd-flex align-items-center gap-1',
                {
                    'app-select2-control--open': isOpen,
                    'opacity-50 pe-none': disabled,
                }
            ]"
            :style="controlStyle"
            @click="openDropdown"
        >
            <template v-if="multiple">
                <div class="d-flex flex-wrap align-items-center gap-1 flex-fill min-w-0">
                    <span
                        v-for="opt in selectedOptions"
                        :key="opt.value"
                        class="badge bg-primary-subtle text-primary d-inline-flex align-items-center gap-1"
                    >
                        <span class="text-truncate app-select2-badge-label">{{ opt.label }}</span>
                        <button
                            type="button"
                            class="btn-close btn-close-sm app-select2-badge-remove"
                            :disabled="disabled"
                            aria-label="Remove"
                            @click.stop="selectOption(opt)"
                        />
                    </span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="app-select2-input border-0 bg-transparent flex-fill"
                        :placeholder="selectedOptions.length ? '' : placeholder"
                        :disabled="disabled"
                        @click.stop
                        @focus="openDropdown"
                    />
                </div>
            </template>
            <template v-else>
                <input
                    type="text"
                    class="app-select2-input border-0 bg-transparent flex-fill"
                    :placeholder="hasValue ? '' : placeholder"
                    :disabled="disabled"
                    :value="isOpen ? searchQuery : singleDisplayLabel"
                    @input="(e) => { searchQuery = e.target.value; if (!isOpen) openDropdown(); }"
                    @focus="openDropdown"
                />
            </template>

            <div class="app-select2-indicators d-flex align-items-center flex-shrink-0 gap-1 text-muted">
                <span v-if="loading" class="spinner-border spinner-border-sm text-primary" role="status" />
                <template v-else>
                    <button
                        v-if="clearable && hasValue"
                        type="button"
                        class="btn btn-link btn-sm p-0 text-muted text-decoration-none app-select2-clear"
                        :disabled="disabled"
                        @click.stop="clearValue"
                    >
                        &times;
                    </button>
                    <span v-if="clearable && hasValue" class="app-select2-sep text-muted">|</span>
                    <i class="bx bx-chevron-down app-select2-arrow" :class="{ 'app-select2-arrow--open': isOpen }" />
                </template>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="app-select2-fade">
                <div
                    v-show="isOpen"
                    class="app-select2-dropdown dropdown-menu show position-fixed shadow"
                    :style="dropdownStyle"
                >
                    <template v-if="filteredOptions.length">
                        <button
                            v-for="opt in filteredOptions"
                            :key="opt.value"
                            type="button"
                            class="dropdown-item"
                            :class="{ active: selectedValues.includes(opt.value) }"
                            @click.stop="selectOption(opt)"
                        >
                            <template v-if="highlightLabel(opt.label) && typeof highlightLabel(opt.label) === 'object'">
                                <span>{{ highlightLabel(opt.label).before }}</span>
                                <span class="fw-semibold text-primary">{{ highlightLabel(opt.label).match }}</span>
                                <span>{{ highlightLabel(opt.label).after }}</span>
                            </template>
                            <span v-else>{{ opt.label }}</span>
                        </button>
                    </template>
                    <div v-else class="dropdown-item-text text-center text-muted py-3">
                        No options
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.app-select2-control {
    cursor: text;
}
.app-select2-control--open {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}
.app-select2-input {
    outline: none;
    min-width: 60px;
    font-size: inherit;
    line-height: inherit;
    padding: 0;
}
.app-select2-input::placeholder {
    color: var(--bs-secondary-color);
}
.app-select2-badge-label {
    max-width: 120px;
}
.app-select2-badge-remove {
    font-size: 0.55rem;
}
.app-select2-arrow {
    font-size: 1.1rem;
    line-height: 1;
    transition: transform 0.2s ease;
}
.app-select2-arrow--open {
    transform: rotate(180deg);
}
.app-select2-dropdown {
    z-index: 9999;
    max-height: 240px;
    overflow-y: auto;
    display: block;
    margin: 0;
}
.app-select2-fade-enter-active,
.app-select2-fade-leave-active {
    transition: opacity 0.1s ease, transform 0.1s ease;
}
.app-select2-fade-enter-from,
.app-select2-fade-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
</style>
