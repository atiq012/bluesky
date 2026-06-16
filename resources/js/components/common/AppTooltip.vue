<script setup>
defineProps({
    content:   { type: String, required: true },
    placement: { type: String, default: 'top' },
    allowHtml: { type: Boolean, default: false },
})
</script>

<template>
    <tippy
        :content="content"
        :placement="placement"
        :allowHTML="allowHtml"
        animation="scale"
        :inertia="true"
        :duration="[400, 200]"
        :arrow="true"
    >
        <slot />
    </tippy>
</template>
