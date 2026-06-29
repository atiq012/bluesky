<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';

import { useAuthStore } from "../../../stores/authStore";
import axiosInstance from "../../../axiosInstance"
import { ref, onMounted, reactive } from "vue";

const authStore = useAuthStore();
//**** create function start
const form = reactive({ cate_name: "", useEmail: authStore.email, type: "",parent_id: "" });


async function save() {

    try {
        const select2Value = $('#parent_id').val();

        // Update the form object with the latest Select2 value
        if (select2Value) {
            form.parent_id = select2Value;
        }
        const response = await axiosInstance.post("/category/save", form);
        // Reset the form properly
        form.cate_name = "";
        form.type = "";
        form.parent_id = "";

        // Reset Select2 properly
        $('#parent_id').val('').trigger('change');

        // Reset the select element to show only default option
        $('#parent_id').empty().append('<option selected value="">Select Category</option>');

        // Reset radio buttons
        $('input[name="types"][value="category"]').prop('checked', true);
        $('#parent_id option:first').prop('selected', true).trigger("change");
        Notification.showToast('s', response.data.message);
    } catch (error) {
        ErrorCatch.CatchError(error);
    }
}

onMounted(() => {

    $("#parent_id").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '100%',
        allowClear: true,
        height: '50',
    });
});
const getTypeValue = () => {
    const types = $('input[name="types"]:checked').val();

    if(types == 'category'){
        form.type = 'category';
    }else if(types == 'subcategory'){
        form.type = 'subcategory';
        getCate();
    }
};

async function getCate() {
    try {

        const response = await axiosInstance.get('categories');

        var options = [];
        $.each(response.data, function (key, value) {
            var obj = { id: value.id, text: value.name, bn: value.bn_name, clr: MF.getRandomColor() }
            options.push(obj);
        });

        $("#parent_id").select2({
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
                var $state = $('<div class="clearfix"><div class="float-start"> ' + state.text + '</div></div>');

                return $state;
            }
        });
        $('#parent_id').prepend('<option selected=""></option>');

    } catch (error) {
        // console.log(error);
    }
}

</script>
<template>
        <AppBreadcrumbs
        title="Setting"
        :back-to="{ name: 'categoryList' }"
        :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Help Desk', to: { name: 'helpDesk' } },
            { label: 'Category & Sub Category', to: { name: 'categoryList' } },
            { label: 'Create' },
        ]"
    />

    <div class="card">
        <div class="card-header">
            <h5 class="m-0 p-0" style="border-left:5px solid #7239ea;"> &nbsp; Create</h5>
        </div>

        <form id="addCateform">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center gap-3">
                            <label for="input1" class="form-label">Type</label>
                            <div class="form-check">
                                <input class="form-check-input" @change="getTypeValue" checked type="radio" value="category" name="types" id="flexRadioDefault1">
                                <label class="form-check-label" for="flexRadioDefault1">
                                    Category
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" @change="getTypeValue" value="subcategory" type="radio" name="types" id="flexRadioDefault1">
                                <label class="form-check-label" for="flexRadioDefault1">
                                    Sub Category
                                </label>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label for="input1" class="form-label">Name</label>
                        <input type="text" v-model="form.cate_name" class="form-control form-control-sm" id="cate_name"
                            name="cate_name" placeholder="Enter Name">
                    </div>
                    <div class="col-md-6" v-if="form.type == 'subcategory'">
                        <label for="input1" class="form-label">Parent Category</label>
                        <select  v-model="form.parent_id" id="parent_id" class="form-select form-select-sm parent_name"
                            aria-label="Default select example">
                            <option selected value="">Select Category</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" @click="save()"
                    class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                <button class="m-2 btn btn-sm btn-danger px-4 ms-2  float-end">Back</button>
            </div>
        </form>
    </div>
</template>
