<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '../../../axiosInstance';
import { runAction } from '../../../utils/runAction';
</script>

<template>
    <div class="container-fluid px-2 px-md-3">
        <!-- Header Section -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <router-link :to="{ name: 'apiManagement' }" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
            </router-link>

            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="fw-semibold text-dark" style="font-size: clamp(14px, 2vw, 18px);">Flight PNR</div>
                    <span class="text-muted small d-none d-sm-inline">|</span>
                    <nav aria-label="breadcrumb" class="d-none d-sm-block">
                        <ol class="breadcrumb mb-0 p-0 small">
                            <li class="breadcrumb-item">
                                <router-link :to="{ name: 'Home' }">Dashboard</router-link>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Flight PNR</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="row page-grid-row g-4">

        <!-- ════ MAIN COLUMN ════ -->
        <div class="col-lg-8 d-flex flex-column gap-4">

            <!-- ── Flight PNR Card ──────────────────────────── -->
            <div class="card-shell">

                <!-- Title -->
                <div class="section-title">
                    <span class="bar-blue"></span>
                    Flight PNR
                    <button class="btn btn-link text-primary fw-semibold p-0 ms-auto" type="button"
                        onclick="showToast('Printing document...')">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>

                <!-- PNR Input -->
                <div class="pnr-row">
                    <label class="form-label-custom">PNR</label>
                    <div class="input-group gap-3">
                        <input type="text" class="form-control pnr-input" value="GZXTCZ"
                            placeholder="Enter GDS or Airline PNR" />
                        <button class="btn-check-pnr" type="button"
                            onclick="showToast('Checking PNR Status...')">Check</button>
                    </div>
                </div>

                <!-- Route + Status -->
                <div class="route-header">
                    <div class="route-text">
                        DAC&nbsp;-&nbsp;DXB &nbsp;<span class="text-muted fw-normal">|</span>&nbsp;
                        DXB&nbsp;-&nbsp;DAC
                    </div>
                    <button class="btn btn-link text-primary text-decoration-underline fw-semibold p-0" type="button">
                        <i class="bi bi-send" style="transform:rotate(-30deg);display:inline-block;"></i> Share
                    </button>
                    <span class="badge-ticketed">
                        <i class="bi bi-check-circle-fill"></i> Ticketed
                    </span>
                </div>

                <!-- Booking Meta -->
                <div class="booking-meta">
                    <div class="meta-item">
                        <div class="meta-key">Agency</div>
                        <div class="meta-val">BCD Travels Ltd. <i class="bi bi-info-circle info-icon"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Agency Info"></i></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-key">Booking Id</div>
                        <div class="meta-val">BLU000001</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-key">GDS PNR</div>
                        <div class="meta-val">SPNR01</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-key">Airline PNR</div>
                        <div class="meta-val">MNERUY</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-key">Issue Date</div>
                        <div class="meta-val">16-Jul-2025</div>
                    </div>
                </div>

                <!-- Flight Details Header -->
                <div class="collapse-header">
                    <span class="collapse-header-title">Flight Details</span>
                    <i class="bi bi-chevron-up collapse-chevron"></i>
                </div>

                <!-- Bootstrap Tabs Structure (Modified to look like original pills) -->
                <ul class="nav segment-tabs nav-pills" id="flightTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link seg-tab-label active" id="outbound-tab" data-bs-toggle="pill"
                            data-bs-target="#outbound-pane" type="button" role="tab">DAC-DXB</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link seg-tab-label" id="return-tab" data-bs-toggle="pill"
                            data-bs-target="#return-pane" type="button" role="tab">DXB-DAC</button>
                    </li>
                </ul>

                <div class="tab-content flight-detail-body" id="flightTabsContent">

                    <!-- Outbound Pane -->
                    <div class="tab-pane fade show active" id="outbound-pane" role="tabpanel">
                        <!-- Segment Card -->
                        <div class="flight-segment-card">
                            <div class="segment-departure-bar">
                                <div class="dep-bar-left">
                                    <i class="bi bi-airplane-fill" style="transform:rotate(45deg);"></i>
                                    Departure From Hazrat Shahjalal International Airport
                                </div>
                                <div class="dep-bar-right">Flight Time: 01 hr 05 min</div>
                            </div>
                            <div class="segment-body">
                                <div class="seg-route-row">
                                    <div>
                                        <div class="seg-airport-code">DAC</div>
                                        <div class="seg-time">10:50 AM <span class="text-muted fw-normal">|</span>
                                            <span class="seg-date">19 Dec, Thursday</span>
                                        </div>
                                        <div class="seg-terminal">Terminal: 2</div>
                                    </div>
                                    <div class="seg-mid">
                                        <div class="seg-plane-track">
                                            <span class="track-dot"></span>
                                            <span class="track-line"></span>
                                            <i class="fa fa-airplane track-plane"
                                                style="transform:rotate(45deg);"></i>
                                            <span class="track-line"></span>
                                            <span class="track-dot"></span>
                                        </div>
                                    </div>
                                    <div class="text-end seg-right">
                                        <div class="seg-airport-code">DXB</div>
                                        <div class="seg-time">11:55 AM <span class="text-muted fw-normal">|</span>
                                            <span class="seg-date">19 Dec, Thursday</span>
                                        </div>
                                        <div class="seg-terminal">Terminal: 3</div>
                                    </div>
                                </div>
                                <div class="seg-footer">
                                    <span class="pill-flight">QR668 – Boeing 777</span>
                                    <span class="pill-class">Class : Economy</span>
                                    <div class="airline-badge">
                                        <div class="airline-logo-box">
                                            <!-- SVG Icon Placeholder -->
                                            <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg"
                                                width="100%" height="100%">
                                                <rect width="36" height="36" rx="6" fill="#5C0632" />
                                                <path
                                                    d="M18 7C12.48 7 8 11.48 8 17c0 5.52 4.48 10 10 10s10-4.48 10-10c0-5.52-4.48-10-10-10zm1 17.93V23h-2v1.93C13.06 24.48 11 22.42 11 20h1v-2h-1c0-2.42 2.06-4.48 5-4.93V15h2v-1.93C21.94 13.52 24 15.58 24 18h-1v2h1c0 2.42-2.06 4.48-5 4.93z"
                                                    fill="white" />
                                            </svg>
                                        </div>
                                        <span class="airline-name">Qatar Airways</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Destination notice -->
                        <div class="dest-notice">
                            <i class="bi bi-geo-alt-fill" style="color:#ef4444;"></i>
                            Reached Destination at Dubai | Dubai International Airport
                        </div>
                    </div>

                    <!-- Return Pane (Placeholder for structure) -->
                    <div class="tab-pane fade" id="return-pane" role="tabpanel">
                        <div class="text-center p-4 text-muted">Return Flight Details Loaded Here</div>
                    </div>
                </div>

            </div><!-- /Flight PNR Card -->

            <!-- ── Passenger Details Card ──────────────────── -->
            <div class="card-shell">
                <div class="collapse-header" style="cursor:default;">
                    <span class="collapse-header-title">Passenger Details</span>
                    <i class="bi bi-chevron-up collapse-chevron"></i>
                </div>
                <div class="table-responsive passenger-table-wrapper">
                    <table class="passenger-table table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Traveler</th>
                                <th>Ticket No.</th>
                                <th>PNR</th>
                                <th>Passport No.</th>
                                <th>Date of Birth</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Mr.Md. Milon Mahmud</td>
                                <td>157-2335700012</td>
                                <td>Male</td>
                                <td>BGD12456734</td>
                                <td>20-Mar-1988</td>
                                <td>+8801770000000</td>
                            </tr>
                            <tr>
                                <td>Mr.Md. Atiqur Rahman</td>
                                <td>157-2335700013</td>
                                <td>Male</td>
                                <td>BGD12457043</td>
                                <td>12-May-1980</td>
                                <td>+8801770000000</td>
                            </tr>
                            <tr>
                                <td>Mr. Sejan Mahmud</td>
                                <td>157-2335700014</td>
                                <td>Male</td>
                                <td>BGD12451904</td>
                                <td>23-Aug-2015</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Mist. Anaya Kabir</td>
                                <td>157-2335700015</td>
                                <td>Female</td>
                                <td>BGD12451945</td>
                                <td>11-Dec-2024</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>Mr.Md. Abu Syed</td>
                                <td>
                                    157-2335700016
                                    <span class="badge-exchange">Exchange</span>
                                </td>
                                <td>Male</td>
                                <td>BGD12451917</td>
                                <td>11-Dec-2024</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div><!-- /Passenger Details Card -->

        </div><!-- /main column -->

        <!-- ════ FARE SIDEBAR ════ -->
        <div class="col-lg-4 fare-sidebar">

            <!-- Fare Summary Card -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    Fare Summary
                    <i class="bi bi-chevron-up" style="font-size:15px;"></i>
                </div>
                <div class="sidebar-body">
                    <div class="fare-group">
                        <div class="fare-group-label">Base Fare</div>
                        <div class="fare-row">
                            <span class="fare-row-key">Adult : 2×<span class="taka"></span>30000</span>
                            <span class="fare-row-val"><span class="taka"></span>60000</span>
                        </div>
                        <div class="fare-row">
                            <span class="fare-row-key">Child : 2×<span class="taka"></span>20000</span>
                            <span class="fare-row-val"><span class="taka"></span>40000</span>
                        </div>
                    </div>
                    <div class="fare-group">
                        <div class="fare-group-label">TAX</div>
                        <div class="fare-row">
                            <span class="fare-row-key">Adult : 2×<span class="taka"></span>7456</span>
                            <span class="fare-row-val"><span class="taka"></span>14912</span>
                        </div>
                        <div class="fare-row">
                            <span class="fare-row-key">Child : 2×<span class="taka"></span>6340</span>
                            <span class="fare-row-val"><span class="taka"></span>12680</span>
                        </div>
                    </div>
                    <div class="fare-group">
                        <div class="fare-group-label">AIT</div>
                        <div class="fare-row">
                            <span class="fare-row-key">Adult : 2×<span class="taka"></span>1275</span>
                            <span class="fare-row-val"><span class="taka"></span>2550</span>
                        </div>
                        <div class="fare-row">
                            <span class="fare-row-key">Child : 2×<span class="taka"></span>870</span>
                            <span class="fare-row-val"><span class="taka"></span>1740</span>
                        </div>
                    </div>
                    <div class="fare-group">
                        <div class="fare-group-label">Service Charge</div>
                        <div class="fare-row">
                            <span class="fare-row-key">Adult : 2×<span class="taka"></span>870</span>
                            <span class="fare-row-val"><span class="taka"></span>1740</span>
                        </div>
                        <div class="fare-row">
                            <span class="fare-row-key">Child : 2×<span class="taka"></span>620</span>
                            <span class="fare-row-val"><span class="taka"></span>1240</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="total-fare">
                <div class="fare-row">
                    <span class="fare-row-key1">Total Fare</span>
                    <span class="fare-row-val1"><span class="taka"></span>133702</span>
                </div>
            </div>

            <!-- Discount + Total Payable Card -->
            <div class="sidebar-card">
                <div class="sidebar-body">
                    <div style="padding-top:6px;">
                        <div style="font-size:11.5px;color:#2970f5;font-weight:500;margin-top:6px;">Discount</div>
                        <div class="discount-row">
                            <span class="discount-label">Availed amount</span>
                            <span class="discount-amount">-<span class="taka"></span>2267</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;padding:6px 0 8px;">
                            <span class="coupon-pill">FLYCITY</span>
                            <span class="coupon-pct">15%</span>
                        </div>
                    </div>
                    <div class="total-payable-row">
                        <span class="total-payable-label">Total Payable</span>
                        <span class="total-payable-val"><span class="taka"></span>131435</span>
                    </div>
                </div>

                <!-- Baggage Bootstrap Accordion -->
                <div class="accordion accordion-flush" id="accordionBaggage">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapseBaggage" aria-expanded="false" aria-controls="collapseBaggage">
                                Baggage Information
                            </button>
                        </h2>
                        <div id="collapseBaggage" class="accordion-collapse collapse"
                            data-bs-parent="#accordionBaggage">
                            <div class="accordion-body">
                                <div class="baggage-item"><span>Adult (Check-in)</span><span>30 kg</span></div>
                                <div class="baggage-item"><span>Adult (Cabin)</span><span>7 kg</span></div>
                                <div class="baggage-item"><span>Child (Check-in)</span><span>25 kg</span></div>
                                <div class="baggage-item"><span>Child (Cabin)</span><span>7 kg</span></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div><!-- /fare-sidebar -->

    </div><!-- /row -->
