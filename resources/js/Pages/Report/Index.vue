<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

const props = defineProps<{
    reports: {
        data: Array<{
            id: number
            title: string
            original_filename: string
            status: string
            dataset_type: string
            total_rows: number
            clean_rows: number
            created_at: string
            updated_at: string
        }>
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
}>()

// Get status badge class
const getStatusBadgeClass = (status: string) => {
    const classes: Record<string, string> = {
        'pending': 'bg-gray-100 text-gray-800',
        'processing': 'bg-blue-100 text-blue-800',
        'done': 'bg-green-100 text-green-800',
        'failed': 'bg-red-100 text-red-800'
    }

    return classes[status] || 'bg-gray-100 text-gray-800'
}

// Get status label
const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        'pending': 'Menunggu',
        'processing': 'Sedang Diproses',
        'done': 'Selesai',
        'failed': 'Gagal'
    }

    return labels[status] || status
}

// Format tanggal
const formatDate = (dateString: string) => {
    const date = new Date(dateString)
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    })
}

// Get dataset type label
const getDatasetTypeLabel = (type: string) => {
    return type.charAt(0).toUpperCase() + type.slice(1)
}

// Delete report
const deleteReport = (id: number) => {
    if (confirm('Apakah Anda yakin ingin menghapus report ini?')) {
        router.delete(route('reports.destroy', id))
    }
}

// Pagination
const goToPage = (page: number) => {
    router.get(route('reports.index', { page }))
}
</script>

<template>
    <Head title="Riwayat Analisis" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Riwayat Analisis
                    </h1>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        Daftar semua analisis data yang pernah Anda lakukan
                    </p>
                </div>

                <button
                    @click="router.visit(route('upload.create'))"
                    class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors font-medium"
                >
                    + Upload Baru
                </button>
            </div>

            <!-- Empty State -->
            <div
                v-if="reports.data.length === 0"
                class="text-center py-12"
            >
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    Belum Ada Analisis
                </h3>
                <p class="text-gray-500 mb-4">
                    Mulai dengan upload file CSV/Excel untuk mendapatkan insight dari data Anda.
                </p>
                <button
                    @click="router.visit(route('upload.create'))"
                    class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors font-medium"
                >
                    Upload Sekarang
                </button>
            </div>

            <!-- Reports List -->
            <div v-else class="space-y-4">
                <div
                    v-for="report in reports.data"
                    :key="report.id"
                    class="bg-white dark:bg-cream-300 border border-gray-200 dark:border-cream-400 rounded-lg p-4 hover:shadow-md transition-shadow"
                >
                    <div class="flex items-center justify-between">
                        <!-- Report Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-3 mb-2">
                                <h3 class="text-lg font-medium text-gray-900 truncate">
                                    {{ report.title || report.original_filename }}
                                </h3>
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="getStatusBadgeClass(report.status)"
                                >
                                    {{ getStatusLabel(report.status) }}
                                </span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ getDatasetTypeLabel(report.dataset_type) }}
                                </span>
                            </div>

                            <div class="text-sm text-gray-500">
                                <div class="flex items-center space-x-4">
                                    <span>{{ formatDate(report.created_at) }}</span>
                                    <span>•</span>
                                    <span>{{ report.total_rows.toLocaleString() }} baris</span>
                                    <span v-if="report.clean_rows > 0">
                                        • {{ report.clean_rows.toLocaleString() }} baris bersih
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center space-x-2 ml-4">
                            <!-- View Button -->
                            <button
                                v-if="report.status === 'done'"
                                @click="router.visit(route('reports.show', report.id))"
                                class="px-3 py-1.5 text-sm font-medium text-teal-600 hover:text-teal-700 border border-teal-600 rounded hover:bg-teal-50 transition-colors"
                            >
                                Lihat
                            </button>

                            <!-- Processing Button -->
                            <button
                                v-else-if="report.status === 'processing' || report.status === 'pending'"
                                @click="router.visit(route('reports.processing', report.id))"
                                class="px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 border border-blue-600 rounded hover:bg-blue-50 transition-colors"
                            >
                                Proses
                            </button>

                            <!-- Retry Button (for failed) -->
                            <button
                                v-else-if="report.status === 'failed'"
                                @click="router.post(route('reports.retry', report.id))"
                                class="px-3 py-1.5 text-sm font-medium text-orange-600 hover:text-orange-700 border border-orange-600 rounded hover:bg-orange-50 transition-colors"
                            >
                                Retry
                            </button>

                            <!-- Delete Button -->
                            <button
                                @click="deleteReport(report.id)"
                                class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 border border-red-600 rounded hover:bg-red-50 transition-colors"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="reports.last_page > 1"
                class="mt-6 flex items-center justify-between"
            >
                <div class="text-sm text-gray-500">
                    Menampilkan {{ ((reports.current_page - 1) * reports.per_page) + 1 }}
                    sampai {{ Math.min(reports.current_page * reports.per_page, reports.total) }}
                    dari {{ reports.total }} report
                </div>

                <div class="flex items-center space-x-2">
                    <!-- Previous Button -->
                    <button
                        :disabled="reports.current_page === 1"
                        @click="goToPage(reports.current_page - 1)"
                        class="px-3 py-1.5 text-sm font-medium border rounded transition-colors"
                        :class="[
                            reports.current_page === 1
                                ? 'border-gray-300 text-gray-400 cursor-not-allowed'
                                : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        ← Sebelumnya
                    </button>

                    <!-- Page Numbers -->
                    <div class="flex items-center space-x-1">
                        <button
                            v-for="page in Math.min(5, reports.last_page)"
                            :key="page"
                            @click="goToPage(page)"
                            class="w-8 h-8 text-sm font-medium border rounded transition-colors"
                            :class="[
                                page === reports.current_page
                                    ? 'border-teal-600 bg-teal-600 text-white'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                            ]"
                        >
                            {{ page }}
                        </button>
                    </div>

                    <!-- Next Button -->
                    <button
                        :disabled="reports.current_page === reports.last_page"
                        @click="goToPage(reports.current_page + 1)"
                        class="px-3 py-1.5 text-sm font-medium border rounded transition-colors"
                        :class="[
                            reports.current_page === reports.last_page
                                ? 'border-gray-300 text-gray-400 cursor-not-allowed'
                                : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        Selanjutnya →
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
