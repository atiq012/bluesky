<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: null,
    },

    year: {
        type: Number,
        default: () => new Date().getFullYear(),
    },

    includeAll: {
        type: Boolean,
        default: true,
    },

    allLabel: {
        type: String,
        default: 'All',
    },
});

const emit = defineEmits(['update:modelValue']);

const months = Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Date(props.year, index, 1).toLocaleString('en-US', {
        month: 'short',
    }),
}));

const selectedLabel = computed(() => {
    if (props.modelValue === null || props.modelValue === '') {
        return props.allLabel;
    }

    const month = months.find(
        month => month.value === Number(props.modelValue)
    );

    return month
        ? `${month.label} ${props.year}`
        : props.allLabel;
});

const selectMonth = (value) => {
    emit('update:modelValue', value);
    //console.log("selected month:", value);
};
</script>

<template>
    <div class="dropdown">
        <button
            class="btn-pill dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
        >
            {{ selectedLabel }}
        </button>

        <ul class="dropdown-menu dropdown-menu-end">

            <!-- All -->
            <li v-if="includeAll">
                <button
                    class="dropdown-item"
                    type="button"
                    @click="selectMonth(null)"
                >
                    {{ allLabel }}
                </button>
            </li>

            <!-- Months -->
            <li
                v-for="month in months"
                :key="month.value"
            >
                <button
                    class="dropdown-item"
                    type="button"
                    @click="selectMonth(month.value)"
                >
                    {{ month.label }} {{ year }}
                </button>
            </li>

        </ul>
    </div>
</template>

<style scoped>
.btn-pill {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 4px 14px;
  font-size: 12px;
  font-weight: 600;
  color: #7b8ab8;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: border-color .2s, color .2s;
  white-space: nowrap;
}

.btn-pill:hover {
  border-color: #3b82f6;
  color: #3b82f6;
}
</style>