</template>

<style scoped>
/* Font Utilities */
.font-nunito {
    font-family: 'Nunito', sans-serif;
}
    .taka::before { content: '৳'; }

.page-body {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 48px;
}

/* ══════════════════════════════════════════════════════
       CARD SHELL OVERRIDES
    ══════════════════════════════════════════════════════ */
.card-shell {
    background: #fff;
    border-radius: 12px;;
    border: 1.5px solid #e0e4f0;
    overflow: hidden;
    margin-bottom: 24px;
}

/* Custom Card Header Styling */
.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Nunito', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: #1a1a2e;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-color);
}

.bar-blue {
    width: 5px;
    height: 22px;
    border-radius: 3px;
    background: linear-gradient(180deg, #5b8cf7, #9b59f7);
    flex-shrink: 0;
}

/* Collapse Header mimic */
.collapse-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    background: #f8f9fc;
    border-bottom: 1px solid var(--border-color);
    user-select: none;
}

.collapse-header-title {
    font-family: 'Be Vietnam Pro', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: #1a1a2e;
}

/* ── PNR Input ─────────────────────────────────── */
.pnr-row {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
}

.form-label-custom {
    font-size: 13px;
    font-weight: 600;
    color: #222;
    margin-bottom: 8px;
}

.pnr-input {
    border: 1.5px solid #e2e5f0;
    border-radius: 7px;
    padding: 11px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #333;
}

