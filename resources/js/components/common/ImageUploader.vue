<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    maxFiles:   { type: Number, default: 1 },
})

const emit = defineEmits(['update:modelValue'])

// ── Drop zone state ────────────────────────────────────────────────
const isDragging = ref(false)
const fileInput  = ref(null)
const errors     = ref([])
const processing = ref(false)

const MAX_BYTES  = 1 * 1024 * 1024
const canAddMore = computed(() => props.modelValue.length < props.maxFiles)

// ── Compression ────────────────────────────────────────────────────
async function compressImage(file) {
    if (file.size <= MAX_BYTES) return { file, compressed: false }

    return new Promise((resolve) => {
        const img = new Image()
        const url = URL.createObjectURL(file)

        img.onload = () => {
            URL.revokeObjectURL(url)
            const canvas = document.createElement('canvas')
            const w = img.naturalWidth
            const h = img.naturalHeight
            let scale   = 1
            let quality = 0.9

            const attempt = () => {
                canvas.width  = Math.round(w * scale)
                canvas.height = Math.round(h * scale)
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height)
                canvas.toBlob((blob) => {
                    if (blob.size <= MAX_BYTES) {
                        resolve({ file: new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }), compressed: true })
                    } else if (quality > 0.15) {
                        quality = Math.round((quality - 0.1) * 10) / 10
                        attempt()
                    } else if (scale > 0.3) {
                        scale   = Math.round((scale - 0.1) * 10) / 10
                        quality = 0.8
                        attempt()
                    } else {
                        resolve({ file: new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }), compressed: true })
                    }
                }, 'image/jpeg', quality)
            }
            attempt()
        }
        img.src = url
    })
}

async function processFiles(fileList) {
    errors.value = []
    const slots = props.maxFiles - props.modelValue.length
    if (slots <= 0) { errors.value = [`Max ${props.maxFiles} image${props.maxFiles > 1 ? 's' : ''} allowed.`]; return }

    const incoming = Array.from(fileList).filter(f => f.type.startsWith('image/')).slice(0, slots)
    if (!incoming.length) { errors.value = ['Only image files accepted.']; return }

    processing.value = true
    try {
        const results = await Promise.all(incoming.map(async (file) => {
            const { file: out, compressed } = await compressImage(file)
            return { file: out, preview: URL.createObjectURL(out), originalName: file.name, compressed }
        }))
        emit('update:modelValue', [...props.modelValue, ...results])
    } finally {
        processing.value = false
    }
}

function onDrop(e)      { isDragging.value = false; processFiles(e.dataTransfer.files) }
function onFileChange(e){ processFiles(e.target.files); e.target.value = '' }

function removeFile(index) {
    const updated = [...props.modelValue]
    URL.revokeObjectURL(updated[index].preview)
    updated.splice(index, 1)
    emit('update:modelValue', updated)
}

function formatBytes(b) {
    return b < 1024 * 1024 ? (b / 1024).toFixed(0) + ' KB' : (b / (1024 * 1024)).toFixed(2) + ' MB'
}

// ── Lightbox ───────────────────────────────────────────────────────
const lb        = ref({ open: false, index: 0, scale: 1, tx: 0, ty: 0 })
const lbPanning = ref(false)
let   panOrigin = { x: 0, y: 0, tx: 0, ty: 0 }

function openLightbox(i) {
    lb.value = { open: true, index: i, scale: 1, tx: 0, ty: 0 }
    document.body.style.overflow = 'hidden'
}

function closeLightbox() {
    lb.value.open = false
    document.body.style.overflow = ''
}

function lbNav(dir) {
    const next = lb.value.index + dir
    if (next < 0 || next >= props.modelValue.length) return
    Object.assign(lb.value, { index: next, scale: 1, tx: 0, ty: 0 })
}

function lbWheel(e) {
    const zf       = e.deltaY < 0 ? 1.12 : 0.89
    const newScale = Math.min(8, Math.max(0.2, lb.value.scale * zf))
    const actual   = newScale / lb.value.scale
    const cx       = window.innerWidth  / 2
    const cy       = window.innerHeight / 2
    // keep point under cursor fixed
    lb.value.tx    = (e.clientX - cx) * (1 - actual) + lb.value.tx * actual
    lb.value.ty    = (e.clientY - cy) * (1 - actual) + lb.value.ty * actual
    lb.value.scale = newScale
}

function lbDblclick() {
    Object.assign(lb.value, { scale: 1, tx: 0, ty: 0 })
}

