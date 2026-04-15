<script setup>
import DataTable from "datatables.net-vue3";
import DataBS5 from "datatables.net-bs5";
import Buttons from 'datatables.net-buttons';
import axiosInstance from "../../../axiosInstance";
import { ref, onMounted, reactive, onBeforeUnmount } from "vue";
// editor
import Quill from 'quill';
import "quill/dist/quill.core.css";
import 'quill/dist/quill.snow.css'
// end editor

import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../../stores/authStore';
const authStore = useAuthStore();

DataTable.use(DataBS5);
DataTable.use(Buttons);

const rData = ref([]);
var regExSearch = ref();
getListValues();

const options = {
    responsive: true,
    pageLength: 30,
    lengthMenu: [3, 10, 20, 30],
    bDestroy: true,
    ordering: false,
    layout: {
        topStart: {
            buttons: [
                {
                    text: 'Create new record',
                    action: function () {
                        // Create new record
                        editor.create({
                            title: 'Create new record',
                            buttons: 'Add'
                        });
                    }
                }
            ]
        }
    },
    dom: "<'row'<'col-sm-4'B><'d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto'f>>" + "<'row'<'col-sm-12'tr>>" +
        "<'row justify-content-between Reduct_table_gap'<'d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto'i><'d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto'p>>",
    buttons: ['copy', 'csv', 'pdf', 'excel', 'print'],
    language: {
        search: "",
        searchPlaceholder: "Search by anything",
    },
    columnDefs: [{
        defaultContent: "0",
        targets: "_all",
    }],
    columns: [
        // { data: "DT_RowIndex", title: "SL" },
        {
            title: "ID",
            render: function (data, type, row) {
                var html = "";
                html += row.request_number;

                return html;
            },
        },

        {
            title: "Subject",
            render: function (data, type, row) {
                var html = "";
                html += row.subject;

                return html;
            },
        },

        {
            title: "Category & Priority",
            render: function (data, type, row) {
                var html = "";
                html += row.category_name;
                html += "<br>";

                html += '<span class="text-primary">';
                html += row.priority + "</span>";
                return html;
            },
        },
        {
            title: "Requester",
            render: function (data, type, row) {
                var html = "";
                html += row.requester_name || "-";
                html += "<br>";

                html += '<span class="text-primary">';
                html += row.created_at + "</span>";

                return html;
            },
        },
        {
            title: "Assigned To",
            render: function (data, type, row) {
                var html = "";
                html += row.assigned_to_name || "-";

                return html;
            },
        },
        {
            title: "Status",
            render: function (data, type, row) {
                var html = "";

                if (row.status == "closed") {
                    html += '<div class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i> ' + row.status + ' </div>';
                }
                else if (row.status == "open") {
                    html += '<div class="badge rounded-pill text-primary bg-light-primary p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i> ' + row.status + ' </div>';
                }
                else if (row.status == "In Progress") {
                    html += '<div class="badge rounded-pill text-info bg-light-info p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i> ' + row.status + ' </div>';
                }
                else if (row.status == "on hold") {
                    html += '<div class="badge rounded-pill text-danger bg-light-danger p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i> ' + row.status + ' </div>';
                }
                else if (row.status == "Cancelled") {
                    html += '<div class="badge rounded-pill text-secondary bg-light-secondary p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i> ' + row.status + ' </div>';
                }
                else if (row.status == "Resolved") {
                    html += '<div class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i> ' + row.status + ' </div>';
                }
                else {
                    html += '<div class="badge rounded-pill text-warning bg-light-warning p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i> ' + row.status + ' </div>';
                }


                return html;
            },
        },
        {
            title: "Action",
            render: function (data, type, row) {
                var html = "";
                var idd = row.idd;
                var status = row.status;
                html += '<div class="d-flex">';

                html += '<button data-bs-toggle="offcanvas" data-bs-target="#staticBackdrop" style="size: 30px; width: 30px; height: 30px; " class="btn btn-outline-only-edit rounded-circle details-item" placement="top" data-item-id=' + idd + '> <i class="fa-solid fa-file" style="margin: 0px 0px 8px -2px; font-size: 14px;"></i> </button>';

                // need add field for check edit permission
                html += '<button  style="size: 30px; width: 30px; height: 30px;margin-left: 5px;" class="btn btn-outline-only-edit rounded-circle edit-item" placement="top" data-item-id=' + idd + '> <i class="fa-solid fa-pencil" style="margin: 0px 0px 10px -5px; font-size: 14px;"></i> </button>';

                html += '<button  style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-only-edit rounded-circle assign-item-id" data-bs-toggle="modal" data-bs-target="#exampleScrollableModal" placement="top" data-item-id=' + idd + '> <i class="fa-solid fa-user-tie" style="margin: 0px 0px 10px -3px; font-size: 14px;"></i> </button>';


                html += '<button  style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-only-edit rounded-circle status-item-id" data-bs-toggle="modal" data-bs-target="#statusChangeModal" placement="top" data-item-id=' + idd + '> <i class="fa-solid fa-recycle" style="margin: 1px 0px 7px -4px; font-size: 14px;"></i> </button>';



                html += '</div>';

                // 2nd row


                html += '<div class="d-flex mt-1">';

                html += '<button  style="size: 30px; width: 30px; height: 30px" class="btn btn-outline-action-log rounded-circle" data-item-id=' + idd + '> <i class="fa-solid fa-clock-rotate-left" style="margin: 2px 0px 10px -5px; font-size: 14px;"></i> </button>';

                html += '<button style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-danger rounded-circle delete-item" data-item-id=' + idd + '> <i class="fa-solid fa-trash" style="margin: 2px 0px 10px  -4px; font-size: 14px;"></i> </button>';

                html += '</div>';

                return html;
            },
        }
    ],
    "drawCallback": function (settings) {
        // edit function
        $(".edit-item").on('click', function (e) {

            var itemIdd = $(this).attr('data-item-id');

            router.push({ name: 'requestEdit', params: { ids: itemIdd } });
        });
        $(".details-item").on('click', function (e) {

            var itemIdd = $(this).attr('data-item-id');

            ticketDetails(itemIdd);

        });

        $(".assign-item-id").on('click', function (e) {

            var itemIdd = $(this).attr('data-item-id');

            assignform.idd = itemIdd;
        });

        $(".status-item-id").on('click', function (e) {

            var itemIdd = $(this).attr('data-item-id');

            changeStatusform.idd = itemIdd;
        });

        // delete function
        $(".delete-item").on('click', function (e) {
            var idd = $(this).attr('data-item-id');

            // delete pop up message

            iziToast.question({
                timeout: 100000,
                pauseOnHover: false,
                close: false,
                overlay: true,
                displayMode: 'once',
                id: 'question',
                zindex: 999,
                message: 'Want to delete this designation?',
                position: 'center',
                buttons: [
                    ['<button><b>No</b></button>', function (instance, toast) {

                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'no');

                    }, true],
                    ['<button><b>Yes</b></button>', function (instance, toast) {

                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'yes');

                    }, true]
                ],
                onClosed: async function (instance, toast, closedBy) {

                    if (closedBy == 'yes') {
                        const response = axiosInstance.post("deleteDesignation", { 'id': idd });
                        getListValues();
                        Notification.showToast('s', 'Successfully Designation Deleted.');
                    } else {

                    }

                }
            });
            // delete pop up message end


        });

        // change status
        $(".status-change").on('click', function (e) {
            // var idd = e.target.dataset.itemId;
            var idd = $(this).attr('data-item-id');

            iziToast.question({
                timeout: 100000,
                pauseOnHover: false,
                close: false,
                overlay: true,
                displayMode: 'once',
                id: 'question',
                zindex: 999,
                message: 'Want to change status this department?',
                position: 'center',
                buttons: [
                    ['<button><b>No</b></button>', function (instance, toast) {

                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'no');

                    }, true],
                    ['<button><b>Yes</b></button>', function (instance, toast) {

                        instance.hide({ transitionOut: 'fadeOut' }, toast, 'yes');

                    }, true]
                ],
                onClosed: async function (instance, toast, closedBy) {

                    if (closedBy == 'yes') {
                        const response = axiosInstance.post("changeDesgStatus", { 'id': idd });
                        getListValues();
                        Notification.showToast('s', 'Successfully Designation status Changed.');
                    } else {

                    }

                }
            });

        });
    }
};