.pnr-input:focus {
    border-color: #3265f0;
    box-shadow: 0 0 0 3px rgba(50, 101, 240, .12);
}

.btn-check-pnr {
    background: #dfe0e1;
    color: #fff;
    /* Original request had this grey initially, but hover was blue. Keeping original intent */
    border: none;
    border-radius: 8px;
    padding: 11px 32px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-check-pnr:hover {
    background: #2c6bfa;
    color: #fff;
}

/* ── Route Header ─────────────────────────────────── */
.route-header {
    padding: 18px 24px;
    display: flex;
    align-items: center;
    gap: 14px;
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
}

.route-text {
    font-family: 'Nunito', sans-serif;
    font-size: 20px;
    font-weight: 800;
    color: #1a1a2e;
}

.badge-ticketed {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--success-bg);
    color: var(--success);
    font-weight: 700;
    font-size: 13px;
    padding: 5px 14px;
    border-radius: 50px;
    margin-left: auto;
}

/* ── Booking Meta ─────────────────────────────────── */
.booking-meta {
    display: flex;
    gap: 0;
    padding: 16px 24px;
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
}

.meta-item {
    flex: 1;
    min-width: 120px;
}

.meta-item+.meta-item {
    border-left: 1px solid var(--border-color);
    padding-left: 18px;
    margin-left: 18px;
}

