<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

const props = defineProps({
    options: {
        type: Array,
        default: () => [],
    },
    placement: { type: String, default: 'bottom-start' },
});

const emit = defineEmits(['select']);

const isOpen = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);
const panelStyle = ref({});
const openAbove = ref(false);

const ESTIMATED_PANEL_HEIGHT = 200;
const PANEL_WIDTH = 180;
const GAP = 4;

const ICON_CLASSES = {
    'file-text': 'fa-solid fa-file-lines',
    receipt: 'fa-solid fa-receipt',
    files: 'fa-solid fa-copy',
    active: 'fa-solid fa-circle-check text-success',
    inactive: 'fa-solid fa-ban text-danger',
};

function getViewportBox() {
    return { w: window.innerWidth, h: window.innerHeight };
}

function computePanelStyle(r) {
    const v = getViewportBox();
    const spaceBelow = v.h - r.bottom - GAP;
    const spaceAbove = r.top - GAP;
    openAbove.value = spaceBelow < ESTIMATED_PANEL_HEIGHT && spaceAbove >= spaceBelow;

    const style = {};
    if (openAbove.value) {
        style.bottom = `${v.h - r.top + GAP}px`;
        style.top = 'auto';
    } else {
        style.top = `${r.bottom + GAP}px`;
    }

    const preferLeft = props.placement === 'bottom-end' ? r.right - PANEL_WIDTH : r.left;
    style.left = `${Math.max(0, Math.min(preferLeft, v.w - PANEL_WIDTH))}px`;
    style.right = 'auto';
    return style;
}

function getTriggerEl() {
    return triggerRef.value?.$el ?? triggerRef.value;
}

function toggle() {
    if (!isOpen.value) {
        const el = getTriggerEl();
        if (el?.getBoundingClientRect) {
            panelStyle.value = computePanelStyle(el.getBoundingClientRect());
        }
    }
    isOpen.value = !isOpen.value;
}

function close() {
    isOpen.value = false;
}

function onSelect(key) {
    emit('select', key);
    close();
}

function updatePosition() {
    if (!isOpen.value) return;
    const el = getTriggerEl();
    if (!el?.getBoundingClientRect) return;
    panelStyle.value = computePanelStyle(el.getBoundingClientRect());
}

function handleClickOutside(e) {
    if (!isOpen.value) return;
    const el = getTriggerEl();
    const panel = panelRef.value;
    if (el && !el.contains(e.target) && panel && !panel.contains(e.target)) {
        close();
    }
}

function iconClass(opt) {
    return ICON_CLASSES[opt.icon] || ICON_CLASSES['file-text'];
}

watch(isOpen, (open) => {
    if (open) nextTick(updatePosition);
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="triggerRef" class="dropdown-menu-trigger d-inline-block">
        <div @click="toggle">
            <slot name="trigger" :open="isOpen" :close="close" />
        </div>
        <Teleport to="body">
            <Transition name="dropdown-menu-fade">
                <div
                    v-show="isOpen"
                    ref="panelRef"
                    class="dropdown-menu-panel dropdown-menu show position-fixed shadow"
                    :style="{ ...panelStyle, maxHeight: 'min(280px, 80vh)', minWidth: '180px' }"
                    @click.stop
                >
                    <button
                        v-for="opt in options"
                        :key="opt.key"
                        type="button"
                        class="dropdown-item d-flex align-items-center gap-2"
                        @click="onSelect(opt.key)"
                    >
                        <i :class="iconClass(opt)" class="dropdown-item-icon" aria-hidden="true"></i>
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.dropdown-menu-panel {
    z-index: 1060;
    overflow-y: auto;
    display: block;
}

.dropdown-item-icon {
    width: 1rem;
    text-align: center;
    flex-shrink: 0;
}

.dropdown-menu-fade-enter-active,
.dropdown-menu-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.dropdown-menu-fade-enter-from,
.dropdown-menu-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>

<style>
[data-bs-theme="dark"] .dropdown-menu-panel {
    background-color: #2b3035;
    border-color: #495057;
}

[data-bs-theme="dark"] .dropdown-menu-panel .dropdown-item {
    color: #dee2e6;
}

[data-bs-theme="dark"] .dropdown-menu-panel .dropdown-item:hover,
[data-bs-theme="dark"] .dropdown-menu-panel .dropdown-item:focus {
    background-color: #343a40;
    color: #fff;
}
</style>
