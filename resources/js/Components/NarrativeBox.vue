<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    narrative: string
    providerUsed?: string
    isLoading?: boolean
}>()

// Format narrative with proper line breaks
const formattedNarrative = computed(() => {
    if (!props.narrative) return ''

    return props.narrative
        .split('\n')
        .filter((line) => line.trim() !== '')
        .join('\n\n')
})

const hasNarrative = computed(() => {
    return props.narrative && props.narrative.trim() !== ''
})

// Provider badge color based on provider name
const providerBadgeColor = computed(() => {
    if (!props.providerUsed) return 'bg-gray-100 text-gray-800'

    const provider = props.providerUsed.toLowerCase()

    if (provider.includes('gemini')) return 'bg-blue-100 text-blue-800'
    if (provider.includes('kimi')) return 'bg-purple-100 text-purple-800'
    if (provider.includes('glm')) return 'bg-orange-100 text-orange-800'
    if (provider.includes('claude')) return 'bg-amber-100 text-amber-800'

    return 'bg-gray-100 text-gray-800'
})
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <!-- Header with Provider Badge -->
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">
                Narasi Analisis
            </h3>

            <!-- Provider Badge -->
            <div
                v-if="providerUsed"
                class="px-3 py-1 rounded-full text-xs font-medium"
                :class="providerBadgeColor"
            >
                {{ providerUsed }}
            </div>
        </div>

        <!-- Loading State -->
        <div
            v-if="isLoading"
            class="p-6"
        >
            <div class="space-y-3">
                <!-- Skeleton Loading Lines -->
                <div class="animate-pulse">
                    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-5/6 mb-2"></div>
                    <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                </div>
            </div>
            <div class="mt-4 text-center text-sm text-gray-500">
                Sedang生成 narasi...
            </div>
        </div>

        <!-- Narrative Content -->
        <div
            v-else-if="hasNarrative"
            class="p-6"
        >
            <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed whitespace-pre-line">
                {{ formattedNarrative }}
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="p-12 text-center"
        >
            <svg
                class="mx-auto h-12 w-12 text-gray-400 mb-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">
                Belum Ada Narasi
            </h3>
            <p class="text-sm text-gray-500">
                Narasi analisis akan muncul di sini setelah processing selesai.
            </p>
        </div>
    </div>
</template>