// authStore.GlobalLoading = true;

async function getListValues() {
    try {
        authStore.GlobalLoading = true;
        const response = await axiosInstance.get("getAllRequests");
        rData.value = response.data.data;
        authStore.GlobalLoading = false;
    } catch (error) {
        // console.log(error);
        authStore.GlobalLoading = false;
    }
}

const assignform = reactive({
    assign_to: '', idd: ''
});

const changeStatusform = reactive({
    status: '', idd: ''
});

getInternalUsers();

async function getInternalUsers() {
    try {
        const response = await axiosInstance.get("getInternalUsers");

        let users = response.data.data;
        let select = document.getElementById('assign_to');

        users.forEach(function (user) {

            let option = document.createElement('option');
            option.value = user.idd;
            option.text = user.name;
            select.appendChild(option);
        });

    } catch (error) {
        console.log(error);
    }
}

function assign() {
    try {
        const select2Value = $('#assign_to').val();

        assignform.assign_to = select2Value;

        authStore.GlobalLoading = true;
        authStore.GlobalLoading = false;
        const response = axiosInstance.post("/assignRequest", assignform).then(response => {
            const data = response.data;
            // modal close
            $('#exampleScrollableModal').modal('hide');
            getListValues();

            Notification.showToast(data.data, data.message);

        }).catch(error => {
            console.log(error);
        });

        // console.log(response);
    } catch (error) {

        authStore.GlobalLoading = false;
    }

}

