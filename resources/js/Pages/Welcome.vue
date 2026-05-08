<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

// Mobile menu
const showMenu = ref(false);

// Animated counters
const counters = ref({ types: 0, tones: 0, providers: 0 });
onMounted(() => {
    const targets = { types: 11, tones: 6, providers: 6 };
    const duration = 1200;
    const steps = 30;
    const interval = duration / steps;
    let step = 0;
    const timer = setInterval(() => {
        step++;
        const progress = step / steps;
        counters.value.types = Math.round(targets.types * progress);
        counters.value.tones = Math.round(targets.tones * progress);
        counters.value.providers = Math.round(targets.providers * progress);
        if (step >= steps) clearInterval(timer);
    }, interval);
});

const features = [
    { icon: '🧹', title: 'Auto Data Cleansing', desc: 'Pembersihan data otomatis: hapus duplikat, isi missing values, deteksi anomali.' },
    { icon: '🤖', title: 'Multi-AI Fallback', desc: '6 provider AI yang saling backup. Jika satu gagal, langsung pindah ke berikutnya.' },
    { icon: '📊', title: 'Interactive Charts', desc: 'Visualisasi data interaktif dengan Chart.js — bar, line, doughnut, dan histogram.' },
    { icon: '✍️', title: 'Narasi AI Otomatis', desc: 'AI menulis insight bisnis dalam Bahasa Indonesia sesuai tipe data dan tone pilihan Anda.' },
];

const steps = [
    { num: '1', title: 'Upload File', desc: 'Upload CSV atau Excel. Pilih tipe analisis dan tone narasi.' },
    { num: '2', title: 'AI Proses Data', desc: 'Data dibersihkan, dianalisis statistik, lalu AI menulis narasi insight.' },
    { num: '3', title: 'Lihat Hasil', desc: 'Lihat chart interaktif, statistik detail, dan narasi AI yang siap digunakan.' },
];
</script>

