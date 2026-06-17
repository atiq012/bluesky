<script setup>
import { computed } from 'vue';
import AvatarWithPreview from './AvatarWithPreview.vue';

const props = defineProps({
    name: { type: String, default: '' },
    date: { type: String, default: '' },
    imagePath: { type: String, default: '' },
});

const imageUrl = computed(() => {
    const p = (props.imagePath || '').trim();
    if (!p) return '';
    return p.startsWith('http') || p.startsWith('/') ? p : `/storage/${p.replace(/^\/+/, '')}`;
});

const formattedDate = computed(() => {
    if (!props.date) return '';
    const d = new Date(props.date);
    if (Number.isNaN(d.getTime())) return props.date;
    const dateStr = new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric' }).format(d);
    const timeStr = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(d);
    return `${dateStr} at ${timeStr}`;
});
</script>

<template>
    <div class="d-flex align-items-center gap-2">
        <AvatarWithPreview
            :src="imageUrl"
            :name="name"
            size="md"
        />
        <div class="d-flex flex-column gap-1 min-w-0">
            <span class="small fw-semibold text-truncate">{{ name || '—' }}</span>
            <span v-if="formattedDate" class="text-muted text-truncate" style="font-size: 0.75rem;">
                {{ formattedDate }}
            </span>
        </div>
    </div>
</template>
