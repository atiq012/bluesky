<script setup>
import { ref, watch, computed, nextTick, onUnmounted } from 'vue';
import { Cropper, CircleStencil, RectangleStencil } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

const props = defineProps({
    modelValue: { type: File, default: null },
    displayUrl: { type: String, default: '' },
    placeholderSrc: { type: String, default: '' },
    sizeClass: { type: String, default: 'company-logo-preview' },
    shape: { type: String, default: 'square' },
    // null = free crop (user drags any ratio); a number locks the stencil to that ratio
    aspectRatio: { type: Number, default: null },
    maxFileSizeMb: { type: Number, default: 5 },
    maxOutputSize: { type: Number, default: 512 },
    jpegQuality: { type: Number, default: 0.85 },
    accept: { type: String, default: 'image/jpeg,image/png,image/gif,image/webp' },
    cropModalTitle: { type: String, default: 'Crop image' },
    removable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const fileInputRef = ref(null);
const showCropModal = ref(false);
const cropImageSrc = ref('');
const cropperRef = ref(null);
const cropOriginalFile = ref(null);
const estimatedOutputSize = ref(null);
const croppedPreviewUrl = ref('');
const isDragging = ref(false);
let estimateSizeTimeout = null;

const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

const isCircle = computed(() => props.shape === 'circle');
const shapeClass = computed(() => (isCircle.value ? 'rounded-circle' : 'rounded'));

// Circle stencil is inherently 1:1; only a rectangle stencil can offer ratio choices
const aspectPresets = [
    { label: 'Free', value: null },
    { label: '1:1', value: 1 },
    { label: '4:3', value: 4 / 3 },
    { label: '3:4', value: 3 / 4 },
    { label: '16:9', value: 16 / 9 },
];
const activeAspect = ref(props.aspectRatio);
const showAspectPresets = computed(() => !isCircle.value && props.aspectRatio === null);

const stencilComponent = computed(() => (isCircle.value ? CircleStencil : RectangleStencil));
const stencilProps = computed(() => {
    if (isCircle.value) return { aspectRatio: 1 };
    // undefined (not null) lets the cropper treat the ratio as unconstrained
    return { aspectRatio: activeAspect.value ?? undefined };
});

const previewSrc = computed(() => croppedPreviewUrl.value || props.displayUrl || '');
const hasPreview = computed(() => !!previewSrc.value);

// PNG/GIF/WebP may have transparency — JPEG export turns alpha into black
function getOutputMime(file) {
    const type = file?.type?.toLowerCase();
    if (type === 'image/png' || type === 'image/gif' || type === 'image/webp') {
        return 'image/png';
    }
    return 'image/jpeg';
}

function getOutputFileName(mime) {
    return mime === 'image/png' ? 'image.png' : 'image.jpg';
}

function revokeCroppedPreview() {
    if (croppedPreviewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(croppedPreviewUrl.value);
    }
    croppedPreviewUrl.value = '';
}

watch(() => props.displayUrl, () => {
    revokeCroppedPreview();
});

// Parent clearing the model (e.g. form reset) must clear our local preview too
watch(() => props.modelValue, (file) => {
    if (!file) revokeCroppedPreview();
});

watch(() => props.aspectRatio, (val) => {
    activeAspect.value = val;
});

function triggerFileInput() {
    fileInputRef.value?.click();
}

function acceptFile(file) {
    if (!file) return;

    const maxBytes = props.maxFileSizeMb * 1024 * 1024;
    if (file.size > maxBytes) {
        iziToast.warning({
            message: `Image must be under ${props.maxFileSizeMb}MB. Selected file is too large.`,
            position: 'topRight',
        });
        return;
    }

    const type = file.type?.toLowerCase();
    if (!allowedTypes.includes(type)) {
        iziToast.warning({
            message: 'Only JPEG, PNG, GIF or WebP images are allowed.',
            position: 'topRight',
        });
        return;
    }

    revokeCropImageSrc();
    cropOriginalFile.value = file;
    cropImageSrc.value = URL.createObjectURL(file);
    activeAspect.value = props.aspectRatio;
    estimatedOutputSize.value = null;
    showCropModal.value = true;
}

function onFileChange(e) {
    const input = e.target;
    acceptFile(input.files?.[0]);
    input.value = '';
}

function onDrop(e) {
    isDragging.value = false;
    acceptFile(Array.from(e.dataTransfer?.files || []).find(f => f.type?.startsWith('image/')));
}

function clearImage() {
    revokeCroppedPreview();
    cropOriginalFile.value = null;
    emit('update:modelValue', null);
}

function revokeCropImageSrc() {
    if (cropImageSrc.value?.startsWith('blob:')) {
        URL.revokeObjectURL(cropImageSrc.value);
    }
}

function closeCropModal() {
    revokeCropImageSrc();
    cropImageSrc.value = '';
    cropOriginalFile.value = null;
    estimatedOutputSize.value = null;
    if (estimateSizeTimeout) clearTimeout(estimateSizeTimeout);
    showCropModal.value = false;
}

function formatFileSize(bytes) {
    if (bytes == null || bytes === 0) return '—';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(i === 0 ? 0 : 1) + ' ' + sizes[i];
}

function buildOutputCanvas(srcCanvas) {
    const w = srcCanvas.width;
    const h = srcCanvas.height;
    const maxSide = Math.max(w, h);
    if (maxSide <= props.maxOutputSize) return srcCanvas;

    const scale = props.maxOutputSize / maxSide;
    const tw = Math.round(w * scale);
    const th = Math.round(h * scale);
    const target = document.createElement('canvas');
    target.width = tw;
    target.height = th;
    const ctx = target.getContext('2d');
    if (ctx) {
        ctx.clearRect(0, 0, tw, th);
        ctx.drawImage(srcCanvas, 0, 0, w, h, 0, 0, tw, th);
    }
    return target;
}

function getCropResult() {
    return cropperRef.value?.getResult({
        canvas: { fillColor: 'transparent' },
    });
}

function canvasToBlob(canvas, mime) {
    return new Promise((resolve) => {
        const quality = mime === 'image/jpeg' ? props.jpegQuality : undefined;
        canvas.toBlob((blob) => resolve(blob), mime, quality);
    });
}

function updateEstimatedSize() {
    const cropper = cropperRef.value;
    if (!cropper || !cropImageSrc.value) return;
    const result = getCropResult();
    if (!result?.canvas) return;
    const mime = getOutputMime(cropOriginalFile.value);
    const targetCanvas = buildOutputCanvas(result.canvas);
    canvasToBlob(targetCanvas, mime).then((blob) => {
        if (blob) estimatedOutputSize.value = blob.size;
    });
}

function onCropChange() {
    if (estimateSizeTimeout) clearTimeout(estimateSizeTimeout);
    estimateSizeTimeout = setTimeout(updateEstimatedSize, 400);
}

// ── Crop toolbar ───────────────────────────────────────────────────
function zoom(factor) { cropperRef.value?.zoom(factor); }
function rotate(angle) { cropperRef.value?.rotate(angle); }
function flip(horizontal, vertical) { cropperRef.value?.flip(horizontal, vertical); }
function resetCropper() {
    activeAspect.value = props.aspectRatio;
    nextTick(() => cropperRef.value?.reset());
}
function setAspect(value) {
    activeAspect.value = value;
    // stencil resizes after the prop lands, so re-measure the output afterwards
    nextTick(() => onCropChange());
}

async function applyCrop() {
    const cropper = cropperRef.value;
    if (!cropper || !cropImageSrc.value) return;
    const result = getCropResult();
    if (!result?.canvas) {
        closeCropModal();
        return;
    }
    const mime = getOutputMime(cropOriginalFile.value);
    const targetCanvas = buildOutputCanvas(result.canvas);
    const blob = await canvasToBlob(targetCanvas, mime);
    if (!blob) return;
    const file = new File([blob], getOutputFileName(mime), { type: mime });
    revokeCroppedPreview();
    croppedPreviewUrl.value = URL.createObjectURL(blob);
    emit('update:modelValue', file);
    closeCropModal();
}

watch(showCropModal, (open) => {
    if (open) nextTick(() => setTimeout(updateEstimatedSize, 600));
});

onUnmounted(() => {
    revokeCroppedPreview();
    revokeCropImageSrc();
});
</script>

<template>
    <div class="image-crop-wrapper">
        <div
            :class="[sizeClass, shapeClass, 'image-crop-upload', { 'image-crop-upload--drag': isDragging }]"
            role="button"
            tabindex="0"
            @click="triggerFileInput"
            @keydown.enter.space.prevent="triggerFileInput"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="fileInputRef"
                type="file"
                :accept="accept"
                class="image-crop-input"
                @change="onFileChange"
            />
            <img
                v-if="hasPreview"
                :src="previewSrc"
                alt="Preview"
                class="image-crop-preview"
            />
            <img
                v-else-if="placeholderSrc"
                :src="placeholderSrc"
                alt=""
                class="image-crop-placeholder"
            />
            <span v-else class="image-crop-empty">
                <i class="fa-solid fa-camera image-crop-placeholder-icon"></i>
                <small class="image-crop-empty-text">Drop or click to upload</small>
            </span>
            <button
                v-if="hasPreview"
                type="button"
                class="image-crop-overlay"
                title="Change photo"
                @click.stop="triggerFileInput"
            >
                <i class="fa-solid fa-camera fa-lg"></i>
            </button>
            <button
                v-if="hasPreview && removable"
                type="button"
                class="image-crop-remove"
                title="Remove image"
                @click.stop="clearImage"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div v-if="$slots.default" class="image-crop-hint">
            <slot></slot>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="showCropModal && cropImageSrc" class="image-crop-modal-backdrop" @click.self="closeCropModal">
            <div class="image-crop-panel">
                <div class="image-crop-header">
                    <h5 class="image-crop-title">{{ cropModalTitle }}</h5>
                    <button type="button" class="btn-close" @click="closeCropModal"></button>
                </div>
                <div class="image-crop-body">
                    <div class="image-crop-meta">
                        <span><strong>Original:</strong> {{ formatFileSize(cropOriginalFile?.size) }}</span>
                        <span><strong>After edit:</strong> {{ estimatedOutputSize != null ? formatFileSize(estimatedOutputSize) : '…' }}</span>
                    </div>
                    <div class="cropper-container">
                        <Cropper
                            ref="cropperRef"
                            :src="cropImageSrc"
                            :stencil-component="stencilComponent"
                            :stencil-props="stencilProps"
                            background-class="cropper-transparency-bg"
                            class="cropper"
                            @change="onCropChange"
                        />
                    </div>

                    <div class="image-crop-tools">
                        <div class="image-crop-toolgroup" role="group" aria-label="Crop tools">
                            <button type="button" class="crop-tool-btn" title="Zoom in" @click="zoom(1.15)">
                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                            </button>
                            <button type="button" class="crop-tool-btn" title="Zoom out" @click="zoom(0.85)">
                                <i class="fa-solid fa-magnifying-glass-minus"></i>
                            </button>
                            <button type="button" class="crop-tool-btn" title="Rotate left" @click="rotate(-90)">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            <button type="button" class="crop-tool-btn" title="Rotate right" @click="rotate(90)">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <button type="button" class="crop-tool-btn" title="Flip horizontal" @click="flip(true, false)">
                                <i class="fa-solid fa-arrows-left-right"></i>
                            </button>
                            <button type="button" class="crop-tool-btn" title="Reset" @click="resetCropper">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                        </div>

                        <div v-if="showAspectPresets" class="image-crop-toolgroup" role="group" aria-label="Aspect ratio">
                            <button
                                v-for="preset in aspectPresets"
                                :key="preset.label"
                                type="button"
                                class="crop-tool-btn crop-tool-btn--text"
                                :class="{ 'crop-tool-btn--active': activeAspect === preset.value }"
                                @click="setAspect(preset.value)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="image-crop-footer">
                    <button type="button" class="btn btn-secondary btn-sm modal-foot-btn" @click="closeCropModal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm modal-foot-btn" @click="applyCrop">Apply</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.image-crop-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.image-crop-upload {
    overflow: hidden;
    border: 2px dashed #adb5bd;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
    position: relative;
    transition: border-color 0.2s, background 0.2s;
}

.image-crop-upload:hover,
.image-crop-upload--drag {
    border-color: #7239ea;
    background: rgba(114, 57, 234, 0.06);
}

.company-logo-preview {
    width: 120px;
    height: 120px;
}

/* Large avatar box for profile-image forms */
.profile-image-preview {
    width: 100%;
    max-width: 180px;
    aspect-ratio: 1 / 1;
    height: auto;
}

.image-crop-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.image-crop-preview {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-crop-placeholder {
    width: 48px;
    height: 48px;
    object-fit: contain;
    opacity: 0.5;
}

.image-crop-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem;
    text-align: center;
    pointer-events: none;
}

.image-crop-empty-text {
    font-size: 11px;
    line-height: 1.2;
    color: #6c757d;
}

.image-crop-placeholder-icon {
    font-size: 1.75rem;
    color: #adb5bd;
}

.image-crop-hint {
    font-size: 11px;
    line-height: 1.3;
    color: #6c757d;
    text-align: center;
    word-break: break-word;
}

.image-crop-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.4);
    opacity: 0;
    transition: opacity 0.2s;
    border: none;
    color: #fff;
}

