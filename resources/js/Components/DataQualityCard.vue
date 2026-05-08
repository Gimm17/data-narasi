<script setup lang="ts">
import { computed, ref } from 'vue'

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
const radius = 44
const circumference = 2 * Math.PI * radius
const dashOffset = computed(() => circumference - (percentage.value / 100) * circumference)

// Toggle detail section
const showDetail = ref(false)

// Impact badge config
const impactConfig: Record<string, { color: string; bg: string; label: string }> = {
    none: { color: '#6b7280', bg: '#f9fafb', label: 'Aman' },
    low: { color: '#0d9488', bg: '#f0fdfa', label: 'Ringan' },
    medium: { color: '#d97706', bg: '#fffbeb', label: 'Sedang' },
    high: { color: '#dc2626', bg: '#fef2f2', label: 'Berat' },
}

// Step icon mapping
const stepIcons: Record<number, string> = {
    1: '🔍', 2: '📂', 3: '✂️', 4: '🗑️', 5: '🔄', 6: '🔢', 7: '⚠️', 8: '📝'
}

// Cleaning steps from log
const cleaningSteps = computed(() => {
    if (!props.cleaningLog?.cleaning_steps) return []
    return props.cleaningLog.cleaning_steps
})

// Issues table — consolidated from all cleaning data
const issueRows = computed(() => {
    if (!props.cleaningLog) return []
    const rows: Array<{ type: string; icon: string; column: string; count: number; action: string; detail: string }> = []

    // Empty rows
    if (props.cleaningLog.empty_rows_removed > 0) {
        rows.push({
            type: 'Baris Kosong',
            icon: '🗑️',
            column: '(semua kolom)',
            count: props.cleaningLog.empty_rows_removed,
            action: 'Dihapus',
            detail: 'Seluruh kolom bernilai NaN/kosong'
        })
    }

    // Duplicates
    if (props.cleaningLog.duplicates_removed > 0) {
        rows.push({
            type: 'Duplikat',
            icon: '🔄',
            column: '(semua kolom)',
            count: props.cleaningLog.duplicates_removed,
            action: 'Dihapus',
            detail: 'Baris identik (exact match semua kolom)'
        })
    }

    // Type conversions
    if (props.cleaningLog.type_conversions) {
        for (const conv of props.cleaningLog.type_conversions) {
            rows.push({
                type: 'Tipe Data',
                icon: '🔢',
                column: conv.column,
                count: Number(conv.failed_values || 0),
                action: `Konversi → ${conv.to_type} (${conv.success_rate})`,
                detail: conv.failed_values > 0
                    ? `${conv.failed_values} value gagal dikonversi → NaN`
                    : `Semua value berhasil dikonversi`
            })
        }
    }

    // Anomalies
    if (props.cleaningLog.anomalies_flagged) {
        for (const anomaly of props.cleaningLog.anomalies_flagged) {
            rows.push({
                type: 'Anomali',
                icon: '⚠️',
                column: anomaly.column,
                count: anomaly.count,
                action: 'Ditandai (flagged)',
                detail: `${anomaly.count} nilai negatif (${anomaly.percentage})`
            })
        }
    }

    // Nulls filled
    if (props.cleaningLog.nulls_filled) {
        for (const [col, info] of Object.entries(props.cleaningLog.nulls_filled) as [string, any][]) {
            rows.push({
                type: 'Nilai Kosong',
                icon: '📝',
                column: col,
                count: info.count,
                action: info.method === 'median'
                    ? `Isi median (${Number(info.value).toLocaleString('id-ID', { maximumFractionDigits: 2 })})`
                    : `Isi "${info.value}"`,
                detail: info.reason || (info.method === 'median' ? 'Kolom numerik' : 'Kolom teks/kategorikal')
            })
        }
    }

    return rows
})

const totalIssues = computed(() => props.cleaningLog?.total_issues_found || issueRows.value.reduce((s, r) => s + r.count, 0))

