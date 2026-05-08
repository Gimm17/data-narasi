<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import DarkToggle from '@/Components/DarkToggle.vue'

const props = defineProps<{
    title?: string
}>()

const isLoggedIn = computed(() => {
    return !!usePage().props?.auth?.user
})

const userName = computed(() => {
    return usePage().props?.auth?.user?.name || 'Guest'
})

const userEmail = computed(() => {
    return usePage().props?.auth?.user?.email || ''
})

const isAdmin = computed(() => {
    return usePage().props?.auth?.user?.is_admin || false
})

const userInitials = computed(() => {
    const name = userName.value
    const parts = name.split(' ')
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase()
    }
    return name.substring(0, 2).toUpperCase()
})

// Profile dropdown
const showDropdown = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value
}

const closeDropdown = (e: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        showDropdown.value = false
    }
}

const logout = () => {
    showDropdown.value = false
    router.post(route('logout'))
}

// Mobile menu
const showMobileMenu = ref(false)
const toggleMobileMenu = () => {
    showMobileMenu.value = !showMobileMenu.value
}
const closeMobileMenu = () => {
    showMobileMenu.value = false
}

onMounted(() => {
    document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
    document.removeEventListener('click', closeDropdown)
})
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col transition-colors duration-300">
        <!-- Topbar -->
        <nav class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-md shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <Link :href="route('home')" class="flex items-center space-x-2 group">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center shadow-sm group-hover:shadow-md transition-shadow">
                                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-gray-900 dark:text-white">
                                Data<span class="text-teal-600 dark:text-teal-400">Narasi</span>
                            </span>
                        </Link>
                    </div>

                    <!-- Desktop Navigation Links -->
                    <div class="hidden md:flex items-center space-x-1">
                        <Link
                            :href="route('upload.create')"
                            class="nav-link"
                            :class="{ 'nav-link-active': route().current('upload.*') }"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            Upload
                        </Link>

                        <Link
                            :href="route('reports.index')"
                            class="nav-link"
                            :class="{ 'nav-link-active': route().current('reports.*') }"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            Riwayat
                        </Link>

                        <Link
                            v-if="isAdmin"
                            :href="route('dashboard')"
                            class="nav-link"
                            :class="{ 'nav-link-active': route().current('dashboard') }"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard
                        </Link>

                        <Link
                            v-if="isAdmin"
                            :href="route('admin.ai-providers.index')"
                            class="nav-link"
                            :class="{ 'nav-link-active': route().current('admin.*') }"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            AI Providers
                        </Link>
                    </div>

                    <!-- Right Section: Profile + Dark Toggle -->
                    <div class="flex items-center gap-2 sm:gap-3">
                        <DarkToggle />
                        <template v-if="isLoggedIn">
                            <!-- Profile Dropdown -->
                            <div class="relative" ref="dropdownRef">
                                <button
                                    @click="toggleDropdown"
                                    class="flex items-center gap-2 p-1 pr-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                                >
                                    <div class="h-9 w-9 rounded-full bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center text-white text-sm font-semibold shadow-sm">
                                        {{ userInitials }}
                                    </div>
                                    <div class="text-right hidden sm:block">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-tight">{{ userName }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 leading-tight">{{ isAdmin ? 'Admin' : 'User' }}</div>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform" :class="{ 'rotate-180': showDropdown }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <Transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 scale-95 -translate-y-1"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 scale-100 translate-y-0"
                                    leave-to-class="opacity-0 scale-95 -translate-y-1"
                                >
                                    <div v-if="showDropdown" class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                                        <!-- User Info -->
                                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center text-white font-semibold">
                                                    {{ userInitials }}
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ userName }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ userEmail }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Menu Items -->
                                        <div class="py-1">
                                            <Link
                                                v-if="isAdmin"
                                                :href="route('dashboard')"
                                                class="dropdown-item"
                                                @click="showDropdown = false"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="3" width="7" height="7"></rect>
                                                    <rect x="14" y="3" width="7" height="7"></rect>
                                                    <rect x="14" y="14" width="7" height="7"></rect>
                                                    <rect x="3" y="14" width="7" height="7"></rect>
                                                </svg>
                                                Dashboard
                                            </Link>

                                            <Link
                                                v-if="isAdmin"
                                                :href="route('admin.ai-providers.index')"
                                                class="dropdown-item"
                                                @click="showDropdown = false"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852 1.002 1.51 1H21a2 2 0 0 1 0 4h-.09c-.658 0-1.25.398-1.51 1z"></path>
                                                </svg>
                                                AI Providers
                                            </Link>
                                        </div>

                                        <!-- Logout -->
                                        <div class="border-t border-gray-100 dark:border-gray-700 py-1">
                                            <button
                                                @click="logout"
                                                class="dropdown-item text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 w-full"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                                    <polyline points="16 17 21 12 16 7"></polyline>
                                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                                </svg>
                                                Keluar
                                            </button>
                                        </div>
                                    </div>
                                </Transition>
                            </div>
                        </template>

                        <template v-else>
                            <Link :href="route('login')" class="hidden md:inline-flex text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                                Login
                            </Link>
                            <Link :href="route('register')" class="hidden md:inline-flex text-sm font-medium bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 transition-colors">
                                Daftar
                            </Link>
                        </template>

                        <button
                            @click="toggleMobileMenu"
                            class="md:hidden inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors"
                            :aria-expanded="showMobileMenu"
                            aria-controls="mobile-navigation"
                            :aria-label="showMobileMenu ? 'Tutup menu navigasi' : 'Buka menu navigasi'"
                        >
                            <svg v-if="!showMobileMenu" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <line x1="3" y1="12" x2="21" y2="12"></line>
                                <line x1="3" y1="18" x2="21" y2="18"></line>
                            </svg>
                            <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="showMobileMenu" id="mobile-navigation" class="md:hidden border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg">
                    <div class="px-4 py-4 space-y-2">
                        <Link :href="route('upload.create')" class="mobile-nav-link" :class="{ 'mobile-nav-active': route().current('upload.*') }" @click="closeMobileMenu">Upload Data</Link>
                        <Link :href="route('reports.index')" class="mobile-nav-link" :class="{ 'mobile-nav-active': route().current('reports.*') }" @click="closeMobileMenu">Riwayat Analisis</Link>
                        <Link v-if="isAdmin" :href="route('dashboard')" class="mobile-nav-link" :class="{ 'mobile-nav-active': route().current('dashboard') }" @click="closeMobileMenu">Dashboard Admin</Link>
                        <Link v-if="isAdmin" :href="route('admin.ai-providers.index')" class="mobile-nav-link" :class="{ 'mobile-nav-active': route().current('admin.*') }" @click="closeMobileMenu">AI Providers</Link>

                        <!-- Auth links for mobile (guest) -->
                        <template v-if="!isLoggedIn">
                            <hr class="border-gray-100 dark:border-gray-700 my-2" />
                            <Link :href="route('login')" class="mobile-nav-link" @click="closeMobileMenu">Login</Link>
                            <Link :href="route('register')" class="mobile-nav-link text-teal-600" @click="closeMobileMenu">Daftar Akun</Link>
                        </template>
                    </div>
                </div>
            </Transition>
        </nav>

        <main class="flex-1 py-4 sm:py-6 overflow-x-hidden transition-colors duration-300">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                    © {{ new Date().getFullYear() }} DataNarasi — Analisis Data dengan AI
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4b5563;
    transition: all 0.2s;
}

