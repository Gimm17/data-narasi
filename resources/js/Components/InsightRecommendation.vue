<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    summaryStats: Record<string, any> | null
    cleanRows: number
    totalRows: number
}>()

interface Insight {
    icon: string
    title: string
    description: string
    type: 'positive' | 'neutral' | 'warning'
}

const insights = computed<Insight[]>(() => {
    const result: Insight[] = []
    if (!props.summaryStats) return result

    const sales = props.summaryStats.sales_insights || {}
    const basic = props.summaryStats.basic_stats || {}

    // Growth rate insight
    if (sales.growth_rate != null) {
        const rate = Number(sales.growth_rate)
        if (rate > 0) {
            result.push({
                icon: '📈',
                title: `Pertumbuhan +${rate.toFixed(1)}%`,
                description: 'Revenue di paruh kedua data menunjukkan tren naik dibanding paruh pertama. Momentum ini bisa dipertahankan dengan strategi yang konsisten.',
                type: 'positive'
            })
        } else if (rate < 0) {
            result.push({
                icon: '📉',
                title: `Penurunan ${rate.toFixed(1)}%`,
                description: 'Revenue mengalami penurunan. Pertimbangkan untuk evaluasi strategi pricing, promosi, atau segmentasi target market.',
                type: 'warning'
            })
        } else {
            result.push({
                icon: '➡️',
                title: 'Revenue Stabil',
                description: 'Tidak ada perubahan signifikan antar periode. Pertimbangkan diversifikasi produk untuk mendorong pertumbuhan.',
                type: 'neutral'
            })
        }
    }

    // Top products insight
    if (sales.top_products && Object.keys(sales.top_products).length > 0) {
        const entries = Object.entries(sales.top_products)
        const topName = entries[0]?.[0] || '-'
        const topValue = Number(entries[0]?.[1] || 0)
        const totalFromTop = entries.reduce((sum, [, v]) => sum + Number(v), 0)

        result.push({
            icon: '🏆',
            title: `Top Performer: ${topName}`,
            description: `Produk ini menghasilkan Rp ${topValue.toLocaleString('id-ID')} — kontribusi terbesar dari ${entries.length} produk teratas.`,
            type: 'positive'
        })

        if (entries.length >= 3) {
            const bottomName = entries[entries.length - 1]?.[0]
            result.push({
                icon: '💡',
                title: 'Peluang Optimasi',
                description: `Produk "${bottomName}" berada di posisi terbawah dari top ${entries.length}. Evaluasi apakah perlu promosi tambahan atau substitusi produk.`,
                type: 'neutral'
            })
        }
    }

    // Total revenue insight
    if (sales.total_revenue != null) {
        const rev = Number(sales.total_revenue)
        result.push({
            icon: '💰',
            title: `Total Revenue: Rp ${rev.toLocaleString('id-ID')}`,
            description: `Dari kolom "${sales.revenue_column || 'harga'}" dengan ${props.cleanRows.toLocaleString('id-ID')} transaksi bersih.`,
            type: 'positive'
        })
    }

    // Data quality insight
    const cleanPct = props.totalRows > 0 ? Math.round((props.cleanRows / props.totalRows) * 100) : 0
    if (cleanPct < 80) {
        result.push({
            icon: '⚠️',
            title: 'Kualitas Data Rendah',
            description: `Hanya ${cleanPct}% data yang lolos cleansing. Pertimbangkan untuk memperbaiki proses input data di sumber.`,
            type: 'warning'
        })
    }

    // Column count insight
    if (basic.numeric_columns != null && basic.string_columns != null) {
        result.push({
            icon: '📊',
            title: `${basic.total_columns} Kolom Teranalisis`,
            description: `${basic.numeric_columns} kolom numerik dan ${basic.string_columns} kolom teks. Dataset menggunakan ~${(basic.memory_usage_mb || 0).toFixed(2)} MB memori.`,
            type: 'neutral'
        })
    }

    return result
})

const typeStyles: Record<string, string> = {
    positive: 'border-l-emerald-400 bg-emerald-50/40',
    neutral: 'border-l-slate-300 bg-slate-50/40',
    warning: 'border-l-amber-400 bg-amber-50/40',
}
</script>

<template>
    <div v-if="insights.length > 0" class="rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Insight & Rekomendasi</h3>

        <div class="space-y-3">
            <div
                v-for="(insight, i) in insights"
                :key="i"
                class="border-l-[3px] rounded-r-lg px-4 py-3 transition-colors"
                :class="typeStyles[insight.type]"
            >
                <div class="flex items-start gap-2.5">
                    <span class="text-lg flex-shrink-0 mt-0.5">{{ insight.icon }}</span>
                    <div>
                        <div class="text-sm font-medium text-gray-800">{{ insight.title }}</div>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ insight.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