// Steps that actually did something
const activeStepCount = computed(() => cleaningSteps.value.filter((s: any) => s.impact !== 'none').length)
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">

        <!-- ═══ HEADER: Quality Score + Summary ═══ -->
        <div class="p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">Kualitas & Pembersihan Data</h3>
                <button
                    @click="showDetail = !showDetail"
                    class="text-xs font-medium px-2.5 py-1 rounded-md transition-colors"
                    :class="showDetail
                        ? 'bg-teal-50 text-teal-700 hover:bg-teal-100'
                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                >
                    {{ showDetail ? '▲ Tutup Detail' : '▼ Lihat Detail' }}
                </button>
            </div>

            <div class="flex items-center gap-5">
                <!-- Circular Progress (compact) -->
                <div class="flex-shrink-0 relative">
                    <svg width="100" height="100" viewBox="0 0 100 100">
                        <circle
                            cx="50" cy="50" :r="radius"
                            fill="none" stroke="#f1f5f9" stroke-width="8"
                        />
                        <circle
                            cx="50" cy="50" :r="radius"
                            fill="none"
                            :stroke="qualityColor.ring"
                            stroke-width="8"
                            stroke-linecap="round"
                            :stroke-dasharray="circumference"
                            :stroke-dashoffset="dashOffset"
                            transform="rotate(-90 50 50)"
                            style="transition: stroke-dashoffset 1s ease-out;"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xl font-bold text-gray-900">{{ percentage }}%</span>
                        <span class="text-[9px] font-medium mt-0.5" :style="{ color: qualityColor.text }">
                            {{ qualityColor.label }}
                        </span>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="flex-1 min-w-0 grid grid-cols-2 gap-2.5">
                    <div class="text-center py-2 rounded-lg" :style="{ backgroundColor: qualityColor.bg }">
                        <div class="text-base font-bold text-gray-900">{{ cleanRows.toLocaleString('id-ID') }}</div>
                        <div class="text-[10px] text-gray-500">Baris bersih</div>
                    </div>
                    <div class="text-center py-2 rounded-lg bg-gray-50">
                        <div class="text-base font-bold text-gray-900">{{ removedRows.toLocaleString('id-ID') }}</div>
                        <div class="text-[10px] text-gray-500">Baris dihapus</div>
                    </div>
                    <div class="text-center py-2 rounded-lg bg-gray-50">
                        <div class="text-base font-bold text-gray-900">{{ totalIssues.toLocaleString('id-ID') }}</div>
                        <div class="text-[10px] text-gray-500">Masalah ditemukan</div>
                    </div>
                    <div class="text-center py-2 rounded-lg bg-gray-50">
                        <div class="text-base font-bold text-gray-900">{{ activeStepCount }}/8</div>
                        <div class="text-[10px] text-gray-500">Langkah aktif</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ DETAIL SECTION (Collapsible) ═══ -->
        <div
            v-if="showDetail && cleaningLog"
            class="border-t border-gray-100"
        >
            <!-- ─── Pipeline Steps Timeline ─── -->
            <div class="p-5 border-b border-gray-100">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                    Pipeline Pembersihan (8 Langkah)
                </h4>
                <div class="space-y-0">
                    <div
                        v-for="step in cleaningSteps"
                        :key="step.step"
                        class="flex items-start gap-3 relative"
                    >
                        <!-- Timeline connector -->
                        <div class="flex flex-col items-center flex-shrink-0">
                            <div
                                class="w-7 h-7 rounded-full flex items-center justify-center text-sm flex-shrink-0 border-2 z-10"
                                :class="{
                                    'border-gray-200 bg-gray-50': step.impact === 'none',
                                    'border-teal-300 bg-teal-50': step.impact === 'low',
                                    'border-amber-300 bg-amber-50': step.impact === 'medium',
                                    'border-red-300 bg-red-50': step.impact === 'high',
                                }"
                            >
                                {{ stepIcons[step.step] || '•' }}
                            </div>
                            <div
                                v-if="step.step < 8"
                                class="w-px h-6 bg-gray-200"
                            />
                        </div>

                        <!-- Step content -->
                        <div class="flex-1 min-w-0 pb-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-semibold text-gray-800">{{ step.action }}</span>
                                <span
                                    v-if="step.impact !== 'none'"
                                    class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                    :style="{
                                        backgroundColor: impactConfig[step.impact]?.bg || '#f9fafb',
                                        color: impactConfig[step.impact]?.color || '#6b7280'
                                    }"
                                >
                                    {{ impactConfig[step.impact]?.label || step.impact }}
                                    <template v-if="step.affected_rows > 0"> · {{ step.affected_rows.toLocaleString('id-ID') }}</template>
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-0.5 leading-relaxed">{{ step.description }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Issues Detail Table ─── -->
            <div v-if="issueRows.length > 0" class="p-5">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    Detail Kerusakan Data ({{ issueRows.length }} masalah)
                </h4>
                <div class="overflow-x-auto -mx-2">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-2 px-2 text-gray-400 font-medium">Tipe</th>
                                <th class="text-left py-2 px-2 text-gray-400 font-medium">Kolom</th>
                                <th class="text-right py-2 px-2 text-gray-400 font-medium">Jumlah</th>
                                <th class="text-left py-2 px-2 text-gray-400 font-medium">Aksi</th>
                                <th class="text-left py-2 px-2 text-gray-400 font-medium hidden sm:table-cell">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, i) in issueRows"
                                :key="i"
                                class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors"
                            >
                                <td class="py-2 px-2">
                                    <span class="inline-flex items-center gap-1 text-gray-700 font-medium">
                                        <span>{{ row.icon }}</span>
                                        <span>{{ row.type }}</span>
                                    </span>
                                </td>
                                <td class="py-2 px-2">
                                    <code class="text-[11px] bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ row.column }}</code>
                                </td>
                                <td class="py-2 px-2 text-right font-medium text-gray-800 tabular-nums">
                                    {{ row.count.toLocaleString('id-ID') }}
                                </td>
                                <td class="py-2 px-2 text-gray-600">{{ row.action }}</td>
                                <td class="py-2 px-2 text-gray-400 hidden sm:table-cell max-w-[200px] truncate" :title="row.detail">
                                    {{ row.detail }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ─── No Issues State ─── -->
            <div v-else class="p-5 text-center">
                <div class="text-2xl mb-1">✨</div>
                <p class="text-xs text-gray-400">Data sudah bersih! Tidak ditemukan masalah yang perlu diperbaiki.</p>
            </div>

            <!-- ─── Whitespace Tags ─── -->
            <div
                v-if="cleaningLog?.whitespace_columns?.length > 0"
                class="px-5 pb-4 border-t border-gray-100 pt-3"
            >
                <div class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-2">
                    Kolom Whitespace Trimmed
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="col in cleaningLog.whitespace_columns"
                        :key="col"
                        class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-mono"
                    >
                        {{ col }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
