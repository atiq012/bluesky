<script setup>
import AppBreadcrumbs from '../../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../../stores/authStore";
import axiosInstance from "../../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
//**** create function start
const form = reactive({ name: "",airline_picture:'', code: "", country: "",airlines_business_type:"", useEmail: authStore.email });


const previewImage = ref('');


const handleFileChange = (event) => {
    form.airline_picture = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.airline_picture);
    // console.log(reader.readAsDataURL(form.airline_picture));

    reader.onload = (e) => {
        previewImage.value = e.target.result;
    };
}

onMounted(() => {

});

async function save() {

    try {

        // const response = await axiosInstance.post("/Airlines/save", form);
        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/Airlines/save', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });
        previewImage.value ="";
        document.getElementById("addAirlinesform").reset();

        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}


</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'airlinesList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Settings', to: { name: 'airlinesList' } },
            { label: 'Airlines List', to: { name: 'airlinesList' } },
            { label: 'Create New Airlines' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Create New Airlines</h5>
        </div>

        <form id="addAirlinesform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label for="input1" class="form-label">Airlines Name</label>
                        <input type="text" v-model="form.name" class="form-control" id="name" name="name"
                            placeholder="Airlines Name">
                    </div>
                    <div class="col-md-4">
                        <label for="input1" class="form-label">Code</label>
                        <input type="text" v-model="form.code" class="form-control" id="code" name="code"
                            placeholder="Code">
                    </div>
                    <div class="col-md-4">
                        <label for="input1" class="form-label">Country</label>
                        <input type="text" v-model="form.country" class="form-control" id="country" name="country"
                            placeholder="Country">
                    </div>
                    <div class="col-md-4 mt-2">
                        <label for="input1" class="form-label">Business Type</label>
                        <input type="text" v-model="form.airlines_business_type" class="form-control" id="airlines_business_type" name="airlines_business_type"
                            placeholder="Business Type">
                    </div>

                    <div class="col-md-4 mt-2">
                        <label for="input1" class="form-label">Logo Upload</label>

                        <input type="file" id="profile-picture" ref="profilePicture" class="w-1/2"
                            @change="handleFileChange" accept="image/*">
                    </div>

                    <div class="col-md-4 mt-2">
                        <label for="input1" class="form-label">Preview Logo</label>

                        <div class="mb-3 pt-0 mx-auto">
                            <img v-if="previewImage" :src="previewImage" height="60" width="60"
                                class="border border-1 rounded rounded-2" alt="Profile Picture">
                            <img v-else src="../../../.../../../../../public/theme/appimages/Plane_origin.svg" height="60" width="60"
                                class="border border-1 rounded rounded-2" alt="Profile Picture">
                        </div>
                    </div>

                </div>
            </div>
            <div class="card-footer">
                <button type="button" @click="save()"
                    class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                <button class="m-2 btn btn-sm btn-danger px-4 ms-2  float-end" >Back</button>
            </div>
        </form>
    </div>
</template>