.image-crop-upload:hover .image-crop-overlay {
    opacity: 1;
}

.image-crop-remove {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    font-size: 12px;
    opacity: 0;
    transition: opacity 0.2s, background 0.2s;
}

.image-crop-upload:hover .image-crop-remove {
    opacity: 1;
}

.image-crop-remove:hover {
    background: #dc3545;
}

.image-crop-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1065;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.8);
}

.image-crop-panel {
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    background-color: #fff;
    color: #212529;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

.image-crop-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
    flex-shrink: 0;
}

.image-crop-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.4;
}

.image-crop-body {
    padding: 1rem 1.5rem 1.25rem;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1 1 auto;
}

.image-crop-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.image-crop-tools {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.image-crop-toolgroup {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.crop-tool-btn {
    min-width: 36px;
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    background: transparent;
    color: #495057;
    font-size: 0.8125rem;
    line-height: 1;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.crop-tool-btn:hover {
    border-color: #7239ea;
    color: #7239ea;
    background: rgba(114, 57, 234, 0.08);
}

.crop-tool-btn--text {
    font-variant-numeric: tabular-nums;
}

.crop-tool-btn--active {
    border-color: #7239ea;
    background: #7239ea;
    color: #fff;
}

.crop-tool-btn--active:hover {
    background: #6030c8;
    color: #fff;
}

.image-crop-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.625rem;
    padding: 0.875rem 1.5rem;
    border-top: 1px solid #dee2e6;
    flex-shrink: 0;
}

.image-crop-footer .modal-foot-btn {
    min-height: 36px;
    min-width: 84px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 1rem;
    font-size: 0.875rem;
    line-height: 1.2;
}

.cropper-container {
    height: 320px;
    background: #f8f9fa;
    border-radius: 0.375rem;
    overflow: hidden;
}

.cropper-container :deep(.cropper) {
    height: 320px;
}

@media (max-width: 575.98px) {
    .cropper-container,
    .cropper-container :deep(.cropper) {
        height: 240px;
    }

    .image-crop-tools {
        justify-content: center;
    }
}
</style>

<style>
[data-bs-theme="dark"] .image-crop-panel {
    background-color: #2b3035;
    color: #dee2e6;
    border-color: #495057;
}

[data-bs-theme="dark"] .image-crop-header,
[data-bs-theme="dark"] .image-crop-footer {
    border-color: #495057;
}

[data-bs-theme="dark"] .image-crop-meta {
    color: #adb5bd;
}

[data-bs-theme="dark"] .cropper-container {
    background: #1a1d20;
}

[data-bs-theme="dark"] .crop-tool-btn {
    border-color: #495057;
    color: #ced4da;
}

[data-bs-theme="dark"] .crop-tool-btn:hover {
    border-color: #9268f0;
    color: #b794ff;
    background: rgba(114, 57, 234, 0.18);
}

[data-bs-theme="dark"] .crop-tool-btn--active,
[data-bs-theme="dark"] .crop-tool-btn--active:hover {
    border-color: #7239ea;
    background: #7239ea;
    color: #fff;
}

[data-bs-theme="dark"] .image-crop-upload {
    border-color: #495057;
}

[data-bs-theme="dark"] .image-crop-upload:hover,
[data-bs-theme="dark"] .image-crop-upload--drag {
    border-color: #7239ea;
    background: rgba(114, 57, 234, 0.12);
}

[data-bs-theme="dark"] .image-crop-empty-text,
[data-bs-theme="dark"] .image-crop-hint {
    color: #adb5bd;
}

/* Checkerboard so transparent PNG areas are visible while cropping (not solid black) */
.cropper-transparency-bg {
    background-color: #fff;
    background-image:
        linear-gradient(45deg, #dee2e6 25%, transparent 25%),
        linear-gradient(-45deg, #dee2e6 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, #dee2e6 75%),
        linear-gradient(-45deg, transparent 75%, #dee2e6 75%);
    background-size: 16px 16px;
    background-position: 0 0, 0 8px, 8px -8px, -8px 0;
}

[data-bs-theme="dark"] .cropper-transparency-bg {
    background-color: #2b3035;
    background-image:
        linear-gradient(45deg, #495057 25%, transparent 25%),
        linear-gradient(-45deg, #495057 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, #495057 75%),
        linear-gradient(-45deg, transparent 75%, #495057 75%);
}
</style>