function changeStatus() {
    try {
        const select2Status = $('#status').val();

        authStore.GlobalLoading = true;
        authStore.GlobalLoading = false;
        const response = axiosInstance.post("/statusChange", changeStatusform).then(response => {
            const data = response.data;
            // modal close
            $('#statusChangeModal').modal('hide');
            getListValues();

            Notification.showToast(data.data, data.message);

        }).catch(error => {
            console.log(error);
        });

        // console.log(response);
    } catch (error) {
        console.log(error);
        authStore.GlobalLoading = false;
    }
}

async function ticketDetails(idd) {
    try {

        const response = await axiosInstance.get("getRequestDetails/" + idd);
        const data = response.data;
        $('.subject').html(data.data.subject);
        $(".ticket-details").val(data.data.description);
        $(".request_number").html('#' + data.data.request_number + ' ');

        data.details.forEach(function (detail) {

            if(detail.from_user_id == data.data.requester_id){
                $(".message_from_requester").append('<div class="col-md-12 pb-3"> <div class="d-flex flex-row-reverse bd-highlight"> <div class="p-2 bd-highlight"> <div class="d-flex"> <img src="'+data.author.img_path+'" width="20" height="20" class="rounded-circle" alt="" /> <div class="flex-grow-1 ms-2"> <p class="mb-0 chat-time">' + data.author.name + ', 3:35 PM</p> </div> </div> </div> </div> <div class="d-flex justify-content-end"> <div class="bg-light-primary p-2 rounded"> <p class="mb-0 chat-time">'+detail.note+'</p> </div> </div> </div>');

            }else{

                $(".message_from_me").append('<div class="col-md-12 mt-2 p-3"><div class="d-flex"><img src="'+data.me.img_path+'" width="20" height="20" class="rounded-circle" alt="" /><div class="flex-grow-1 ms-2"><p class="mb-0 chat-time">' + data.me.name + ', '+data.data.created_at+'</p></div></div><div class="d-flex"><div class="bg-light-primary p-2 rounded mt-2"><p class="mb-0 chat-time">'+detail.note+'</p></div></div></div>');
            }
        });

    } catch (error) {
        console.log(error);
    }

}



// Create a ref for the editor element
const editorRef = ref(null)
// Store Quill instance
let quillInstance = null

