<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { ref, onMounted, reactive } from "vue";
import axiosInstance from "../../../axiosInstance"
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { useAuthStore } from "../../../stores/authStore";

const form = reactive({
    name: "", email: "", country: "", address: "", established_date: "", postal_code: "", ca_number: "", iata_number: "", agent_code: "", phone: "", city: "", zone: "", reg_number: "", fax: "", trade_licence: "", hajj_no: '', ownername: "", nid_number: "", email_address: "", designation: "", dob: "", owner_phone: "", kam_ids: "", remarks: ""
});

const previewImage = ref('');
const trade_licence_Image = ref('');
const ca_Image = ref('');
const Iata_Image = ref('');
const Hajj_Image = ref('');
const TIN_Image = ref('');
const NID_Image = ref('');
const owner_profile_img = ref('');


const isAgencyDetails = ref(true);
const isAgencyDocument = ref(false);
const isAgencyUserInfo = ref(false);
const isAssignKam = ref(false);
const classAgencyActive = ref(true);
const classDocActive = ref(false);
const classDocupActive = ref(false);
const classKAMActive = ref(false);
const state_iata = ref(false);
const state_hajj = ref(false);

onMounted(() => {

    $('#country').on("change", function () {
        form.country = $(this).val();
    });

    $('#zone').on("change", function () {
        form.zone = $(this).val();
    });
    $('#district').on("change", function () {
        form.city = $(this).val();
    });

    $('.kam_ids').on("change", function () {
        form.kam_ids = $(this).val();
    });

});

function nextPhase(id) {
    if (id == 1) {
        this.isAgencyDetails = false;
        this.isAgencyDocument = true;
        this.classAgencyActive = false;
        this.classDocActive = true;
        window.scrollTo(0, 0);

    }
    else if (id == 2) {
        this.isAgencyDocument = false;
        this.isAgencyUserInfo = true;
        this.classDocActive = false;
        this.classDocupActive = true;
        window.scrollTo(0, 0);

    }
    else if (id == 3) {
        getKam();
        this.isAgencyUserInfo = false;
        this.isAssignKam = true;
        this.classKAMActive = true;
        this.classDocupActive = false;
    }

    else if (id == 44) {
        getDistrict();
        getZone();
        this.isAgencyDetails = true;
        this.isAgencyDocument = false;
        this.classAgencyActive = true;
        this.classDocActive = false;

    } else if (id == 42) {

        this.isAgencyDocument = true;
        this.isAgencyUserInfo = false;

        this.classDocupActive = false;
        this.classDocActive = true;

    }

    else if (id == 40) {

        this.isAgencyUserInfo = true;
        this.isAssignKam = false;
        this.classDocupActive = true;
        this.classKAMActive = false;
    }
    else if (id == 4) {
        save();

    }
}

async function save() {

    try {
        const authStore = useAuthStore();
        const accessToken = authStore.decryptWithAES(authStore.token);
        const response = await axios.post('/api/agent/save', form, {
            headers: {
                'Content-Type': 'multipart/form-data',
                Authorization: "Bearer " + accessToken,
                Accept: "application/json",

            },
        });
        document.getElementById("addAgentForm").reset();
        Notification.showToast('s', response.data.message);
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

function iata_non_iata(value_stage) {
    if (value_stage == 1) {
        return this.state_iata = true;
    } else {
        return this.state_iata = false;
    }
}

function hajjNonHajj(value_stage) {
    if (value_stage == 1) {
        return this.state_hajj = true;
    } else {
        return this.state_hajj = false;
    }
}

// agency image
const handleFileChange = (event) => {
    form.agency_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.agency_img);

    reader.onload = (e) => {
        previewImage.value = e.target.result;
    };
}

// trade licence
const handleTradeLicenceFileChange = (event) => {

    form.trade_licence_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.trade_licence_img);

    reader.onload = (e) => {
        trade_licence_Image.value = e.target.result;
    };
}

// civil aviation
const handleCivilAviationFileChange = (event) => {

    form.ca_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.ca_img);

    reader.onload = (e) => {
        ca_Image.value = e.target.result;
    };
}

// iata
const handleIataFileChange = (event) => {

    form.iata_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.iata_img);

    reader.onload = (e) => {
        Iata_Image.value = e.target.result;
    };
}

