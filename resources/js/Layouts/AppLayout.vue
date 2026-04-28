<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    title?: string
}>()

// Ambil nama user untuk avatar initials
const userName = computed(() => {
    // @ts-ignore - auth user akan di-inject oleh Laravel
    return props.$page?.props?.auth?.user?.name || 'User'
})

const userInitials = computed(() => {
    const name = userName.value
    const parts = name.split(' ')
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase()
    }
    return name.substring(0, 2).toUpperCase()
})
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-gray-50">
        <!-- Topbar -->
        <nav class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <Link :href="route('home')" class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-gray-900">
                                Data<span class="text-teal-600">Narasi</span>
                            </span>
                        </Link>
                    </div>

                    <!-- Navigation Links -->
                    <div class="flex items-center space-x-8">
                        <Link
                            :href="route('upload.create')"
                            class="text-gray-700 hover:text-teal-600 font-medium transition-colors"
                            :class="{ 'text-teal-600': route().current('upload.*') }"
                        >
                            Upload
                        </Link>

                        <Link
                            :href="route('reports.index')"
                            class="text-gray-700 hover:text-teal-600 font-medium transition-colors"
                            :class="{ 'text-teal-600': route().current('reports.*') }"
                        >
                            Riwayat
                        </Link>

                        <Link
                            :href="route('admin.ai-providers.index')"
                            class="text-gray-700 hover:text-teal-600 font-medium transition-colors"
                            :class="{ 'text-teal-600': route().current('admin.*') }"
                        >
                            Admin
                        </Link>
                    </div>

                    <!-- User Avatar -->
                    <div class="flex items-center">
                        <div class="flex items-center space-x-3">
                            <div class="text-right hidden sm:block">
                                <div class="text-sm font-medium text-gray-900">{{ userName }}</div>
                                <div class="text-xs text-gray-500">User</div>
                            </div>
                            <div class="h-10 w-10 rounded-full bg-teal-600 flex items-center justify-center text-white font-semibold">
                                {{ userInitials }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="py-6">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="text-center text-sm text-gray-500">
                    © 2025 DataNarasi — Analisis Data dengan AI
                </div>
            </div>
        </footer>
    </div>
</template>
