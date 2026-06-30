<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import { runAction } from '../../../utils/runAction';
</script>

<template>
    <div class="container-fluid px-2 px-md-3">
        <AppBreadcrumbs
            title="Flight PNR"
            :back-to="{ name: 'apiManagement' }"
            :breadcrumbs="[
                { label: 'Dashboard', to: { name: 'Home' } },
                { label: 'Flight PNR' },
            ]"
        />

        <!-- PNR Form Card -->
        <div class="row">
            <div class="col-12">
                <div class="pnr-card">
                    <div class="section-heading">
                        <div class="section-heading-left">
                            <span class="bar-blue"></span> Flight PNR
                        </div>
                    </div>

                    <div class="pnr-form-row">
                        <label class="form-label-custom">PNR</label>
                        <div class="pnr-input-group">
                            <input
                                type="text"
                                class="input-custom pnr-input"
                                placeholder="Enter GDS or Airline PNR"
                            />
                             <router-link class="btn-check-pnr" :to="{ name: 'checkFlightPNR' }">Check</router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Base Styles */
.pnr-card {
    background: #fff;
    border-radius: 12px;
    border: 1.5px solid #e0e4f0;
    padding: 20px 16px 24px;
    margin: 0;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.section-heading-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #1a1a2e;
}

.bar-blue {
    width: 5px;
    height: 22px;
    border-radius: 3px;
    background: linear-gradient(180deg, #5b8cf7, #9b59f7);
    flex-shrink: 0;
}

.pnr-form-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-label-custom {
    font-size: 13px;
    font-weight: 600;
    color: #222;
    margin-bottom: 6px;
    display: block;
}

.pnr-input-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.pnr-input {
    flex: 1 1 auto;
    min-width: 200px;
    border: 1.5px solid #e2e5f0;
    border-radius: 7px;
    padding: 11px 16px;
    font-size: 13px;
    color: #333;
    background: #fff;
    outline: none;
    transition: border-color 0.2s;
    height: 44px;
}

.pnr-input:focus {
    border-color: #7c3aed;
}

.pnr-input::placeholder {
    color: #b0b4c4;
}

.btn-check-pnr {
    background: #3b79f2;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 11px 32px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
    height: 44px;
    min-width: 100px;
    flex-shrink: 0;
}

.btn-check-pnr:hover {
    background: #2c6bfa;
}

.btn-check-pnr:active {
    transform: scale(0.98);
}

/* Tablet Responsive (768px - 1024px) */
@media screen and (max-width: 1024px) {
    .pnr-card {
        padding: 18px 14px 22px;
    }

    .pnr-input {
        min-width: 160px;
        font-size: 12px;
        padding: 10px 14px;
    }

    .btn-check-pnr {
        padding: 10px 24px;
        font-size: 13px;
        min-width: 90px;
    }
}

/* Mobile Responsive (up to 768px) */
@media screen and (max-width: 768px) {
    .pnr-card {
        padding: 16px 12px 20px;
        border-radius: 10px;
    }

    .section-heading {
        margin-bottom: 16px;
    }

    .section-heading-left {
        font-size: 14px;
    }

    .bar-blue {
        height: 18px;
        width: 4px;
    }

    .form-label-custom {
        font-size: 12px;
        margin-bottom: 4px;
    }

    .pnr-input-group {
        gap: 8px;
        flex-direction: column;
        align-items: stretch;
    }

    .pnr-input {
        min-width: unset;
        width: 100%;
        font-size: 13px;
        padding: 10px 14px;
        height: 42px;
    }

    .btn-check-pnr {
        width: 100%;
        padding: 10px 16px;
        font-size: 14px;
        height: 42px;
        min-width: unset;
    }
}

/* Small Mobile (up to 480px) */
@media screen and (max-width: 480px) {
    .container-fluid {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    .pnr-card {
        padding: 14px 10px 18px;
        border-radius: 8px;
        border-width: 1px;
    }

    .section-heading-left {
        font-size: 13px;
        gap: 8px;
    }

    .bar-blue {
        height: 16px;
        width: 3.5px;
    }

    .pnr-input {
        font-size: 12px;
        padding: 9px 12px;
        height: 38px;
    }

    .btn-check-pnr {
        font-size: 13px;
        padding: 9px 14px;
        height: 38px;
        border-radius: 6px;
    }

    .form-label-custom {
        font-size: 11px;
        margin-bottom: 3px;
    }
}

/* Touch-friendly improvements */
@media (hover: none) {
    .btn-check-pnr:hover {
        background: #3b79f2;
    }

    .btn-check-pnr:active {
        background: #2c6bfa;
    }

    .pnr-input,
    .btn-check-pnr {
        min-height: 44px;
    }
}

/* Landscape orientation on mobile */
@media screen and (max-height: 500px) and (orientation: landscape) {
    .pnr-card {
        padding: 12px 14px 16px;
    }

    .section-heading {
        margin-bottom: 12px;
    }

    .pnr-input-group {
        flex-direction: row;
        flex-wrap: nowrap;
    }

    .pnr-input {
        flex: 1;
        min-width: 120px;
    }

    .btn-check-pnr {
        width: auto;
        flex-shrink: 0;
    }
}

/* Accessibility - Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .btn-check-pnr {
        transition: none;
    }

    .btn-check-pnr:active {
        transform: none;
    }
}

/* Accessibility - High Contrast */
@media (prefers-contrast: high) {
    .pnr-card {
        border-color: #000;
    }

    .pnr-input {
        border-color: #000;
    }

    .btn-check-pnr {
        background: #0055cc;
    }
}

/* Print Styles */
@media print {
    .pnr-card {
        border: 1px solid #000;
        box-shadow: none;
    }

    .btn-check-pnr {
        background: #000;
        color: #fff;
    }
}
</style>
