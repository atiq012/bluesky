<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import AvatarWithPreview from './AvatarWithPreview.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: null },
    users: { type: Array, default: () => [] },
    placeholder: { type: String, default: '=Search employee=' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    error: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const LIST_CAP = 80;

function resolveImageUrl(path) {
    if (!path) return '';
    const p = path.trim();
    return p.startsWith('http') || p.startsWith('/') ? p : `/storage/${p.replace(/^\/+/, '')}`;
}

const normalizedOptions = computed(() =>
    (props.users || []).map((u) => {
        const roleStr = (u.roles || []).join(', ');
        const searchText = [u.name, u.email, roleStr].filter(Boolean).join(' ').toLowerCase();
        return { value: u.id, user: u, roleStr, searchText };
    }),
);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const dropdownStyle = ref({ top: 0, left: 0, minWidth: 0 });

const filteredOptions = computed(() => {
    const q = (searchQuery.value || '').trim().toLowerCase();
    if (!q) return normalizedOptions.value;
    return normalizedOptions.value.filter((o) => o.searchText.includes(q));
});

const displayOptions = computed(() => {
    const list = filteredOptions.value;
    return list.length > LIST_CAP ? list.slice(0, LIST_CAP) : list;
});

const showListCapHint = computed(() => filteredOptions.value.length > LIST_CAP);

const selectedOption = computed(() =>
    normalizedOptions.value.find((o) => String(o.value) === String(props.modelValue)) ?? null,
);

const hasValue = computed(() => props.modelValue != null && props.modelValue !== '');
const controlDisplay = computed(() => selectedOption.value?.user?.name ?? '');

function openDropdown() {
    if (props.disabled) return;
    updateDropdownPosition();
    isOpen.value = true;
    searchQuery.value = '';
}

function closeDropdown() {
    isOpen.value = false;
    searchQuery.value = '';
}

function selectOption(opt) {
    emit('update:modelValue', opt.value);
    closeDropdown();
}

function clearValue(e) {
    e.stopPropagation();
    if (props.loading || props.disabled) return;
    emit('update:modelValue', null);
    closeDropdown();
}

function splitHighlight(text) {
    const q = (searchQuery.value || '').trim();
    const str = text == null ? '' : String(text);
    if (!q || !str) return [{ text: str, match: false }];
    const lower = str.toLowerCase();
    const lowerQ = q.toLowerCase();
    const idx = lower.indexOf(lowerQ);
    if (idx < 0) return [{ text: str, match: false }];
    return [
        { text: str.slice(0, idx), match: false },
        { text: str.slice(idx, idx + q.length), match: true },
        { text: str.slice(idx + q.length), match: false },
    ].filter((s) => s.text);
}

function updateDropdownPosition() {
    if (!containerRef.value) return;
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

function onDocumentClick(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) closeDropdown();
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    if (scrollResizeCleanup) scrollResizeCleanup();
});
</script>

<template>
    <div ref="containerRef" class="user-select2 position-relative w-100">
        <div
            class="user-select2-control form-control form-control-sm d-flex align-items-center gap-2"
            :class="{
                'user-select2-control--open': isOpen,
                'is-invalid': error && !isOpen,
                'opacity-50 pe-none': disabled,
            }"
            @click="openDropdown"
        >
            <template v-if="hasValue && !isOpen && selectedOption">
                <div class="d-flex align-items-center gap-2 flex-fill min-w-0">
                    <AvatarWithPreview
                        :src="resolveImageUrl(selectedOption.user.image_path)"
                        :name="selectedOption.user.name"
                        size="sm"
                    />
                    <span class="small fw-medium text-truncate">{{ selectedOption.user.name }}</span>
                </div>
            </template>

            <input
                v-else
                type="text"
                class="user-select2-input border-0 bg-transparent flex-fill"
                :placeholder="hasValue ? '' : placeholder"
                :disabled="disabled"
                :value="isOpen ? searchQuery : controlDisplay"
                @input="(e) => { searchQuery = e.target.value; if (!isOpen) openDropdown(); }"
                @focus="openDropdown"
            />

            <div class="user-select2-indicators d-flex align-items-center flex-shrink-0 gap-1 text-muted">
                <span v-if="loading" class="spinner-border spinner-border-sm text-primary" role="status" />
                <template v-else>
                    <button
                        v-if="clearable && hasValue && !disabled"
                        type="button"
                        class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                        @click.stop="clearValue"
                    >
                        &times;
                    </button>
                    <span v-if="clearable && hasValue" class="text-muted">|</span>
                    <i class="bx bx-chevron-down user-select2-arrow" :class="{ 'user-select2-arrow--open': isOpen }" />
                </template>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="user-select2-fade">
                <div
                    v-show="isOpen"
                    class="user-select2-dropdown dropdown-menu show position-fixed shadow"
                    :style="dropdownStyle"
                >
                    <template v-if="displayOptions.length">
                        <button
                            v-for="opt in displayOptions"
                            :key="opt.value"
                            type="button"
                            class="dropdown-item d-flex align-items-center gap-2 py-2"
                            :class="{ active: String(opt.value) === String(modelValue) }"
                            @click.stop="selectOption(opt)"
                        >
                            <AvatarWithPreview
                                :src="resolveImageUrl(opt.user.image_path)"
                                :name="opt.user.name"
                                size="sm"
                            />
                            <div class="flex-fill min-w-0">
                                <div class="small fw-semibold text-truncate">
                                    <template v-for="(seg, i) in splitHighlight(opt.user.name)" :key="`n-${opt.value}-${i}`">
                                        <span :class="seg.match ? 'text-primary fw-bold' : ''">{{ seg.text }}</span>
                                    </template>
                                </div>
                                <div v-if="opt.roleStr" class="text-muted text-truncate" style="font-size: 0.7rem;">
                                    {{ opt.roleStr }}
                                </div>
                            </div>
                        </button>

                        <div
                            v-if="showListCapHint"
                            class="dropdown-item-text text-center text-muted py-2 border-top"
                            style="font-size: 0.7rem;"
                        >
                            Showing {{ LIST_CAP }} of {{ filteredOptions.length }}. Type to search.
                        </div>
                    </template>
                    <div v-else class="dropdown-item-text text-center text-muted py-4">
                        <div class="small">No employees found</div>
                        <div class="text-muted mt-1" style="font-size: 0.7rem;">Try name, email, or role</div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.user-select2-control {
    min-height: 31px;
    cursor: text;
}

.user-select2-control--open {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}

.user-select2-input {
    outline: none;
    min-width: 60px;
    font-size: inherit;
    line-height: inherit;
    padding: 0;
}

.user-select2-input::placeholder {
    color: var(--bs-secondary-color);
}

.user-select2-arrow {
    font-size: 1.1rem;
    line-height: 1;
    transition: transform 0.2s ease;
}

.user-select2-arrow--open {
    transform: rotate(180deg);
}

.user-select2-dropdown {
    z-index: 9999;
    max-height: 288px;
    overflow-y: auto;
    display: block;
    margin: 0;
}

.user-select2-fade-enter-active,
.user-select2-fade-leave-active {
    transition: opacity 0.1s ease, transform 0.1s ease;
}

.user-select2-fade-enter-from,
.user-select2-fade-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
</style>
