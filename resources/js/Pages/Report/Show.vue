<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import NarrativeBox from '@/Components/NarrativeBox.vue'
import InteractiveChart from '@/Components/InteractiveChart.vue'
import DataQualityCard from '@/Components/DataQualityCard.vue'
import StatsDetailPanel from '@/Components/StatsDetailPanel.vue'
import InsightRecommendation from '@/Components/InsightRecommendation.vue'
import { computed, ref } from 'vue'

const props = defineProps<{
    report: {
        id: number
        title: string
        original_filename: string
        status: string
        dataset_type: string
        tone: string
        total_rows: number
        clean_rows: number
        summary_stats: any
        cleaning_log: any
        chart_paths: string[]
        ai_narrative: string
        ai_provider_used: string
        processing_time_ms: number
        prompt_tokens: number | null
        completion_tokens: number | null
        total_tokens: number | null
        cost_usd: number | null
        model_used: string | null
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

// ── Quick stats ──
const cleanPercentage = computed(() => {
    if (!props.report.total_rows) return 0
    return Math.round((props.report.clean_rows / props.report.total_rows) * 100)
})

const processingTime = computed(() => {
    const ms = props.report.processing_time_ms || 0
    if (ms < 1000) return `${ms}ms`
    return `${(ms / 1000).toFixed(1)}s`
})

const formattedDate = computed(() => {
    const d = new Date(props.report.created_at)
    return d.toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    })
})

const formattedTokens = computed(() => {
    const t = props.report.total_tokens
    if (!t) return '-'
    if (t >= 1000) return `${(t / 1000).toFixed(1)}K`
    return String(t)
})

const tokenDetail = computed(() => {
    const p = props.report.prompt_tokens || 0
    const c = props.report.completion_tokens || 0
    return `${p.toLocaleString('id-ID')} in · ${c.toLocaleString('id-ID')} out`
})

const formattedCost = computed(() => {
    const c = Number(props.report.cost_usd || 0)
    if (c === 0) return '-'
    if (c < 0.01) return `$${c.toFixed(4)}`
    return `$${c.toFixed(3)}`
})

const datasetLabel = computed(() => {
    const map: Record<string, string> = {
        penjualan: 'Penjualan', keuangan: 'Keuangan',
        operasional: 'Operasional', marketing: 'Marketing',
        inventori: 'Inventori', umum: 'Umum'
    }
    return map[props.report.dataset_type] || props.report.dataset_type
})

// ── Chart data builders ──
const hasTopProducts = computed(() => {
    return props.report.summary_stats?.sales_insights?.top_products &&
        Object.keys(props.report.summary_stats.sales_insights.top_products).length > 0
})

const topProductsChart = computed(() => {
    if (!hasTopProducts.value) return null
    const products = props.report.summary_stats.sales_insights.top_products
    const labels = Object.keys(products).map(n => n.length > 18 ? n.slice(0, 18) + '…' : n)
    const data = Object.values(products).map(Number)
    return {
        labels,
        datasets: [{ label: 'Revenue', data }]
    }
})

const topProductsPieChart = computed(() => {
    if (!hasTopProducts.value) return null
    const products = props.report.summary_stats.sales_insights.top_products
    return {
        labels: Object.keys(products).map(n => n.length > 15 ? n.slice(0, 15) + '…' : n),
        datasets: [{ label: 'Kontribusi', data: Object.values(products).map(Number) }]
    }
})

// Numeric distribution chart from column_info
// Excludes index/ID/No columns — they're sequential row numbers with no analytical value
const indexLikeNames = ['index', 'id', 'no', 'row', 'unnamed: 0', 'unnamed:0', 'sr', 'sno', 's.no', 'sl', 'sl.no', 'serial']
const numericDistChart = computed(() => {
    const colInfo = props.report.summary_stats?.column_info
    if (!colInfo) return null

    const numCols = Object.values(colInfo).filter((c: any) =>
        c.is_numeric && c.mean != null &&
        !indexLikeNames.includes(c.name.toLowerCase().trim())
    )
    if (numCols.length === 0 || numCols.length > 8) return null

    return {
        labels: (numCols as any[]).map((c: any) => c.name.length > 12 ? c.name.slice(0, 12) + '…' : c.name),
        datasets: [
            { label: 'Mean', data: (numCols as any[]).map((c: any) => Number(c.mean || 0)) },
            { label: 'Median', data: (numCols as any[]).map((c: any) => Number(c.median || 0)) },
        ]
    }
})

