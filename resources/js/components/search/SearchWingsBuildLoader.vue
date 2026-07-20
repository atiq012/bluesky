<script setup>
// Plane flies in carrying one wing at a time and drops it into place, so the
// BlueSky logo visibly builds itself. Replaces pp1.gif — as a GIF the timing
// was fixed and the background could not be made transparent without fringing.
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    // upper cap only — the stage is sized responsively below this so the built
    // logo stays large enough to read on small screens too
    size: { type: Number, default: 500 },
})

const stage = ref(null)
const canvas = ref(null)
const plane = ref(null)
const planePath = ref(null)
const w1 = ref(null)
const w2 = ref(null)
const w3 = ref(null)

// the whole animation is confined to a circle inscribed in the stage, so the
// logo is sized against that circle rather than the page
const LOGO_SCALE = 0.30
// a carried wing starts at this fraction of its final size and grows as the
// plane approaches, so it reads as being brought in rather than dragged along
const CARRY_FROM = 0.12

// ANCHOR = where the wing's pointed end sits inside the untransformed PNG
// (measured from the image trim boxes). It is also the CSS transform-origin,
// so scaling or rotating a wing never shifts its tip.
const ANCHOR = { x: 0.05, y: 0.82 }
// where the tip must rest for the logo to land centred at a given scale. The
// wings' combined bounding box midpoint is (.4938,.4852); scaling about ANCHOR
// drags it off-centre, so the tip is offset by exactly that drift.
function tipFor(s) {
    return {
        x: 0.5 - (0.4938 - ANCHOR.x) * s,
        y: 0.5 - (0.4852 - ANCHOR.y) * s,
    }
}
const TIP = tipFor(LOGO_SCALE)

const WING_COLORS = ['#3DEBCB', '#2F5CE0', '#8B3FDB']

// all three wings land within BUILD_MS, then the finished logo holds
const BUILD_MS = 2000
const HOLD_MS = 900
const DUR = BUILD_MS + HOLD_MS
const PASS = (BUILD_MS / 3) / DUR

// the plane starts this far along its approach; the stretch behind it is
// pre-seeded with aged smoke, otherwise the trail builds from nothing and the
// smoke visibly lags the plane by a few frames
const LEAD = 0.26

const T = { x: TIP.x, y: TIP.y }

// every endpoint sits on a circle of this radius (stage fractions, 0.5 = the
// inscribed circle's edge) so the plane never flies off across the page — the
// whole animation stays inside one compact disc
const EDGE = 0.46
// beyond this fraction of the radius the plane and its smoke fade out, so they
// dissolve at the boundary instead of being sliced off by it
const FADE_FROM = 0.70

const PATHS = [
    // teal — enter lower-left, exit upper-right
    { in: [{ x: 0.10, y: 0.73 }, { x: 0.16, y: 0.72 }, { x: 0.24, y: 0.68 }, T],
      out: [T, { x: 0.52, y: 0.53 }, { x: 0.73, y: 0.39 }, { x: 0.90, y: 0.27 }] },
    // blue — enter upper-left, exit lower-right
    { in: [{ x: 0.10, y: 0.27 }, { x: 0.15, y: 0.37 }, { x: 0.23, y: 0.51 }, T],
      out: [T, { x: 0.52, y: 0.69 }, { x: 0.73, y: 0.72 }, { x: 0.90, y: 0.73 }] },
    // purple — enter from below, exit upper-left
    { in: [{ x: 0.50, y: 0.95 }, { x: 0.46, y: 0.86 }, { x: 0.41, y: 0.75 }, T],
      out: [T, { x: 0.25, y: 0.56 }, { x: 0.16, y: 0.45 }, { x: 0.07, y: 0.35 }] },
]

