<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    src: { type: String, default: '' },
    name: { type: String, default: '' },
    sizeClass: { type: String, default: '' },
    size: { type: String, default: 'md' },
    alt: { type: String, default: '' },
});

const showPreview = ref(false);
const imageFailed = ref(false);

const hasImage = computed(() => props.src && props.src.trim() && !imageFailed.value);

const initials = computed(() => {
    const n = (props.name || '').trim();
    if (!n) return '?';
    const parts = n.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return n.slice(0, 2).toUpperCase();
});

const imageSrc = computed(() => {
    const s = (props.src || '').trim();
    if (!s) return '';
    return s.startsWith('http') || s.startsWith('/') ? s : `/${s.replace(/^\/+/, '')}`;
});

const avatarClass = computed(() => {
    if (props.sizeClass) return props.sizeClass;
    const map = {
        sm: 'avatar-size-sm',
        md: 'avatar-size-md',
        lg: 'avatar-size-lg',
    };
    return map[props.size] || map.md;
});

function onImageError() {
    imageFailed.value = true;
}

function openPreview() {
    if (!hasImage.value) return;
    showPreview.value = true;
}

function closePreview() {
    showPreview.value = false;
}
</script>

<template>
    <div class="d-inline-flex align-items-center justify-content-center">
        <button
            type="button"
            class="avatar-btn rounded-circle overflow-hidden d-flex align-items-center justify-content-center flex-shrink-0 border-0 p-0"
            :class="[avatarClass, hasImage ? 'avatar-btn-clickable' : '']"
            :title="hasImage ? 'Click to enlarge' : ''"
            @click="openPreview"
        >
            <img
                v-if="hasImage"
                :src="imageSrc"
                :alt="alt || name || 'Avatar'"
                class="w-100 h-100 object-fit-cover"
                @error="onImageError"
            />
            <span v-else class="avatar-initials w-100 h-100 d-flex align-items-center justify-content-center fw-semibold text-white">
                {{ initials }}
            </span>
        </button>

        <Teleport to="body">
            <div
                v-if="showPreview && hasImage"
                class="avatar-preview-backdrop"
                @click.self="closePreview"
            >
                <button
                    type="button"
                    class="avatar-preview-close btn btn-light btn-sm rounded-circle"
                    aria-label="Close"
                    @click="closePreview"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <img
                    :src="imageSrc"
                    :alt="alt || name || 'Preview'"
                    class="avatar-preview-image"
                    @click.stop
                />
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.avatar-size-sm { width: 2rem; height: 2rem; }
.avatar-size-md { width: 2.5rem; height: 2.5rem; }
.avatar-size-lg { width: 3rem; height: 3rem; }

.avatar-btn-clickable {
    cursor: pointer;
}

.avatar-btn-clickable:hover {
    opacity: 0.9;
}

.avatar-initials {
    font-size: 0.75rem;
    background-color: var(--bs-primary);
}

.avatar-preview-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.7);
}

.avatar-preview-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 2.5rem;
    height: 2.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.avatar-preview-image {
    max-width: 100%;
    max-height: 90vh;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.35);
}
</style>
