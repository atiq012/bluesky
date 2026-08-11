<script setup>
import { ref, onMounted, onBeforeUnmount, reactive } from 'vue';
import Chart from 'chart.js/auto';
import axiosInstance from '../../axiosInstance';


/* ─────────────────────────────────────────
   Chart instance refs
───────────────────────────────────────── */
let bookingDonut = null;
let ticketingDonut = null;
let salesBarChart = null;
let trendingBarChart = null;
let travelerDonut = null;
let bookingClassPie = null;
let ratioAreaChart = null;
let airlinesGauge = null;
let transactionBar = null;

/* ─────────────────────────────────────────
   Static chart data
───────────────────────────────────────── */
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const salesData = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const travelerData = ref([0, 0, 0]);
const bookingClassData = ref([0, 0, 0, 0]);
const bookingVals = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const ticketingVals = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const depositData = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const creditData = ref([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
const airlines = ref([]);


//const totalTravelers = computed(() => travelerData.value.reduce((a, b) => a + b, 0));

//const salesData = [650000, 820000, 530000, 200000, 280000, 960000, 800000, 490000, 720000, 590000, 840000, 380000];
//const bookingVals = [300, 420, 380, 750, 620, 480, 900, 820, 700, 560, 640, 420];
//const ticketingVals = [200, 300, 260, 500, 400, 320, 620, 550, 460, 380, 450, 280];
//const depositData = [600000, 820000, 700000, 780000, 260000, 920000, 490000, 560000, 630000, 610000, 750000, 420000];
//const creditData = [200000, 300000, 280000, 300000, 100000, 420000, 210000, 240000, 280000, 250000, 310000, 150000];

const airlineColors = ['#f97316', '#06b6d4', '#eab308', '#ef4444', '#3b82f6', '#14b8a6', '#64748b', '#a855f7', '#ec4899', '#10b981'];
const airlineLabels = ['Indigo', 'Qatar Airways', 'Emirates', 'Oman Air', 'US Bangla', 'Biman Bangladesh', 'Saudia', 'Jazeera', 'Batik', 'Fly Dubai'];
const airlineValues = [18, 15, 13, 12, 10, 9, 8, 6, 5, 4];

/* ─────────────────────────────────────────
   Reactive data
───────────────────────────────────────── */
const routes = ref([]);
// const routes = ref([
//   { code: 'DAC-DXB', count: 420, color: '#3b82f6', max: 500 },
//   { code: 'DAC-JFK', count: 314, color: '#06b6d4', max: 500 },
//   { code: 'DAC-JED', count: 301, color: '#8b5cf6', max: 500 },
//   { code: 'DAC-KWI', count: 257, color: '#ec4899', max: 500 },
//   { code: 'DAC-BKK', count: 167, color: '#f97316', max: 500 },
//   { code: 'DAC-MLE', count: 126, color: '#e879f9', max: 500 },
//   { code: 'DAC-DEL', count: 90, color: '#14b8a6', max: 500 },
// ]);

const upcomingFlights = ref([
  { route: 'DAC-DXB', type: 'Round Way', date: '08 May | 10:45' },
  { route: 'DAC-JFK-MLE', type: 'Multicity', date: '13 May | 10:45' },
  { route: 'DAC-JED', type: 'One Way', date: '15 May | 10:45' },
  { route: 'DAC-BKK', type: 'Round Way', date: '24 May | 10:45' },
  { route: 'DAC-DOH', type: 'Round Way', date: '28 May | 10:45' },
  { route: 'DAC-KWI', type: 'One Way', date: '31 May | 10:45' },
]);

const bookingStats = reactive({
  totalBookings: 0,
  confirmedBookings: 0,
  cancelledBookings: 0,
  todayBookings: 0,
  bookingRatio: 0,
});

const ticketingStats = reactive({
  totalTicketing: 0,
  ticketed: 0,
  voided: 0,
  todayTicketing: 0,
  ticketingRatio: 0,
});

const salesStats = reactive({
  totalSales: 0,
  ratio: 0,
  todaySales: 0,
})

const fetchDashboardStats = async () => {
  try {
    const response = await axiosInstance.get('/dashboard'); // Adjust endpoint if needed
    const data = response.data?.data ?? response.data ?? {};
    console.log('Dashboard stats fetched:', data);

    // Update booking stats
    bookingStats.totalBookings = data.total_bookings ?? data.totalBookings ?? 0;
    bookingStats.confirmedBookings = data.confirmed_bookings ?? data.confirmedBookings ?? 0;
    bookingStats.cancelledBookings = data.cancelled_bookings ?? data.cancelledBookings ?? 0;
    bookingStats.todayBookings = data.today_booking ?? data.todayBookings ?? 0;
    bookingStats.bookingRatio = data.bookingRatio ?? 0;

    // Update ticketing stats
    ticketingStats.totalTicketing = data.totalTicketing ?? 0;
    ticketingStats.ticketed = data.ticketed ?? 0;
    ticketingStats.voided = data.voided ?? 0;
    ticketingStats.todayTicketing = data.todayTicketing ?? 0;
    ticketingStats.ticketingRatio = data.ticketingRatio ?? 0;

    if (data.monthlySales && Array.isArray(data.monthlySales)) {
      salesData.value = data.monthlySales;
    }
    //console.log('Monthly sales data updated:', salesData.value);

    if (data.trendingRoutes && Array.isArray(data.trendingRoutes)) {
      routes.value = data.trendingRoutes;
    }

    if (data.travelerData && Array.isArray(data.travelerData)) {
      travelerData.value = data.travelerData;
    }

    if (data.bookingClassData && Array.isArray(data.bookingClassData)) {
      bookingClassData.value = data.bookingClassData;
    }

    if (data.monthlyBookings && Array.isArray(data.monthlyBookings)) {
      bookingVals.value = data.monthlyBookings;
    }
    if (data.monthlyTicketing && Array.isArray(data.monthlyTicketing)) {
      ticketingVals.value = data.monthlyTicketing;
    }

    if (data.depositData && Array.isArray(data.depositData)) {
      depositData.value = data.depositData;
    }
    if (data.creditData && Array.isArray(data.creditData)) {
      creditData.value = data.creditData;
    }

    if (data.topAirlines && Array.isArray(data.topAirlines)) {
      airlines.value = data.topAirlines;
    }




  } catch (error) {
    console.error('Error fetching dashboard stats:', error);
  }
};


/* ─────────────────────────────────────────
   Chart helpers
───────────────────────────────────────── */
const makeDonut = (id, data, colors, cutout = '72%') => {
  const el = document.getElementById(id);
  if (!el) return null;
  return new Chart(el.getContext('2d'), {
    type: 'doughnut',
    data: { datasets: [{ data, backgroundColor: colors, borderWidth: 0, cutout }] },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false }, tooltip: { enabled: false } },
      animation: { animateRotate: true, duration: 900 },
    },
  });
};

