<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, reactive } from 'vue'
import axios from 'axios'

const props = defineProps<{
    providers: Array<{
        id: number
        name: string
        slug: string
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
        today_cost: number
        today_avg_response: number | null
    }>
    todayStats: {
        total_requests: number
        successful_requests: number
        failed_requests: number
        total_tokens: number
        total_cost: number
        avg_response_time: number | null
        fallback_count: number
    }
    recentFallbacks: Array<{
        id: number
        report_id: number
        provider_name: string
        is_success: boolean
        error_message: string | null
        created_at: string
    }>
    modelCatalog: Record<string, Array<{ id: string; name: string; desc: string }>>
}>()

// ── Stats ──
const successRate = computed(() => {
    if (!props.todayStats.total_requests) return 0
    return Math.round((props.todayStats.successful_requests / props.todayStats.total_requests) * 100)
})
const activeProviders = computed(() => props.providers.filter(p => p.is_enabled).length)

// ── Edit Modal ──
const showEditModal = ref(false)
const editingProvider = reactive({
    id: 0,
    name: '',
    slug: '',
    api_key_env: '',
    api_key_value: '',
    model_id: '',
    max_tokens: 1024,
    timeout_seconds: 30,
    priority: 1,
})
const isSaving = ref(false)
const saveMessage = ref('')
const showModelDropdown = ref(false)
const modelSearch = ref('')

const openEditModal = (provider: any) => {
    editingProvider.id = provider.id
    editingProvider.name = provider.name
    editingProvider.slug = provider.slug || ''
    editingProvider.api_key_env = provider.api_key_env
    editingProvider.api_key_value = ''
    editingProvider.model_id = provider.model_id
    editingProvider.max_tokens = provider.max_tokens
    editingProvider.timeout_seconds = provider.timeout_seconds
    editingProvider.priority = provider.priority
    showEditModal.value = true
    showModelDropdown.value = false
    modelSearch.value = ''
    saveMessage.value = ''
}

const availableModels = computed(() => {
    const slug = editingProvider.slug
    const models = props.modelCatalog[slug] || []
    if (!modelSearch.value) return models
    const q = modelSearch.value.toLowerCase()
    return models.filter(m => m.name.toLowerCase().includes(q) || m.id.toLowerCase().includes(q))
})

const selectModel = (modelId: string) => {
    editingProvider.model_id = modelId
    showModelDropdown.value = false
    modelSearch.value = ''
}

const closeEditModal = () => {
    showEditModal.value = false
    saveMessage.value = ''
}

const saveProvider = async () => {
    isSaving.value = true
    saveMessage.value = ''

    try {
        const payload: Record<string, any> = {
            model_id: editingProvider.model_id,
            max_tokens: editingProvider.max_tokens,
            timeout_seconds: editingProvider.timeout_seconds,
            priority: editingProvider.priority,
        }

        // Only send api_key_value if user entered something
        if (editingProvider.api_key_value.trim()) {
            payload.api_key_value = editingProvider.api_key_value.trim()
            payload.api_key_env = editingProvider.api_key_env
        }

        const response = await axios.put(
            route('admin.ai-providers.update', editingProvider.id),
            payload
        )

        saveMessage.value = response.data.message || 'Berhasil disimpan!'
        setTimeout(() => {
            closeEditModal()
            router.reload()
        }, 800)
    } catch (error: any) {
        saveMessage.value = error.response?.data?.message || 'Gagal menyimpan.'
    } finally {
        isSaving.value = false
    }
}

// ── Toggle provider ──
const toggleProvider = async (providerId: number) => {
    router.patch(
        route('admin.ai-providers.toggle', providerId),
        {},
        { preserveScroll: true, preserveState: false }
    )
}

// ── Reset counters ──
const resetCounters = async (providerId: number) => {
    if (!confirm('Reset semua counter provider ini?')) return
    router.post(
        route('admin.ai-providers.reset', providerId),
        {},
        { preserveScroll: true, preserveState: false }
    )
}

// ── Drag reorder ──
const dragIndex = ref<number | null>(null)

