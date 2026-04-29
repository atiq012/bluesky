<script setup>
import DataTable from "datatables.net-vue3";
import DataBS5 from "datatables.net-bs5";
import axiosInstance from "../../../axiosInstance";
import { computed, ref, watch } from "vue";
import { useRouter } from 'vue-router';
const router = useRouter();
import { useAuthStore } from '../../../stores/authStore';
const authStore = useAuthStore();

DataTable.use(DataBS5);

const rData = ref([]);
const tableRef = ref(null);
const searchText = ref("");
const filterDate = ref("");
const filterAuthor = ref("");
const filterStatus = ref("");
getListValues();

watch(searchText, (val) => {
    const dt = tableRef.value?.dt;
    if (!dt) return;
    dt.search(val ?? "").draw();
});

function dtTrigger(buttonClass) {
    const dt = tableRef.value?.dt;
    if (!dt) return;
    dt.button(buttonClass).trigger();
}

function clearFilters() {
    filterDate.value = "";
    filterAuthor.value = "";
    filterStatus.value = "";
    searchText.value = "";
    const dt = tableRef.value?.dt;
    if (!dt) return;
    dt.search("").columns().search("").draw();
}

const totalCount = computed(() => rData.value?.length ?? 0);
const activeCount = computed(() => (rData.value ?? []).filter((r) => Number(r?.status) === 1).length);
const inactiveCount = computed(() => Math.max(0, totalCount.value - activeCount.value));

function maskSecret(value, visible = 4) {
    const v = String(value ?? "");
    if (!v) return "-";
    if (v.length <= visible) return "•".repeat(Math.max(6, v.length));
    return `${"•".repeat(Math.max(8, v.length - visible))}${v.slice(-visible)}`;
}

function escAttr(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;");
}

