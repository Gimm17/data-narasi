<script setup lang="ts">
import { computed, ref } from 'vue'
import { Bar, Doughnut, Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js'

ChartJS.register(
    CategoryScale, LinearScale, BarElement, LineElement,
    PointElement, ArcElement, Title, Tooltip, Legend, Filler
)

const props = defineProps<{
    type: 'bar' | 'doughnut' | 'line'
    title: string
    labels: string[]
    datasets: Array<{
        label: string
        data: number[]
        backgroundColor?: string | string[]
        borderColor?: string
    }>
    height?: number
}>()

// Palette yang clean — bukan rainbow generic
const PALETTE = [
    '#0d9488', '#0891b2', '#6366f1',
    '#8b5cf6', '#ec4899', '#f59e0b',
    '#10b981', '#3b82f6'
]

const PALETTE_SOFT = [
    'rgba(13,148,136,0.15)', 'rgba(8,145,178,0.15)', 'rgba(99,102,241,0.15)',
    'rgba(139,92,246,0.15)', 'rgba(236,72,153,0.15)', 'rgba(245,158,11,0.15)',
    'rgba(16,185,129,0.15)', 'rgba(59,130,246,0.15)'
]

const chartData = computed(() => {
    const ds = props.datasets.map((d, i) => ({
        ...d,
        backgroundColor: d.backgroundColor || (props.type === 'doughnut' ? PALETTE : PALETTE_SOFT[i % PALETTE_SOFT.length]),
        borderColor: d.borderColor || PALETTE[i % PALETTE.length],
        borderWidth: props.type === 'doughnut' ? 2 : 2,
        borderRadius: props.type === 'bar' ? 6 : 0,
        tension: 0.3,
        fill: props.type === 'line',
        pointRadius: props.type === 'line' ? 4 : 0,
        pointHoverRadius: props.type === 'line' ? 7 : 0,
    }))

    return { labels: props.labels, datasets: ds }
})

const chartOptions = computed(() => {
    const base: any = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 800, easing: 'easeOutQuart' },
        plugins: {
            legend: {
                display: props.datasets.length > 1 || props.type === 'doughnut',
                position: props.type === 'doughnut' ? 'bottom' : 'top',
                labels: {
                    usePointStyle: true,
                    padding: 16,
                    font: { family: "'Inter', sans-serif", size: 12 }
                }
            },
            tooltip: {
                backgroundColor: '#1e293b',
                titleFont: { family: "'Inter', sans-serif", size: 13 },
                bodyFont: { family: "'Inter', sans-serif", size: 12 },
                cornerRadius: 8,
                padding: 10,
                displayColors: true,
                callbacks: {
                    label: (ctx: any) => {
                        const value = ctx.parsed.y ?? ctx.parsed
                        const formatted = typeof value === 'number'
                            ? value.toLocaleString('id-ID')
                            : value
                        return ` ${ctx.dataset.label}: ${formatted}`
                    }
                }
            }
        }
    }

    if (props.type !== 'doughnut') {
        base.scales = {
            x: {
                grid: { display: false },
                ticks: {
                    font: { family: "'Inter', sans-serif", size: 11 },
                    color: '#64748b',
                    maxRotation: 45
                }
            },
            y: {
                grid: { color: '#f1f5f9' },
                ticks: {
                    font: { family: "'Inter', sans-serif", size: 11 },
                    color: '#64748b',
                    callback: (v: number) => v >= 1000 ? `${(v / 1000).toFixed(0)}K` : v
                },
                beginAtZero: true
            }
        }
    } else {
        base.cutout = '65%'
    }

    return base
})

const componentMap: Record<string, any> = { bar: Bar, doughnut: Doughnut, line: Line }
const chartComponent = computed(() => componentMap[props.type] || Bar)
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ title }}</h3>
        <div :style="{ height: (height || 280) + 'px' }">
            <component
                :is="chartComponent"
                :data="chartData"
                :options="chartOptions"
            />
        </div>
    </div>
</template>