const onDragStart = (index: number) => {
    dragIndex.value = index
}

const onDrop = async (targetIndex: number) => {
    if (dragIndex.value === null || dragIndex.value === targetIndex) return

    // Build new priority order
    const sorted = [...props.providers].sort((a, b) => a.priority - b.priority)
    const [moved] = sorted.splice(dragIndex.value, 1)
    sorted.splice(targetIndex, 0, moved)

    const payload = sorted.map((p, i) => ({ id: p.id, priority: i + 1 }))

    try {
        await axios.post(route('admin.ai-providers.priority'), { providers: payload })
        router.reload()
    } catch (e) {
        console.error('Reorder failed', e)
    }

    dragIndex.value = null
}

// ── Helpers ──
const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Belum pernah'
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    if (diffMins < 1) return 'Baru saja'
    if (diffMins < 60) return `${diffMins}m lalu`
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)}j lalu`
    return `${Math.floor(diffMins / 1440)}h lalu`
}

const getTodayErrorRate = (provider: any) => {
    if (provider.today_calls === 0) return '0%'
    return `${((provider.today_errors / provider.today_calls) * 100).toFixed(0)}%`
}

const formatCost = (cost: number) => {
    if (!cost || cost === 0) return '-'
    if (cost < 0.01) return `$${cost.toFixed(4)}`
    if (cost < 1) return `$${cost.toFixed(3)}`
    return `$${cost.toFixed(2)}`
}

// ── API Key Health Check ──
const healthCheckResults = reactive<Record<number, any>>({})
const checkingProviders = reactive<Record<number, boolean>>({})
const showHealthModal = ref(false)
const healthModalData = ref<any>(null)

const checkApiKey = async (provider: any) => {
    checkingProviders[provider.id] = true
    try {
        const response = await axios.post(
            route('admin.ai-providers.check-key', provider.id)
        )
        healthCheckResults[provider.id] = response.data
        healthModalData.value = { ...response.data, providerName: provider.name, providerSlug: provider.slug }
        showHealthModal.value = true
    } catch (error: any) {
        healthCheckResults[provider.id] = {
            valid: false,
            error: error.response?.data?.error || 'Gagal mengecek API key',
            checked_at: new Date().toISOString()
        }
        healthModalData.value = { ...healthCheckResults[provider.id], providerName: provider.name, providerSlug: provider.slug }
        showHealthModal.value = true
    } finally {
        checkingProviders[provider.id] = false
    }
}

const getHealthBadge = (providerId: number) => {
    const result = healthCheckResults[providerId]
    if (!result) return null
    return result.valid
}
</script>

<template>
    <Head title="Admin - AI Providers" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">AI Providers</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Kelola konfigurasi, urutan prioritas, dan monitor performa AI providers.
                </p>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
                <div class="rounded-xl border border-gray-200 dark:border-cream-400 bg-white dark:bg-cream-300 px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Request Hari Ini</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1.5 tabular-nums">
                        {{ todayStats.total_requests }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-cream-400 bg-white dark:bg-cream-300 px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Success Rate</div>
                    <div class="text-2xl font-bold mt-1.5 tabular-nums" :class="{
                        'text-emerald-600': successRate >= 90,
                        'text-amber-600': successRate >= 50 && successRate < 90,
                        'text-red-600': successRate < 50 && todayStats.total_requests > 0,
                        'text-gray-400': todayStats.total_requests === 0
                    }">
                        {{ todayStats.total_requests > 0 ? successRate + '%' : '-' }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-cream-400 bg-white dark:bg-cream-300 px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Token Digunakan</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1.5 tabular-nums">
                        {{ todayStats.total_tokens.toLocaleString('id-ID') }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-cream-400 bg-white dark:bg-cream-300 px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Biaya Hari Ini</div>
                    <div class="text-2xl font-bold mt-1.5 tabular-nums" :class="{
                        'text-teal-600': todayStats.total_cost > 0,
                        'text-gray-400': !todayStats.total_cost
                    }">
                        {{ formatCost(todayStats.total_cost) }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-cream-400 bg-white dark:bg-cream-300 px-4 py-4">
                    <div class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Provider Aktif</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1.5 tabular-nums">
                        {{ activeProviders }} / {{ providers.length }}
                    </div>
                </div>
            </div>

            <!-- Providers Table -->
            <div class="rounded-xl border border-gray-200 dark:border-cream-400 bg-white dark:bg-cream-300 overflow-hidden mb-8">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Daftar Provider</h2>
                    <span class="text-[11px] text-gray-400">Drag untuk reorder · Klik ⚙ untuk edit</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50/80 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-400 uppercase w-8">#</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-400 uppercase">Provider</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-400 uppercase">Model</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-400 uppercase">Calls</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-400 uppercase">Hari Ini</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-400 uppercase">Cost</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-400 uppercase">Error</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-400 uppercase">Terakhir</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-400 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr
                                v-for="(provider, index) in providers"
                                :key="provider.id"
                                draggable="true"
                                @dragstart="onDragStart(index)"
                                @dragover.prevent
                                @drop="onDrop(index)"
                                class="hover:bg-gray-50/50 transition-colors cursor-grab active:cursor-grabbing"
                                :class="{ 'bg-red-50/30': !provider.is_ready && provider.is_enabled }"
                            >
                                <td class="px-4 py-3 text-xs text-gray-400 tabular-nums">
                                    {{ provider.priority }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-800">{{ provider.name }}</div>
                                    <div class="text-[11px] text-gray-400 font-mono">{{ provider.api_key_env }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <code class="text-xs text-gray-600 bg-gray-50 px-1.5 py-0.5 rounded">
                                        {{ provider.model_id }}
                                    </code>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        @click="toggleProvider(provider.id)"
                                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="provider.is_enabled ? 'bg-emerald-500' : 'bg-gray-300'"
                                    >
                                        <span
                                            class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition duration-200"
                                            :class="provider.is_enabled ? 'translate-x-4' : 'translate-x-0'"
                                        />
                                    </button>
                                    <div v-if="!provider.is_ready && provider.is_enabled" class="text-[10px] text-red-500 mt-0.5">
                                        No API key
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-gray-600 tabular-nums">
                                    {{ provider.total_calls.toLocaleString() }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-xs text-gray-700 tabular-nums">{{ provider.today_calls }}</span>
                                    <span v-if="provider.today_success > 0" class="text-[10px] text-emerald-600 ml-1">
                                        ✓{{ provider.today_success }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-xs tabular-nums" :class="{
                                        'text-teal-600 font-medium': provider.today_cost > 0,
                                        'text-gray-400': !provider.today_cost
                                    }">
                                        {{ formatCost(provider.today_cost) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="text-xs tabular-nums"
                                        :class="getTodayErrorRate(provider) !== '0%' ? 'text-red-500 font-medium' : 'text-gray-400'"
                                    >
                                        {{ getTodayErrorRate(provider) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-[11px] text-gray-400">
                                    {{ formatDate(provider.last_used_at) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Health check badge -->
                                        <span
                                            v-if="getHealthBadge(provider.id) === true"
                                            class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0"
                                            title="API Key valid"
                                        ></span>
                                        <span
                                            v-else-if="getHealthBadge(provider.id) === false"
                                            class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"
                                            title="API Key invalid"
                                        ></span>

                                        <!-- Check API Key button -->
                                        <button
                                            @click="checkApiKey(provider)"
                                            :disabled="checkingProviders[provider.id]"
                                            class="text-gray-400 hover:text-blue-600 transition-colors p-1 disabled:opacity-50"
                                            title="Cek API Key"
                                        >
                                            <svg v-if="!checkingProviders[provider.id]" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                        </button>

                                        <button
                                            @click="openEditModal(provider)"
                                            class="text-gray-400 hover:text-gray-700 transition-colors p-1"
                                            title="Edit provider"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="resetCounters(provider.id)"
                                            class="text-gray-400 hover:text-red-500 transition-colors p-1"
                                            title="Reset counters"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Fallback Logs -->
            <div v-if="recentFallbacks.length > 0" class="rounded-xl border border-gray-200 dark:border-cream-400 bg-white dark:bg-cream-300 overflow-hidden mb-8">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Log Error Terbaru</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    <div
                        v-for="fb in recentFallbacks"
                        :key="fb.id"
                        class="px-5 py-3 flex items-start gap-4"
                    >
                        <span class="flex-shrink-0 w-2 h-2 rounded-full mt-1.5 bg-red-400"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium text-gray-700">{{ fb.provider_name }}</span>
                                <span class="text-[10px] text-gray-400">Report #{{ fb.report_id }}</span>
                            </div>
                            <p v-if="fb.error_message" class="text-xs text-gray-500 mt-0.5 truncate">
                                {{ fb.error_message }}
                            </p>
                        </div>
                        <span class="text-[11px] text-gray-400 flex-shrink-0">
                            {{ formatDate(fb.created_at) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Back -->
            <div class="pt-2">
                <button
                    @click="router.visit(route('home'))"
                    class="text-sm text-gray-500 hover:text-gray-700 font-medium"
                >
                    ← Kembali
                </button>
            </div>
        </div>

        <!-- ═══ EDIT MODAL ═══ -->
        <Teleport to="body">
            <div
                v-if="showEditModal"
                class="fixed inset-0 z-50 flex items-center justify-center"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/30" @click="closeEditModal" />

                <!-- Modal -->
                <div class="relative bg-white dark:bg-cream-300 rounded-xl shadow-xl w-full max-w-md mx-4">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">
                            Edit · {{ editingProvider.name }}
                        </h3>
                    </div>

                    <div class="px-5 py-5 space-y-4">
                        <!-- API Key -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">
                                API Key <span class="text-gray-400">({{ editingProvider.api_key_env }})</span>
                            </label>
                            <input
                                v-model="editingProvider.api_key_value"
                                type="password"
                                placeholder="Biarkan kosong jika tidak ingin mengubah"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none transition-colors"
                            />
                            <p class="text-[10px] text-gray-400 mt-1">
                                Nilai akan disimpan di .env — tidak pernah ditampilkan di UI.
                            </p>
                        </div>

                        <!-- Model ID -->
                        <div class="relative">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Model ID</label>
                            <div class="flex gap-2">
                                <input
                                    v-model="editingProvider.model_id"
                                    type="text"
                                    placeholder="Ketik atau pilih dari dropdown"
                                    class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none transition-colors font-mono"
                                />
                                <button
                                    v-if="availableModels.length > 0 || modelCatalog[editingProvider.slug]"
                                    @click="showModelDropdown = !showModelDropdown"
                                    type="button"
                                    class="px-2.5 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-500 hover:text-gray-700 transition-colors"
                                    title="Pilih model"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Model Dropdown -->
                            <div
                                v-if="showModelDropdown"
                                class="absolute z-10 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto"
                            >
                                <div class="sticky top-0 bg-white border-b border-gray-100 p-2">
                                    <input
                                        v-model="modelSearch"
                                        type="text"
                                        placeholder="Cari model..."
                                        class="w-full text-xs border border-gray-200 rounded px-2.5 py-1.5 focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none"
                                    />
                                </div>
                                <div v-if="availableModels.length === 0" class="px-3 py-4 text-xs text-gray-400 text-center">
                                    Tidak ada model yang cocok
                                </div>
                                <button
                                    v-for="model in availableModels"
                                    :key="model.id"
                                    @click="selectModel(model.id)"
                                    class="w-full text-left px-3 py-2.5 hover:bg-teal-50 transition-colors border-b border-gray-50 last:border-0"
                                    :class="{ 'bg-teal-50/50': editingProvider.model_id === model.id }"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-medium text-gray-800">{{ model.name }}</span>
                                        <span v-if="editingProvider.model_id === model.id" class="text-teal-600 text-xs">✓</span>
                                    </div>
                                    <div class="text-[10px] text-gray-400 font-mono mt-0.5">{{ model.id }}</div>
                                    <div class="text-[10px] text-gray-400 mt-0.5">{{ model.desc }}</div>
                                </button>
                            </div>

                            <p v-if="!modelCatalog[editingProvider.slug]" class="text-[10px] text-gray-400 mt-1">
                                Katalog model belum tersedia untuk provider ini. Ketik model ID manual.
                            </p>
                        </div>

                        <!-- Max Tokens + Timeout -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Max Tokens</label>
                                <input
                                    v-model.number="editingProvider.max_tokens"
                                    type="number"
                                    min="100"
                                    max="32000"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none transition-colors tabular-nums"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Timeout (detik)</label>
                                <input
                                    v-model.number="editingProvider.timeout_seconds"
                                    type="number"
                                    min="5"
                                    max="300"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none transition-colors tabular-nums"
                                />
                            </div>
                        </div>

                        <!-- Priority -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Priority (1 = tertinggi)</label>
                            <input
                                v-model.number="editingProvider.priority"
                                type="number"
                                min="1"
                                max="20"
                                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none transition-colors tabular-nums"
                            />
                        </div>

                        <!-- Save message -->
                        <p v-if="saveMessage" class="text-xs font-medium" :class="saveMessage.includes('Gagal') ? 'text-red-600' : 'text-emerald-600'">
                            {{ saveMessage }}
                        </p>
                    </div>

                    <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                        <button
                            @click="closeEditModal"
                            class="text-xs font-medium text-gray-500 hover:text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            @click="saveProvider"
                            :disabled="isSaving"
                            class="text-xs font-medium text-white bg-teal-600 hover:bg-teal-700 disabled:opacity-50 px-4 py-1.5 rounded-lg transition-colors"
                        >
                            {{ isSaving ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- API Key Health Check Result Modal -->
        <Teleport to="body">
            <div v-if="showHealthModal && healthModalData" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showHealthModal = false">
                <div class="bg-white dark:bg-cream-300 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
                    <!-- Header -->
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                                :class="healthModalData.valid ? 'bg-emerald-500' : 'bg-red-500'"
                            >
                                <svg v-if="healthModalData.valid" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">{{ healthModalData.providerName }}</h3>
                                <p class="text-xs" :class="healthModalData.valid ? 'text-emerald-600' : 'text-red-600'">
                                    {{ healthModalData.valid ? 'API Key Valid ✓' : 'API Key Invalid ✗' }}
                                </p>
                            </div>
                        </div>
                        <button @click="showHealthModal = false" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-5 py-4 space-y-3">
                        <!-- Error Message -->
                        <div v-if="healthModalData.error" class="bg-red-50 border border-red-100 rounded-xl px-4 py-3">
                            <div class="text-[11px] text-red-400 font-medium uppercase tracking-wide mb-1">Error</div>
                            <p class="text-xs text-red-700">{{ healthModalData.error }}</p>
                        </div>

                        <!-- Models Count -->
                        <div v-if="healthModalData.models_count !== null" class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs text-gray-500">Model Tersedia</span>
                            <span class="text-sm font-semibold text-gray-900 tabular-nums">{{ healthModalData.models_count }}</span>
                        </div>

                        <!-- Tier -->
                        <div v-if="healthModalData.tier" class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs text-gray-500">Tipe Akun</span>
                            <span
                                class="text-[11px] font-semibold uppercase tracking-wide px-2.5 py-0.5 rounded-full"
                                :class="{
                                    'bg-amber-50 text-amber-700': healthModalData.tier === 'premium',
                                    'bg-gray-100 text-gray-600': healthModalData.tier === 'free',
                                    'bg-blue-50 text-blue-600': healthModalData.tier === 'coding_plan',
                                    'bg-gray-50 text-gray-500': healthModalData.tier === 'standard' || healthModalData.tier === 'unknown'
                                }"
                            >
                                {{ healthModalData.tier }}
                            </span>
                        </div>

                        <!-- Balance (Kimi) -->
                        <div v-if="healthModalData.balance !== null && healthModalData.balance !== undefined" class="bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3">
                            <div class="text-[11px] text-emerald-500 font-medium uppercase tracking-wide mb-2">💰 Balance</div>
                            <div class="text-xl font-bold text-emerald-700 tabular-nums mb-2">
                                ¥{{ typeof healthModalData.balance === 'number' ? healthModalData.balance.toFixed(2) : healthModalData.balance }}
                            </div>
                            <div v-if="healthModalData.balance_detail" class="grid grid-cols-2 gap-2">
                                <div class="bg-white/60 rounded-lg px-3 py-1.5">
                                    <div class="text-[10px] text-gray-400">Voucher</div>
                                    <div class="text-xs font-semibold text-gray-700 tabular-nums">
                                        ¥{{ typeof healthModalData.balance_detail.voucher === 'number' ? healthModalData.balance_detail.voucher.toFixed(2) : (healthModalData.balance_detail.voucher ?? '-') }}
                                    </div>
                                </div>
                                <div class="bg-white/60 rounded-lg px-3 py-1.5">
                                    <div class="text-[10px] text-gray-400">Cash</div>
                                    <div class="text-xs font-semibold text-gray-700 tabular-nums">
                                        ¥{{ typeof healthModalData.balance_detail.cash === 'number' ? healthModalData.balance_detail.cash.toFixed(2) : (healthModalData.balance_detail.cash ?? '-') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rate Limits (Claude) -->
                        <div v-if="healthModalData.rate_limit && Object.keys(healthModalData.rate_limit).length > 0" class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                            <div class="text-[11px] text-blue-500 font-medium uppercase tracking-wide mb-2">⚡ Rate Limits</div>
                            <div class="space-y-1.5">
                                <div v-if="healthModalData.rate_limit.requests_remaining !== undefined" class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600">Requests</span>
                                    <span class="text-xs font-semibold text-gray-800 tabular-nums">
                                        {{ healthModalData.rate_limit.requests_remaining?.toLocaleString() }} / {{ healthModalData.rate_limit.requests_limit?.toLocaleString() }}
                                    </span>
                                </div>
                                <div v-if="healthModalData.rate_limit.input_tokens_remaining !== undefined" class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600">Input Tokens</span>
                                    <span class="text-xs font-semibold text-gray-800 tabular-nums">
                                        {{ healthModalData.rate_limit.input_tokens_remaining?.toLocaleString() }} / {{ healthModalData.rate_limit.input_tokens_limit?.toLocaleString() }}
                                    </span>
                                </div>
                                <div v-if="healthModalData.rate_limit.output_tokens_remaining !== undefined" class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600">Output Tokens</span>
                                    <span class="text-xs font-semibold text-gray-800 tabular-nums">
                                        {{ healthModalData.rate_limit.output_tokens_remaining?.toLocaleString() }} / {{ healthModalData.rate_limit.output_tokens_limit?.toLocaleString() }}
                                    </span>
                                </div>
                                <div v-if="healthModalData.rate_limit.status === 'rate_limited'" class="text-xs text-amber-600 font-medium">
                                    ⚠️ Sedang rate limited
                                </div>
                            </div>
                        </div>

                        <!-- Response Time -->
                        <div v-if="healthModalData.response_ms" class="flex items-center justify-between py-2 border-b border-gray-50">
                            <span class="text-xs text-gray-500">Response Time</span>
                            <span class="text-xs font-semibold text-gray-700 tabular-nums">{{ healthModalData.response_ms }}ms</span>
                        </div>

                        <!-- Checked At -->
                        <div v-if="healthModalData.checked_at" class="flex items-center justify-between py-2">
                            <span class="text-xs text-gray-500">Dicek pada</span>
                            <span class="text-[11px] text-gray-400">{{ new Date(healthModalData.checked_at).toLocaleTimeString('id-ID') }}</span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                        <button
                            @click="showHealthModal = false"
                            class="text-xs font-medium text-gray-500 hover:text-gray-700 px-4 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