function lbMousedown(e) {
    if (e.button !== 0) return
    lbPanning.value = true
    panOrigin = { x: e.clientX, y: e.clientY, tx: lb.value.tx, ty: lb.value.ty }
}
function lbMousemove(e) {
    if (!lbPanning.value) return
    lb.value.tx = panOrigin.tx + (e.clientX - panOrigin.x)
    lb.value.ty = panOrigin.ty + (e.clientY - panOrigin.y)
}
function lbMouseup() { lbPanning.value = false }

// touch pan
let touchOrigin = null
function lbTouchstart(e) {
    if (e.touches.length !== 1) return
    touchOrigin = { x: e.touches[0].clientX, y: e.touches[0].clientY, tx: lb.value.tx, ty: lb.value.ty }
}
function lbTouchmove(e) {
    if (!touchOrigin || e.touches.length !== 1) return
    e.preventDefault()
    lb.value.tx = touchOrigin.tx + (e.touches[0].clientX - touchOrigin.x)
    lb.value.ty = touchOrigin.ty + (e.touches[0].clientY - touchOrigin.y)
}
function lbTouchend() { touchOrigin = null }

function lbKeydown(e) {
    if (!lb.value.open) return
    if (e.key === 'Escape')                       closeLightbox()
    if (e.key === 'ArrowLeft')                    lbNav(-1)
    if (e.key === 'ArrowRight')                   lbNav(1)
    if (e.key === '+' || e.key === '=')           lb.value.scale = Math.min(8,   lb.value.scale * 1.2)
    if (e.key === '-')                            lb.value.scale = Math.max(0.2, lb.value.scale / 1.2)
    if (e.key === '0')                            lbDblclick()
}

onMounted(()   => document.addEventListener('keydown', lbKeydown))
onUnmounted(() => {
    document.removeEventListener('keydown', lbKeydown)
    if (lb.value.open) document.body.style.overflow = ''
})
</script>

<template>
    <div class="img-uploader">
        <!-- Drop zone -->
        <div
            v-if="canAddMore"
            class="img-uploader__zone"
            :class="{ 'img-uploader__zone--drag': isDragging, 'img-uploader__zone--busy': processing }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="!processing && fileInput.click()"
        >
            <input ref="fileInput" type="file" accept="image/*" :multiple="maxFiles > 1" class="d-none" @change="onFileChange" />
            <div class="img-uploader__prompt">
                <template v-if="processing">
                    <span class="spinner-border spinner-border-sm text-purple" role="status"></span>
                    <span>Compressing…</span>
                </template>
                <template v-else>
                    <i class="bx bx-cloud-upload"></i>
                    <span>Drop or <u>click to upload</u></span>
                    <small>Max {{ maxFiles }} image{{ maxFiles > 1 ? 's' : '' }} · 1 MB each <span class="text-purple">(auto-compressed if larger)</span></small>
                </template>
            </div>
        </div>

        <div v-if="errors.length" class="mt-1">
            <small v-for="e in errors" :key="e" class="text-danger d-block">{{ e }}</small>
        </div>

        <!-- Thumbnails -->
        <div v-if="modelValue.length" class="img-uploader__previews">
            <div
                v-for="(item, i) in modelValue"
                :key="i"
                class="img-uploader__preview"
                title="Click to view"
                @click="openLightbox(i)"
            >
                <img :src="item.preview" :alt="item.originalName" draggable="false" />
                <button type="button" class="img-uploader__remove" title="Remove" @click.stop="removeFile(i)">
                    <i class="bx bx-x"></i>
                </button>
                <span class="img-uploader__size">{{ formatBytes(item.file.size) }}</span>
                <span v-if="item.compressed" class="img-uploader__badge">compressed</span>
            </div>
        </div>
    </div>

    <!-- Lightbox (teleported to body to escape any overflow:hidden ancestors) -->
    <Teleport to="body">
        <div
            v-if="lb.open"
            class="lb-backdrop"
            @click.self="closeLightbox"
            @wheel.prevent="lbWheel"
            @mousemove="lbMousemove"
            @mouseup="lbMouseup"
            @mouseleave="lbMouseup"
            @touchstart.passive="lbTouchstart"
            @touchmove.prevent="lbTouchmove"
            @touchend="lbTouchend"
        >
            <!-- Close -->
            <button class="lb-btn lb-close" @click="closeLightbox" title="Close (Esc)">
                <i class="bx bx-x"></i>
            </button>

            <!-- Prev -->
            <button
                v-if="lb.index > 0"
                class="lb-btn lb-nav lb-nav--prev"
                @click="lbNav(-1)"
                title="Previous (←)"
            >
                <i class="bx bx-chevron-left"></i>
            </button>

            <!-- Image -->
            <div
                class="lb-stage"
                :style="{ cursor: lbPanning ? 'grabbing' : 'grab' }"
                @mousedown="lbMousedown"
                @dblclick="lbDblclick"
            >
                <img
                    :src="modelValue[lb.index]?.preview"
                    :alt="modelValue[lb.index]?.originalName"
                    class="lb-img"
                    :style="{ transform: `translate(${lb.tx}px, ${lb.ty}px) scale(${lb.scale})` }"
                    draggable="false"
                />
            </div>

            <!-- Next -->
            <button
                v-if="lb.index < modelValue.length - 1"
                class="lb-btn lb-nav lb-nav--next"
                @click="lbNav(1)"
                title="Next (→)"
            >
                <i class="bx bx-chevron-right"></i>
            </button>

            <!-- Footer bar -->
            <div class="lb-footer">
                <span class="lb-footer__name">{{ modelValue[lb.index]?.originalName }}</span>
                <span class="lb-footer__meta">
                    <span v-if="modelValue.length > 1">{{ lb.index + 1 }} / {{ modelValue.length }}</span>
                    <span class="lb-footer__zoom">{{ Math.round(lb.scale * 100) }}%</span>
                    <span class="lb-footer__hint">Scroll = zoom · Drag = pan · Dbl-click = reset · 0 = fit</span>
                </span>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* ── Drop zone ──────────────────────────────────────────────────── */
