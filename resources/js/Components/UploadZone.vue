<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
    modelValue: File | null
    accept?: string
    maxSizeMb?: number
}>()

const emit = defineEmits<{
    'update:modelValue': [file: File | null]
}>()

const isDragging = ref(false)
const errorMessage = ref('')
const maxSizeBytes = computed(() => props.maxSizeMb ? props.maxSizeMb * 1024 * 1024 : 10 * 1024 * 1024) // Default 10MB

// Handle file selection
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0] || null

    if (file) {
        validateAndSetFile(file)
    }
}

// Handle drag and drop events
const handleDragOver = (event: DragEvent) => {
    event.preventDefault()
    isDragging.value = true
}

const handleDragLeave = (event: DragEvent) => {
    event.preventDefault()
    isDragging.value = false
}

const handleDrop = (event: DragEvent) => {
    event.preventDefault()
    isDragging.value = false

    const file = event.dataTransfer?.files[0] || null

    if (file) {
        validateAndSetFile(file)
    }
}

// Validate file
const validateAndSetFile = (file: File) => {
    errorMessage.value = ''

    // Check file size
    if (file.size > maxSizeBytes.value) {
        const maxSizeMB = (maxSizeBytes.value / (1024 * 1024)).toFixed(0)
        errorMessage.value = `Ukuran file terlalu besar. Maksimal ${maxSizeMB}MB.`
        return
    }

    // Check file type (if accept is specified)
    if (props.accept) {
        const acceptedTypes = props.accept.split(',').map((type) => type.trim())
        const fileType = file.type
        const fileName = file.name
        const fileExtension = '.' + fileName.split('.').pop()

        const isValidType = acceptedTypes.some((acceptType) => {
            if (acceptType.startsWith('.')) {
                // Extension check
                return fileExtension.toLowerCase() === acceptType.toLowerCase()
            } else if (acceptType.includes('/*')) {
                // MIME type wildcard check
                const mimeType = acceptType.split('/')[0]
                return fileType.startsWith(mimeType + '/')
            } else {
                // Exact MIME type check
                return fileType === acceptType
            }
        })

        if (!isValidType) {
            errorMessage.value = `Tipe file tidak valid. Harap upload: ${props.accept}`
            return
        }
    }

    // File is valid
    emit('update:modelValue', file)
}

// Remove file
const removeFile = () => {
    emit('update:modelValue', null)
    errorMessage.value = ''
}

// Computed properties
const fileName = computed(() => {
    return props.modelValue?.name || ''
})

const fileSize = computed(() => {
    if (!props.modelValue) return ''

    const bytes = props.modelValue.size
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
})

const fileExtension = computed(() => {
    if (!props.modelValue) return ''
    const parts = props.modelValue.name.split('.')
    return parts[parts.length - 1]?.toUpperCase() || ''
})
</script>

<template>
    <div class="w-full">
        <!-- Upload Zone -->
        <div
            v-if="!modelValue"
            class="relative border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors"
            :class="[
                isDragging ? 'border-teal-600 bg-teal-50' : 'border-gray-300 hover:border-teal-400',
                errorMessage ? 'border-red-300 bg-red-50' : ''
            ]"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="$refs.fileInput?.click()"
        >
            <!-- Upload Icon -->
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <!-- Instructions -->
            <div class="text-sm text-gray-600 mb-2">
                <span class="font-semibold text-teal-600">Klik untuk upload</span> atau drag & drop file CSV/Excel
            </div>

            <div class="text-xs text-gray-500">
                Maksimal ukuran file: {{ maxSizeMb }}MB
            </div>

            <!-- Hidden File Input -->
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="accept"
                @change="handleFileSelect"
            />
        </div>

        <!-- File Preview (when file is selected) -->
        <div
            v-else
            class="relative border-2 border-teal-200 bg-teal-50 rounded-lg p-4"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <!-- File Icon -->
                    <div class="flex-shrink-0 h-10 w-10 rounded bg-teal-600 flex items-center justify-center text-white font-semibold text-sm">
                        {{ fileExtension }}
                    </div>

                    <!-- File Info -->
                    <div>
                        <div class="text-sm font-medium text-gray-900 truncate max-w-xs">
                            {{ fileName }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ fileSize }}
                        </div>
                    </div>
                </div>

                <!-- Remove Button -->
                <button
                    type="button"
                    @click="removeFile"
                    class="text-red-600 hover:text-red-700 text-sm font-medium"
                >
                    Hapus
                </button>
            </div>
        </div>

        <!-- Error Message -->
        <div
            v-if="errorMessage"
            class="mt-2 text-sm text-red-600"
        >
            {{ errorMessage }}
        </div>
    </div>
</template>