.meta-key {
    font-size: 11.5px;
    color: #8a94a6;
    font-weight: 500;
    margin-bottom: 4px;
}

.meta-val {
    font-size: 13.5px;
    font-weight: 700;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 4px;
}

.meta-val .info-icon {
    color: #b0b4c4;
    font-size: 13px;
    cursor: pointer;
}

/* ── Tabs (Pills style) ──────────────────────────── */
.segment-tabs {
    padding: 14px 24px 0;
    gap: 8px;
    border-bottom: 1px solid var(--border-color);
}

.seg-tab-label {
    padding: 6px 18px;
    border-radius: var(--radius-input);
    font-size: 12.5px;
    font-weight: 700;
    border: 1.5px solid #e3e8f0;
    background: #fff;
    color: #8a94a6;
    cursor: pointer;
    text-decoration: none;
    margin-bottom: -1.5px;
    /* Overlap border */
    transition: all .15s;
}

.seg-tab-label:hover {
    background: #f8f9fc;
}

/* Active state for Bootstrap nav-pill/link */
.seg-tab-label.active {
    background: #3b79f2;
    color: #fff;
    border-color: #3b79f2;
}

/* ── Flight Segment Card ───────────────────────────── */
.flight-segment-card {
    margin: 14px 24px;
    border: 1.5px solid #e0e4f0;
    border-radius: 12px;;
    overflow: hidden;
}

