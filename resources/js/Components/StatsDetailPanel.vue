<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
    columnInfo: Record<string, any> | null
}>()

const isOpen = ref(false)

const columns = computed(() => {
    if (!props.columnInfo) return []

    return Object.values(props.columnInfo).map((col: any) => ({
        name: col.name,
        dtype: col.dtype,
        isNumeric: col.is_numeric,
        nullCount: col.null_count ?? 0,
        nullPct: col.null_percentage != null ? col.null_percentage.toFixed(1) : '0.0',
        uniqueCount: col.unique_count ?? 0,
        min: col.min != null ? Number(col.min).toLocaleString('id-ID') : '-',
        max: col.max != null ? Number(col.max).toLocaleString('id-ID') : '-',
        mean: col.mean != null ? Number(col.mean).toLocaleString('id-ID', { maximumFractionDigits: 2 }) : '-',
        median: col.median != null ? Number(col.median).toLocaleString('id-ID', { maximumFractionDigits: 2 }) : '-',
        std: col.std != null ? Number(col.std).toLocaleString('id-ID', { maximumFractionDigits: 2 }) : '-',
        sampleValues: col.sample_values || [],
    }))
})

const numericColumns = computed(() => columns.value.filter(c => c.isNumeric))
const textColumns = computed(() => columns.value.filter(c => !c.isNumeric))

const dtypeBadge = (dtype: string) => {
    if (dtype.includes('int') || dtype.includes('float')) return { label: 'Numerik', cls: 'bg-blue-50 text-blue-700' }
    if (dtype.includes('datetime')) return { label: 'Tanggal', cls: 'bg-purple-50 text-purple-700' }
    return { label: 'Teks', cls: 'bg-gray-100 text-gray-600' }
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white">
        <!-- Toggle header -->
        <button
            @click="isOpen = !isOpen"
            class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors rounded-xl"
        >
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Detail Statistik per Kolom</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ columns.length }} kolom terdeteksi</p>
            </div>
            <svg
                class="w-5 h-5 text-gray-400 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
                fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Content -->
        <div
            v-show="isOpen"
            class="border-t border-gray-100 px-5 pb-5"
        >
            <!-- Numeric columns -->
            <div v-if="numericColumns.length > 0" class="mt-4">
                <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Kolom Numerik</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-2 pr-4 font-medium text-gray-500">Kolom</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-500">Min</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-500">Max</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-500">Mean</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-500">Median</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-500">Std Dev</th>
                                <th class="text-right py-2 pl-3 font-medium text-gray-500">Null</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="col in numericColumns"
                                :key="col.name"
                                class="border-b border-gray-50 hover:bg-gray-50/50"
                            >
                                <td class="py-2.5 pr-4">
                                    <span class="font-medium text-gray-800">{{ col.name }}</span>
                                </td>
                                <td class="text-right py-2.5 px-3 text-gray-600 tabular-nums">{{ col.min }}</td>
                                <td class="text-right py-2.5 px-3 text-gray-600 tabular-nums">{{ col.max }}</td>
                                <td class="text-right py-2.5 px-3 text-gray-600 tabular-nums">{{ col.mean }}</td>
                                <td class="text-right py-2.5 px-3 text-gray-600 tabular-nums">{{ col.median }}</td>
                                <td class="text-right py-2.5 px-3 text-gray-600 tabular-nums">{{ col.std }}</td>
                                <td class="text-right py-2.5 pl-3">
                                    <span
                                        class="inline-block min-w-[32px] text-center rounded px-1.5 py-0.5"
                                        :class="col.nullCount > 0 ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'"
                                    >
                                        {{ col.nullCount }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Text columns -->
            <div v-if="textColumns.length > 0" class="mt-5">
                <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Kolom Teks</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div
                        v-for="col in textColumns"
                        :key="col.name"
                        class="rounded-lg border border-gray-100 p-3"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-gray-800">{{ col.name }}</span>
                            <span
                                class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                :class="dtypeBadge(col.dtype).cls"
                            >
                                {{ dtypeBadge(col.dtype).label }}
                            </span>
                        </div>
                        <div class="flex gap-3 text-[11px] text-gray-500 mb-2">
                            <span>{{ col.uniqueCount }} unik</span>
                            <span>{{ col.nullCount }} null</span>
                        </div>
                        <div v-if="col.sampleValues.length > 0" class="flex flex-wrap gap-1">
                            <span
                                v-for="(val, i) in col.sampleValues.slice(0, 3)"
                                :key="i"
                                class="text-[10px] bg-gray-50 text-gray-600 px-1.5 py-0.5 rounded"
                            >
                                {{ String(val).substring(0, 20) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