const makeGradient = (ctx, colorTop, colorBottom) => {
  const g = ctx.createLinearGradient(0, 0, 0, 380);
  g.addColorStop(0, colorTop);
  g.addColorStop(1, colorBottom);
  return g;
};

/* ─────────────────────────────────────────
   Init all charts
───────────────────────────────────────── */
const initCharts = () => {

  /* ── KPI Donuts ── */
  // bookingDonut = makeDonut('bookingDonut',
  //   [30, 40, 15, 15],
  //   ['#f59e0b', '#10b981', '#ef4444', '#f97316']);

  // ticketingDonut = makeDonut('ticketingDonut',
  //   [70, 30],
  //   ['#10b981', '#ef4444']);

  /* ── Total Sales & Commission bar ── */
  const sbEl = document.getElementById('salesBarChart');
  if (sbEl) {
    salesBarChart = new Chart(sbEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{
          label: 'Total Sales',
          data: salesData.value,
          backgroundColor: '#4f7ef8',
          borderRadius: 8,
          barPercentage: 0.6,
          categoryPercentage: 0.75,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ' BDT ' + c.parsed.y.toLocaleString() } },
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: '#eef1f9' },
            ticks: {
              color: '#94a3b8', font: { size: 11 },
              callback: (v) => v >= 1e6 ? (v / 1e6).toFixed(0) + 'M' : v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v,
              stepSize: 200000,
            },
          },
          x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
        },
      },
    });
  }

  /* ── Trending Routes horizontal bar ── */
  const trEl = document.getElementById('trendingBarChart');
  if (trEl) {
    trendingBarChart = new Chart(trEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: routes.value.map(r => ''),   // labels hidden; route badges are in HTML overlay
        datasets: routes.value.map((r, i) => ({
          label: r.code,
          data: routes.value.map((_, j) => j === i ? r.count : null),
          backgroundColor: r.color,
          borderRadius: 6,
          barPercentage: 0.65,
          categoryPercentage: 0.85,
          skipNull: true,
        })),
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: true } },
        scales: {
          x: {
            beginAtZero: true, max: 500,
            grid: { color: '#eef1f9' },
            ticks: { stepSize: 100, color: '#94a3b8', font: { size: 11 } },
          },
          y: { display: false, grid: { display: false } },
        },
      },
    });
  }

  /* ── Total Traveler donut ── */
  const ttEl = document.getElementById('travelerDonut');
  if (ttEl) {
    travelerDonut = new Chart(ttEl.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Adult', 'Children', 'Infant'],
        datasets: [{
          data: travelerData.value,
          backgroundColor: ['#3b82f6', '#8b5cf6', '#06b6d4'],
          borderWidth: 0, cutout: '74%',
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.parsed}` } },
        },
        animation: { duration: 900 },
      },
    });
  }

  /* ── Trending Booking Class pie ── */
  const bcEl = document.getElementById('bookingClassPie');
  if (bcEl) {
    bookingClassPie = new Chart(bcEl.getContext('2d'), {
      type: 'pie',
      data: {
        labels: ['Economy', 'Premium Economy', 'Business Class', 'First Class'],
        datasets: [{
          data: bookingClassData.value,
          backgroundColor: ['#06b6d4', '#eab308', '#8b5cf6', '#f97316'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.parsed}%` } },
        },
        animation: { duration: 900 },
      },
    });
  }

  /* ── Booking vs Ticketing area ── */
  const raEl = document.getElementById('ratioAreaChart');
  if (raEl) {
    const raCtx = raEl.getContext('2d');
    ratioAreaChart = new Chart(raCtx, {
      type: 'line',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Booking',
            data: bookingVals.value,
            borderColor: '#c084fc',
            backgroundColor: makeGradient(raCtx, 'rgba(192,132,252,0.55)', 'rgba(192,132,252,0.0)'),
            borderWidth: 2.5, fill: true, tension: 0.55, pointRadius: 0,
          },
          {
            label: 'Ticketing',
            data: ticketingVals.value,
            borderColor: '#818cf8',
            backgroundColor: makeGradient(raCtx, 'rgba(129,140,248,0.45)', 'rgba(129,140,248,0.0)'),
            borderWidth: 2.5, fill: true, tension: 0.55, pointRadius: 0,
          },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          y: { beginAtZero: true, grid: { color: '#eef1f9' }, ticks: { color: '#94a3b8', font: { size: 11 } } },
          x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
        },
      },
    });
  }

  /* ── Top 10 Airlines half-gauge ── */
  const taEl = document.getElementById('airlinesGauge');
  if (taEl) {
    airlinesGauge = new Chart(taEl.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: airlineLabels,
        datasets: [{
          data: airlineValues,
          backgroundColor: airlineColors,
          borderWidth: 0,
          cutout: '55%',
          circumference: 180,
          rotation: -90,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.parsed}%` } },
        },
        animation: { duration: 900 },
      },
    });
  }

  /* ── Total Transaction grouped bar ── */
  const txEl = document.getElementById('transactionBar');
  if (txEl) {
    transactionBar = new Chart(txEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Deposit',
            data: depositData.value,
            backgroundColor: '#4f7ef8',
            borderRadius: 6,
            barPercentage: 0.55, categoryPercentage: 0.75,
          },
          {
            label: 'Credit',
            data: creditData.value,
            backgroundColor: '#f97316',
            borderRadius: 6,
            barPercentage: 0.55, categoryPercentage: 0.75,
          },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            mode: 'index', intersect: false,
            callbacks: { label: (c) => ` ${c.dataset.label}: BDT ${c.parsed.y.toLocaleString()}` }
          },
        },
        scales: {
          y: {
            beginAtZero: true, grid: { color: '#eef1f9' },
            ticks: {
              color: '#94a3b8', font: { size: 11 },
              callback: (v) => v >= 1e6 ? (v / 1e6).toFixed(0) + 'M' : v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v,
              stepSize: 200000,
            },
          },
          x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
        },
      },
    });
  }

  const total = bookingStats.totalBookings;
  const confirmed = bookingStats.confirmedBookings;

  const confirmedPercent = total > 0 ? Math.round((confirmed / total) * 100) : 0;

  const bookingCanvas = document.getElementById('bookingDonut');
  if (bookingCanvas) {
    const ctx = bookingCanvas.getContext('2d');
    bookingDonut = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Confirmed', 'Cancelled'],
        datasets: [{
          data: [confirmedPercent, 100 - confirmedPercent],
          backgroundColor: ['#10b981', '#f97316'], // Blue for confirmed, red for cancelled
          borderWidth: 0,
          cutout: '75%',
          borderRadius: 8,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2,
        rotation: 270,
        circumference: 180,
        plugins: {
          legend: { display: false },
          tooltip: { enabled: false }
        },
        animation: { animateRotate: true, duration: 900 }
      }
    });
  }

  const ticketingCanvas = document.getElementById('ticketingDonut');
  if (ticketingCanvas) {
    const ctx = ticketingCanvas.getContext('2d');
    ticketingDonut = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Ticketed', 'Others'],
        datasets: [{
          data: [ticketingStats.ticketed, ticketingStats.voided],
          backgroundColor: ['#10b981', '#ef4444'],
          borderWidth: 0,
          cutout: '70%',
          spacing: 0,
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,

        plugins: {
          legend: { display: false },
          tooltip: { enabled: false }
        },
        animation: { animateRotate: true, duration: 900 }
      }
    });
  }
};


const addCenterTextToBookingGauge = (percent) => {
  if (bookingDonut) {
    const originalDraw = bookingDonut.draw;
    bookingDonut.draw = function () {
      originalDraw.apply(this, arguments);
      const ctx = this.ctx;
      const canvas = this.canvas;

      // ✅ Use chartArea for accurate center (accounts for responsive resize)
      const chartArea = this.chartArea;
      const centerX = (chartArea.left + chartArea.right) / 2;
      const centerY = chartArea.bottom;  // bottom of chart = center of half-gauge arc

      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = 'bold 16px "Plus Jakarta Sans", sans-serif';
      ctx.fillStyle = '#0f1535';
      ctx.fillText(`${percent}%`, centerX, centerY - 22);   // ✅ relative offset

      ctx.font = '10px "Plus Jakarta Sans", sans-serif';
      ctx.fillStyle = '#0f1535';
      ctx.fillText(`Confirmed`, centerX, centerY - 8);      // ✅ relative offset
      ctx.restore();
    };
    bookingDonut.draw();
  }
};

/* ─────────────────────────────────────────
   Utilities
───────────────────────────────────────── */
const pct = (count, max) => ((count / max) * 100).toFixed(1);
//const totalTravelers = computed(() => travelerData.value.reduce((a, b) => a + b, 0))

function formatTotal(value) {
  if (value >= 1000000) {
    return (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
  } else if (value >= 100000) { // 1 lakh = 100,000
    return (value / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  } else {
    return value.toLocaleString('en-US'); // comma-separated (Indian style)
  }
}

function formatValues(value) {
  if (value >= 1000000) {
    return (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
  } else if (value >= 10000) { // 10 thousand = 10,000
    return (value / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  } else {
    return value.toLocaleString('en-US'); // comma-separated (Indian style)
  }
}

onMounted(async () => {
  await fetchDashboardStats();
  setTimeout(() => {
    initCharts();
    setTimeout(() => {
      const total = bookingStats.totalBookings;
      const confirmed = bookingStats.confirmedBookings;
      const percent = total > 0 ? Math.round((confirmed / total) * 100) : 0;
      addCenterTextToBookingGauge(percent);
    }, 100);
  }, 100);
});
onBeforeUnmount(() => {
  [bookingDonut, ticketingDonut, salesBarChart, trendingBarChart,
    travelerDonut, bookingClassPie, ratioAreaChart, airlinesGauge, transactionBar]
    .forEach(c => c?.destroy());
});
</script>

<template>
  <div class="dashboard-wrapper">
    <div class="container-fluid px-0">

      <!-- ══════════════════════════════════════════════════════
           ROW 1 — KPI cards (left col) + Sales & Commission bar
      ══════════════════════════════════════════════════════ -->
      <div class="row g-4 mb-4">

        <!-- Left: Total Booking + Total Ticket stacked -->
        <div class="col-12 col-lg-4">
          <div class="row g-4 h-100">

            <!-- Total Booking -->
            <div class="col-12">
              <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="kpi-label">Bookings</span>
                  <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
                </div>
                <div class="kpi-value-row d-flex align-items-center gap-3">
                  <div class="kpi-value">{{ formatTotal(bookingStats.totalBookings) }}</div>
                  <div class="kpi-badge-row">
                    <span :class="['kpi-trend', bookingStats.bookingRatio >= 0 ? 'up' : 'down']">
                      <i :class="bookingStats.bookingRatio >= 0
                        ? 'fa-solid fa-arrow-trend-up'
                        : 'fa-solid fa-arrow-trend-down'
                        "></i>

                      {{ bookingStats.bookingRatio > 0 ? '+' + bookingStats.bookingRatio : bookingStats.bookingRatio }}%
                    </span>
                    <!-- <i class="bi bi-graph-up-arrow text-success"></i>
                  <span class="text-success fw-bold small">{{ bookingStats.bookingRatio }}%</span> -->
                    <span class="text-secondary small">vs YD</span>
                  </div>
                </div>


                <div class="d-flex align-items-center gap-3 mt-3">
                  <div class="kpi-donut-wrap">
                    <canvas id="bookingDonut" width="120" height="60"></canvas>
                  </div>
                  <div class="chart-legend">
                    <!-- <div><span class="ldot" style="background:#f59e0b"></span>In Progress</div> -->
                    <div><span class="ldot" style="background:#10b981"></span>{{
                      formatValues(bookingStats.confirmedBookings) }} Confirmed
                    </div>
                    <!-- <div><span class="ldot" style="background:#ef4444"></span>Failed</div> -->
                    <div><span class="ldot" style="background:#f97316"></span>{{
                      formatValues(bookingStats.cancelledBookings) }} Canceled
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Total Ticket -->
            <div class="col-12">
              <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="kpi-label">Tickets</span>
                  <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
                </div>

                <div class="kpi-value-row d-flex align-items-center gap-3">
                  <div class="kpi-value">{{ formatTotal(ticketingStats.totalTicketing) }}</div>
                  <div class="kpi-badge-row">
                    <span :class="['kpi-trend', ticketingStats.ticketingRatio >= 0 ? 'up' : 'down']">
                      <i :class="bookingStats.bookingRatio >= 0
                        ? 'fa-solid fa-arrow-trend-up'
                        : 'fa-solid fa-arrow-trend-down'
                        "></i>

                      {{ ticketingStats.ticketingRatio > 0 ? '+' + ticketingStats.ticketingRatio :
                        ticketingStats.ticketingRatio }}%
                    </span>
                    <span class="text-secondary small">vs YD</span>
                  </div>
                </div>

                <!-- <div class="kpi-value">{{ ticketingStats.totalTicketing }}</div>
                <div class="kpi-badge-row">
                  <i class="bi bi-graph-up-arrow text-success"></i>
                  <span class="text-success fw-bold small">{{ ticketingStats.ticketingRatio }}%</span>
                  <span class="text-secondary small">vs. YD</span>
                </div> -->
                <div class="d-flex align-items-center gap-3 mt-3">
                  <div class="kpi-donut-wrap">
                    <canvas id="ticketingDonut"></canvas>
                  </div>
                  <div class="chart-legend">
                    <div><span class="ldot" style="background:#10b981"></span>{{ formatValues(ticketingStats.ticketed)
                      }}
                      Ticketed</div>
                    <div><span class="ldot" style="background:#ef4444"></span>{{ formatValues(ticketingStats.voided) }}
                      Others</div>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /inner row -->
        </div><!-- /left col -->

        <!-- Right: Total Sales & Commission bar -->
        <div class="col-12 col-lg-8">
          <div class="dash-card  d-flex flex-column">
            <div class="dash-card-header">
              <h3>Total Sales &amp; Commission</h3>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="d-flex align-items-center gap-2 text-muted small">
                  <span class="legend-pill-rect" style="background:#4f7ef8"></span>Total Sales
                </span>
                <button class="btn-pill">Monthly <i class="bi bi-chevron-down"></i></button>
              </div>
            </div>
            <div class="chart-canvas-wrap flex-grow-1">
              <canvas id="salesBarChart"></canvas>
            </div>
          </div>
        </div>

      </div><!-- /row 1 -->

      <!-- ══════════════════════════════════════════════════════
           ROW 2 — Trending Routes | Total Traveler | Booking Class
      ══════════════════════════════════════════════════════ -->
      <div class="row g-4 mb-4">

        <!-- Trending Routes -->
        <div class="col-12 col-lg-6">
          <div class="dash-card h-100 d-flex flex-column">
            <div class="dash-card-header">
              <h3>Trending Routes</h3>
              <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
            </div>

            <!-- Custom route rows with inline progress badges -->
            <div class="routes-list flex-grow-1">
              <div v-for="route in routes" :key="route.code" class="route-row">
                <div class="route-badge-wrap">
                  <span class="route-badge" :style="{ background: route.color, color: route.textColor }">
                    {{ route.code }}
                  </span>
                  <span class="route-num" :style="{ color: route.color }">{{ route.count }}</span>
                </div>
                <div class="route-track">
                  <div class="route-fill"
                    :style="{ width: pct(route.count, route.max) + '%', background: route.color }">
                  </div>
                </div>
              </div>
              <!-- x-axis labels
              <div class="route-axis">
                <span>0</span><span>100</span><span>200</span><span>300</span><span>400</span><span>500</span>
              </div> -->
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="dash-card h-100">
            <div class="dash-card-header mb-2">
              <h3>Upcoming Flights</h3>
            </div>
            <div class="flights-list">
              <div v-for="flight in upcomingFlights" :key="flight.route" class="flight-row">
                <span class="flight-route">{{ flight.route }}</span>
                <span class="flight-type">{{ flight.type }}</span>
                <span class="flight-date">{{ flight.date }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Total Traveler -->
        <!-- <div class="col-12 col-md-6 col-lg-4">
          <div class="dash-card h-100">
            <div class="dash-card-header">
              <h3>Total Traveler</h3>
              <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
            </div>
            <div class="d-flex flex-column align-items-center gap-3">
              <div class="traveler-donut-wrap">
                <canvas id="travelerDonut"></canvas>
                <div class="donut-label">
                  <span class="donut-num">{{travelerData.reduce((a, b) => a + b, 0)}}</span>
                </div>
              </div>
              <div class="d-flex gap-3 justify-content-center flex-wrap">
                <span class="legend-item"><span class="ldot" style="background:#3b82f6"></span>Adult</span>
                <span class="legend-item"><span class="ldot" style="background:#8b5cf6"></span>Children</span>
                <span class="legend-item"><span class="ldot" style="background:#06b6d4"></span>Infant</span>
              </div>
            </div>
          </div>
        </div> -->

        <!-- Trending Booking Class -->
        <!-- <div class="col-12 col-md-6 col-lg-3">
          <div class="dash-card h-100">
            <div class="dash-card-header flex-wrap gap-2">
              <h3>Trending Booking Class</h3>
              <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
            </div>
            <div class="d-flex flex-column align-items-center gap-3">
              <canvas id="bookingClassPie" style="max-width:200px;max-height:200px"></canvas>
              <div class="booking-legend">
                <div><span class="ldot" style="background:#06b6d4"></span>Economy</div>
                <div><span class="ldot" style="background:#eab308"></span>Premium Economy</div>
                <div><span class="ldot" style="background:#8b5cf6"></span>Business Class</div>
                <div><span class="ldot" style="background:#f97316"></span>First Class</div>
              </div>
            </div>
          </div>
        </div> -->

      </div><!-- /row 2 -->

      <!-- ══════════════════════════════════════════════════════
           ROW 3 — Top 10 Airlines gauge | Booking vs Ticketing area
      ══════════════════════════════════════════════════════ -->
      <div class="row g-4 mb-4">

        <!-- Top 10 Most Selling Airlines -->
        <!-- <div class="col-12 col-lg-5">
          <div class="dash-card h-100">
            <div class="dash-card-header">
              <h3>Top 10 Most Selling Airlines</h3>
              <button class="btn-pill">April, 2025 <i class="bi bi-chevron-down"></i></button>
            </div>
            <div class="gauge-wrap">
              <canvas id="airlinesGauge"></canvas>
            </div>
            <div class="airlines-legend">
              <div v-for="(lbl, i) in airlineLabels" :key="lbl" class="airline-legend-item">
                <span class="ldot" :style="{ background: airlineColors[i] }"></span>{{ lbl }}
              </div>
            </div>
          </div>
        </div> -->

        <div class="col-12 col-lg-6">
          <div class="dash-card h-100">
            <div class="dash-card-header">
              <h3>Top 10 Selling Airlines</h3>
              <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
            </div>
            <div class="table-responsive">
              <table class="table align-middle data-table mb-0">
                <thead>
                  <tr>
                    <th></th>
                    <th>#</th>
                    <th>Airline</th>
                    <th>Ticketing</th>
                    <th>Sales (BDT)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(airline, index) in airlines" :key="airline.name">
                    <td>
                      <i v-if="airline.trend === 'up'" class="bi bi-arrow-up-short text-success fw-bold"></i>
                      <i v-else-if="airline.trend === 'down'" class="bi bi-arrow-down-short text-danger fw-bold"></i>
                      <i v-else class="bi bi-dash text-secondary"></i>
                    </td>
                    <td><span class="rank-badge">{{ index + 1 }}</span></td>
                    <td class="airline-name">{{ airline.name }}</td>
                    <td class="ticketing-count">{{ airline.ticketing }}</td>
                    <td class="sales-amount">৳ {{ airline.sales }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- Booking vs Ticketing Ratio -->
        <div class="col-12 col-lg-6">
          <div class="dash-card h-100 d-flex flex-column">
            <div class="dash-card-header">
              <h3>Booking vs Ticketing Ratio</h3>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="legend-item">
                  <span class="legend-line" style="background:#c084fc"></span>Booking
                </span>
                <span class="legend-item">
                  <span class="legend-line" style="background:#818cf8"></span>Ticketing
                </span>
                <button class="btn-pill">Monthly <i class="bi bi-chevron-down"></i></button>
              </div>
            </div>
            <div class="chart-canvas-wrap flex-grow-1">
              <canvas id="ratioAreaChart"></canvas>
            </div>
          </div>
        </div>

      </div><!-- /row 3 -->

      <!-- ══════════════════════════════════════════════════════
           ROW 4 — Total Transaction bar | Upcoming Flights
      ══════════════════════════════════════════════════════ -->
      <div class="row g-4">

        <!-- Total Transaction -->
        <div class="col-12 col-lg-7">
          <div class="dash-card d-flex flex-column">
            <div class="dash-card-header">
              <h3>Total Transaction</h3>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="legend-item">
                  <span class="legend-pill-rect" style="background:#4f7ef8"></span>Deposit
                </span>
                <span class="legend-item">
                  <span class="legend-pill-rect" style="background:#f97316"></span>Credit
                </span>
                <button class="btn-pill">Monthly <i class="bi bi-chevron-down"></i></button>
              </div>
            </div>
            <div class="chart-canvas-wrap flex-grow-1">
              <canvas id="transactionBar"></canvas>
            </div>
          </div>
        </div>


        <div class="col-12 col-lg-4">
          <div class="row g-4">
            <!-- Total Traveler -->
            <div class="col-12">
              <div class="dash-card">
                <div class="dash-card-header">
                  <h3>Total Traveler</h3>
                  <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
                </div>
                <div class="d-flex flex-column align-items-center gap-3">
                  <div class="traveler-donut-wrap">
                    <canvas id="travelerDonut"></canvas>
                    <div class="donut-label">
                      <span class="donut-num">{{travelerData.reduce((a, b) => a + b, 0)}}</span>
                    </div>
                  </div>
                  <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <span class="legend-item"><span class="ldot" style="background:#3b82f6"></span>Adult</span>
                    <span class="legend-item"><span class="ldot" style="background:#8b5cf6"></span>Children</span>
                    <span class="legend-item"><span class="ldot" style="background:#06b6d4"></span>Infant</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Trending Booking Class -->
            <!-- <div class="col-12">
              <div class="dash-card h-100">
                <div class="dash-card-header flex-wrap gap-2">
                  <h3>Trending Booking Class</h3>
                  <button class="btn-pill">August, 2026 <i class="bi bi-chevron-down"></i></button>
                </div>
                <div class="d-flex flex-column align-items-center gap-3">
                  <canvas id="bookingClassPie" style="max-width:100px;max-height:100px"></canvas>
                  <div class="booking-legend">
                    <div><span class="ldot" style="background:#06b6d4"></span>Economy</div>
                    <div><span class="ldot" style="background:#eab308"></span>Premium Economy</div>
                    <div><span class="ldot" style="background:#8b5cf6"></span>Business Class</div>
                    <div><span class="ldot" style="background:#f97316"></span>First Class</div>
                  </div>
                </div>
              </div>
            </div> -->
          </div>
        </div>


      </div><!-- /row 4 -->

    </div><!-- /container-fluid -->
  </div>
</template>

<style scoped>
/* ── Root ── */
.dashboard-wrapper {
  padding: 24px;
  background: #f0f4fa;
  min-height: 100vh;
  font-family: 'Plus Jakarta Sans', 'Nunito', sans-serif;
}

/* ════════════════════════════════
   KPI Card
════════════════════════════════ */
.kpi-card {
  background: #fff;
  border-radius: 18px;
  padding: 20px 22px;
  box-shadow: 0 2px 16px rgba(15, 21, 53, .06);
  height: 100%;
  transition: transform .2s, box-shadow .2s;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(59, 130, 246, .1);
}

.kpi-label {
  font-size: 13px;
  font-weight: 700;
  color: #0f1535;
}

.kpi-value-row {
  display: flex;
  align-items: center;
  width: 100%;
}

.kpi-value {
  font-size: 24px;
  font-weight: 800;
  color: #0f1535;
  line-height: 1.1;
  margin: 4px 0 6px;
}

.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 20px;
}

.kpi-trend.up {
  background: #ecfdf5;
  color: #10b981;
}

.kpi-trend.down {
  background: #fef2f2;
  /* Soft reddish background */
  color: #ef4444;
  /* Reddish icon & text color */
}

.kpi-badge-row {
  display: flex;
  align-items: center;
  gap: 5px;
}

#bookingDonut {
  width: 120px !important;
  height: 60px !important;
  flex-shrink: 0;
}

/* .kpi-donut-wrap {
  width: 88px;
  height: 88px;
  flex-shrink: 0;
} */

.kpi-donut-wrap canvas {
  width: 88px !important;
  height: 88px !important;
}

/* ════════════════════════════════
   Shared card
════════════════════════════════ */
.dash-card {
  background: #fff;
  border-radius: 18px;
  padding: 20px 22px;
  box-shadow: 0 2px 16px rgba(15, 21, 53, .06);
}

.dash-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
  flex-wrap: wrap;
  gap: 10px;
}

.dash-card-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: #0f1535;
  margin: 0;
}

/* chart canvas container */
.chart-canvas-wrap {
  position: relative;
  min-height: 260px;
}

.chart-canvas-wrap canvas {
  width: 100% !important;
  height: 100% !important;
}

/* ════════════════════════════════
   Buttons & Legend atoms
════════════════════════════════ */
.btn-pill {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 4px 14px;
  font-size: 12px;
  font-weight: 600;
  color: #7b8ab8;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: border-color .2s, color .2s;
  white-space: nowrap;
}

.btn-pill:hover {
  border-color: #3b82f6;
  color: #3b82f6;
}

.ldot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
  display: inline-block;
}

.legend-line {
  width: 22px;
  height: 3px;
  border-radius: 2px;
  display: inline-block;
  flex-shrink: 0;
}

.legend-pill-rect {
  width: 22px;
  height: 8px;
  border-radius: 4px;
  display: inline-block;
  flex-shrink: 0;
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #7b8ab8;
}

.chart-legend {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.chart-legend div {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: #7b8ab8;
}

/* ════════════════════════════════
   Trending Routes custom rows
════════════════════════════════ */
.routes-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.route-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.route-badge-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 130px;
}

.route-badge {
  color: #fff;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.route-num {
  font-size: 13px;
  font-weight: 700;
}

.route-track {
  flex: 1;
  height: 28px;
  background: #f4f6fb;
  border-radius: 6px;
  overflow: hidden;
}

.route-fill {
  height: 100%;
  border-radius: 6px;
  transition: width .6s ease;
}

.route-axis {
  display: flex;
  justify-content: space-between;
  padding: 4px 0 0;
  font-size: 11px;
  color: #94a3b8;
}

/* ════════════════════════════════
   Total Traveler donut
════════════════════════════════ */
.traveler-donut-wrap {
  position: relative;
  width: 90px;
  height: 90px;
  flex-shrink: 0;
}

.traveler-donut-wrap canvas {
  width: 90px !important;
  height: 90px !important;
}

.donut-label {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.donut-num {
  font-size: 30px;
  font-weight: 800;
  color: #8b5cf6;
}

/* ════════════════════════════════
   Booking Class legend
════════════════════════════════ */
.booking-legend {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.booking-legend div {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  color: #7b8ab8;
}

/* ════════════════════════════════
   Airlines gauge
════════════════════════════════ */
.gauge-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 4px;
}

.gauge-wrap canvas {
  max-width: 300px;
  max-height: 170px;
}

.airlines-legend {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px 24px;
  padding: 0 4px;
}

.airline-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #7b8ab8;
}

/* ════════════════════════════════
   Upcoming Flights
════════════════════════════════ */
.flights-list {
  display: flex;
  flex-direction: column;
}

.flight-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 0;
  border-bottom: 1px solid #f3f5fb;
  gap: 8px;
}

.flight-row:last-child {
  border-bottom: none;
}

.flight-route {
  font-size: 13px;
  font-weight: 700;
  color: #8b5cf6;
  min-width: 110px;
}

.flight-type {
  flex: 1;
  font-size: 13px;
  color: #64748b;
  text-align: center;
}

.flight-date {
  font-size: 13px;
  color: #64748b;
  white-space: nowrap;
}

/* ════════════════════════════════
   Responsive
════════════════════════════════ */
@media (max-width: 576px) {
  .dashboard-wrapper {
    padding: 12px;
  }

  .kpi-value {
    font-size: 30px;
  }

  .dash-card-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .route-badge-wrap {
    min-width: 110px;
  }
}
</style>
