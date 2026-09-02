<template>
    <div class="d-flex justify-content-center align-items-center w100">
        <span class="fw-bolder fs-4">Logout</span>
    </div>
</template>

<script setup>
import { onMounted } from "vue";
import { performLogout } from "../../Helpers/logout";

onMounted(() => {
    void performLogout({ showToast: true });
});
</script>

<style scoped>
.w100 {
    height: 50vh;
    margin: 0;
}
</style>