:root.dark .nav-link {
    color: #d1d5db;
}

.nav-link:hover {
    color: #0d9488;
    background-color: #f0fdfa;
}

:root.dark .nav-link:hover {
    color: #5eead4;
    background-color: rgba(20, 184, 166, 0.1);
}

.nav-link-active {
    color: #0d9488;
    background-color: #f0fdfa;
}

:root.dark .nav-link-active {
    color: #5eead4;
    background-color: rgba(20, 184, 166, 0.1);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    color: #374151;
    transition: background-color 0.15s;
    cursor: pointer;
}

:root.dark .dropdown-item {
    color: #d1d5db;
}

.dropdown-item:hover {
    background-color: #f9fafb;
}

:root.dark .dropdown-item:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.mobile-nav-link {
    display: flex;
    align-items: center;
    min-height: 48px;
    padding: 0.75rem 0.875rem;
    border-radius: 0.75rem;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #374151;
    transition: all 0.15s;
}

:root.dark .mobile-nav-link {
    color: #d1d5db;
}

.mobile-nav-link:hover {
    background-color: #f0fdfa;
    color: #0d9488;
}

:root.dark .mobile-nav-link:hover {
    background-color: rgba(20, 184, 166, 0.1);
    color: #5eead4;
}

.mobile-nav-active {
    background-color: #f0fdfa;
    color: #0d9488;
}

:root.dark .mobile-nav-active {
    background-color: rgba(20, 184, 166, 0.1);
    color: #5eead4;
}
</style>