onMounted(() => {
    $("#assign_to").select2({
        placeholder: '=Select=',
        theme: 'bootstrap-5',
        width: '100%',
        allowClear: true,
    }).on('change', function () {
        assignform.assign_to = $(this).val(); // Sync with Vue reactive state
    });
    // Initialize Quill when component is mounted
    if (editorRef.value) {
        quillInstance = new Quill(editorRef.value, {
            theme: 'snow', // or 'bubble' for a different theme
            modules: {
                toolbar: [
                    [{ size: ['small', false, 'large', 'huge'] }],
                    ['bold', 'italic', 'underline'],
                    ['blockquote', 'code-block'],
                    [{ header: 1 }, { header: 2 }],
                    // [{ list: 'ordered' }, { list: 'bullet' }],
                    // [{ script: 'sub' }, { script: 'super' }],
                    [{ indent: '-1' }, { indent: '+1' }],
                    [{ direction: 'rtl' }],
                    // [{ header: [1, 2, 3, 4, 5, 6, false] }],
                    // [{ color: [] }, { background: [] }],
                    // [{ font: [] }],
                    // [{ align: [] }],
                    ['clean'],
                    ['link', 'image', 'video']
                ]
            },
            placeholder: 'Write something...',
            readOnly: false
        })

        // Set initial content if needed
        quillInstance.root.innerHTML = '<p>   </p>'

        // Listen for text changes
        quillInstance.on('text-change', () => {
            const html = quillInstance.root.innerHTML;
            const text = quillInstance.getText();
        })
    }
})

// Clean up on component unmount
onBeforeUnmount(() => {
    if (quillInstance) {
        quillInstance = null
    }
})

// Helper functions to interact with the editor
const getEditorContent = () => {
    return quillInstance?.root.innerHTML || ''
}

const setEditorContent = (content) => {
    if (quillInstance) {
        quillInstance.root.innerHTML = content
    }
}

const getPlainText = () => {
    return quillInstance?.getText() || ''
}

// Expose methods if needed
defineExpose({
    getEditorContent,
    setEditorContent,
    getPlainText
})
</script>
<template>
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">

        <div class="breadcrumb-title pe-3">Help Desk</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                    </li>
                    <li class="breadcrumb-item">
                        <router-link :to="{ name: 'helpDesk' }">Support Request </router-link>
                    </li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <div class="btn-group">
                <router-link :to="{ name: 'requestCreate' }" class="btn btn-primary btn-sm">
                    <i class="fa fa-circle-plus"></i>Request
                </router-link>
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-agency">
                <span class="info-agency-icon bg-info elevation-1"><i class="fa-solid fa-ticket"></i></span>
                <div class="info-agency-content">
                    <span class="info-agency-text">Total</span>
                    <span class="info-agency-number">
                        1200
                    </span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="active-agency mb-3">
                <span class="active-agency-icon bg-success elevation-1 text-white"><i class="fa fa-check"></i></span>
                <div class="active-agency-content">
                    <span class="active-agency-text">Open</span>
                    <span class="active-agency-number">760</span>
                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-warning elevation-1"><i class="fa-solid fa-ban"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">In Progress</span>
                    <span class="pending-agnt-number">20</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="pending-agnt mb-3">
                <span class="pending-agnt-icon bg-danger elevation-1"><i class="fa-solid fa-circle-pause"></i></span>
                <div class="pending-agnt-content">
                    <span class="pending-agnt-text">In Progress</span>
                    <span class="pending-agnt-number">20</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row position-relative">
        <div class="modal fade" id="exampleScrollableModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body">
                        <form id="addAssignform">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label for="input1" class="form-label">Assign</label>
                                            <select v-model="assignform.assign_to" id="assign_to"
                                                class="form-select form-select-sm assign_to"
                                                aria-label="Default select example">
                                                <option selected value="">Select </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="button" @click="assign"
                                        class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">
                                        Assign
                                    </button>
                                    <button type="button" data-bs-dismiss="modal"
                                        class="m-2 btn btn-sm btn-danger px-4 ms-2 float-end">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="statusChangeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body">
                        <form id="addchangeStatusform">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label for="input1" class="form-label">Status</label>
                                            <select v-model="changeStatusform.status" id="status"
                                                class="form-select form-select-sm status"
                                                aria-label="Default select example">
                                                <option selected value="">Select </option>
                                                <option value="open">Open</option>
                                                <option value="closed">Closed</option>
                                                <option value="on hold">On Hold</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="button" @click="changeStatus"
                                        class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">
                                        Change
                                    </button>
                                    <button type="button" data-bs-dismiss="modal"
                                        class="m-2 btn btn-sm btn-danger px-4 ms-2 float-end">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- canvas -->
        <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="staticBackdrop">
            <div class="offcanvas-header border-bottom h-60">
                <div class="mt-2">
                    <p class="">
                        <span class="request_number" style=" color: rgb(121, 68, 235); font-size: 12px;">#001</span>
                        <span class="fw-bold subject"> Ticket Issue Problem When Booking new ticket</span>
                    </p>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div></div>
            <div class="offcanvas-body">
                <div class="row">
                    <div class="col-md-12 pb-1">
                        <label for="">Details</label>
                    </div>
                    <div class="col-md-12">
                        <textarea name="" cols="4" rows="4" class="form-control ticket-details" id="" readonly=""></textarea>
                    </div>
                </div>
                <div class="row mt-3">
                    <span class="message_from_me"></span>


                    <!-- reverse -->
                    <span class="message_from_requester"></span>
                    <!-- end reverse -->
                </div>

                <div class="row mt-2">
                    <div class="col-md-12">
                        <div class="editor-container">
                            <!-- Quill editor container -->
                            <div ref="editorRef"></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                            <label class="form-check-label" for="flexCheckDefault">Show this note to assignee</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                            <label class="form-check-label" for="flexCheckDefault">Also send as Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                            <label class="form-check-label" for="flexCheckDefault">Send as Notification</label>
                        </div>
                    </div>
                </div>
                <div class="row">

                    <div class="col-md-12 mt-3">
                        <button class="btn btn-sm btn-secondary float-center px-4 ms-2 mt-2" type="button" data-bs-dismiss="offcanvas" aria-label="Close">Cancel</button>
                        <button type="button" class="m-2 btn btn-sm btn-info px-4 ms-2 float-end text-white">Save</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- end canvas -->


        <div class="col-md-12">
            <div class="card rounded rounded-2 shadow-none p-3">
                <div v-if="authStore.GlobalLoading" class="center-body position-absolute top-50 start-50">
                    <div class="loader-circle-57">
                        <img class="position-absolute" src="../../../../../public/theme/appimages/blueskywings.png"
                            height="22" width="22" alt="">
                    </div>
                </div>

                <DataTable :options="options" :data="rData" class="table table-sm table-striped table-bordered">
                </DataTable>
            </div>
        </div>

    </div>

