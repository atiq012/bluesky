<script setup>
import { nextTick, onMounted } from "vue";
import { useSearchStore } from '../../stores/searchStore';
import SearchResultLegacy from "./searchResult.vue";

const searchStore = useSearchStore();

onMounted(async () => {
    await nextTick();
    setTimeout(() => {
        // Only click round-trip default when no valid session — clicking calls
        // tourTypeChange(2) which resets dates and overwrites the restored form.
        if (!searchStore.isValid) {
            const roundTripRadio = document.getElementById("flexRadioDefault2");
            if (roundTripRadio) {
                roundTripRadio.click();
            }
        }
    }, 50);
});
</script>

<template>
    <SearchResultLegacy />
</template>
