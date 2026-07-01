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
    freeAspect: { type: Boolean, default: false },
    maxFileSizeMb: { type: Number, default: 5 },
    maxOutputSize: { type: Number, default: 512 },
    jpegQuality: { type: Number, default: 0.85 },
    accept: { type: String, default: 'image/jpeg,image/png,image/gif,image/webp' },
    cropModalTitle: { type: String, default: 'Crop image' },
});

const emit = defineEmits(['update:modelValue']);

const fileInputRef = ref(null);
const showCropModal = ref(false);
const cropImageSrc = ref('');
const cropperRef = ref(null);
const cropOriginalFile = ref(null);
const estimatedOutputSize = ref(null);
const croppedPreviewUrl = ref('');
let estimateSizeTimeout = null;

const shapeClass = props.shape === 'circle' ? 'rounded-circle' : 'rounded';
const stencilComponent = computed(() => (props.shape === 'circle' ? CircleStencil : RectangleStencil));
const stencilProps = computed(() => (props.freeAspect ? {} : { aspectRatio: 1 }));
const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

const previewSrc = computed(() => croppedPreviewUrl.value || props.displayUrl || '');
const hasPreview = computed(() => !!previewSrc.value);

function revokeCroppedPreview() {
    if (croppedPreviewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(croppedPreviewUrl.value);
    }
    croppedPreviewUrl.value = '';
}

watch(() => props.displayUrl, () => {
    revokeCroppedPreview();
});

function triggerFileInput() {
    fileInputRef.value?.click();
}

function onFileChange(e) {
    const input = e.target;
    const file = input.files?.[0];
    if (!file) return;

    const maxBytes = props.maxFileSizeMb * 1024 * 1024;
    if (file.size > maxBytes) {
        iziToast.warning({
            message: `Image must be under ${props.maxFileSizeMb}MB. Selected file is too large.`,
            position: 'topRight',
        });
        input.value = '';
        return;
    }

    const type = file.type?.toLowerCase();
    if (!allowedTypes.includes(type)) return;

    cropOriginalFile.value = file;
    cropImageSrc.value = URL.createObjectURL(file);
    estimatedOutputSize.value = null;
    showCropModal.value = true;
    input.value = '';
}

function closeCropModal() {
    if (cropImageSrc.value?.startsWith('blob:')) {
        URL.revokeObjectURL(cropImageSrc.value);
    }
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
    if (ctx) ctx.drawImage(srcCanvas, 0, 0, w, h, 0, 0, tw, th);
    return target;
}

function updateEstimatedSize() {
    const cropper = cropperRef.value;
    if (!cropper || !cropImageSrc.value) return;
    const result = cropper.getResult();
    if (!result?.canvas) return;
    const targetCanvas = buildOutputCanvas(result.canvas);
    targetCanvas.toBlob((blob) => {
        if (blob) estimatedOutputSize.value = blob.size;
    }, 'image/jpeg', props.jpegQuality);
}

function onCropChange() {
    if (estimateSizeTimeout) clearTimeout(estimateSizeTimeout);
    estimateSizeTimeout = setTimeout(updateEstimatedSize, 400);
}

function applyCrop() {
    const cropper = cropperRef.value;
    if (!cropper || !cropImageSrc.value) return;
    const result = cropper.getResult();
    if (!result?.canvas) {
        closeCropModal();
        return;
    }
    const targetCanvas = buildOutputCanvas(result.canvas);
    targetCanvas.toBlob((blob) => {
        if (!blob) return;
        const file = new File([blob], 'image.jpg', { type: 'image/jpeg' });
        revokeCroppedPreview();
        croppedPreviewUrl.value = URL.createObjectURL(blob);
        emit('update:modelValue', file);
        closeCropModal();
    }, 'image/jpeg', props.jpegQuality);
}

watch(showCropModal, (open) => {
    if (open) nextTick(() => setTimeout(updateEstimatedSize, 600));
});

onUnmounted(() => {
    revokeCroppedPreview();
    if (cropImageSrc.value?.startsWith('blob:')) {
        URL.revokeObjectURL(cropImageSrc.value);
    }
});
</script>

<template>
    <div
        :class="[sizeClass, shapeClass, 'image-crop-upload']"
        role="button"
        tabindex="0"
        @click="triggerFileInput"
        @keydown.enter.space.prevent="triggerFileInput"
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
        <i v-else class="fa-solid fa-camera image-crop-placeholder-icon"></i>
        <button
            v-if="hasPreview"
            type="button"
            class="image-crop-overlay"
            title="Change photo"
            @click.stop="triggerFileInput"
        >
            <i class="fa-solid fa-camera fa-lg"></i>
        </button>
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
                            class="cropper"
                            @change="onCropChange"
                        />
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
.image-crop-upload {
    overflow: hidden;
    border: 2px dashed #adb5bd;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
    position: relative;
}

.company-logo-preview {
    width: 120px;
    height: 120px;
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

.image-crop-placeholder-icon {
    font-size: 2rem;
    color: #adb5bd;
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
</style>
