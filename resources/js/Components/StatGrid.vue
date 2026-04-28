<script setup lang="ts">
import { computed } from 'vue'

interface StatItem {
    label: string
    value: string | number
    prefix?: string
    suffix?: string
    icon?: string
    color?: string
}

const props = defineProps<{
    stats: StatItem[]
    columns?: 2 | 3 | 4 | 6
}>()

// Grid columns class based on props
const gridClass = computed(() => {
    switch (props.columns) {
        case 2:
            return 'grid-cols-2'
        case 3:
            return 'grid-cols-3'
        case 6:
            return 'grid-cols-6'
        case 4:
        default:
            return 'grid-cols-4'
    }
})
</script>

<template>
    <div :class="['grid gap-4', gridClass]">
        <div
            v-for="(stat, index) in stats"
            :key="index"
            class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow"
        >
            <!-- Label -->
            <div class="text-sm font-medium text-gray-600 mb-1">
                {{ stat.label }}
            </div>

            <!-- Value with optional prefix/suffix -->
            <div class="flex items-baseline space-x-1">
                <span
                    v-if="stat.prefix"
                    class="text-lg text-gray-500"
                >
                    {{ stat.prefix }}
                </span>
                <span class="text-2xl font-semibold text-gray-900">
                    {{ stat.value }}
                </span>
                <span
                    v-if="stat.suffix"
                    class="text-sm text-gray-500"
                >
                    {{ stat.suffix }}
                </span>
            </div>

            <!-- Optional Icon -->
            <div
                v-if="stat.icon"
                class="mt-2 text-2xl"
            >
                {{ stat.icon }}
            </div>

            <!-- Optional Color Indicator -->
            <div
                v-if="stat.color"
                class="mt-2 h-1 w-full rounded-full"
                :class="stat.color"
            />
        </div>
    </div>
</template>
