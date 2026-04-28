<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatGrid from '@/Components/StatGrid.vue'
import NarrativeBox from '@/Components/NarrativeBox.vue'
import { computed } from 'vue'

const props = defineProps<{
    report: {
        id: number
        title: string
        original_filename: string
        status: string
        dataset_type: string
        total_rows: number
        clean_rows: number
        summary_stats: any
        chart_paths: string[]
        ai_narrative: string
        ai_provider_used: string
        created_at: string
        outputs: Array<{
            id: number
            output_type: string
            file_path: string
            is_public: boolean
            share_token: string
        }>
    }
}>()

// Statistik untuk StatGrid
const stats = computed(() => {
    const statsArray = []

    // Total Rows
    statsArray.push({
        label: 'Total Baris',
        value: props.report.total_rows.toLocaleString('id-ID')
    })

    // Clean Rows
    statsArray.push({
        label: 'Baris Bersih',
        value: props.report.clean_rows.toLocaleString('id-ID')
    })

    // Clean Percentage
    const cleanPercentage = props.report.total_rows > 0
        ? Math.round((props.report.clean_rows / props.report.total_rows) * 100)
        : 0

    statsArray.push({
        label: 'Persentase Bersih',
        value: cleanPercentage.toString(),
        suffix: '%'
    })

    // Dataset Type
    statsArray.push({
        label: 'Tipe Dataset',
        value: props.report.dataset_type.charAt(0).toUpperCase() + props.report.dataset_type.slice(1)
    })

    return statsArray
})

// Format tanggal
const formattedDate = computed(() => {
    const date = new Date(props.report.created_at)
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
})

// Get chart URL
const getChartUrl = (path: string) => {
    // Jika path sudah full URL, return as is
    if (path.startsWith('http')) {
        return path
    }

    // Jika relative path dari storage, convert ke URL
    return '/storage/' + path.replace('public/', '')
}

// Get output file URL
const getOutputUrl = (output: any) => {
    if (output.public_url) {
        return output.public_url
    }

    if (output.file_path) {
        return '/storage/' + output.file_path.replace('public/', '')
    }

    return '#'
}

// Get output label
const getOutputLabel = (type: string) => {
    const labels: Record<string, string> = {
        'pdf': 'PDF Document',
        'excel': 'Excel Spreadsheet',
        'chart_png': 'Chart Image',
        'json_api': 'JSON Data',
        'share_page': 'Shareable Page'
    }

    return labels[type] || type
}

// Get output icon
const getOutputIcon = (type: string) => {
    const icons: Record<string, string> = {
        'pdf': '📄',
        'excel': '📊',
        'chart_png': '📈',
        'json_api': '🔌',
        'share_page': '🔗'
    }

    return icons[type] || '📁'
}

// Delete report
const deleteReport = () => {
    if (confirm('Apakah Anda yakin ingin menghapus report ini?')) {
        router.delete(route('reports.destroy', props.report.id), {
            onSuccess: () => {
                router.visit(route('reports.index'))
            }
        })
    }
}
</script>

<template>
    <Head :title="report.title || 'Detail Report'" />

    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            {{ report.title || report.original_filename }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ formattedDate }}
                        </p>
                    </div>

                    <!-- Status Badge -->
                    <div class="flex items-center space-x-3">
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            ✓ Selesai
                        </span>
                        <span v-if="report.ai_provider_used" class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            AI: {{ report.ai_provider_used }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Stat Grid -->
            <div class="mb-8">
                <StatGrid :stats="stats" :columns="4" />
            </div>

            <!-- Narrative Box -->
            <div class="mb-8">
                <NarrativeBox
                    :narrative="report.ai_narrative"
                    :provider-used="report.ai_provider_used"
                />
            </div>

            <!-- Charts Section -->
            <div
                v-if="report.chart_paths && report.chart_paths.length > 0"
                class="mb-8"
            >
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    Chart Visualisasi
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(chartPath, index) in report.chart_paths"
                        :key="index"
                        class="bg-white border border-gray-200 rounded-lg p-4"
                    >
                        <img
                            :src="getChartUrl(chartPath)"
                            :alt="`Chart ${index + 1}`"
                            class="w-full h-auto rounded"
                        />
                    </div>
                </div>
            </div>

            <!-- Download/Output Section -->
            <div
                v-if="report.outputs && report.outputs.length > 0"
                class="mb-8"
            >
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    Download & Share
                </h2>
                <div class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-200">
                    <div
                        v-for="output in report.outputs"
                        :key="output.id"
                        class="p-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
                    >
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">{{ getOutputIcon(output.output_type) }}</span>
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ getOutputLabel(output.output_type) }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ output.file_path ? 'File tersedia' : 'Link share' }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <a
                                :href="getOutputUrl(output)"
                                target="_blank"
                                class="px-3 py-1.5 text-sm font-medium text-teal-600 hover:text-teal-700 border border-teal-600 rounded hover:bg-teal-50 transition-colors"
                            >
                                Unduh
                            </a>

                            <button
                                v-if="output.share_token"
                                @click="navigator.clipboard.writeText(window.location.origin + '/share/' + output.share_token)"
                                class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-700 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
                            >
                                Salin Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                <button
                    @click="router.visit(route('reports.index'))"
                    class="text-gray-600 hover:text-gray-900 font-medium"
                >
                    ← Kembali ke Riwayat
                </button>

                <button
                    @click="deleteReport"
                    class="px-4 py-2 text-red-600 hover:text-red-700 font-medium hover:bg-red-50 rounded-lg transition-colors"
                >
                    Hapus Report
                </button>
            </div>
        </div>
    </AppLayout>
</template>
