<template>
    <div class="d-flex justify-content-center align-items-center w100">
        <span class="fw-bolder fs-4">Logout</span>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../stores/authStore';
const authStore = useAuthStore();

LogOutLaravel();

async function LogOutLaravel() {

    const tkn = authStore.decryptWithAES(authStore.token);

    const config = {
        headers: { Authorization: 'Bearer ' + tkn, "Accept": "application/json", }
    };

    try {
        const res = await axios.get('/api/logout', config);
        Notification.showToast("w", res.data.message);
    } catch (eEes) {
        console.log(eEes);
    } finally {
        authStore.logout();
        router.push({ name: 'Login' });
    }

}
</script>

<style scoped>
.w100 {
    height: 50vh;
    margin: 0;
}
</style>
