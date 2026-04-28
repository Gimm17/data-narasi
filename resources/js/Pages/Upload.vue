<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import UploadZone from '@/Components/UploadZone.vue'
import { ref } from 'vue'

const props = defineProps<{
    analysisTypes: Array<{ value: string; label: string }>
    tones: Array<{ value: string; label: string }>
}>()

// Form state dengan Inertia
const form = useForm({
    file: null as File | null,
    analysis_type: 'umum' as string,
    tone: 'formal' as string,
    title: '' as string,
})

// Selected analysis type untuk UI
const selectedAnalysisType = ref('umum')

// Handle file selection dari UploadZone
const handleFileChange = (file: File | null) => {
    form.file = file
}

// Handle analysis type selection
const selectAnalysisType = (type: string) => {
    selectedAnalysisType.value = type
    form.analysis_type = type
}

// Submit form
const submit = () => {
    form.post(route('upload.store'), {
        onSuccess: () => {
            // Redirect otomatis ke halaman processing
        },
        onError: (errors) => {
            console.error('Validation errors:', errors)
        }
    })
}

// Cek apakah tombol submit harus disabled
const isSubmitDisabled = () => {
    return !form.file || form.processing
}
</script>

<template>
    <Head title="Upload Data" />

    <AppLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    Upload Data
                </h1>
                <p class="mt-2 text-gray-600">
                    Upload file CSV atau Excel untuk dianalisis dengan AI
                </p>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit">
                <!-- Upload Zone -->
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        File Data <span class="text-red-500">*</span>
                    </label>
                    <UploadZone
                        v-model="form.file"
                        :max-size-mb="10"
                        @update:model-value="handleFileChange"
                    />
                    <p v-if="form.errors.file" class="mt-2 text-sm text-red-600">
                        {{ form.errors.file }}
                    </p>
                </div>

                <!-- Tipe Analisis -->
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        Tipe Analisis <span class="text-red-500">*</span>
                    </label>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <button
                            v-for="type in analysisTypes"
                            :key="type.value"
                            type="button"
                            @click="selectAnalysisType(type.value)"
                            class="relative p-4 border-2 rounded-lg text-left transition-all hover:shadow-md"
                            :class="[
                                selectedAnalysisType === type.value
                                    ? 'border-teal-600 bg-teal-50 ring-2 ring-teal-600 ring-offset-2'
                                    : 'border-gray-300 hover:border-teal-400'
                            ]"
                        >
                            <div class="font-medium text-gray-900">
                                {{ type.label }}
                            </div>
                            <div
                                v-if="selectedAnalysisType === type.value"
                                class="absolute top-2 right-2 h-4 w-4 rounded-full bg-teal-600 flex items-center justify-center"
                            >
                                <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </div>
                    <p v-if="form.errors.analysis_type" class="mt-2 text-sm text-red-600">
                        {{ form.errors.analysis_type }}
                    </p>
                </div>

                <!-- Tone Narasi -->
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tone Narasi <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.tone"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
                        :class="{ 'border-red-500': form.errors.tone }"
                    >
                        <option value="">Pilih tone narasi</option>
                        <option v-for="tone in tones" :key="tone.value" :value="tone.value">
                            {{ tone.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.tone" class="mt-2 text-sm text-red-600">
                        {{ form.errors.tone }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        Pilih gaya bahasa untuk narasi hasil analisis
                    </p>
                </div>

                <!-- Judul (Opsional) -->
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Judul Laporan <span class="text-gray-400">(Opsional)</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Contoh: Laporan Penjualan Q1 2025"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-600 focus:border-teal-600"
                        :class="{ 'border-red-500': form.errors.title }"
                    />
                    <p v-if="form.errors.title" class="mt-2 text-sm text-red-600">
                        {{ form.errors.title }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        Kosongkan untuk menggunakan nama file sebagai judul
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-4">
                    <button
                        type="button"
                        @click="form.clearErrors()"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Reset
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitDisabled()"
                        class="px-6 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="{ 'opacity-50 cursor-not-allowed': isSubmitDisabled() }"
                    >
                        <span v-if="form.processing">
                            Sedang Mengupload...
                        </span>
                        <span v-else>
                            Upload & Analisis
                        </span>
                    </button>
                </div>
            </form>

            <!-- Info Box -->
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Informasi
                        </h3>
                        <div class="mt-1 text-sm text-blue-700">
                            <p>File Anda akan diproses secara otomatis. Estimasi waktu: 1-3 menit tergantung ukuran file.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
