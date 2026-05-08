<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import UploadZone from '@/Components/UploadZone.vue'
import { ref, computed } from 'vue'

const props = defineProps<{
    analysisTypes: Array<{ value: string; label: string; icon: string; description: string }>
    tones: Array<{ value: string; label: string; icon: string; description: string }>
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
const selectedTone = ref('formal')

// Handle file selection dari UploadZone
const handleFileChange = (file: File | null) => {
    form.file = file
}

// Handle analysis type selection
const selectAnalysisType = (type: string) => {
    selectedAnalysisType.value = type
    form.analysis_type = type
}

// Handle tone selection
const selectTone = (tone: string) => {
    selectedTone.value = tone
    form.tone = tone
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
const isSubmitDisabled = computed(() => !form.file || form.processing)

// Selected tone info
const selectedToneInfo = computed(() =>
    props.tones.find(t => t.value === selectedTone.value)
)
</script>

<template>
    <Head title="Upload Data CSV/Excel — DataNarasi">
        <meta name="description" content="Upload file CSV atau Excel ke DataNarasi untuk cleansing otomatis, chart interaktif, statistik detail, dan narasi insight bisnis berbasis AI." />
        <meta property="og:title" content="Upload Data CSV/Excel — DataNarasi" />
        <meta property="og:description" content="Mulai analisis data AI: upload file, pilih tipe analisis, dan dapatkan insight otomatis dalam Bahasa Indonesia." />
    </Head>

    <AppLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-10 sm:pb-12">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">
                    Upload Data
                </h1>
                <p class="mt-1 text-sm text-gray-500">
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

                <!-- ═══ TIPE ANALISIS ═══ -->
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Tipe Analisis <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-3">Pilih jenis data yang paling sesuai untuk hasil analisis optimal</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                        <button
                            v-for="type in analysisTypes"
                            :key="type.value"
                            type="button"
                            @click="selectAnalysisType(type.value)"
                            class="relative text-left min-h-[92px] px-3.5 py-3 rounded-xl border-2 transition-all duration-150 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                            :class="[
                                selectedAnalysisType === type.value
                                    ? 'border-teal-500 bg-teal-50/60'
                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                            ]"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-base">{{ type.icon }}</span>
                                <span class="text-sm font-medium text-gray-800">{{ type.label }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400 leading-snug">{{ type.description }}</p>

                            <!-- Check indicator -->
                            <div
                                v-if="selectedAnalysisType === type.value"
                                class="absolute top-2 right-2 h-4 w-4 rounded-full bg-teal-500 flex items-center justify-center"
                            >
                                <svg class="h-2.5 w-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </div>
                    <p v-if="form.errors.analysis_type" class="mt-2 text-sm text-red-600">
                        {{ form.errors.analysis_type }}
                    </p>
                </div>

                <!-- ═══ TONE NARASI ═══ -->
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Tone Narasi <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-3">Gaya bahasa untuk narasi hasil analisis</p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <button
                            v-for="tone in tones"
                            :key="tone.value"
                            type="button"
                            @click="selectTone(tone.value)"
                            class="relative text-left min-h-[92px] px-3.5 py-3 rounded-xl border-2 transition-all duration-150 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                            :class="[
                                selectedTone === tone.value
                                    ? 'border-teal-500 bg-teal-50/60'
                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                            ]"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-base">{{ tone.icon }}</span>
                                <span class="text-sm font-medium text-gray-800">{{ tone.label }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400 leading-snug">{{ tone.description }}</p>

                            <div
                                v-if="selectedTone === tone.value"
                                class="absolute top-2 right-2 h-4 w-4 rounded-full bg-teal-500 flex items-center justify-center"
                            >
                                <svg class="h-2.5 w-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </div>
                    <p v-if="form.errors.tone" class="mt-2 text-sm text-red-600">
                        {{ form.errors.tone }}
                    </p>
                </div>

                <!-- ═══ JUDUL (Opsional) ═══ -->
                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Judul Laporan <span class="text-gray-400 text-xs font-normal">(Opsional)</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Contoh: Laporan Penjualan Q1 2025"
                        class="w-full min-h-11 px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-colors"
                        :class="{ 'border-red-500': form.errors.title }"
                    />
                    <p v-if="form.errors.title" class="mt-2 text-sm text-red-600">
                        {{ form.errors.title }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400">
                        Kosongkan untuk menggunakan nama file sebagai judul
                    </p>
                </div>

                <!-- ═══ SUBMIT ═══ -->
                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                    <button
                        type="button"
                        @click="form.reset(); selectedAnalysisType = 'umum'; selectedTone = 'formal'"
                        class="w-full sm:w-auto text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-3 rounded-xl hover:bg-gray-50 transition-colors"
                    >
                        Reset
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitDisabled"
                        class="w-full sm:w-auto min-h-11 px-6 py-3 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                    >
                        <span v-if="form.processing" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Mengupload...
                        </span>
                        <span v-else>Upload & Analisis</span>
                    </button>
                </div>
            </form>

            <!-- ═══ INFO BOX ═══ -->
            <div class="mt-8 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                <div class="flex gap-3">
                    <span class="text-blue-400 mt-0.5">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-medium text-blue-800">Format yang didukung</h3>
                        <p class="mt-1 text-xs text-blue-600 leading-relaxed">
                            CSV (.csv), Excel (.xlsx, .xls) — maksimal 10MB.
                            Estimasi waktu proses: 1-3 menit tergantung ukuran file.
                            Data akan otomatis dibersihkan (cleansing) sebelum dianalisis.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