<template>
    <Head title="DataNarasi — Analisis Data AI untuk CSV dan Excel">
        <meta name="description" content="DataNarasi membantu Anda upload CSV/Excel, membersihkan data otomatis, membuat chart interaktif, dan menghasilkan narasi insight bisnis dengan AI dalam Bahasa Indonesia." />
        <meta property="og:title" content="DataNarasi — Analisis Data AI untuk CSV dan Excel" />
        <meta property="og:description" content="Ubah data mentah menjadi visualisasi dan insight bisnis siap pakai dalam hitungan menit." />
    </Head>

    <div class="min-h-screen bg-gray-50">
        <!-- Navbar -->
        <nav class="bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between min-h-16">
                <span class="text-xl sm:text-2xl font-bold text-gray-900 whitespace-nowrap">Data<span class="text-teal-600">Narasi</span></span>

                <!-- Desktop links -->
                <div class="hidden md:flex items-center gap-3">
                    <Link :href="route('upload.create')" class="px-4 py-2 text-sm font-medium text-teal-700 hover:text-teal-800 transition-colors">
                        Upload
                    </Link>
                    <Link :href="route('reports.index')" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                        Riwayat
                    </Link>
                    <Link v-if="canLogin" :href="route('login')" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Admin Login
                    </Link>
                </div>

                <!-- Mobile hamburger -->
                <button
                    @click="showMenu = !showMenu"
                    class="md:hidden inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl hover:bg-gray-100 text-gray-600 transition-colors"
                    :aria-expanded="showMenu"
                    :aria-label="showMenu ? 'Tutup menu' : 'Buka menu'"
                >
                    <svg v-if="!showMenu" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

            <!-- Mobile menu panel -->
            <div v-if="showMenu" class="md:hidden border-t border-gray-100 bg-white shadow-lg">
                <div class="px-4 py-3 space-y-1">
                    <Link :href="route('upload.create')" class="block px-4 py-3 rounded-xl text-sm font-semibold text-gray-700 hover:bg-teal-50 hover:text-teal-700 transition-colors" @click="showMenu = false">
                        Upload Data
                    </Link>
                    <Link :href="route('reports.index')" class="block px-4 py-3 rounded-xl text-sm font-semibold text-gray-700 hover:bg-teal-50 hover:text-teal-700 transition-colors" @click="showMenu = false">
                        Riwayat Analisis
                    </Link>
                    <Link v-if="canLogin" :href="route('login')" class="block px-4 py-3 rounded-xl text-sm font-semibold text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors" @click="showMenu = false">
                        Admin Login
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-teal-50 via-white to-blue-50"></div>
            <div class="absolute top-20 right-10 w-72 h-72 bg-teal-200/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 left-10 w-64 h-64 bg-blue-200/20 rounded-full blur-3xl"></div>

            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-24 lg:py-28 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-50 border border-teal-100 text-teal-700 text-xs font-medium mb-6">
                    <span class="w-1.5 h-1.5 bg-teal-500 rounded-full animate-pulse"></span>
                    Gratis — Tanpa Login
                </div>

                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-bold text-gray-900 tracking-tight leading-tight">
                    Upload Data,<br>
                    <span class="text-teal-600">AI Tulis Insightnya</span>
                </h1>

                <p class="mt-5 text-base sm:text-lg text-gray-500 max-w-2xl mx-auto leading-relaxed">
                    Upload file CSV/Excel → data dibersihkan otomatis → AI menganalisis dan menulis narasi bisnis dalam Bahasa Indonesia. Selesai dalam hitungan menit.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link :href="route('upload.create')" class="w-full sm:w-auto px-8 py-3 bg-teal-600 text-white font-semibold rounded-xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-600/20 hover:shadow-xl hover:shadow-teal-600/30">
                        Mulai Analisis →
                    </Link>
                    <a href="#features" class="w-full sm:w-auto px-8 py-3 text-gray-600 font-medium rounded-xl hover:bg-gray-100 transition-colors">
                        Lihat Fitur
                    </a>
                </div>

                <!-- Stats counters -->
                <div class="mt-12 grid grid-cols-3 gap-3 sm:gap-6 max-w-md mx-auto">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-teal-600">{{ counters.types }}</div>
                        <div class="text-xs text-gray-400 mt-1">Tipe Analisis</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-teal-600">{{ counters.tones }}</div>
                        <div class="text-xs text-gray-400 mt-1">Tone Narasi</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-teal-600">{{ counters.providers }}</div>
                        <div class="text-xs text-gray-400 mt-1">AI Provider</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">Fitur Utama</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-5">
                <div v-for="f in features" :key="f.title" class="bg-white rounded-2xl p-6 border border-gray-100 hover:border-teal-200 hover:shadow-md transition-all duration-200 group">
                    <div class="text-3xl mb-3 group-hover:scale-110 transition-transform">{{ f.icon }}</div>
                    <h3 class="font-semibold text-gray-900 text-sm mb-1.5">{{ f.title }}</h3>
                    <p class="text-xs text-gray-400 leading-relaxed">{{ f.desc }}</p>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="bg-white border-y border-gray-100">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">Cara Kerja</h2>
                <div class="grid md:grid-cols-3 gap-8">
                    <div v-for="s in steps" :key="s.num" class="text-center">
                        <div class="w-10 h-10 rounded-full bg-teal-600 text-white font-bold flex items-center justify-center mx-auto mb-3 text-sm">{{ s.num }}</div>
                        <h3 class="font-semibold text-gray-900 text-sm mb-1">{{ s.title }}</h3>
                        <p class="text-xs text-gray-400 leading-relaxed">{{ s.desc }}</p>
                    </div>
                </div>

                <div class="text-center mt-10">
                    <Link :href="route('upload.create')" class="inline-flex px-6 py-2.5 bg-teal-600 text-white font-medium rounded-xl hover:bg-teal-700 transition-all text-sm">
                        Coba Sekarang — Gratis
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center text-sm text-gray-400">
                © {{ new Date().getFullYear() }} DataNarasi — Analisis Data dengan AI
            </div>
        </footer>
    </div>
</template>