const options = {
    responsive: true,
    pageLength: 30,
    lengthMenu: [3, 10, 20, 30],
    bDestroy: true,
    ordering: false,
    dom: "rt<'row align-items-center mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>",
    buttons: ['copy', 'csv', 'pdf', 'excel', 'print'],
    language: {
        emptyTable: "No data found",
    },
    columnDefs: [{
        defaultContent: "0",
        targets: "_all",
    }],
    columns: [
        { data: "DT_RowIndex", title: "SL" },
        {
            title: "API Name",
            render: function (data, type, row) {
                console.log(row);

                var html = "";
                html += row.name ?? "-";

                return html;
            },
        },
        {
            title: "Author",
            render: function (data, type, row) {
                var html = "";
                html += row.author ?? row.vendor ?? row.provider ?? "-";

                return html;
            },
        },
        {
            title: "Credentials",
            render: function (data, type, row) {
                const email = row.email ?? row.username ?? row.user_email ?? "-";
                const apiKey = row.api_key ?? row.apiKey ?? row.key ?? "";
                const apiSecret = row.api_secret ?? row.apiSecret ?? row.secret ?? "";

                let html = "";
                html += `<div class="small text-muted">${email}</div>`;
                html += `<div class="d-flex flex-column gap-1 mt-1">`;
                if (apiKey) {
                    html += `<div class="d-flex align-items-center gap-2">`;
                    html += `<i class="fa-solid fa-key text-primary small"></i>`;
                    html += `<span class="font-monospace small">${maskSecret(apiKey)}</span>`;
                    html += `<button type="button" class="btn btn-sm btn-light border copy-cred" data-copy="${escAttr(apiKey)}" title="Copy"><i class="fa-regular fa-copy"></i></button>`;
                    html += `</div>`;
                }
                if (apiSecret) {
                    html += `<div class="d-flex align-items-center gap-2">`;
                    html += `<i class="fa-solid fa-asterisk text-primary small"></i>`;
                    html += `<span class="font-monospace small">${maskSecret(apiSecret)}</span>`;
                    html += `<button type="button" class="btn btn-sm btn-light border copy-cred" data-copy="${escAttr(apiSecret)}" title="Copy"><i class="fa-regular fa-copy"></i></button>`;
                    html += `</div>`;
                }
                if (!apiKey && !apiSecret) {
                    html += `<div class="small text-muted">-</div>`;
                }
                html += `</div>`;

                return html;
            },
        },

        {
            title: "Created By",
            render: function (data, type, row) {
                var html = "";
                html += row.created_by;
                html += "<br>";

                html += '<span class="text-primary">';
                html += row.created_at + "</span>";
                return html;
            },
        },
        {
            title: "Updated By",
            render: function (data, type, row) {
                var html = "";
                html += row.updated_by || "-";
                html += "<br>";
                if (row.updated_by) {
                    html += '<span class="text-primary">';
                    html += row.updated_at + "</span>";
                }
                return html;
            },
        },
        {
            title: "Status",
            render: function (data, type, row) {
                var html = "";

                if (row.status == 'active' || row.status == '1' || row.status == 1) {
                    html += '<div class="badge rounded-pill text-success bg-light-success p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i>Active </div>';
                } else {
                    html += '<div class="badge rounded-pill text-danger bg-light-danger p-2 text-uppercase px-3"><i class="bx bxs-circle me-1"></i>Deactivated </div>';
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

                html += '<button  style="size: 30px; width: 30px; height: 30px" class="btn btn-outline-only-edit rounded-circle edit-item" placement="top" id="edit_tool" data-item-id=' + idd + '> <i class="fa-solid fa-pencil" style="margin: 0px 0px 10px -5px; font-size: 14px;" ></i> </button>';
                if (status == 1) {

                    html += '<button type="button" style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-ban rounded-circle status-change" data-item-id=' + idd + '> <i class="fa-solid fa-ban" style="margin: 2px 0px 10px -5px; font-size: 14px;"></i> </button>';
                } else {
                    html += '<button type="button" style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-success rounded-circle status-change" data-item-id=' + idd + '> <i class="fa-solid fa-check" style="margin: 2px 0px 10px -5px; font-size: 14px;"></i> </button>';
                }

                html += '<button style="size: 30px; width: 30px; height: 30px; margin-left: 5px;" class="btn btn-outline-danger rounded-circle delete-item" data-item-id=' + idd + '> <i class="fa-solid fa-trash" style="margin: 2px 0px 10px  -4px; font-size: 14px;"></i> </button>';

                return html;
            },
        }
    ],
    "drawCallback": function (settings) {
        $(".copy-cred")
            .off("click")
            .on("click", async function () {
                const value = $(this).attr("data-copy") ?? "";
                try {
                    await navigator.clipboard.writeText(value);
                    if (typeof Notification !== "undefined" && Notification?.showToast) {
                        Notification.showToast("s", "Copied to clipboard.");
                    }
                } catch (e) {
                    if (typeof Notification !== "undefined" && Notification?.showToast) {
                        Notification.showToast("e", "Copy failed.");
                    }
                }
            });

        // edit function
        $(".edit-item").off("click").on('click', function (e) {

            var itemIdd = $(this).attr('data-item-id');

            router.push({ name: 'offEdit', params: { id: itemIdd } });
        });

        // delete function
        $(".delete-item").off("click").on('click', function (e) {

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
                message: 'Want to delete this office location?',
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
                        const response = axiosInstance.post("deleteAPI", { 'id': idd });
                        getListValues();
                        Notification.showToast('s', 'Successfully API Deleted.');
                    } else {

                    }

                }
            });
            // delete pop up message end


        });

        // change status
        $(".status-change").off("click").on('click', function (e) {

            var idd = $(this).attr('data-item-id');

            iziToast.question({
                timeout: 100000,
                pauseOnHover: false,
                close: false,
                overlay: true,
                displayMode: 'once',
                id: 'question',
                zindex: 999,
                message: 'Want to change status this api status?',
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
                        console.log(idd);

                        const response = axiosInstance.post("changeAPIStatus", { 'id': idd });
                        getListValues();
                        Notification.showToast('s', 'Successfully API status Changed.');
                    } else {

                    }

                }
            });

        });
    }
};


