<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatGrid from '@/Components/StatGrid.vue'
import { ref, computed } from 'vue'

const props = defineProps<{
    providers: Array<{
        id: number
        name: string
        model_id: string
        is_enabled: boolean
        priority: number
        api_key_env: string
        max_tokens: number
        timeout_seconds: number
        total_calls: number
        total_errors: number
        last_used_at: string | null
        is_ready: boolean
        today_calls: number
        today_success: number
        today_errors: number
        today_tokens: number
        today_avg_response: number | null
    }>
    todayStats: {
        total_requests: number
        successful_requests: number
        failed_requests: number
        total_tokens: number
        avg_response_time: number | null
        fallback_count: number
    }
    recentFallbacks: Array<{
        id: number
        report_id: number
        provider_name: string
        status: string
        attempt_number: number
        error_message: string | null
        created_at: string
    }>
}>()

// Stats untuk StatGrid
const stats = computed(() => {
    const successRate = props.todayStats.total_requests > 0
        ? Math.round((props.todayStats.successful_requests / props.todayStats.total_requests) * 100)
        : 0

    const activeProviders = props.providers.filter((p) => p.is_enabled).length

    return [
        {
            label: 'Total Request Hari Ini',
            value: props.todayStats.total_requests.toLocaleString(),
            icon: '📊'
        },
        {
            label: 'Success Rate',
            value: successRate.toString(),
            suffix: '%',
            icon: '✅'
        },
        {
            label: 'Fallback Count',
            value: props.todayStats.fallback_count.toString(),
            icon: '🔄'
        },
        {
            label: 'Provider Aktif',
            value: activeProviders.toString(),
            icon: '🤖'
        }
    ]
})

// Toggle provider
const toggleProvider = async (providerId: number) => {
    try {
        const response = await router.patch(
            route('admin.ai-providers.toggle', providerId),
            {},
            {
                preserveScroll: true,
                preserveState: false
            }
        )

        // Refresh halaman untuk update data
        router.reload()
    } catch (error) {
        console.error('Toggle failed:', error)
    }
}

// Reset counters
const resetCounters = async (providerId: number) => {
    if (!confirm('Apakah Anda yakin ingin mereset counter provider ini?')) {
        return
    }

    try {
        await router.post(
            route('admin.ai-providers.reset', providerId),
            {},
            {
                preserveScroll: true,
                preserveState: false
            }
        )

        router.reload()
    } catch (error) {
        console.error('Reset failed:', error)
    }
}

// Format tanggal
const formatDate = (dateString: string) => {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffMins = Math.floor(diffMs / 60000)

    if (diffMins < 1) {
        return 'Baru saja'
    } else if (diffMins < 60) {
        return `${diffMins} menit yang lalu`
    } else if (diffMins < 1440) {
        const hours = Math.floor(diffMins / 60)
        return `${hours} jam yang lalu`
    } else {
        const days = Math.floor(diffMins / 1440)
        return `${days} hari yang lalu`
    }
}

// Format response time
const formatResponseTime = (ms: number | null) => {
    if (ms === null) return 'N/A'
    if (ms < 1000) return `${ms}ms`
    return `${(ms / 1000).toFixed(1)}s`
}

// Get error rate for today
const getTodayErrorRate = (provider: any) => {
    if (provider.today_calls === 0) return '0%'
    const rate = (provider.today_errors / provider.today_calls) * 100
    return `${rate.toFixed(1)}%`
}

// Get status badge class for recent fallbacks
const getFallbackStatusClass = (status: string) => {
    const classes: Record<string, string> = {
        'rate_limit': 'bg-orange-100 text-orange-800',
        'timeout': 'bg-yellow-100 text-yellow-800',
        'auth_error': 'bg-red-100 text-red-800',
        'validation_fail': 'bg-purple-100 text-purple-800',
        'error': 'bg-red-100 text-red-800'
    }

    return classes[status] || 'bg-gray-100 text-gray-800'
}

// Get fallback status label
const getFallbackStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        'rate_limit': 'Rate Limit',
        'timeout': 'Timeout',
        'auth_error': 'Auth Error',
        'validation_fail': 'Validation Fail',
        'error': 'Error'
    }

    return labels[status] || status
}
</script>

<template>
    <Head title="Admin - AI Providers" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    AI Providers Management
                </h1>
                <p class="mt-1 text-gray-600">
                    Kelola konfigurasi dan monitor performa AI providers
                </p>
            </div>

            <!-- Today's Stats -->
            <div class="mb-8">
                <StatGrid :stats="stats" :columns="4" />
            </div>

            <!-- Providers List -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    Daftar Provider
                </h2>

                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Provider
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Model
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Total Calls
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Hari Ini
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                        Error Rate
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="provider in providers"
                                    :key="provider.id"
                                    :class="{ 'bg-red-50': !provider.is_ready && provider.is_enabled }"
                                >
                                    <!-- Provider Name -->
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">
                                            {{ provider.name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Priority: {{ provider.priority }}
                                        </div>
                                    </td>

                                    <!-- Model ID -->
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ provider.model_id }}
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            @click="toggleProvider(provider.id)"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2"
                                            :class="provider.is_enabled ? 'bg-teal-600' : 'bg-gray-300'"
                                        >
                                            <span
                                                aria-hidden="true"
                                                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition duration-200 ease-in-out"
                                                :class="provider.is_enabled ? 'translate-x-5' : 'translate-x-0'"
                                            />
                                        </button>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ provider.is_enabled ? 'Aktif' : 'Nonaktif' }}
                                        </div>
                                        <div v-if="!provider.is_ready && provider.is_enabled" class="text-xs text-red-600">
                                            API Key missing
                                        </div>
                                    </td>

                                    <!-- Total Calls -->
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">
                                        {{ provider.total_calls.toLocaleString() }}
                                    </td>

                                    <!-- Today's Stats -->
                                    <td class="px-4 py-3 text-right text-sm text-gray-600">
                                        <div>{{ provider.today_calls }}</div>
                                        <div class="text-xs text-green-600">
                                            ✓ {{ provider.today_success }}
                                        </div>
                                    </td>

                                    <!-- Error Rate -->
                                    <td class="px-4 py-3 text-center text-sm"
                                        :class="{
                                            'text-red-600': getTodayErrorRate(provider) !== '0%',
                                            'text-gray-600': getTodayErrorRate(provider) === '0%'
                                        }"
                                    >
                                        {{ getTodayErrorRate(provider) }}
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            @click="resetCounters(provider.id)"
                                            class="text-xs text-gray-600 hover:text-gray-900 underline"
                                        >
                                            Reset
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Fallbacks -->
            <div
                v-if="recentFallbacks.length > 0"
                class="mb-8"
            >
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    Log Fallback Terbaru
                </h2>

                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Provider
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Attempt
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Error Message
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Waktu
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="fallback in recentFallbacks"
                                    :key="fallback.id"
                                >
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ fallback.provider_name }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="px-2 py-1 rounded-full text-xs font-medium"
                                            :class="getFallbackStatusClass(fallback.status)"
                                        >
                                            {{ getFallbackStatusLabel(fallback.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        Attempt #{{ fallback.attempt_number }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">
                                        {{ fallback.error_message || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ formatDate(fallback.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-8">
                <button
                    @click="router.visit(route('home'))"
                    class="text-gray-600 hover:text-gray-900 font-medium"
                >
                    ← Kembali ke Dashboard
                </button>
            </div>
        </div>
    </AppLayout>
</template>