// 1 at the centre, ramping to 0 at the circle's edge
function edgeFade(x, y) {
    const R = Math.min(W, H) / 2
    if (!R) return 1
    const d = Math.hypot(x - W / 2, y - H / 2) / R
    if (d >= 1) return 0
    return d <= FADE_FROM ? 1 : 1 - (d - FADE_FROM) / (1 - FADE_FROM)
}

let ctx = null
let wings = []
let W = 0, H = 0, planeW = 0, planeH = 0
let raf = null
let ro = null
let particles = []
let dropped = [false, false, false]
let seeded = [false, false, false]
let prevX = null, prevY = null

function bez(a, b, c, d, t) {
    const u = 1 - t
    return {
        x: u * u * u * a.x + 3 * u * u * t * b.x + 3 * u * t * t * c.x + t * t * t * d.x,
        y: u * u * u * a.y + 3 * u * u * t * b.y + 3 * u * t * t * c.y + t * t * t * d.y,
    }
}
function planeIn(i, t) { const p = PATHS[i].in; return bez(p[0], p[1], p[2], p[3], t) }
function planeOut(i, t) { const p = PATHS[i].out; return bez(p[0], p[1], p[2], p[3], t) }

function resize() {
    if (!stage.value || !canvas.value || !plane.value) return
    const r = stage.value.getBoundingClientRect()
    W = r.width; H = r.height
    const dpr = window.devicePixelRatio || 1
    canvas.value.width = W * dpr
    canvas.value.height = H * dpr
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    // SVG elements have no offsetWidth/offsetHeight, so measure the rect
    const pr = plane.value.getBoundingClientRect()
    planeW = pr.width; planeH = pr.height
}

function emit(x, y, color, life = 1, count = null) {
    const n = count ?? (4 + Math.floor(Math.random() * 3))
    for (let i = 0; i < n; i++) {
        particles.push({
            x: x + (Math.random() - 0.5) * 6,
            y: y + (Math.random() - 0.5) * 6,
            vx: (Math.random() - 0.5) * 0.4,
            vy: (Math.random() - 0.5) * 0.4 + 0.15,
            r: 1 + Math.random() * 2.4,
            life,
            decay: 0.009 + Math.random() * 0.015,
            color,
        })
    }
}

// spread this frame's smoke along the segment the plane actually covered —
// emitting only at the current point leaves gaps once the plane moves fast
function emitAlong(x0, y0, x1, y1, color) {
    const steps = Math.max(1, Math.min(10, Math.round(Math.hypot(x1 - x0, y1 - y0) / 4)))
    for (let s = 1; s <= steps; s++) {
        const t = s / steps
        emit(x0 + (x1 - x0) * t, y0 + (y1 - y0) * t, color, 1, 2)
    }
}

// lay down the trail the plane "already flew" before it appears, aged so the
// far end is faint — makes smoke read the instant the plane shows up
function seedTrail(i, color) {
    const STEPS = 16
    for (let s = 0; s <= STEPS; s++) {
        const pos = planeIn(i, (s / STEPS) * LEAD)
        emit(pos.x * W, pos.y * H, color, 0.30 + 0.70 * (s / STEPS))
    }
}

function drawParticles() {
    ctx.clearRect(0, 0, W, H)
    for (const pt of particles) {
        ctx.globalAlpha = Math.max(0, pt.life) * 0.9 * edgeFade(pt.x, pt.y)
        ctx.fillStyle = pt.color
        ctx.beginPath()
        ctx.arc(pt.x, pt.y, pt.r, 0, Math.PI * 2)
        ctx.fill()
    }
    ctx.globalAlpha = 1
}
function stepParticles() {
    for (const pt of particles) {
        pt.x += pt.vx; pt.y += pt.vy
        pt.r *= 0.992
        pt.life -= pt.decay
    }
    particles = particles.filter(p => p.life > 0)
}

// both poses keep the same transform function list so the drop can transition
function placedTransform(s = LOGO_SCALE) {
    const t = tipFor(s)
    return `translate(${(t.x - ANCHOR.x) * W}px, ${(t.y - ANCHOR.y) * H}px) `
        + `rotate(0deg) scale(${s})`
}