async function getListValues() {
    try {
        authStore.GlobalLoading = true;
        const response = await axiosInstance.get("getAPI");
        rData.value = response.data.data;
        authStore.GlobalLoading = false;
    } catch (error) {
        // console.log(error);
        authStore.GlobalLoading = false;
    }
}

</script>
<template>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
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
                            <router-link :to="{ name: 'offLoc' }">Setting</router-link>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">API List</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="ms-auto">
            <router-link :to="{ name: 'addApi' }" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
                <i class="fa fa-circle-plus"></i>
                <span>Create</span>
            </router-link>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-primary">
                        <i class="fa-solid fa-link"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small">Total</div>
                        <div class="stat-number">{{ totalCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-success">
                        <i class="fa-solid fa-link"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small">Active</div>
                        <div class="stat-number">{{ activeCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-danger">
                        <i class="fa-solid fa-link"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small">Inactive</div>
                        <div class="stat-number">{{ inactiveCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white">
                            <i class="fa-regular fa-calendar"></i>
                        </span>
                        <input v-model="filterDate" type="text" class="form-control" placeholder="01-Aug-2024 - 22-Aug-2024">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select v-model="filterAuthor" class="form-select form-select-sm">
                        <option value="">Author</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select v-model="filterStatus" class="form-select form-select-sm">
                        <option value="">Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none px-0" @click="clearFilters">
                        <i class="fa-solid fa-xmark me-1"></i>Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm position-relative">
        <div v-if="authStore.GlobalLoading" class="table-loading">
            <div class="loader-circle-57">
                <img class="position-absolute" src="../../../../../public/theme/appimages/blueskywings.png" height="22" width="22" alt="">
            </div>
        </div>

        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-danger" @click="dtTrigger('.buttons-pdf')">
                        <i class="fa-regular fa-file-pdf me-1"></i>Pdf
                    </button>
                    <button type="button" class="btn btn-sm btn-success" @click="dtTrigger('.buttons-excel')">
                        <i class="fa-regular fa-file-excel me-1"></i>Excel
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" @click="dtTrigger('.buttons-csv')">
                        <i class="fa-solid fa-file-csv me-1"></i>CSV
                    </button>
                </div>

                <div class="table-search input-group input-group-sm">
                    <span class="input-group-text bg-white">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input v-model="searchText" type="search" class="form-control" placeholder="Search by anything">
                </div>
            </div>

            <div class="table-responsive">
                <DataTable ref="tableRef" :options="options" :data="rData"
                    class="table table-sm align-middle table-hover table-bordered table-striped w-100">
                </DataTable>
            </div>
        </div>
    </div>

</template>

<style>
.dt-buttons,
.dt-search {
    display: none !important;
}

.table-loading {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.6);
    z-index: 5;
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

.odd td {
    background-color: #F5F8FA;
}

.even td {
    background-color: #fff;
}

.stat-card {
    border-radius: 10px;
}

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    color: #fff;
    flex: 0 0 auto;
}

.stat-icon-primary {
    background: linear-gradient(135deg, #2f6fed 0%, #6aa8ff 100%);
}

.stat-icon-success {
    background: linear-gradient(135deg, #00b86b 0%, #7bf0bf 100%);
}

.stat-icon-danger {
    background: linear-gradient(135deg, #e53935 0%, #ffb3b3 100%);
}

.stat-number {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.1;
    color: #111827;
}

.table-search {
    width: min(320px, 100%);
}

.table-search .form-control {
    border-left: 0;
}

.table-search .input-group-text {
    border-right: 0;
}

.copy-cred {
    line-height: 1;
    padding: 0.25rem 0.4rem;
}

.copy-cred i {
    font-size: 0.85rem;
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
</style>