.img-uploader__zone {
    border: 2px dashed #c8c8e8;
    border-radius: 8px;
    background: #faf9ff;
    padding: 18px 12px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    text-align: center;
    user-select: none;
}
.img-uploader__zone:hover,
.img-uploader__zone--drag  { border-color: #7239ea; background: #f3eeff; }
.img-uploader__zone--busy  { cursor: default; opacity: 0.75; }

.img-uploader__prompt { display: flex; flex-direction: column; align-items: center; gap: 3px; color: #7239ea; }
.img-uploader__prompt i    { font-size: 26px; }
.img-uploader__prompt span { font-size: 13px; }
.img-uploader__prompt small{ font-size: 11px; color: #888; }
.text-purple { color: #7239ea; }

/* ── Thumbnails ─────────────────────────────────────────────────── */
.img-uploader__previews { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }

.img-uploader__preview {
    position: relative;
    width: 82px; height: 82px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e0daf5;
    flex-shrink: 0;
    cursor: zoom-in;
}
.img-uploader__preview img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }

.img-uploader__remove {
    position: absolute; top: 2px; right: 2px;
    background: rgba(0,0,0,.55); border: none; border-radius: 50%;
    color: #fff; width: 20px; height: 20px;
    display: flex; align-items: center; justify-content: center;
    padding: 0; cursor: pointer; font-size: 14px;
}
.img-uploader__remove:hover { background: #dc3545; }

.img-uploader__size {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: rgba(0,0,0,.45); color: #fff;
    font-size: 9px; text-align: center; padding: 2px 0;
}
.img-uploader__badge {
    position: absolute; top: 2px; left: 2px;
    background: rgba(114,57,234,.85); color: #fff;
    font-size: 8px; border-radius: 3px;
    padding: 1px 4px; text-transform: uppercase; letter-spacing: 0.4px;
}

/* ── Lightbox ───────────────────────────────────────────────────── */
.lb-backdrop {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0, 0, 0, 0.92);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}

.lb-stage {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    user-select: none;
}
.lb-img {
    max-width: 90vw;
    max-height: 88vh;
    object-fit: contain;
    transform-origin: center center;
    transition: transform 0.06s ease-out;
    border-radius: 4px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.6);
    pointer-events: none;
}

/* shared button base */
.lb-btn {
    position: absolute;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.18);
    color: #fff;
    border-radius: 50%;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
    z-index: 10;
    backdrop-filter: blur(4px);
}
.lb-btn:hover { background: rgba(114,57,234,0.75); border-color: #7239ea; }

.lb-close {
    top: 16px; right: 16px;
    width: 40px; height: 40px;
    font-size: 22px;
}

.lb-nav {
    top: 50%; transform: translateY(-50%);
    width: 48px; height: 48px;
    font-size: 26px;
}
.lb-nav--prev { left: 16px; }
.lb-nav--next { right: 16px; }

/* Footer */
.lb-footer {
    position: absolute; bottom: 0; left: 0; right: 0;
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 20px;
    background: linear-gradient(transparent, rgba(0,0,0,0.65));
    color: rgba(255,255,255,0.85);
    font-size: 12px;
    pointer-events: none;
    z-index: 10;
}
.lb-footer__name { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 40%; }
.lb-footer__meta { display: flex; align-items: center; gap: 14px; }
.lb-footer__zoom { font-variant-numeric: tabular-nums; min-width: 38px; text-align: right; }
.lb-footer__hint { opacity: 0.55; font-size: 10px; }
</style>