// hajj
const handleHajjLicenseFileChange = (event) => {

    form.hajj_licence_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.hajj_licence_img);

    reader.onload = (e) => {
        Hajj_Image.value = e.target.result;
    };
}
// tin
const handleTINFileChange = (event) => {

    form.tin_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.tin_img);

    reader.onload = (e) => {
        TIN_Image.value = e.target.result;
    };
}

// nid

const handleNIDFileChange = (event) => {

    form.nid_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.nid_img);

    reader.onload = (e) => {
        NID_Image.value = e.target.result;
    };
}

//owner image

const handleOwnerProfileFileChange = (event) => {

    form.owner_pro_img = event.target.files[0];
    const reader = new FileReader();
    reader.readAsDataURL(form.owner_pro_img);

    reader.onload = (e) => {
        owner_profile_img.value = e.target.result;
    };
}


getDistrict();

async function getDistrict() {
    try {
        const response = await axiosInstance.get('divisions');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name, bn: value.bn_name, clr: MF.getRandomColor() }
            options.push(obj);

        });

        $("#district").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
            tags: true,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }

                var $state = $('<div class="clearfix"><div class="float-start">' + state.text + '</div><div class="float-end">' + state.bn + '</div></div>');
                return $state;
            }
        });
        $('#district').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);

    }
}

getZone();

async function getZone() {
    try {
        const response = await axiosInstance.get('getAllOffLoc');
        console.log(response.data);

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);

        });

        $("#zone").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
            tags: true,
            templateResult: function (state) {
                if (!state.id) {

                    return state.text;
                }

                var $state = $('<div class="clearfix"><div class="float-start">' + state.text + '</div></div>');
                return $state;
            }
        });
        $('#zone').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);

    }
}

getKam();

async function getKam() {
    try {
        const response = await axiosInstance.get('getKam');
        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name }
            options.push(obj);
        });

        $("#kam_ids").select2({
            placeholder: '=Select=',
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            height: '50',
            data: options,
            tags: true,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }
                var $state = $('<div class="clearfix"><div class="float-start">' + state.text + '</div></div>');
                return $state;
            }
        });
        $('#kam_ids').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);
    }
}
</script>