// Static chart images (from Matplotlib)
const hasStaticCharts = computed(() =>
    props.report.chart_paths && props.report.chart_paths.length > 0
)

const getChartUrl = (path: string) => {
    if (path.startsWith('http')) return path
    return '/storage/' + path.replace('public/', '')
}

// ── Outputs ──
const getOutputUrl = (output: any) => {
    if (output.public_url) return output.public_url
    if (output.file_path) return '/storage/' + output.file_path.replace('public/', '')
    return '#'
}

const outputMeta: Record<string, { label: string; icon: string }> = {
    pdf: { label: 'PDF Document', icon: '📄' },
    excel: { label: 'Excel Spreadsheet', icon: '📊' },
    chart_png: { label: 'Chart Image', icon: '📈' },
    json_api: { label: 'JSON Data', icon: '🔌' },
    share_page: { label: 'Shareable Page', icon: '🔗' }
}

// ── Actions ──
const deleteReport = () => {
    if (confirm('Apakah Anda yakin ingin menghapus report ini? Aksi ini tidak dapat dibatalkan.')) {
        router.delete(route('reports.destroy', props.report.id), {
            onSuccess: () => router.visit(route('reports.index'))
        })
    }
}

// Tab state for charts
const activeTab = ref<'interactive' | 'static'>('interactive')
</script>