</template>

<style>
.center-body {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100vh;
    width: 100px;
    height: 100px;
}

.loader-circle-57 {
    width: 70px;
    height: 70px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.loader-circle-57:before {
    content: "";
    color: red;
    height: 50px;
    width: 50px;
    background: #0000;
    border-radius: 50%;
    border: 5px solid #027de2d5;
    animation: loader-circle-57-spin 1s infinite
}

@keyframes loader-circle-57-spin {
    50% {
        transform: rotatez(180deg);
        border-style: dashed;
        border-color: #9c54f0 #02b9af #4e86f4;
    }

    100% {
        transform: rotatez(360deg);
    }
}


.dt-search {
    margin-bottom: -15px;
    width: 190px;
}

.Reduct_table_gap {
    margin-top: -10px;
}

.dt-search input[type=search] {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #E4EAEF;
    border-radius: 9px;
    background-color: white;
    background-image: url('../../../../../../public/theme/appimages/Search.svg');
    background-position: 7px 6px;
    /*left,top*/
    background-repeat: no-repeat;
    padding-left: 35px;
    padding-top: 8px;
    color: #A1ABB7;
    padding-bottom: 8px;
    font-size: 13px;
    font-family: 'inter';
}

.text-blue {
    color: blue;
}

/* dashboard design */
.info-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #cbdff4, #bcd6f1, #aecced, #8eb6e4, #6da1dc, #4a8bd2, #1576c9);
    /* background-image: linear-gradient(to right top, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #dae9f8, #d6e7f8, #d1e5f8, #cde3f8, #c2def8, #b8d9f8, #add5f8, #a1d0f8); */
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.info-agency .info-agency-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-info,
.bg-info>a {
    color: #fff !important;
}


.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-info {
    background-color: #0880e1 !important;
}


.info-agency .info-agency-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.info-agency .info-agency-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.info-agency .info-agency-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

/* active agency */

.active-agency {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #d7f1e9, #c9f1e4, #baf1de, #acf0d7, #8cefc6, #6decb1, #4ce998, #24e57c);
    /* background-image: linear-gradient(to right top, #dbf1eb, #dbf1eb, #dbf1eb, #dbf1eb, #dbf1eb, #d2f1e8, #c9f1e5, #c0f1e1, #acf1d7, #99f0cb, #87efbe, #76eeae); */
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.active-agency .active-agency-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-success,
.bg-success>a {
    color: #fff !important;
}

.bg-success {
    background-color: #05cc61 !important;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-success {
    background-color: #05cc61 !important;
}


.active-agency .active-agency-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.active-agency .active-agency-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.active-agency .active-agency-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

/* Pending */
.pending-agnt {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #f0ded6, #f1d7c9, #f2cfbd, #f3bea2, #f3ac88, #f29b6f, #ef8956);
    /* background-image: linear-gradient(to right top, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #eee6e2, #efe2db, #efddd5, #f0d9ce, #f1d0bf, #f2c6b1, #f2bda2, #f1b494); */
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.pending-agnt .pending-agnt-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-warning,
.bg-warning>a {
    color: #fff !important;
}

.bg-warning {
    background-color: #fb8e28 !important;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-warning {
    background-color: #fb8e28 !important;
}


.pending-agnt .pending-agnt-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.pending-agnt .pending-agnt-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pending-agnt .pending-agnt-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}


/* On Hold */
.info-box {
    box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    border-radius: .25rem;
    background-image: linear-gradient(to right top, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #eef1e2, #ebf0d6, #e9eeca, #e8ecbe, #e7e7a2, #e8e285, #ebdb66, #efd444);
    display: -ms-flexbox;
    display: flex;
    margin-bottom: 1rem;
    min-height: 90px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.info-box .info-box-icon {
    border-radius: .25rem;
    -ms-flex-align: center;
    align-items: center;
    display: -ms-flexbox;
    display: flex;
    font-size: 1.875rem;
    -ms-flex-pack: center;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.bg-danger,
.bg-danger>a {
    color: #fff !important;
}

.bg-danger {
    background-color: #efb51d !important;
}

.elevation-1 {
    box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .24) !important;
}

.bg-danger {
    background-color: #efb51d !important;
}


.info-box .info-box-content {
    display: -ms-flexbox;
    display: flex;
    -ms-flex-direction: column;
    flex-direction: column;
    -ms-flex-pack: center;
    justify-content: center;
    line-height: 1.5;
    -ms-flex: 1;
    flex: 1;
    padding: 0 30px;
    overflow: hidden;
}

.info-box .info-box-text {
    font-size: 19px;
    letter-spacing: normal;
    color: #838587;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.info-box .info-box-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
    font-size: 22px;
}

.odd td {
    background-color: #F5F8FA;
}

.even td {
    background-color: #fff;
}

.btn-outline-user-edit {
    --bs-btn-color: #7239ea;
    --bs-btn-border-color: #7239ea;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #7239ea;
    --bs-btn-hover-border-color: #7239ea;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #7239ea;
    --bs-btn-active-border-color: #7239ea;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #7239ea;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #7239ea;
    --bs-gradient: none;
}

.btn-outline-ban {
    --bs-btn-color: #e25802;
    --bs-btn-border-color: #e25802;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #e25802;
    --bs-btn-hover-border-color: #e25802;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #e25802;
    --bs-btn-active-border-color: #e25802;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #e25802;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #e25802;
    --bs-gradient: none;
}

.btn-outline-action-log {
    --bs-btn-color: #f1892a;
    --bs-btn-border-color: #f1892a;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #f1892a;
    --bs-btn-hover-border-color: #f1892a;
    --bs-btn-focus-shadow-rgb: 108, 117, 125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #f1892a;
    --bs-btn-active-border-color: #f1892a;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #f1892a;
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: #f1892a;
    --bs-gradient: none;
}

/* editor css */

.editor-container {
    /* Adjust height as needed */
    min-height: 140px;
}

/* You can customize Quill styles here */
:deep(.ql-editor) {
    min-height: 400px;
    font-size: 16px;
}

:deep(.ql-toolbar) {
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
}

:deep(.ql-container) {
    border-bottom-left-radius: 4px;
    border-bottom-right-radius: 4px;
}
</style>