.segment-departure-bar {
    background: #eff4ff;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #dbeafe;
}

.dep-bar-left {
    font-size: 12.5px;
    color: #3b79f2;
    font-weight: 600;
    display: flex;
    gap: 8px;
    align-items: center;
}

.dep-bar-right {
    font-size: 12.5px;
    color: #3b79f2;
    font-weight: 600;
}

.segment-body {
    padding: 16px 18px 14px;
}

.seg-route-row {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 10px;
}

.seg-airport-code {
    font-family: 'Nunito', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: #3b79f2;
}

.seg-time {
    font-size: 13.5px;
    font-weight: 700;
    color: #1a1a2e;
    margin-top: 2px;
}

.seg-date {
    font-size: 12px;
    color: #8a94a6;
}

.seg-terminal {
    font-size: 12px;
    color: #8a94a6;
    margin-top: 2px;
}

.seg-mid {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.seg-plane-track {
    display: flex;
    align-items: center;
    gap: 4px;
    width: 100%;
    min-width: 80px;
}

.track-dot {
    width: 9px;
    height: 9px;
    background: #ef4444;
    border-radius: 50%;
    flex-shrink: 0;
}

.track-line {
    flex: 1;
    height: 2px;
    background: repeating-linear-gradient(90deg, #9ca3af 0, #9ca3af 4px, transparent 4px, transparent 8px);
}

.track-plane {
    color: #3b79f2;
    font-size: 15px;
    flex-shrink: 0;
}

.seg-footer {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #e3e8f0;
    flex-wrap: wrap;
}

.pill-flight {
    background: #eff4ff;
    color: #3b79f2;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 50px;
    border: 1px solid #bfdbfe;
}

.pill-class {
    background: #f0fdf4;
    color: #16a34a;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 50px;
    border: 1px solid #bbf7d0;
}

.airline-badge {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}

.airline-logo-box {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #e3e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #fff;
}

.airline-name {
    font-size: 13px;
    font-weight: 600;
    color: #1a1a2e;
}

.dest-notice {
    margin: 0 24px 16px;
    padding: 10px 16px;
    background: #eff4ff;
    border-radius: var(--radius-input);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #3b79f2;
    font-weight: 500;
}

/* ── Passenger Table ───────────────────────────────── */
.passenger-table {
    width: 100%;
    border-collapse: collapse;
}

.passenger-table thead tr th {
    background: #3b79f2;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    padding: 12px 16px;
    text-align: left;
    white-space: nowrap;
}

.passenger-table tbody tr td {
    padding: 12px 16px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
    color: #1a1a2e;
    white-space: nowrap;
}

.passenger-table tbody tr:hover td {
    background: #fafbff;
}

.badge-exchange {
    display: inline-block;
    background: var(--warning-bg);
    color: var(--warning);
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    margin-left: 6px;
    border: 1px solid #fde68a;
}

/* ── Fare Sidebar ──────────────────────────────────── */
.fare-sidebar {
    position: sticky;
    top: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sidebar-card {
    background: #f6f9ff;
    border-radius: 12px;;
    border: 1.5px solid #e0e4f0;
    overflow: hidden;
}

.sidebar-header {
    background: #3b79f2;
    color: #fff;
    padding: 13px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
}

.sidebar-body {
    padding: 0 20px;
}

.fare-group {
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.fare-group:last-child {
    border-bottom: none;
}

.fare-group-label {
    font-size: 11px;
    font-weight: 700;
    color: #3b79f2;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 8px;
}

.fare-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.fare-row-key {
    font-size: 12.5px;
    color: #8a94a6;
}

.fare-row-val {
    font-size: 12.5px;
    font-weight: 600;
    color: #1a1a2e;
}

.fare-row-key1 {
    font-size: 13px;
    font-weight: 600;
    color: #3b79f2;
}

.fare-row-val1 {
    font-size: 13px;
    font-weight: 600;
    color: #3b79f2;
}

.total-fare {
    background: #fff;
    border-radius: 10px;
    padding: 14px 12px;
}

.discount-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.discount-label {
    font-size: 12px;
    color: #999ea7;
    font-weight: 500;
    flex: 1;
    margin-top: 10px;
}

.discount-amount {
    font-size: 13px;
    color: #ef4444;
    font-weight: 700;
}

.coupon-pill {
    background: #3b79f2;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
}

.coupon-pct {
    font-size: 12px;
    color: #324467;
    font-weight: 600;
}

.total-payable-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0 0;
    border-top: 1.5px solid #e3e8f0;
    padding-bottom: 14px;
}

.total-payable-label {
    font-size: 13px;
    font-weight: 700;
    color: #1a1a2e;
}

.total-payable-val {
    font-family: 'Nunito', sans-serif;
    font-size: 18px;
    font-weight: 800;
    color: #3b79f2;
}

/* Accordion Overrides for Baggage */
.accordion-flush .accordion-item {
    border-left: none;
    border-right: none;
}

.accordion-flush .accordion-button {
    padding: 12px 20px;
    font-size: 13px;
    font-weight: 600;
    color: #1a1a2e;
    background: #fff;
    box-shadow: none;
}

.accordion-flush .accordion-button:not(.collapsed) {
    background: #f8f9fc;
    color: #1a1a2e;
    box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .125);
}

.accordion-flush .accordion-button::after {
    filter: invert(0.5);
}

.accordion-body {
    padding: 10px 20px 14px;
}

.baggage-item {
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
    color: #8a94a6;
    margin-bottom: 5px;
}

.baggage-item span:last-child {
    font-weight: 600;
    color: #1a1a2e;
}

/* ══════════════════════════════════════════════════════
       RESPONSIVE
    ══════════════════════════════════════════════════════ */
@media (max-width: 991px) {
    .page-grid-row>div {
        width: 100%;
    }

    .fare-sidebar {
        position: static;
        margin-top: 24px;
    }

    .nav-sidebar {
        display: none;
    }

    /* Simplified mobile handling */
}

@media (max-width: 768px) {
    .booking-meta {
        flex-direction: column;
        gap: 15px;
    }

    .meta-item+.meta-item {
        border-left: none;
        border-top: 1px solid var(--border-color);
        padding-left: 0;
        margin-left: 0;
        padding-top: 10px;
        margin-top: 4px;
    }

    .route-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .badge-ticketed {
        margin-left: 0;
    }

    .page-body {
        padding: 16px;
    }

    .pnr-breadcrumb {
        padding: 14px 16px;
    }
}
</style>
