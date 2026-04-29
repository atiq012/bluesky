<script setup>
import axiosInstance from "../../../axiosInstance";
import { reactive } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../../stores/authStore";

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
    name: "",
    author: "",
    email: "",
    password: "",
    branch_code: "",
    application_id: "",
    application_secret: "",
    end_point: "",
});

async function save() {
    try {
        authStore.GlobalLoading = true;
        await axiosInstance.post("/api/save", form);
        authStore.GlobalLoading = false;
        if (typeof Notification !== "undefined" && Notification?.showToast) {
            Notification.showToast("s", "Saved successfully.");
        }
        router.push({ name: "apiManagement" });
    } catch (error) {
        authStore.GlobalLoading = false;
        if (typeof ErrorCatch !== "undefined" && ErrorCatch?.CatchError) {
            ErrorCatch.CatchError(error);
            return;
        }
        if (typeof Notification !== "undefined" && Notification?.showToast) {
            Notification.showToast("e", "Save failed.");
        }
    }
}

</script>
<template>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <router-link :to="{ name: 'apiManagement' }" class="btn btn-sm btn-link text-decoration-none px-0">
            <i class="fa-solid fa-arrow-left"></i>
        </router-link>

        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="fw-semibold text-dark">API Management</div>
                <span class="text-muted small">|</span>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0 small">
                        <li class="breadcrumb-item">
                            <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                        </li>
                        <li class="breadcrumb-item">
                            <router-link :to="{ name: 'apiManagement' }">API Management</router-link>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Create</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm api-create-card position-relative">
        <div v-if="authStore.GlobalLoading" class="api-create-loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <div class="card-header bg-white border-0 pb-0">
            <h6 class="m-0 api-create-title">Create</h6>
        </div>

        <form @submit.prevent="save">
            <div class="card-body pt-3">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>Name</b></label>
                        <input v-model="form.name" type="text" class="form-control form-control-sm"
                            placeholder="API Name" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>Author</b></label>
                        <input v-model="form.author" type="text" class="form-control form-control-sm"
                            placeholder="Author Name" />
                    </div>

                    <div class="col-12">
                        <div class="credentials-header small fw-semibold">Credentials</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>Email</b></label>
                        <input v-model="form.email" type="email" class="form-control form-control-sm"
                            placeholder="Email" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>Password</b></label>
                        <input v-model="form.password" type="password" class="form-control form-control-sm"
                            placeholder="Enter Password" />
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>Branch code</b></label>
                        <input v-model="form.branch_code" type="text" class="form-control form-control-sm"
                            placeholder="Enter Branch Code" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>Application ID</b></label>
                        <input v-model="form.application_id" type="text" class="form-control form-control-sm"
                            placeholder="Enter Application ID" />
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>Application Secret</b></label>
                        <input v-model="form.application_secret" type="text" class="form-control form-control-sm"
                            placeholder="Enter Application Secret" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label"><b>End Point</b></label>
                        <input v-model="form.end_point" type="text" class="form-control form-control-sm"
                            placeholder="Enter End Point" />
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white border-0 pt-0">
                <button type="submit" class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                <router-link :to="{ name: 'apiManagement' }" class="m-2 btn btn-sm btn-danger px-4 ms-2 float-end">Back</router-link>
            </div>
        </form>
    </div>
</template>

<style scoped>
.api-create-card {
    border-radius: 10px;
}

.api-create-title {
    border-left: 4px solid #2f6fed;
    padding-left: 10px;
    font-weight: 600;
}

.credentials-header {
    background: #eef2f6;
    color: #4b5563;
    border-radius: 6px;
    padding: 10px 12px;
    text-align: center;
}

.api-create-loading {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.65);
    z-index: 5;
    border-radius: 10px;
}
</style>
