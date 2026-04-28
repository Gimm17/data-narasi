<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ProgressTracker from '@/Components/ProgressTracker.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import axios from 'axios'

const props = defineProps<{
    report: {
        id: number
        title: string
        original_filename: string
        status: string
        created_at: string
    }
}>()

// Polling state
const pollingInterval = ref<NodeJS.Timeout | null>(null)
const currentStatus = ref(props.report.status)
const error = ref<string | null>(null)
const aiProvider = ref<string | null>(null)

// Steps untuk ProgressTracker
const steps = ref([
    {
        name: 'Upload File',
        description: 'File berhasil diupload ke server',
        status: currentStatus.value !== 'pending' ? 'done' : 'active'
    },
    {
        name: 'Validasi Data',
        description: 'Membaca dan memvalidasi struktur file',
        status: 'waiting'
    },
    {
        name: 'Data Cleansing',
        description: 'Membersihkan data: handle missing values, duplikat, dll',
        status: 'waiting'
    },
    {
        name: 'Analisis Statistik',
        description: 'Menghitung statistik dan tren data',
        status: 'waiting'
    },
    {
        name: 'Generate Narasi AI',
        description: 'Membuat narasi insight dengan AI',
        status: 'waiting'
    }
])

// Poll status setiap 3 detik
const startPolling = () => {
    pollingInterval.value = setInterval(async () => {
        try {
            const response = await axios.get(route('reports.status', props.report.id))
            const data = response.data

            // Update status
            currentStatus.value = data.status
            aiProvider.value = data.ai_provider_used

            // Update steps berdasarkan status
            updateSteps(data)

            // Cek jika selesai
            if (data.is_done) {
                stopPolling()
                // Redirect ke halaman show setelah delay singkat
                setTimeout(() => {
                    router.visit(route('reports.show', props.report.id))
                }, 1000)
            }

            // Cek jika gagal
            if (data.is_failed) {
                stopPolling()
                error.value = data.error_message || 'Processing gagal'
            }

        } catch (err) {
            console.error('Polling error:', err)
            // Lanjut polling meskipun error
        }
    }, 3000) // Poll setiap 3 detik
}

// Stop polling
const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}

// Update steps berdasarkan status data
const updateSteps = (data: any) => {
    // Reset semua steps ke waiting dulu
    steps.value.forEach((step) => {
        step.status = 'waiting'
    })

    // Step 1: Upload - selalu done jika tidak pending
    steps.value[0].status = 'done'

    // Step 2-5: Update berdasarkan status
    if (data.is_processing || data.is_done || data.is_failed) {
        steps.value[1].status = 'done' // Validasi
    }

    if (data.clean_rows > 0) {
        steps.value[2].status = 'done' // Cleansing
        steps.value[3].status = 'active' // Analisis aktif
    }

    if (data.ai_provider_used) {
        steps.value[3].status = 'done' // Analisis selesai
        steps.value[4].status = 'active' // Generate narasi aktif
    }

    if (data.is_done) {
        steps.value[4].status = 'done' // Semua selesai
    }
}

// Retry upload
const retry = () => {
    router.visit(route('upload.create'))
}

// Komputed
const isProcessing = computed(() => currentStatus.value === 'processing')
const isDone = computed(() => currentStatus.value === 'done')
const isFailed = computed(() => currentStatus.value === 'failed')

// Lifecycle hooks
onMounted(() => {
    // Jika masih pending/processing, mulai polling
    if (currentStatus.value === 'pending' || currentStatus.value === 'processing') {
        startPolling()
    }
})

onUnmounted(() => {
    stopPolling()
})
</script>

<template>
    <Head title="Processing Data" />

    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div v-if="isProcessing" class="mb-4">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-teal-600"></div>
                </div>

                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    {{ isDone ? 'Analisis Selesai!' : 'Sedang Memproses Data...' }}
                </h1>

                <p class="text-gray-600">
                    {{ report.title || report.original_filename }}
                </p>

                <p v-if="aiProvider" class="mt-2 text-sm text-gray-500">
                    Menggunakan AI: <span class="font-medium text-teal-600">{{ aiProvider }}</span>
                </p>
            </div>

            <!-- Progress Tracker -->
            <div class="mb-8">
                <ProgressTracker :steps="steps" />
            </div>

            <!-- Error State -->
            <div
                v-if="isFailed"
                class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8"
            >
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293-1.293a1 1 0 111.414-1.414l2 2a1 1 0 001.414 0l4-4a1 1 0 00-1.414-1.414L10 8.586l-1.293-1.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-red-800 mb-2">
                            Processing Gagal
                        </h3>
                        <div class="text-sm text-red-700">
                            <p v-if="error">{{ error }}</p>
                            <p v-else>
                                Terjadi kesalahan saat memproses data Anda. Silakan coba lagi.
                            </p>
                        </div>
                        <div class="mt-4">
                            <button
                                @click="retry"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                            >
                                Coba Lagi
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div
                v-if="isProcessing"
                class="bg-blue-50 border border-blue-200 rounded-lg p-4"
            >
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Sedang Diproses
                        </h3>
                        <div class="mt-1 text-sm text-blue-700">
                            <p>Halaman ini akan otomatis redirect ke hasil analisis setelah selesai.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-8 text-center">
                <button
                    @click="router.visit(route('reports.index'))"
                    class="text-gray-600 hover:text-gray-900 font-medium"
                >
                    ← Kembali ke Riwayat
                </button>
            </div>
        </div>
    </AppLayout>
</template>
