<script setup>
import { ref, onMounted } from 'vue';
import axiosInstance from '../../axiosInstance';
import { useAuthStore } from '../../stores/authStore';

const authStore = useAuthStore();
const creditBalance = ref(0);
const netBalance = ref(0);

function menuTaggle() {
    $(".wrapper").toggleClass("toggled");
}


function darkMode() {
    $("html").attr("data-bs-theme", function (i, v) {

        if ($(".dark-mode-icon i").attr("class") == 'bx bx-sun') {
            $(".dark-mode-icon i").attr("class", "bx bx-moon");
        } else {
            $(".dark-mode-icon i").attr("class", "bx bx-sun");
        }
        authStore.isDarkMode = authStore.isDarkMode == false ? true : false;
        return v === 'dark' ? 'light' : 'dark';
    })
}

function formatMoney(v) {
    return Number(v ?? 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

async function loadBalance() {
    try {
        const res = await axiosInstance.get('agent/balance');
        const data = res.data?.data || {};
        creditBalance.value = data.credit_balance ?? 0;
        netBalance.value = data.net_balance ?? 0;
    } catch {}
}

onMounted(loadBalance);
</script>
<template>
    <header>
        <div class="topbar">
            <nav class="navbar navbar-expand gap-2 align-items-center">
                <div class="mobile-toggle-menu d-flex" @click="menuTaggle"><i class='bx bx-menu'></i>
                </div>

                <div class="top-menu ms-auto">
                    <ul class="navbar-nav align-items-center gap-1">

                        <li class="nav-item agent-balance-strip d-none d-lg-flex align-items-center">
                            <span class="balance-label text-danger">Credit : {{ formatMoney(creditBalance) }}</span>
                            <span class="balance-sep">|</span>
                            <span class="balance-label text-primary">Balance : {{ formatMoney(netBalance) }}</span>
                        </li>

                        <li class="nav-item dark-mode d-none d-sm-flex" @click="darkMode">
                            <a v-wave class="nav-link dark-mode-icon" href="javascript:;"><i class='bx bx-moon'></i>
                            </a>
                        </li>

                        <li class="nav-item dropdown dropdown-app">

                            <div v-show="authStore.GlobalLoading" class="nav-link dropdown-toggle dropdown-toggle-nocaret">
                                <div class="center-body1">
                                    <div class="loader-circle-571">
                                        <img class="position-absolute"
                                            src="../../../../public/theme/appimages/blueskywings.png" height="15"
                                            width="15" alt="">
                                    </div>
                                </div>
                            </div>

                        </li>

                        <li class="nav-item dropdown dropdown-large">

                            <a v-wave class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative"
                                href="#" data-bs-toggle="dropdown"><span class="alert-count">7</span>
                                <i class='bx bx-bell'></i>
                            </a>

                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="javascript:;">
                                    <div class="msg-header">
                                        <p class="msg-header-title">Notifications</p>
                                        <p class="msg-header-badge">8 New</p>
                                    </div>
                                </a>
                                <Scrollbar height="100%">
                                    <div class="header-notifications-list">
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="user-online">
                                                    <img src="../../themeassets/images/avatars/avatar-1.png"
                                                        class="msg-avatar" alt="user avatar">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Daisy Anderson<span
                                                            class="msg-time float-end">5
                                                            sec
                                                            ago</span></h6>
                                                    <p class="msg-info">The standard chunk of lorem</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-danger text-danger">dc
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New Orders <span class="msg-time float-end">2
                                                            min
                                                            ago</span></h6>
                                                    <p class="msg-info">You have recived new orders</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="user-online">
                                                    <img src="../../themeassets/images/avatars/avatar-2.png"
                                                        class="msg-avatar" alt="user avatar">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Althea Cabardo <span
                                                            class="msg-time float-end">14
                                                            sec ago</span></h6>
                                                    <p class="msg-info">Many desktop publishing packages</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-success text-success">
                                                    <img src="../../themeassets/images/app/outlook.png" width="25"
                                                        alt="user avatar">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Account Created<span
                                                            class="msg-time float-end">28
                                                            min
                                                            ago</span></h6>
                                                    <p class="msg-info">Successfully created new email</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-info text-info">Ss
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New Product Approved <span
                                                            class="msg-time float-end">2 hrs ago</span></h6>
                                                    <p class="msg-info">Your new product has approved</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="user-online">
                                                    <img src="../../themeassets/images/avatars/avatar-4.png"
                                                        class="msg-avatar" alt="user avatar">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Katherine Pechon <span
                                                            class="msg-time float-end">15
                                                            min ago</span></h6>
                                                    <p class="msg-info">Making this the first true generator</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-success text-success"><i
                                                        class='bx bx-check-square'></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Your item is shipped <span
                                                            class="msg-time float-end">5 hrs
                                                            ago</span></h6>
                                                    <p class="msg-info">Successfully shipped your item</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-primary">
                                                    <img src="../../themeassets/images/app/github.png" width="25"
                                                        alt="user avatar">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New 24 authors<span
                                                            class="msg-time float-end">1
                                                            day
                                                            ago</span></h6>
                                                    <p class="msg-info">24 new authors joined last week</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="user-online">
                                                    <img src="../../themeassets/images/avatars/avatar-8.png"
                                                        class="msg-avatar" alt="user avatar">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Peter Costanzo <span
                                                            class="msg-time float-end">6
                                                            hrs
                                                            ago</span></h6>
                                                    <p class="msg-info">It was popularised in the 1960s</p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </Scrollbar>
                                <a href="javascript:;">
                                    <div class="text-center msg-footer">
                                        <button class="btn btn-primary w-100">View All Notifications</button>
                                    </div>
                                </a>
                            </div>
                        </li>

                    </ul>
                </div>
                <div v-wave class="user-box dropdown px-3">
                    <a class="d-flex align-items-center nav-link dropdown-toggle gap-3 dropdown-toggle-nocaret" href="#"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="../../themeassets/images/avatars/avatar-4.png" class="user-img" alt="user avatar">
                        <div class="user-info">
                            <p class="user-name mb-0">{{ authStore.name }}</p>
                            <p class="designattion mb-0">{{ authStore.email }}</p>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-user fs-5"></i><span>Profile</span></a>
                        </li>
                        <li><a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-cog fs-5"></i><span>Settings</span></a>
                        </li>
                        <li><a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-home-circle fs-5"></i><span>Dashboard</span></a>
                        </li>
                        <li><a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-dollar-circle fs-5"></i><span>Earnings</span></a>
                        </li>
                        <li><a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-download fs-5"></i><span>Downloads</span></a>
                        </li>
                        <li>
                            <div class="dropdown-divider mb-0"></div>
                        </li>
                        <li><router-link class="dropdown-item d-flex align-items-center" :to="{ name: 'Logout' }"><i
                                    class="bx bx-log-out-circle"></i><span>Logout</span></router-link>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>
    </header>
</template>
<style>
.agent-balance-strip {
    gap: 0.45rem;
    padding: 0.1rem 0.55rem;
    margin-right: 0.15rem;
    border-radius: 6px;
    background: transparent;
    border: none;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    list-style: none;
}

.agent-balance-strip .balance-sep {
    color: #9ca3af;
    font-weight: 400;
}

.center-body1 {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 30px;
    height: 30px;
}

.loader-circle-571 {
    width: 70px;
    height: 70px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.loader-circle-571:before {
    content: "";
    color: red;
    height: 30px;
    width: 30px;
    background: #0000;
    border-radius: 50%;
    border: 4px solid #027de2d5;
    animation: loader-circle-57-spin 1s infinite
}

@keyframes loader-circle-571-spin {
    50% {
        transform: rotatez(180deg);
        border-style: dashed;
        border-color: #9c54f0 #02b9af #4e86f4;
    }

    100% {
        transform: rotatez(360deg);
    }
}
</style>
