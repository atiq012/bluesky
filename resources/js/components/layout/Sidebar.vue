<script setup>
import { ref, inject, onMounted } from 'vue';
import { MetisMenu } from "metismenujs";
import axiosInstance from "../../axiosInstance"
import ability from '../../services/ability';
import { AbilityBuilder, createMongoAbility } from '@casl/ability';
import { useAbility } from '@casl/vue';

// const can  = useAbility();

function menuTaggle() {
    $(".wrapper").toggleClass("toggled");
}

async function getPermissionValues() {

    try {
        const response = await axiosInstance.get("abilities");
        const permissions = response.data;
        const { can, rules } = new AbilityBuilder(createMongoAbility);
        can(permissions);
        ability.update(rules);
        // console.log(permissions);

    } catch (error) {

    }
}

onMounted(() => {
    new MetisMenu("#menu");
    getPermissionValues();
});

</script>
<template>
    <!-- main script -->
    <div class="sidebar-wrapper">
        <div class="sidebar-header">
            <div>
                <img src="../../../../public/theme/appimages/blueskywings.png" class="logo-icon" alt="logo icon">
            </div>
            <div>
                <h4 class="logo-text tx">BlueSky</h4>
            </div>
            <div class="mobile-toggle-icon ms-auto" @click="menuTaggle"><i class='bx bx-x'></i>
            </div>
        </div>
        <!--navigation-->
        <Scrollbar height="100%">
            <ul class="metismenu" id="menu">
                <li class="menu-label">MAIN</li>

                <li>
                    <router-link v-wave :to="{ name: 'Home' }">
                        <!-- <div class="parent-icon">
                            <img src="../../../../public/theme/Sidebar_icons/B2B_Agency.svg" alt="">

                        </div> -->
                        <i class="bx bx-home-circle fs-5"></i>
                        <div class="menu-title">Dashboard</div>
                    </router-link>
                </li>

                <li class="menu-label">OPERATIONS</li>

                <li>
                    <a v-wave class="has-arrow" href="javascript:;">
                        <div class="parent-icon">

                            <img src="../../../../public/theme/Sidebar_icons/Flight.svg" alt="">
                        </div>
                        <div class="menu-title">Flight Management</div>
                    </a>
                    <ul>

                        <li>
                            <router-link v-wave :to="{ name: 'searchResult' }">
                                <i class='bx bx-radio-circle'></i> Search
                            </router-link>
                        </li>

                        <li>
                            <router-link v-wave :to="{ name: 'bookingList' }">

                                <i class='bx bx-radio-circle'></i> Booking & Ticketing
                            </router-link>
                        </li>
                        <li>
                            <router-link v-wave :to="{ name: 'searchResult' }">
                                <i class='bx bx-radio-circle'></i> Flight PNR
                            </router-link>
                        </li>
                    </ul>
                </li>
                <li class="menu-label">Finance</li>

                <li>
                    <router-link v-wave :to="{ name: 'depositList' }">
                        <div class="parent-icon">
                            <img src="../../../../public/theme/Sidebar_icons/Deposit.svg" alt="">
                        </div>
                        <div class="menu-title">Deposits
                        </div>
                    </router-link>
                </li>

                <li class="menu-label">Administration</li>

                <li>
                    <router-link v-wave :to="{ name: 'TravelerList' }">
                        <div class="parent-icon">
                            <img src="../../../../public/theme/Sidebar_icons/Traveler.svg" alt="">

                            <!-- <i style="font-size: 15px;" class="fa fa-users"></i> -->
                        </div>
                        <div class="menu-title">Travelers</div>
                    </router-link>
                </li>


                <li>
                    <a v-wave class="has-arrow" href="javascript:;">
                        <div class="parent-icon">
                            <img src="../../../../public/theme/Sidebar_icons/Setting.svg" alt="">
                            <!-- <i class="bx bx-cog"></i> -->
                        </div>
                        <div class="menu-title">Settings</div>
                    </a>
                    <ul>
                        <router-link v-wave :to="{ name: 'UserList' }">
                            <div class="parent-icon">
                                <img src="../../../../public/theme/Sidebar_icons/User.svg" alt="">
                                <!-- <i class="bx bx-user"></i> -->
                            </div>
                            <div class="menu-title">Users</div>
                        </router-link>
                    </ul>
                </li>

                <li>
                    <router-link v-wave :to="{ name: 'Logout' }">
                        <div class="parent-icon"><i class="bx bx-power-off link-danger"></i>
                        </div>
                        <div class="menu-title">Logout</div>
                    </router-link>
                </li>

            </ul>
        </Scrollbar>
    </div>
</template>
<style scoped>
@import url("https://fonts.googleapis.com/css?family=Pirata+One|Rubik:900");

@font-face {
    font-family: "cart";
    src: url("../../../../public/fonts/cartagestenciljnl.ttf");
}


.tx {
    text-transform: Uppercase;
    font-family: "cart", sans-serif;
    font-size: 6rem;
    color: #e4e5e6;
    background: linear-gradient(to right, #02b9af, #4e86f4, #9c54f0);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
</style>
