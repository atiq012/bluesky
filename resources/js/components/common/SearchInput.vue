<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Search by anything' },
    inputId: { type: String, default: '' },
    wrapperClass: { type: String, default: '' },
    searchIconSrc: { type: String, default: '/theme/appimages/Search.svg' },
});

const emit = defineEmits(['update:modelValue']);

function clear() {
    emit('update:modelValue', '');
}
</script>

<template>
    <div class="search-input position-relative" :class="wrapperClass">
        <input
            :id="inputId"
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            class="search-input-field"
            autocomplete="off"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <span class="search-input-icon">
            <img
                v-if="searchIconSrc"
                :src="searchIconSrc"
                alt=""
                class="search-input-icon-img"
                aria-hidden="true"
            />
            <i v-else class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </span>
        <button
            v-if="modelValue"
            type="button"
            class="search-input-clear"
            aria-label="Clear search"
            @click="clear"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
</template>

<style scoped>
.search-input-field {
    width: 100%;
    height: calc(2.5rem - 4px);
    padding: 0.375rem 2rem 0.375rem 2.5rem;
    border: 1px solid #C5D5E8;
    border-radius: 9px;
    background: #fff;
    color: #334155;
    font-size: 0.875rem;
    line-height: 1.25;
    box-shadow: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.search-input-field::placeholder {
    color: #8FA8C2;
    opacity: 1;
}

.search-input-field:focus {
    border-color: #027DE2;
    box-shadow: 0 0 0 0.15rem rgba(2, 125, 226, 0.12);
    outline: none;
}

.search-input-icon {
    position: absolute;
    left: 0.85rem;
    top: 0;
    bottom: 0;
    height: auto;
    transform: none;
    pointer-events: none;
    line-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-input-icon-img {
    width: 1rem;
    height: 1rem;
    display: block;
}

.search-input-clear {
    position: absolute;
    right: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #8FA8C2;
    line-height: 1;
    padding: 0;
    font-size: 0.8rem;
}

.search-input-clear:hover {
    color: #027DE2;
}
</style>

<style>
[data-bs-theme="dark"] .search-input-field {
    background: #2b3035;
    border-color: #495057;
    color: #dee2e6;
}

[data-bs-theme="dark"] .search-input-field::placeholder {
    color: #8b9cb0;
}

[data-bs-theme="dark"] .search-input-field:focus {
    border-color: #027DE2;
    box-shadow: 0 0 0 0.15rem rgba(2, 125, 226, 0.2);
}
</style>
