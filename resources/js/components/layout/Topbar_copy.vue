<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axiosInstance from '../../axiosInstance';
import { useAuthStore } from '../../stores/authStore';
import { useRealtimeList } from '../../composables/useRealtimeList';

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
    } catch { }
}

onMounted(() => {
    loadBalance();
    window.addEventListener('balance:refresh', loadBalance);
});

onUnmounted(() => {
    window.removeEventListener('balance:refresh', loadBalance);
});

useRealtimeList('deposits', loadBalance);
</script>
<template>
    <header>
        <div class="topbar">
            <nav class="navbar navbar-expand gap-2 align-items-center">
                <div class="mobile-toggle-menu d-flex" @click="menuTaggle"><i class='bx bx-menu'></i>
                </div>
                <a href="#" class="gt-brand me-auto">

                    <span class="agent_company_name fadeIn animated">{{ authStore.agent_name }}</span>
                </a>

                <div class="top-menu ms-auto">
                    <ul class="navbar-nav align-items-center gap-1">
                        <div class="wallet-pill-group me-3 dropdown" id="walletDropdown">
                            <div class="d-flex align-items-center" data-bs-toggle="dropdown"
                                data-bs-auto-close="outside" aria-expanded="false"
                                onclick="document.getElementById('walletDropdown').classList.toggle('open')">
                                <!-- Credit pill -->
                                <div class="wallet-pill pill-credit me-1">
                                    <i class="bi bi-coin"></i>
                                    <span>Credit</span>
                                    <strong id="nav-credit-val">৳{{ formatMoney(creditBalance) }}</strong>
                                </div>
                                <!-- Balance pill -->
                                <div class="wallet-pill pill-balance">
                                    <i class="bi bi-wallet2"></i>
                                    <span>Balance</span>
                                    <strong id="nav-balance-val">৳{{ formatMoney(netBalance) }}</strong>
                                </div>
                                <i class="bi bi-chevron-down pill-chevron ms-1"></i>
                            </div>

                            <!-- Dropdown menu -->
                            <!-- <div class="dropdown-menu wallet-dropdown">


                                <div
                                    class="wallet-dropdown-header dropdown-header d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-wallet2 me-1"></i> My Wallet</span>
                                    <small class="text-muted"
                                        style="font-size:.7rem;text-transform:none;letter-spacing:0;">Azra
                                        Shahida</small>
                                </div>


                                <div class="balance-row">
                                    <div class="balance-card credit">
                                        <div class="bc-label"><i class="bi bi-coin me-1"></i>Credit</div>
                                        <div class="bc-amount" id="dd-credit-val">৳0</div>
                                        <div class="bc-sub" id="dd-credit-sub">No credit available</div>
                                    </div>
                                    <div class="balance-card balance">
                                        <div class="bc-label"><i class="bi bi-wallet2 me-1"></i>Balance</div>
                                        <div class="bc-amount" id="dd-balance-val">৳0</div>
                                        <div class="bc-sub" id="dd-balance-sub">No funds available</div>
                                    </div>
                                </div>


                                <div class="zero-alert" id="zero-alert">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <span>Ticket issuance is blocked. Top up your balance or request credit to continue
                                        booking.</span>
                                </div>


                                <div class="wallet-actions">
                                    <button class="btn btn-topup" data-bs-toggle="modal" data-bs-target="#topupModal">
                                        <i class="bi bi-plus-circle me-1"></i>Top up balance
                                    </button>
                                    <button class="btn btn-credit">
                                        <i class="bi bi-coin me-1"></i>Request credit
                                    </button>
                                </div>


                                <div class="wallet-footer">
                                    <span id="last-topup-label">Last topped up: Never</span>
                                    <a href="#">View all transactions →</a>
                                </div>

                            </div> -->
                        </div>

                        <li class="nav-item dark-mode d-none d-sm-flex" @click="darkMode">
                            <a v-wave class="nav-link dark-mode-icon" href="javascript:;"><i class='bx bx-moon'></i>
                            </a>
                        </li>

                        <li class="nav-item dropdown dropdown-app">

                            <div v-show="authStore.GlobalLoading"
                                class="nav-link dropdown-toggle dropdown-toggle-nocaret">
                                <div class="center-body1">
                                    <div class="loader-circle-571">
                                        <img class="position-absolute"
                                            src="../../../../public/theme/appimages/blueskywings.png" height="15"
                                            width="15" alt="">
                                    </div>
                                </div>
                            </div>

                        </li>

                        <!-- <li class="nav-item dropdown dropdown-large">

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
                        </li> -->

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
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-building fs-5"></i><span>Agency Profile</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-user fs-5"></i><span>User Profile</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
                                    class="bx bx-key fs-5"></i><span>Change Password</span>
                            </a>
                        </li>
                        <!-- <li><a class="dropdown-item d-flex align-items-center" href="javascript:;"><i
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
                        </li> -->
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