<template>
        <AppBreadcrumbs
        title="B2B Agent"
        :back-to="{ name: 'AgentList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'B2B Agent List', to: { name: 'AgentList' } },
            { label: 'Create Agent' },
        ]"
    />

    <form id="addAgentForm">
        <div class="card" style="box-shadow: none !important;">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3" style="background-color: #5087f42b;">
                        <div id="stepper3" class="bs-stepper gap-4 vertical linear">
                            <div class="left-side-pro-bar" role="tablist">
                                <div class="step" :class="{ active: classAgencyActive }" data-target="#test-vl-1">
                                    <div class="step-trigger" role="tab" id="stepper3trigger1" aria-controls="test-vl-1"
                                        aria-selected="true">
                                        <div class="bs-stepper-circle"><i class="lni lni-pointer "></i></div>
                                        <div class="">
                                            <h5 class="mb-0 steper-title">Agency Details</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="vl"></div>
                                <div class="step" :class="{ active: classDocActive }" data-target="#test-vl-2">
                                    <div class="step-trigger" role="tab" id="stepper3trigger2" aria-controls="test-vl-2"
                                        aria-selected="false" disabled="disabled">
                                        <div class="bs-stepper-circle"><i class="bx bx-file fs-4"></i></div>
                                        <div class="">
                                            <h5 class="mb-0 steper-title">Upload Docs</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="vl"></div>

                                <div class="step" :class="{ active: classDocupActive }" data-target="#test-vl-3">
                                    <div class="step-trigger" role="tab" id="stepper3trigger3" aria-controls="test-vl-3"
                                        aria-selected="false" disabled="disabled">
                                        <div class="bs-stepper-circle">
                                            <i class="bx bx-user fs-4"></i>
                                        </div>
                                        <div class="">
                                            <h5 class="mb-0 steper-title">Primary User</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="vl"></div>

                                <div class="step" :class="{ active: classKAMActive }" data-target="#test-vl-4">
                                    <div class="step-trigger" role="tab" id="stepper3trigger4" aria-controls="test-vl-4"
                                        aria-selected="false" disabled="disabled">
                                        <div class="bs-stepper-circle"><i class="bx bx-briefcase fs-4"></i></div>
                                        <div class="">
                                            <h5 class="mb-0 steper-title">KAM & Remarks</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-md-8" style="margin-left: 20px;">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mt-2" v-if="isAgencyDetails">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-12 col-lg-12">
                                                    <label for="name" class="form-label">Name</label>
                                                    <input type="text" class="form-control form-control-sm" id="name"
                                                        name="name" v-model="form.name" placeholder="Enter Name">
                                                </div>

                                                <div class="col-12 col-lg-12 mt-2">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="text" class="form-control form-control-sm" id="email"
                                                        v-model="form.email" name="email" placeholder="Enter Email">
                                                </div>
                                                <div class="col-12 col-lg-12 mt-2 mt-2">
                                                    <label for="address" class="form-label">Address</label>

                                                    <textarea class="form-control form-control-sm" cols="1" rows="4"
                                                        name="address" v-model="form.address" id="address"
                                                        placeholder="Enter Address"></textarea>
                                                </div>

                                                <div class="col-12 col-lg-12 mt-2">
                                                    <label for="country" class="form-label">Country</label>
                                                    <select class="form-select form-select-sm" name="country"
                                                        v-model="form.country" id="country"
                                                        aria-label="Default select example">
                                                        <option value="" selected>Select Country</option>
                                                        <option value="Bangladesh">Bangladesh</option>
                                                    </select>
                                                </div>



                                                <div class="col-12 col-md-12 mt-2">
                                                    <label for="date" class="form-label">Established Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="edate"
                                                        name="established_date" v-model="form.established_date"
                                                        placeholder="Date of Birth">
                                                    <div class="invalid-feedback">
                                                        Please select date.
                                                    </div>
                                                </div>

                                                <div class="col-12 col-lg-12 mt-2">
                                                    <label for="postal_code" class="form-label">Postal Code</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        id="postal_code" v-model="form.postal_code" name="postal_code"
                                                        placeholder="Enter Postal Code">
                                                </div>

                                                <div class="col-12 col-lg-12 mt-2">
                                                    <label for="postal_code" class="form-label">Civil Aviation
                                                        Certificate
                                                        Number</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        id="ca_number" v-model="form.ca_number"
                                                        placeholder="Enter Postal Code">
                                                </div>


                                                <div class="col-12 col-lg-12 d-flex align-items-center gap-3 mt-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="iata_non_iata" @change="iata_non_iata(1)" value="1"
                                                            id="flexRadioDefault1">
                                                        <label class="form-check-label" for="flexRadioDefault1">
                                                            IATA
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="iata_non_iata" @change="iata_non_iata(2)" value="2"
                                                            id="flexRadioSuccess">
                                                        <label class="form-check-label" for="flexRadioSuccess">
                                                            Non-IATA
                                                        </label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="iata_non_iata" @change="iata_non_iata(3)" value="3"
                                                            id="flexRadioInfo">
                                                        <label class="form-check-label" for="flexRadioInfo">
                                                            Corporate
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-12 mt-2" v-if="state_iata">
                                                    <input type="text" v-model="form.iata_number"
                                                        class="form-control form-control-sm" id="iata_number"
                                                        placeholder="Enter IATA License Number">
                                                </div>

                                                <div class="col-12 col-md-12 mt-2">
                                                    <label for="agent_code" class="form-label">Company Logo (Max
                                                        2Mb)</label>

                                                    <div class="input-group mb-3">
                                                        <input type="file" @change="handleFileChange"
                                                            class="form-control" id="inputGroupFile02">

                                                        <label class="input-group-text"
                                                            for="inputGroupFile02">Upload</label>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-12 col-lg-12">
                                                    <label for="agent_code" class="form-label">Agency Code</label>
                                                    <input type="text" v-model="form.agent_code"
                                                        class="form-control form-control-sm" name="agent_code"
                                                        id="agent_code" placeholder="Enter Agency Code">
                                                </div>

                                                <div class="col-12 col-md-12 mt-2">
                                                    <label for="date" class="form-label">Phone</label>
                                                    <input type="text" v-model="form.phone"
                                                        class="form-control form-control-sm" id="phone" name="phone"
                                                        placeholder="Enter Phone Number">
                                                </div>

                                                <div class="col-12 col-lg-12 mt-2">
                                                    <label for="district" class="form-label">District</label>
                                                    <select class="form-select form-select-sm" name="district"
                                                        v-model="form.city" id="district"
                                                        aria-label="Default select example">
                                                        <option value="" selected>Select District</option>
                                                    </select>
                                                </div>

                                                <div class="col-12 col-lg-12 mt-2">
                                                    <label for="zone" class="form-label">Zone</label>
                                                    <select class="form-select form-select-sm" name="zone"
                                                        v-model="form.zone" id="zone"
                                                        aria-label="Default select example">
                                                        <option value="" selected>Select Zone</option>
                                                    </select>
                                                </div>

                                                <div class="col-12 col-md-12 mt-2">
                                                    <label for="registration" class="form-label">Registration
                                                        Number</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                        id="reg_number" name="reg_number" v-model="form.reg_number"
                                                        placeholder="Enter Registration Number">
                                                </div>

                                                <div class="col-12 col-md-12 mt-2">
                                                    <label for="fax" class="form-label">Fax</label>
                                                    <input type="text" name="fax" v-model="form.fax"
                                                        class="form-control form-control-sm" id="fax"
                                                        placeholder="Enter Fax">
                                                </div>

                                                <div class="col-12 col-md-12 mt-2">
                                                    <label for="trade_licence" class="form-label">Trade Licence</label>
                                                    <input type="text" name="trade_licence" v-model="form.trade_licence"
                                                        class="form-control form-control-sm" id="trade_licence"
                                                        placeholder="Enter Trade Licence">
                                                </div>

                                                <div class="col-12 col-lg-12 d-flex align-items-center gap-3 mt-2">

                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="hajjNonHajj"
                                                            @change="hajjNonHajj(1)" id="flexRadioDefault1">
                                                        <label class="form-check-label" for="flexRadioDefault1">
                                                            Hajj
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="hajjNonHajj"
                                                            @change="hajjNonHajj(2)" id="flexRadioSuccess">
                                                        <label class="form-check-label" for="flexRadioSuccess">
                                                            Non-Hajj
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-12 mt-2" v-show="state_hajj">
                                                    <input type="text" class="form-control form-control-sm" id="hajn"
                                                        v-model="form.hajj_no" placeholder="Enter Number">
                                                </div>

                                                <div v-if="previewImage" class="d-flex align-items-center mt-4">
                                                    <img :src="previewImage" height="60" width="60"
                                                        class="border border-1 rounded rounded-2" alt="Profile Picture">

                                                    <div class="flex-grow-1 ms-3">
                                                        <p class="mb-0"><i
                                                                class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                            Travel logo uploaded successfully.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <!-- mobile version -->
                                        <div class="col-12 mt-3	d-lg-none">
                                            <button class="btn btn-primary btn-sm px-4 ms-2"
                                                v-on:click="nextPhase(1)">Next</button>
                                        </div>
                                        <!-- end mobile version -->
                                        <div class="col-12 col-lg-6 offset-10 d-none d-lg-block">
                                            <button class="btn btn-primary btn-sm px-4 ms-2"
                                                v-on:click="nextPhase(1)">Next</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="row" v-if="isAgencyDocument">
                                    <p>Upload Your Documents <br> <span style="font-size: 12px;">Supported Formats
                                            :Jpeg,Png or Pdf | Max File Size : 2MB</span></p>

                                    <!-- Trade Licence -->
                                    <div class="col-md-3 d-flex justify-content-center m-1 flex-column"
                                        style="width: 250px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: black; height: 100px; background-color: transparent; border-radius: 3px; border: 2px dotted #7785f4">
                                        <div v-if="trade_licence_Image" class="d-inline text-center">
                                            <img id="tl_doc_output" class="rounded" :src="trade_licence_Image"
                                                height="40" width="40" alt="">

                                        </div>
                                        <div v-else class="d-inline text-center">
                                            <img id="doc_output" class="rounded" src="/public/theme/appimages/rqf.png"
                                                height="40" width="40" alt="">
                                        </div>

                                        <div class="d-inline text-center">Trade License</div>
                                        <label for="tl" role="button" class="d-inline text-center">
                                            Choose File <b style="color: #7C1843">Here</b></label>
                                        <input type="file" id="tl" form="myform" name="trade_licence_img"
                                            style="display: none" @change="handleTradeLicenceFileChange">

                                    </div>
                                    <!-- ./End Trade Licence -->


                                    <!-- Civil Aviation Certificate -->
                                    <div class="col-md-3 d-flex justify-content-center m-1 flex-column"
                                        style="width: 250px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: black; height: 100px; background-color: transparent; border-radius: 3px; border: 2px dotted #7785f4">


                                        <div v-if="ca_Image" class="d-inline text-center">
                                            <img id="doc_output" class="rounded" :src="ca_Image" height="40" width="40"
                                                alt="">
                                        </div>
                                        <div v-else class="d-inline text-center">
                                            <img id="doc_output" class="rounded" src="/public/theme/appimages/rqf.png"
                                                height="40" width="40" alt="">
                                        </div>

                                        <div class="d-inline text-center">Civil Aviation Certificate</div>
                                        <label for="ca_certificate" role="button" class="d-inline text-center">
                                            Choose File <b style="color: #7C1843">Here</b></label>
                                        <input type="file" @change="handleCivilAviationFileChange" id="ca_certificate"
                                            form="myform" name="ca_certificate_img" style="display: none">
                                    </div>

                                    <!-- ./End Civil Aviation Certificate -->

                                    <!-- IATA Certificate -->
                                    <div class="col-md-3 d-flex justify-content-center m-1 flex-column"
                                        style="width: 250px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: black; height: 100px; background-color: transparent; border-radius: 3px; border: 2px dotted #7785f4">
                                        <div v-if="Iata_Image" class="d-inline text-center">
                                            <img id="doc_output" class="rounded" :src="Iata_Image" height="40"
                                                width="40" alt="">
                                        </div>
                                        <div v-else class="d-inline text-center">
                                            <img id="doc_output" class="rounded" src="/public/theme/appimages/rqf.png"
                                                height="40" width="40" alt="">
                                        </div>

                                        <div class="d-inline text-center">IATA Certificate</div>
                                        <label for="iata" role="button" class="d-inline text-center">
                                            Choose File <b style="color: #7C1843">Here</b></label>
                                        <input type="file" id="iata" @change="handleIataFileChange" form="myform"
                                            name="iata_img" style="display: none">
                                    </div>

                                    <!-- ./end IATA Certificate -->


                                    <!-- Hajj License -->
                                    <div class="col-md-3 d-flex justify-content-center m-1 flex-column"
                                        style="width: 250px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: black; height: 100px; background-color: transparent; border-radius: 3px; border: 2px dotted #7785f4">
                                        <div v-if="Hajj_Image" class="d-inline text-center">
                                            <img id="doc_output" class="rounded" :src="Hajj_Image" height="40"
                                                width="40" alt="">

                                        </div>
                                        <div v-else class="d-inline text-center">
                                            <img id="doc_output" class="rounded" src="/public/theme/appimages/rqf.png"
                                                height="40" width="40" alt="">
                                        </div>

                                        <div class="d-inline text-center">Hajj License</div>
                                        <label for="hajj" role="button" class="d-inline text-center">
                                            Choose File <b style="color: #7C1843">Here</b></label>
                                        <input type="file" @change="handleHajjLicenseFileChange" id="hajj" form="myform"
                                            name="hajj_licence_img" style="display: none">
                                    </div>

                                    <!-- ./End Hajj License  -->

                                    <!-- TIN -->
                                    <div class="col-md-3 d-flex justify-content-center m-1 flex-column"
                                        style="width: 250px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: black; height: 100px; background-color: transparent; border-radius: 3px; border: 2px dotted #7785f4">

                                        <div v-if="TIN_Image" class="d-inline text-center">
                                            <img id="doc_output" class="rounded" :src="TIN_Image" height="40" width="40"
                                                alt="">

                                        </div>
                                        <div v-else class="d-inline text-center">
                                            <img id="doc_output" class="rounded" src="/public/theme/appimages/rqf.png"
                                                height="40" width="40" alt="">
                                        </div>

                                        <div class="d-inline text-center">TIN</div>
                                        <label for="tin" role="button" class="d-inline text-center">
                                            Choose File <b style="color: #7C1843">Here</b></label>
                                        <input type="file" @change="handleTINFileChange" id="tin" form="myform"
                                            name="tin_img" style="display: none">
                                    </div>

                                    <!-- ./End TIN -->

                                    <!--    NID -->
                                    <div class="col-md-3 d-flex justify-content-center m-1 flex-column"
                                        style="width: 250px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: black; height: 100px; background-color: transparent; border-radius: 3px; border: 2px dotted #7785f4">
                                        <div v-if="NID_Image" class="d-inline text-center">
                                            <img id="doc_output" class="rounded" :src="NID_Image" height="40" width="40"
                                                alt="">

                                        </div>
                                        <div v-else class="d-inline text-center">
                                            <img id="doc_output" class="rounded" src="/public/theme/appimages/rqf.png"
                                                height="40" width="40" alt="">

                                        </div>

                                        <div class="d-inline text-center">NID</div>
                                        <label for="nid" role="button" class="d-inline text-center">
                                            Choose File <b style="color: #7C1843">Here</b></label>
                                        <input type="file" id="nid" @change="handleNIDFileChange" form="myform"
                                            name="nid_img" style="display: none">
                                    </div>

                                    <!-- ./End NID -->


                                </div>

                                <div class="row mt-5" v-if="isAgencyDocument">
                                    <!-- mobile version -->
                                    <div class="col-12 	d-lg-none">
                                        <div class="d-flex align-items-center gap-3">
                                            <button class="btn btn-sm btn-secondary px-4 ms-2 float-left"
                                                v-on:click="nextPhase(44)">Back</button>

                                            <button class="btn btn-primary btn-sm px-4 ms-2 float-right"
                                                v-on:click="nextPhase(2)">Next</button>
                                        </div>
                                    </div>
                                    <!-- end mobile version -->

                                    <!-- big screen -->
                                    <div class="col-sm-2 col-md-2 col-lg-1 d-none d-lg-block">
                                        <button class="btn btn-sm btn-secondary px-4 ms-2"
                                            v-on:click="nextPhase(44)">Back</button>
                                    </div>
                                    <div class="col-sm-8 col-md-9 d-none d-lg-block" v-if="isAgencyDocument"></div>
                                    <div class="col-sm-2 col-md-2  col-lg-1 d-none d-lg-block" v-if="isAgencyDocument">
                                        <button class="btn btn-primary btn-sm px-4 ms-2"
                                            v-on:click="nextPhase(2)">Next</button>
                                    </div>
                                    <!-- end big screen -->

                                </div>

                                <div class="row" v-if="isAgencyUserInfo">
                                    <div class="col-md-6">
                                        <div class="row">
                                            <div class="col-12 col-lg-12">
                                                <label for="ownername" class="form-label">Owner Name</label>
                                                <input type="text" class="form-control form-control-sm" id="ownername"
                                                    placeholder="Enter Owner Name" v-model="form.ownername">
                                            </div>
                                            <div class="col-12 col-lg-12 mt-2">
                                                <label for="nidNum" class="form-label">NID Number</label>
                                                <input type="number" class="form-control form-control-sm" id="nidNum"
                                                    placeholder="Enter NID Number" v-model="form.nid_number">
                                            </div>
                                            <div class="col-12 col-lg-12 mt-2">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control form-control-sm" id="email"
                                                    placeholder="Enter Email" v-model="form.email_address">
                                            </div>
                                            <div class="col-12 col-md-12 mt-2"><label for="pro_img"
                                                    class="form-label">Profile Image (Max 2Mb)</label>
                                                <div class="input-group mb-3">
                                                    <input type="file" class="form-control" id="owner_pro_img"
                                                        name="owner_pro_img" @change="handleOwnerProfileFileChange">
                                                    <label class="input-group-text" for="owner_pro_img">Upload</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row">
                                            <div class="col-12 col-lg-12">
                                                <label for="desg" class="form-label">Designation</label>
                                                <input type="text" class="form-control form-control-sm" id="desg"
                                                    placeholder="Enter Designation" v-model="form.designation">
                                            </div>
                                            <div class="col-12 col-lg-12 mt-2">
                                                <label for="dob" class="form-label">Date of Birth</label>
                                                <input type="date" class="form-control form-control-sm" id="dob"
                                                    placeholder="Enter Date of Birth" v-model="form.dob">
                                            </div>
                                            <div class="col-12 col-lg-12 mt-2">
                                                <label for="phone" class="form-label">Phone</label>
                                                <input type="phone" class="form-control form-control-sm" id="phone"
                                                    placeholder="Enter Phone Number" v-model="form.owner_phone">
                                            </div>

                                            <div v-if="owner_profile_img" class="d-flex align-items-center mt-4"><img
                                                    :src="owner_profile_img" class="rounded-circle p-1 border"
                                                    width="60" height="60" alt="...">
                                                <div class="flex-grow-1 ms-3">
                                                    <p class="mb-0"><i
                                                            class="btn-outline-success rounded-circle fa fa-circle-check"></i>
                                                        Profile image uploaded successfully.</p>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-5" v-if="isAgencyUserInfo">
                                    <!-- mobile version -->
                                    <div class="col-12 	d-lg-none">
                                        <div class="d-flex align-items-center gap-3">
                                            <button class="btn btn-sm btn-secondary px-4 ms-2"
                                                v-on:click="nextPhase(42)">Back</button>

                                            <button class="btn btn-primary btn-sm px-4 ms-2 float-right"
                                                v-on:click="nextPhase(3)">Next</button>
                                        </div>
                                    </div>
                                    <!-- end mobile version -->



                                    <div class="col-12 col-lg-1 d-none d-lg-block">
                                        <button class="btn btn-sm btn-secondary px-4 ms-2"
                                            v-on:click="nextPhase(42)">Back</button>
                                    </div>
                                    <div class="col-md-9 d-none d-lg-block" v-if="isAgencyUserInfo"></div>
                                    <div class="col-12 col-lg-1 d-none d-lg-block" v-if="isAgencyUserInfo">
                                        <button class="btn btn-primary btn-sm px-4 ms-2"
                                            v-on:click="nextPhase(3)">Next</button>
                                    </div>
                                </div>

                                <div class="row" v-if="isAssignKam">

                                    <div class="col-12 col-lg-6">
                                        <label for="kam" class="form-label">KAM</label>

                                        <select class="form-select form-select-sm kam_ids" name="kam_ids"
                                            v-model="form.kam_ids" id="kam_ids" aria-label="Default select example">
                                            <option value="" selected>Select KAM</option>
                                        </select>
                                    </div>

                                    <div class="col-12 col-lg-6">
                                        <label for="remarks" class="form-label">Remarks</label>
                                        <textarea name="remarks" id="remarks" placeholder="Enter Short Note"
                                            class="form-control from-control-sm" v-model="form.remarks"></textarea>
                                    </div>

                                </div>

                                <div class="row mt-5" v-if="isAssignKam">
                                    <!-- mobile version -->
                                    <div class="col-12 	d-lg-none">
                                        <div class="d-flex align-items-center gap-3">
                                            <button class="btn btn-sm btn-secondary px-4 ms-2"
                                                v-on:click="nextPhase(40)">Back</button>

                                            <button class="btn btn-primary btn-sm px-4 ms-2 float-right"
                                                v-on:click="nextPhase(4)">Submit</button>
                                        </div>
                                    </div>
                                    <!-- end mobile version -->

                                    <div class="col-12 col-lg-1 d-none d-lg-block">
                                        <button class="btn btn-sm btn-secondary px-4 ms-2" v-if="isAssignKam"
                                            v-on:click="nextPhase(40)">Back</button>
                                    </div>
                                    <div class="col-md-9 d-none d-lg-block"></div>
                                    <div class="col-12 col-lg-1 d-none d-lg-block">
                                        <a class="btn btn-primary btn-sm px-4 ms-2" v-if="isAssignKam"
                                            v-on:click="nextPhase(4)">Submit</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </form>



</template>

<style>
.form-check-input[type=radio] {
    border-color: #8556ea;
}
.vl {
    border-left: 6px solid #fff;
    height: 80px;
    margin: -16px 0px -16px 18px;
}

.background-color-left-side-row {
    padding: 0px;
}

.background-color-left-side {
    background-color: #e4e5fe;
    margin: 0 0 0 15px;
    border-radius: 10px;
    min-height: 776px;
}

.left-side-pro-bar {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    margin: 0;
    padding: 1rem;
    border-bottom: none;
    height: auto;
}

@media only screen and (max-width: 600px) {
    .background-color-left-side-row {
        padding: 0 10px 0 10px;
    }

    .background-color-left-side {
        background-color: #e4e5fe;
        margin: 0 0px 0 0px;
        border-radius: 10px;
        min-height: 500px;

    }
}
</style>
