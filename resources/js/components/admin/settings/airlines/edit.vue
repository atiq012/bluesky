<script setup>
import { useAuthStore } from "../../../../stores/authStore";
import axiosInstance from "../../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
const props = defineProps(['id'])
const Loading = ref(true);
const previewImage = ref('');

const form = reactive({ name: "",airlines_id: '',airline_picture:'', code: "", country: "",airlines_business_type:"", useEmail: authStore.email });

async function update(props) {

    form.airlines_id = props.id;

    try {
        // const response = await axiosInstance.post("/airlines/update", form);

        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/airlines/update', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });
        Notification.showToast('s', response.data.message);

    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

const handleFileChange = (event) => {
    form.airline_picture = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.airline_picture);
    reader.onload = (e) => {
        previewImage.value = e.target.result;
    };
}

getAirLinesData(props);

async function getAirLinesData(props) {

    try {
        authStore.GlobalLoading = true;
        Loading.value = false;

        const response = await axiosInstance.post('editAirlines', { 'id': props });

        const name = response.data[0].name;
        const code = response.data[0].code;
        const country_name = response.data[0].country_name;
        const airline_business_type = response.data[0].airline_business_type;
        const logo_path = response.data[0].logo_path;
        // console.log(response.data[0]);

        form.name = name;
        form.code = code;
        form.country_name = country_name;
        form.airlines_business_type = airline_business_type;
        previewImage.value = logo_path
        authStore.GlobalLoading = false;
        Loading.value = true;
    } catch (error) {
        authStore.GlobalLoading = false;
        Loading.value = true;
    }
}

onMounted(() => {

});


</script>
<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Setting</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>

                    </li>
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'airlinesList' }">Airlines List</router-link>

                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Airlines Edit</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Airlines Edit</h5>
        </div>

        <div v-if="authStore.GlobalLoading" class="mt-2 center-body position-absolute top-50 start-50">
            <div class="loader-circle-57">
                <img class="position-absolute" src="../../../.../../../../../public/theme/appimages/blueskywings.png" height="22"
                    width="22" alt="">
            </div>
        </div>

        <form v-show="Loading" id="addZoneform">
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
                <button type="button" @click="update(props)"
                    class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Update</button>
                <button class="m-2 btn btn-sm btn-danger px-4 ms-2  float-end">Back</button>
            </div>
        </form>
    </div>
</template>