// the tip is pinned to the plane by ANCHOR, so changing the scale grows the
// wing without moving where it is held
function carryTransform(px, py, rot, s = LOGO_SCALE) {
    return `translate(${px - ANCHOR.x * W}px, ${py - ANCHOR.y * H}px) `
        + `rotate(${rot}deg) scale(${s})`
}

// grows CARRY_FROM → 1 across the approach, landing exactly on LOGO_SCALE so
// the hand-off to the placed pose has nothing to jump
function carryScale(a) {
    const eased = a * a * (3 - 2 * a)
    return LOGO_SCALE * (CARRY_FROM + (1 - CARRY_FROM) * eased)
}

function dropWing(i) {
    const w = wings[i]
    if (!w) return
    // kept short so it finishes inside the pass and never bleeds into the next
    w.style.transition = 'transform .26s cubic-bezier(.22,1,.36,1), opacity .18s ease'
    w.style.transform = placedTransform()
    w.style.opacity = '1'
}

function resetWings() {
    wings.forEach(w => {
        if (!w) return
        w.style.transition = 'none'
        w.style.opacity = '0'
        w.style.transform = `scale(${LOGO_SCALE * 0.6})`
    })
}

// with reduced motion we just show the finished logo instead of flying it in
function showStaticLogo() {
    wings.forEach(w => {
        if (!w) return
        w.style.transition = 'none'
        w.style.opacity = '1'
        w.style.transform = placedTransform()
    })
    if (plane.value) plane.value.style.opacity = '0'
}

function startAnim() {
    cancelAnimationFrame(raf)
    resize()
    particles = []
    dropped = [false, false, false]
    seeded = [false, false, false]
    prevX = prevY = null
    resetWings()
    const t0 = performance.now()

    function frame(now) {
        const gp = ((now - t0) % DUR) / DUR

        // loop wrapped → clear everything for a clean rebuild
        if (gp < 0.02 && dropped[2]) {
            dropped = [false, false, false]
            seeded = [false, false, false]
                    resetWings()
        }

        const i = Math.max(0, Math.min(2, Math.floor(gp / PASS)))
        const lp = (gp - i * PASS) / PASS
        const inLogoHold = gp >= PASS * 3
        const color = WING_COLORS[i]

        let planeVisible = false, px = 0, py = 0, ang = 0

        if (!inLogoHold && lp >= 0.05 && lp < 0.55) {
            // APPROACH — plane flies in carrying wing i, trail seeded on entry
            if (!seeded[i]) { seeded[i] = true; seedTrail(i, color) }
            const a = LEAD + (1 - LEAD) * ((lp - 0.05) / 0.50)
            const pos = planeIn(i, a)
            const posN = planeIn(i, Math.min(a + 0.02, 1))
            px = pos.x * W; py = pos.y * H
            ang = Math.atan2(posN.y - pos.y, posN.x - pos.x) * 180 / Math.PI
            planeVisible = true

            // wing tip pinned to the plane, lean straightening as it arrives so
            // the hand-off to the placed pose is seamless
            if (!dropped[i] && wings[i]) {
                wings[i].style.transition = 'none'
                wings[i].style.opacity = String(Math.min(1, a * 4))
                wings[i].style.transform = carryTransform(px, py, -15 * (1 - a), carryScale(a))
            }
        } else if (!inLogoHold && lp >= 0.55 && lp < 0.88) {
            // EXIT — wing dropped, plane flies out empty
            if (!dropped[i]) { dropped[i] = true; dropWing(i) }
            const e = (lp - 0.55) / 0.33
            const pos = planeOut(i, e)
            const posN = planeOut(i, Math.min(e + 0.02, 1))
            px = pos.x * W; py = pos.y * H
            ang = Math.atan2(posN.y - pos.y, posN.x - pos.x) * 180 / Math.PI
            planeVisible = true
        } else if (!inLogoHold && lp >= 0.88 && !dropped[i]) {
            // safety: never let a pass end with its wing still undropped
            dropped[i] = true; dropWing(i)
        }

        if (planeVisible) {
            plane.value.style.opacity = String(edgeFade(px, py))
            plane.value.style.transform =
                `translate(${px - planeW / 2}px, ${py - planeH / 2}px) rotate(${ang + 90}deg)`
            planePath.value.setAttribute('fill', color)
            if (prevX === null) emit(px, py, color)
            else emitAlong(prevX, prevY, px, py, color)
            prevX = px; prevY = py
        } else {
            plane.value.style.opacity = '0'
            prevX = prevY = null   // don't streak smoke across the gap between passes
        }

        stepParticles()
        drawParticles()
    }

    // one bad frame must never kill the loop while a search is running
    const safe = (now) => {
        try { frame(now) } catch (e) { /* keep looping */ }
        raf = requestAnimationFrame(safe)
    }
    raf = requestAnimationFrame(safe)
}