<template>
    <Head :title="report.title || 'Detail Report'" />

    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">

            <!-- ═══ HEADER ═══ -->
            <div class="mb-8">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                            {{ report.title || report.original_filename }}
                        </h1>
                        <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                            <span>{{ formattedDate }}</span>
                            <span>·</span>
                            <span>{{ report.original_filename }}</span>
                            <span>·</span>
                            <span>{{ datasetLabel }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700">
                            ✓ Selesai
                        </span>
                        <span
                            v-if="report.ai_provider_used"
                            class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-slate-100 text-slate-600"
                        >
                            AI: {{ report.ai_provider_used }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ═══ QUICK STATS ═══ -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Total Baris</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1.5 tabular-nums">
                        {{ report.total_rows.toLocaleString('id-ID') }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Baris Bersih</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1.5 tabular-nums">
                        {{ report.clean_rows.toLocaleString('id-ID') }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Kualitas Data</div>
                    <div class="text-2xl font-bold mt-1.5 tabular-nums" :class="{
                        'text-emerald-600': cleanPercentage >= 90,
                        'text-amber-600': cleanPercentage >= 70 && cleanPercentage < 90,
                        'text-red-600': cleanPercentage < 70
                    }">
                        {{ cleanPercentage }}%
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Waktu Proses</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1.5 tabular-nums">
                        {{ processingTime }}
                    </div>
                </div>
            </div>

            <!-- ═══ INTERACTIVE CHARTS ═══ -->
            <div v-if="topProductsChart || numericDistChart || hasStaticCharts" class="mb-8">
                <!-- Tab switcher (only if both exist) -->
                <div
                    v-if="(topProductsChart || numericDistChart) && hasStaticCharts"
                    class="flex gap-1 mb-4 bg-gray-100 rounded-lg p-1 w-fit"
                >
                    <button
                        @click="activeTab = 'interactive'"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="activeTab === 'interactive'
                            ? 'bg-white text-gray-800 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                    >
                        Chart Interaktif
                    </button>
                    <button
                        @click="activeTab = 'static'"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="activeTab === 'static'
                            ? 'bg-white text-gray-800 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700'"
                    >
                        Chart Gambar
                    </button>
                </div>

                <!-- Interactive charts -->
                <div
                    v-if="activeTab === 'interactive' && (topProductsChart || numericDistChart)"
                    class="grid grid-cols-1 lg:grid-cols-2 gap-4"
                >
                    <InteractiveChart
                        v-if="topProductsChart"
                        type="bar"
                        title="Top Produk (Revenue)"
                        :labels="topProductsChart.labels"
                        :datasets="topProductsChart.datasets"
                        :height="300"
                    />
                    <InteractiveChart
                        v-if="topProductsPieChart"
                        type="doughnut"
                        title="Distribusi Revenue"
                        :labels="topProductsPieChart.labels"
                        :datasets="topProductsPieChart.datasets"
                        :height="300"
                    />
                    <InteractiveChart
                        v-if="numericDistChart"
                        type="bar"
                        title="Perbandingan Mean vs Median"
                        :labels="numericDistChart.labels"
                        :datasets="numericDistChart.datasets"
                        :height="300"
                    />
                </div>

                <!-- Static chart images (fallback / tab) -->
                <div
                    v-if="hasStaticCharts && (activeTab === 'static' || (!topProductsChart && !numericDistChart))"
                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                >
                    <div
                        v-for="(chartPath, index) in report.chart_paths"
                        :key="index"
                        class="rounded-xl border border-gray-200 bg-white p-4"
                    >
                        <img
                            :src="getChartUrl(chartPath)"
                            :alt="`Chart ${index + 1}`"
                            class="w-full h-auto rounded-lg"
                        />
                    </div>
                </div>
            </div>

            <!-- ═══ DATA QUALITY + INSIGHTS (side by side) ═══ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                <DataQualityCard
                    :clean-rows="report.clean_rows"
                    :total-rows="report.total_rows"
                    :cleaning-log="report.cleaning_log"
                />
                <InsightRecommendation
                    :summary-stats="report.summary_stats"
                    :clean-rows="report.clean_rows"
                    :total-rows="report.total_rows"
                />
            </div>

            <!-- ═══ AI NARRATIVE ═══ -->
            <div class="mb-8">
                <NarrativeBox
                    :narrative="report.ai_narrative"
                    :provider-used="report.ai_provider_used"
                    :report-title="report.title || report.original_filename"
                />
            </div>

            <!-- ═══ DETAILED STATS ═══ -->
            <div v-if="report.summary_stats?.column_info" class="mb-8">
                <StatsDetailPanel :column-info="report.summary_stats.column_info" />
            </div>

            <!-- ═══ AI USAGE INFO (compact) ═══ -->
            <div v-if="report.total_tokens || report.cost_usd" class="mb-8">
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-3.5">
                    <div class="flex items-center justify-between flex-wrap gap-y-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">⚡ AI Usage</span>
                        </div>
                        <div class="flex items-center gap-4 flex-wrap">
                            <div v-if="report.model_used" class="flex items-center gap-1.5">
                                <span class="text-[11px] text-gray-400">Model</span>
                                <code class="text-xs text-gray-600 bg-gray-50 px-1.5 py-0.5 rounded">{{ report.model_used }}</code>
                            </div>
                            <div v-if="report.total_tokens" class="flex items-center gap-1.5">
                                <span class="text-[11px] text-gray-400">Token</span>
                                <span class="text-xs font-medium text-gray-700 tabular-nums">{{ formattedTokens }}</span>
                                <span class="text-[10px] text-gray-400 tabular-nums">({{ tokenDetail }})</span>
                            </div>
                            <div v-if="formattedCost !== '-'" class="flex items-center gap-1.5">
                                <span class="text-[11px] text-gray-400">Biaya</span>
                                <span class="text-xs font-semibold text-teal-600 tabular-nums">{{ formattedCost }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ DOWNLOADS ═══ -->
            <div
                v-if="report.outputs && report.outputs.length > 0"
                class="mb-8"
            >
                <div class="rounded-xl border border-gray-200 bg-white">
                    <div class="px-5 py-3.5 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Download & Share</h3>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <div
                            v-for="output in report.outputs"
                            :key="output.id"
                            class="px-5 py-3.5 flex items-center justify-between hover:bg-gray-50/50 transition-colors"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-xl">{{ (outputMeta[output.output_type] || { icon: '📁' }).icon }}</span>
                                <div>
                                    <div class="text-sm font-medium text-gray-800">
                                        {{ (outputMeta[output.output_type] || { label: output.output_type }).label }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a
                                    :href="getOutputUrl(output)"
                                    target="_blank"
                                    class="text-xs font-medium text-teal-700 hover:text-teal-800 px-3 py-1.5 border border-teal-200 rounded-lg hover:bg-teal-50 transition-colors"
                                >
                                    Unduh
                                </a>
                                <button
                                    v-if="output.share_token"
                                    @click="navigator.clipboard.writeText(window.location.origin + '/share/' + output.share_token)"
                                    class="text-xs font-medium text-gray-500 hover:text-gray-700 px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                >
                                    Salin Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ FOOTER ACTIONS ═══ -->
            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                <button
                    @click="router.visit(route('reports.index'))"
                    class="text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors"
                >
                    ← Kembali ke Riwayat
                </button>
                <button
                    @click="deleteReport"
                    class="text-sm text-red-500 hover:text-red-600 font-medium px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                >
                    Hapus Report
                </button>
            </div>
        </div>
    </AppLayout>
</template>
