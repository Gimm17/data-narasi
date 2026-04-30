<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const isLoaded = ref(false);

onMounted(() => {
    setTimeout(() => (isLoaded.value = true), 100);
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login — DataNarasi" />

    <div class="login-page">
        <!-- Subtle animated background gradient -->
        <div class="bg-decor">
            <div class="gradient-blob blob-1"></div>
            <div class="gradient-blob blob-2"></div>
        </div>

        <div class="login-wrapper" :class="{ 'loaded': isLoaded }">
            <!-- Left: Form -->
            <div class="form-side">
                <div class="form-inner">
                    <!-- Brand -->
                    <div class="brand">
                        <Link href="/" class="brand-link">
                            <span class="brand-text">Data<span class="brand-accent">Narasi</span></span>
                        </Link>
                    </div>

                    <h1 class="form-title">Selamat Datang!</h1>
                    <p class="form-subtitle">Masuk ke akun Anda untuk melanjutkan analisis data.</p>

                    <!-- Status Message -->
                    <div v-if="status" class="status-msg">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        {{ status }}
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="login-form">
                        <div class="field">
                            <label for="email" class="field-label">Email</label>
                            <div class="input-wrap">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <input
                                    id="email"
                                    type="email"
                                    class="field-input"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="nama@email.com"
                                />
                            </div>
                            <InputError class="field-error" :message="form.errors.email" />
                        </div>

                        <div class="field">
                            <label for="password" class="field-label">Password</label>
                            <div class="input-wrap">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="field-input"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                />
                                <button type="button" class="eye-btn" @click="showPassword = !showPassword" tabindex="-1">
                                    <svg v-if="!showPassword" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                            <InputError class="field-error" :message="form.errors.password" />
                        </div>

                        <div class="options-row">
                            <label class="remember">
                                <input type="checkbox" v-model="form.remember" class="remember-box" />
                                <span>Ingat saya</span>
                            </label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="forgot"
                            >
                                Lupa password?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            class="submit-btn"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="spinner" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round" />
                            </svg>
                            <template v-else>
                                Masuk
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </template>
                        </button>
                    </form>

                    <p class="footer-text">
                        Belum punya akun?
                        <Link :href="route('register')" class="register-link">Daftar Sekarang</Link>
                    </p>
                </div>
            </div>

            <!-- Right: Illustration / Info Panel -->
            <div class="info-side">
                <div class="info-content">
                    <div class="info-badge">✨ Gratis & Tanpa Batas</div>
                    <h2 class="info-title">Ubah Data Mentah<br />Menjadi <span class="info-accent">Insight Bermakna</span></h2>
                    <p class="info-desc">Upload CSV/Excel → AI menganalisis dan menulis narasi bisnis dalam hitungan menit.</p>

                    <div class="info-stats">
                        <div class="stat">
                            <div class="stat-num">11</div>
                            <div class="stat-label">Tipe Analisis</div>
                        </div>
                        <div class="stat">
                            <div class="stat-num">6</div>
                            <div class="stat-label">Tone Narasi</div>
                        </div>
                        <div class="stat">
                            <div class="stat-num">6</div>
                            <div class="stat-label">AI Provider</div>
                        </div>
                    </div>

                    <div class="info-features">
                        <div class="feature-pill">
                            <span class="pill-icon">🧹</span> Auto Cleansing
                        </div>
                        <div class="feature-pill">
                            <span class="pill-icon">🤖</span> Multi-AI Fallback
                        </div>
                        <div class="feature-pill">
                            <span class="pill-icon">📊</span> Interactive Charts
                        </div>
                        <div class="feature-pill">
                            <span class="pill-icon">✍️</span> Narasi Otomatis
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

* { font-family: 'Inter', sans-serif; }

.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafb;
    position: relative;
    overflow: hidden;
    padding: 1rem;
}

/* Subtle background blobs matching landing page */
.bg-decor {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}
.gradient-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.35;
}
.blob-1 {
    width: 500px; height: 500px;
    background: radial-gradient(circle, #ccfbf1, transparent 70%);
    top: -150px; right: -100px;
    animation: drift 12s ease-in-out infinite alternate;
}
.blob-2 {
    width: 400px; height: 400px;
    background: radial-gradient(circle, #dbeafe, transparent 70%);
    bottom: -120px; left: -80px;
    animation: drift 10s ease-in-out infinite alternate-reverse;
}
@keyframes drift {
    0% { transform: translate(0, 0); }
    100% { transform: translate(30px, -20px); }
}

/* Layout Wrapper */
.login-wrapper {
    position: relative;
    z-index: 10;
    display: flex;
    width: 100%;
    max-width: 960px;
    min-height: 560px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 40px rgba(0,0,0,0.06);
    border: 1px solid #e5e7eb;
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.login-wrapper.loaded {
    opacity: 1;
    transform: translateY(0);
}

/* Left: Form Side */
.form-side {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 2.5rem;
}
.form-inner {
    width: 100%;
    max-width: 360px;
}

.brand-link { text-decoration: none; }
.brand-text {
    font-size: 1.5rem;
    font-weight: 800;
    color: #111827;
}
.brand-accent { color: #0d9488; }

.form-title {
    font-size: 1.625rem;
    font-weight: 700;
    color: #111827;
    margin-top: 1.5rem;
    line-height: 1.2;
}
.form-subtitle {
    color: #6b7280;
    font-size: 0.9rem;
    margin-top: 0.375rem;
    margin-bottom: 1.75rem;
    line-height: 1.5;
}

/* Status */
.status-msg {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.875rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    color: #15803d;
    font-size: 0.8125rem;
    margin-bottom: 1.25rem;
}

/* Form */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.125rem;
}

.field-label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.375rem;
}

.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.input-icon {
    position: absolute;
    left: 0.875rem;
    width: 18px;
    height: 18px;
    color: #9ca3af;
    pointer-events: none;
}
.field-input {
    width: 100%;
    padding: 0.75rem 0.875rem 0.75rem 2.75rem;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    color: #111827;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.2s;
}
.field-input::placeholder { color: #9ca3af; }
.field-input:focus {
    border-color: #14b8a6;
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
    background: #fff;
}

.eye-btn {
    position: absolute;
    right: 0.75rem;
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    padding: 4px;
    transition: color 0.2s;
}
.eye-btn:hover { color: #6b7280; }

.field-error { color: #dc2626; font-size: 0.8125rem; margin-top: 0.25rem; }

/* Options */
.options-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.remember {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: #6b7280;
    cursor: pointer;
}
.remember-box {
    width: 16px; height: 16px;
    border-radius: 4px;
    border: 1.5px solid #d1d5db;
    accent-color: #0d9488;
}
.forgot {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #0d9488;
    text-decoration: none;
    transition: color 0.15s;
}
.forgot:hover { color: #0f766e; }

/* Submit */
.submit-btn {
    width: 100%;
    padding: 0.8rem;
    background: #0d9488;
    color: #fff;
    font-size: 0.9375rem;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.25s;
    margin-top: 0.25rem;
}
.submit-btn:hover {
    background: #0f766e;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(13, 148, 136, 0.25);
}
.submit-btn:active { transform: translateY(0); }
.submit-btn:disabled { opacity: 0.6; pointer-events: none; }

.spinner {
    width: 22px; height: 22px;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Footer */
.footer-text {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}
.register-link {
    color: #0d9488;
    font-weight: 600;
    text-decoration: none;
}
.register-link:hover { color: #0f766e; text-decoration: underline; }

/* Right: Info Panel */
.info-side {
    flex: 1;
    background: linear-gradient(145deg, #f0fdfa, #e6fffa, #ecfdf5);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 2.5rem;
    position: relative;
    border-left: 1px solid #e5e7eb;
}
.info-content {
    max-width: 340px;
}

.info-badge {
    display: inline-block;
    padding: 0.375rem 0.875rem;
    background: rgba(13, 148, 136, 0.1);
    color: #0d9488;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 999px;
    margin-bottom: 1.25rem;
}
.info-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    line-height: 1.3;
    margin-bottom: 0.75rem;
}
.info-accent { color: #0d9488; }
.info-desc {
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.info-stats {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.stat { text-align: center; }
.stat-num {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0d9488;
}
.stat-label {
    font-size: 0.6875rem;
    color: #6b7280;
    margin-top: 0.125rem;
    font-weight: 500;
}

.info-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.feature-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.4rem 0.75rem;
    background: rgba(255,255,255,0.7);
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
    color: #374151;
    backdrop-filter: blur(4px);
}
.pill-icon { font-size: 0.875rem; }

/* Responsive */
@media (max-width: 768px) {
    .login-wrapper {
        flex-direction: column;
        max-width: 440px;
    }
    .info-side {
        display: none;
    }
    .form-side {
        padding: 2rem 1.5rem;
    }
}
</style>
