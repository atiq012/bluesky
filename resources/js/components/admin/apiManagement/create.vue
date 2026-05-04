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
    remarks: "",
    fields: [{ title: "", value: "" }],
});

function addField() {
    form.fields.push({ title: "", value: "" });
}

function removeField(index) {
    if (form.fields.length > 1) {
        form.fields.splice(index, 1);
    } else {
        form.fields[0] = { title: "", value: "" };
    }
}

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
                        <label class="form-label"><b>Remarks</b></label>
                        <textarea v-model="form.remarks" rows="4" class="form-control"
                            placeholder="Any details you want to attach"></textarea>
                    </div>

                    <div class="col-12">
                        <div class="fields-header small fw-semibold">Fields</div>
                    </div>

                    <div class="col-12" v-for="(field, index) in form.fields" :key="index">
                        <!-- <div class="row align-items-end field-item mt-3-">
                            <div class="col-12 col-md-5">
                                <label class="form-label"><b>Title</b></label>
                                <input v-model="field.title" type="text" class="form-control form-control-sm"
                                    placeholder="Enter Title" />
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label"><b>Value</b></label>
                                <input v-model="field.value" type="text" class="form-control form-control-sm"
                                    placeholder="Enter Value" />
                            </div>
                            <div class="col-12 col-md-2 d-flex justify-content-end">
                                <button type="button" @click="removeField(index)"
                                    class="btn btn-outline-danger btn-sm w-100">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div> -->

                        <div class="alert alert-secondary border-0 bg-secondary alert-dismissible fade show field-item">
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <!-- <label class="form-label"><b>Title</b></label> -->
                                    <input v-model="field.title" type="text" class="form-control form-control-sm"
                                        placeholder="Enter Title" />
                                </div>
                                <div class="col-12 col-md-6">
                                    <!-- <label class="form-label"><b>Value</b></label> -->
                                    <input v-model="field.value" type="text" class="form-control form-control-sm"
                                        placeholder="Enter Value" />
                                </div>
                            </div>
                            <button v-if="index != 0" @click="removeField(index)" type="button" class="close-btn-field">
                                <i class="fa fa-times" style="border: none; outline: none;"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-center pl-2">
                        <button type="button" @click="addField" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-plus me-1"></i> Add New Field
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white border-0 pt-0">
                <button type="submit" class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                <router-link :to="{ name: 'apiManagement' }"
                    class="m-2 btn btn-sm btn-danger px-4 ms-2 float-end">Back</router-link>
            </div>

        </form>
    </div>
</template>

<style scoped>
.close-btn-field {

    border-radius: 50%;
    background-color: #ff8f8fb3;
    position: absolute;
    top: 3px;
    right: 4px;
    z-index: 2;
    border: none;
    outline: none;
    box-shadow: none;
}

.api-create-card {
    border-radius: 10px;
}

.api-create-title {
    border-left: 4px solid #2f6fed;
    padding-left: 10px;
    font-weight: 600;
}

.fields-header {
    background: #eef2f6;
    color: #4b5563;
    border-radius: 6px;
    padding: 10px 12px;
    text-align: center;
}

.field-item {
    background: #fafbff;
    margin: 0 4px 0px 4px;
    padding: 10px 36px 10px 16px;
    border-radius: 10px;
    border: 1px solid #e4e9f3;
}

.field-item+.field-item {
    margin-top: 0.75rem;
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
