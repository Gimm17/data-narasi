<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    cleanRows: number
    totalRows: number
    cleaningLog?: Record<string, any> | null
}>()

const percentage = computed(() => {
    if (!props.totalRows || props.totalRows === 0) return 0
    return Math.round((props.cleanRows / props.totalRows) * 100)
})

const removedRows = computed(() => props.totalRows - props.cleanRows)

// Color based on quality
const qualityColor = computed(() => {
    if (percentage.value >= 90) return { ring: '#0d9488', bg: '#f0fdfa', text: '#115e59', label: 'Sangat Baik' }
    if (percentage.value >= 70) return { ring: '#f59e0b', bg: '#fffbeb', text: '#92400e', label: 'Cukup Baik' }
    return { ring: '#ef4444', bg: '#fef2f2', text: '#991b1b', label: 'Perlu Perhatian' }
})

// SVG circle math
const radius = 54
const circumference = 2 * Math.PI * radius
const dashOffset = computed(() => circumference - (percentage.value / 100) * circumference)

// Cleaning log details
const logEntries = computed(() => {
    if (!props.cleaningLog) return []
    const entries: Array<{ label: string; value: string }> = []

    if (props.cleaningLog.duplicates_removed != null) {
        entries.push({ label: 'Duplikat dihapus', value: String(props.cleaningLog.duplicates_removed) })
    }
    if (props.cleaningLog.null_rows_removed != null) {
        entries.push({ label: 'Baris null dihapus', value: String(props.cleaningLog.null_rows_removed) })
    }
    if (props.cleaningLog.encoding_fixed != null) {
        entries.push({ label: 'Encoding diperbaiki', value: props.cleaningLog.encoding_fixed ? 'Ya' : 'Tidak' })
    }
    if (props.cleaningLog.columns_converted != null) {
        entries.push({ label: 'Kolom dikonversi', value: String(props.cleaningLog.columns_converted) })
    }
    if (props.cleaningLog.original_encoding) {
        entries.push({ label: 'Encoding asli', value: props.cleaningLog.original_encoding })
    }

    return entries
})
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-5">Kualitas Data</h3>

        <div class="flex items-start gap-6">
            <!-- Circular Progress -->
            <div class="flex-shrink-0 relative">
                <svg width="128" height="128" viewBox="0 0 128 128">
                    <!-- Background circle -->
                    <circle
                        cx="64" cy="64" :r="radius"
                        fill="none" stroke="#f1f5f9" stroke-width="10"
                    />
                    <!-- Progress circle -->
                    <circle
                        cx="64" cy="64" :r="radius"
                        fill="none"
                        :stroke="qualityColor.ring"
                        stroke-width="10"
                        stroke-linecap="round"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="dashOffset"
                        transform="rotate(-90 64 64)"
                        style="transition: stroke-dashoffset 1s ease-out;"
                    />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-bold text-gray-900">{{ percentage }}%</span>
                    <span class="text-[10px] font-medium mt-0.5" :style="{ color: qualityColor.text }">
                        {{ qualityColor.label }}
                    </span>
                </div>
            </div>

            <!-- Details -->
            <div class="flex-1 min-w-0">
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="text-center py-2.5 rounded-lg" :style="{ backgroundColor: qualityColor.bg }">
                        <div class="text-lg font-bold text-gray-900">{{ cleanRows.toLocaleString('id-ID') }}</div>
                        <div class="text-[11px] text-gray-500">Baris bersih</div>
                    </div>
                    <div class="text-center py-2.5 rounded-lg bg-gray-50">
                        <div class="text-lg font-bold text-gray-900">{{ removedRows.toLocaleString('id-ID') }}</div>
                        <div class="text-[11px] text-gray-500">Baris dihapus</div>
                    </div>
                </div>

                <!-- Cleaning log entries -->
                <div v-if="logEntries.length > 0" class="space-y-1.5">
                    <div
                        v-for="entry in logEntries"
                        :key="entry.label"
                        class="flex justify-between text-xs"
                    >
                        <span class="text-gray-500">{{ entry.label }}</span>
                        <span class="font-medium text-gray-700">{{ entry.value }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