/* ── Wallet pill wrapper ── */
.wallet-pill-group {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    user-select: none;
}

/* ── Individual pill ── */
.wallet-pill {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    border-radius: 5px;
    border: 1px solid transparent;
    font-size: .8rem;
    font-weight: 500;
    transition: filter .15s;
}

.wallet-pill:hover {
    filter: brightness(.95);
}

.pill-credit {
    background: #ffeceb;
    border-color: #fa7a75;
    color: #633806;
}

.pill-credit i {
    color: #BA7517;
    font-size: .9rem;
}

.pill-balance {
    background: #e3ecf7de;
    border-color: #95acd7;
    color: #173404;
}

.pill-balance i {
    color: #3B6D11;
    font-size: .9rem;
}

.pill-chevron {
    color: #adb5bd;
    font-size: .75rem;
    margin-left: 2px;
    transition: transform .2s;
}

.wallet-pill-group.open .pill-chevron {
    transform: rotate(180deg);
}

/* ── Dropdown ── */
.wallet-dropdown {
    width: 300px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .10);
    padding: 0;
    overflow: hidden;
    top: calc(100% + 10px) !important;
    right: 0;
    left: auto !important;
}

.wallet-dropdown .dropdown-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.wallet-dropdown .dropdown-header span {
    font-size: .78rem;
    font-weight: 600;
    color: #64748b;
    letter-spacing: .04em;
    text-transform: uppercase;
}

/* ── Balance cards inside dropdown ── */
.balance-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 14px 16px;
}

.balance-card {
    border-radius: 10px;
    padding: 12px;
    border: 1px solid transparent;
}

.balance-card.credit {
    background: #FFF4E5;
    border-color: #FAC775;
}

.balance-card.balance {
    background: #EAF3DE;
    border-color: #C0DD97;
}

.balance-card .bc-label {
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.balance-card.credit .bc-label {
    color: #BA7517;
}

.balance-card.balance .bc-label {
    color: #3B6D11;
}

.balance-card .bc-amount {
    font-size: 1.3rem;
    font-weight: 600;
    line-height: 1.2;
}

.balance-card.credit .bc-amount {
    color: #633806;
}

.balance-card.balance .bc-amount {
    color: #173404;
}

.balance-card .bc-sub {
    font-size: .72rem;
    margin-top: 4px;
}

.balance-card.credit .bc-sub {
    color: #BA7517;
}

.balance-card.balance .bc-sub {
    color: #3B6D11;
}

/* zero-state warning strip */
.zero-alert {
    margin: 0 16px 10px;
    background: #FCEBEB;
    border: 1px solid #F7C1C1;
    border-radius: 8px;
    padding: 9px 12px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: .78rem;
    color: #791F1F;
}

.zero-alert i {
    color: #E24B4A;
    font-size: 1rem;
    flex-shrink: 0;
    margin-top: 1px;
}

.zero-alert.d-none {
    display: none !important;
}

/* Action buttons */
.wallet-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 0 16px 14px;
}

.wallet-actions .btn {
    font-size: .8rem;
    padding: 8px 10px;
    border-radius: 8px;
    font-weight: 500;
}

.btn-topup {
    background: #4A7CF6;
    color: #fff;
    border: none;
}

.btn-topup:hover {
    background: #185FA5;
    color: #fff;
}

.btn-credit {
    background: var(--gt-amber-light);
    color: #633806;
    border: 1px solid #FAC775;
}

.btn-credit:hover {
    background: #FAC775;
    color: #633806;
}

/* divider + footer */
.wallet-footer {
    border-top: 1px solid #e9ecef;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.wallet-footer span {
    font-size: .72rem;
    color: #94a3b8;
}

.wallet-footer a {
    font-size: .72rem;
    color: #4A7CF6;
    text-decoration: none;
    font-weight: 500;
}

.wallet-footer a:hover {
    text-decoration: underline;
}
</style>
