<script setup>
defineProps({
    title: { type: String, required: true },
    backTo: { type: Object, default: null },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <router-link
            v-if="backTo"
            :to="backTo"
            class="app-bc-back-btn"
        >
            <i class="fa-solid fa-arrow-left"></i>
        </router-link>

        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2 app-bc-row">
                <div class="app-bc-title">{{ title }}</div>
                <span class="text-muted app-bc-sep">|</span>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li
                            v-for="(crumb, index) in breadcrumbs"
                            :key="index"
                            class="breadcrumb-item"
                            :class="{ active: !crumb.to }"
                            :aria-current="!crumb.to ? 'page' : undefined"
                        >
                            <router-link v-if="crumb.to" :to="crumb.to">{{ crumb.label }}</router-link>
                            <template v-else>{{ crumb.label }}</template>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>

        <div v-if="$slots.actions" class="ms-auto">
            <slot name="actions" />
        </div>
    </div>
</template>

<style scoped>
.app-bc-back-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    display: grid;
    place-items: center;
    color: #64748b;
    background: #fff;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.15s;
    flex-shrink: 0;
}

.app-bc-back-btn:hover {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}

.app-bc-row {
    font-size: 13px;
    line-height: 1.35;
}

.app-bc-title {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}

.app-bc-row :deep(.breadcrumb) {
    font-size: 13px;
}

.app-bc-sep {
    font-size: 13px;
    opacity: 0.65;
}

[data-bs-theme="dark"] .app-bc-back-btn {
    background: #1e293b;
    border-color: #334155;
    color: #94a3b8;
}

[data-bs-theme="dark"] .app-bc-back-btn:hover {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}

[data-bs-theme="dark"] .app-bc-title {
    color: #e2e8f0;
}

[data-bs-theme="dark"] :deep(.breadcrumb-item a) {
    color: #94a3b8;
}

[data-bs-theme="dark"] :deep(.breadcrumb-item.active) {
    color: #e2e8f0;
}
</style>