onMounted(() => {
    ctx = canvas.value.getContext('2d')
    wings = [w1.value, w2.value, w3.value]

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        resize()
        showStaticLogo()
        return
    }

    startAnim()
    // the loader sits in a flex shell that can reflow, so track the box itself
    ro = new ResizeObserver(() => resize())
    ro.observe(stage.value)
})

onBeforeUnmount(() => {
    cancelAnimationFrame(raf)
    ro?.disconnect()
})
</script>

<template>
    <div
        ref="stage"
        class="wings-build-loader"
        :style="{ '--wbl-max': `${props.size}px` }"
        role="img"
        aria-label="Searching flights"
    >
        <img ref="w1" class="wbl-wing wbl-wing--1" src="../../../../public/theme/appimages/1.png" alt="" />
        <img ref="w2" class="wbl-wing wbl-wing--2" src="../../../../public/theme/appimages/2.png" alt="" />
        <img ref="w3" class="wbl-wing wbl-wing--3" src="../../../../public/theme/appimages/3.png" alt="" />

        <canvas ref="canvas" class="wbl-trail"></canvas>

        <svg ref="plane" class="wbl-plane" viewBox="0 0 512 512">
            <path
                ref="planePath"
                fill="#3DEBCB"
                d="M256 24c-14 0-26 26-30 74l-2 46-150 92c-6 4-10 11-10 18v22c0 5 5 9 10 7l142-46 6 84-40 32c-4 3-6 8-6 13v14c0 4 4 8 9 6l71-24 71 24c5 2 9-2 9-6v-14c0-5-2-10-6-13l-40-32 6-84 142 46c5 2 10-2 10-7v-22c0-7-4-14-10-18l-150-92-2-46c-4-48-16-74-30-74z"
            />
        </svg>
    </div>
</template>

<style scoped>
.wings-build-loader {
    position: relative;
    flex-shrink: 0;
    width: min(78vw, 78vh, var(--wbl-max, 300px));
    aspect-ratio: 1 / 1;
    /* hard boundary for the disc. The paths and the radial fade already keep
       everything inside, so this only catches stray drifting particles */
    border-radius: 50%;
    overflow: hidden;
}

/* the three PNGs share one box, so untransformed they overlay into the logo */
.wbl-wing {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    /* pivot on the wing's pointed end so scale/rotate never move the tip */
    transform-origin: 5% 82%;
    opacity: 0;
    will-change: transform, opacity;
}

/* z-order matches the final logo: teal in front, purple at the back */
.wbl-wing--1 { z-index: 3; }
.wbl-wing--2 { z-index: 2; }
.wbl-wing--3 { z-index: 1; }

.wbl-trail {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 5;
}

.wbl-plane {
    position: absolute;
    top: 0;
    left: 0;
    width: 12%;
    opacity: 0;
    will-change: transform;
    z-index: 10;
}
</style>
