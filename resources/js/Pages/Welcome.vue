<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

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
    <Head title="DataNarasi — Analisis Data dengan AI" />

    <div class="min-h-screen bg-gray-50">
        <!-- Navbar -->
        <nav class="bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                <span class="text-2xl font-bold text-gray-900">Data<span class="text-teal-600">Narasi</span></span>
                <div class="flex items-center gap-3">
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
            </div>
        </nav>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-teal-50 via-white to-blue-50"></div>
            <div class="absolute top-20 right-10 w-72 h-72 bg-teal-200/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 left-10 w-64 h-64 bg-blue-200/20 rounded-full blur-3xl"></div>

            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-50 border border-teal-100 text-teal-700 text-xs font-medium mb-6">
                    <span class="w-1.5 h-1.5 bg-teal-500 rounded-full animate-pulse"></span>
                    Gratis — Tanpa Login
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 tracking-tight leading-tight">
                    Upload Data,<br>
                    <span class="text-teal-600">AI Tulis Insightnya</span>
                </h1>

                <p class="mt-5 text-lg text-gray-500 max-w-2xl mx-auto leading-relaxed">
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
                <div class="mt-14 grid grid-cols-3 gap-6 max-w-md mx-auto">
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
