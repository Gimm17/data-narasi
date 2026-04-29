<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

const props = defineProps<{
    stats: {
        total_reports: number
        success_rate: number
        avg_processing_time: number
        total_rows: number
    }
    weeklyCounts: Array<{ label: string; count: number }>
    providerUsage: Array<{ name: string; count: number }>
    analysisTypes: Array<{ dataset_type: string; count: number }>
    recentReports: Array<{
        id: number
        title: string
        original_filename: string
        status: string
        dataset_type: string
        total_rows: number
        created_at: string
        ai_provider_used: string
    }>
}>()

const formatTime = (ms: number) => {
    if (ms < 1000) return `${ms}ms`
    return `${(ms / 1000).toFixed(1)}s`
}

const formatDate = (d: string) => new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })

const statusColor: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-700',
    processing: 'bg-blue-100 text-blue-700',
    done: 'bg-green-100 text-green-700',
    failed: 'bg-red-100 text-red-700',
}

// Simple sparkline percentages for weekly chart
const maxWeekly = computed(() => Math.max(...props.weeklyCounts.map(w => w.count), 1))
</script>

<template>
    <Head title="Dashboard Admin" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Dashboard Admin</h1>
                <p class="mt-1 text-sm text-gray-500">Overview seluruh aktivitas DataNarasi</p>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-gray-100 p-5">
                    <div class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Report</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ stats.total_reports }}</div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 p-5">
                    <div class="text-xs text-gray-400 font-medium uppercase tracking-wide">Success Rate</div>
                    <div class="text-3xl font-bold mt-1" :class="stats.success_rate >= 80 ? 'text-green-600' : 'text-orange-500'">{{ stats.success_rate }}%</div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 p-5">
                    <div class="text-xs text-gray-400 font-medium uppercase tracking-wide">Avg Process Time</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ formatTime(stats.avg_processing_time) }}</div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 p-5">
                    <div class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Baris</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">{{ stats.total_rows.toLocaleString() }}</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6 mb-8">
                <!-- Weekly Chart (CSS bars) -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Reports per Minggu</h3>
                    <div v-if="weeklyCounts.length" class="flex items-end gap-2 h-32">
                        <div v-for="w in weeklyCounts" :key="w.label" class="flex-1 flex flex-col items-center gap-1">
                            <span class="text-[10px] text-gray-400 font-medium">{{ w.count }}</span>
                            <div
                                class="w-full bg-teal-500 rounded-t transition-all duration-500"
                                :style="{ height: (w.count / maxWeekly * 100) + '%', minHeight: '4px' }"
                            ></div>
                            <span class="text-[10px] text-gray-400">{{ w.label }}</span>
                        </div>
                    </div>
                    <div v-else class="h-32 flex items-center justify-center text-sm text-gray-400">Belum ada data</div>
                </div>

                <!-- Provider Usage -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">AI Provider Usage</h3>
                    <div v-if="providerUsage.length" class="space-y-3">
                        <div v-for="p in providerUsage" :key="p.name" class="flex items-center justify-between">
                            <span class="text-xs text-gray-600 font-medium truncate">{{ p.name }}</span>
                            <span class="text-xs font-bold text-teal-600">{{ p.count }}×</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 text-center py-8">Belum ada data</div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Report Terbaru</h3>
                <div v-if="recentReports.length" class="space-y-3">
                    <div v-for="r in recentReports" :key="r.id" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-xs text-gray-400">{{ formatDate(r.created_at) }}</span>
                            <span class="text-sm text-gray-800 font-medium truncate">{{ r.title || r.original_filename }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium" :class="statusColor[r.status] || 'bg-gray-100 text-gray-700'">{{ r.status }}</span>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0">{{ r.total_rows?.toLocaleString() }} rows</span>
                    </div>
                </div>
                <div v-else class="text-sm text-gray-400 text-center py-8">Belum ada report</div>
            </div>
        </div>
    </AppLayout>
</template>
