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
        <!-- Animated background -->
        <div class="bg-animation">
            <div class="floating-orb orb-1"></div>
            <div class="floating-orb orb-2"></div>
            <div class="floating-orb orb-3"></div>
            <div class="floating-orb orb-4"></div>
            <div class="grid-overlay"></div>
        </div>

        <!-- Login Card -->
        <div class="login-container" :class="{ 'loaded': isLoaded }">
            <!-- Brand Header -->
            <div class="brand-section">
                <div class="brand-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="brand-svg">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline>
                        <polyline points="7.5 19.79 7.5 14.6 3 12"></polyline>
                        <polyline points="21 12 16.5 14.6 16.5 19.79"></polyline>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
                <h1 class="brand-title">
                    Data<span class="brand-accent">Narasi</span>
                </h1>
                <p class="brand-subtitle">Analisis Data Cerdas dengan AI</p>
            </div>

            <!-- Status Message -->
            <div v-if="status" class="status-message">
                <svg class="status-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ status }}
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="login-form">
                <!-- Email Field -->
                <div class="form-group">
                    <label for="email" class="form-label">
                        <svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        class="form-input"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nama@email.com"
                    />
                    <InputError class="form-error" :message="form.errors.email" />
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Password
                    </label>
                    <div class="password-wrapper">
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="form-input"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <button type="button" class="password-toggle" @click="showPassword = !showPassword">
                            <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                    <InputError class="form-error" :message="form.errors.password" />
                </div>

                <!-- Remember + Forgot -->
                <div class="form-options">
                    <label class="remember-label">
                        <input type="checkbox" v-model="form.remember" class="remember-checkbox" />
                        <span>Ingat saya</span>
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="forgot-link"
                    >
                        Lupa password?
                    </Link>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="submit-btn"
                    :class="{ 'is-loading': form.processing }"
                    :disabled="form.processing"
                >
                    <svg v-if="form.processing" class="spinner" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round" />
                    </svg>
                    <span v-else>Masuk</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                <p>Belum punya akun? <Link :href="route('register')" class="register-link">Daftar Sekarang</Link></p>
            </div>
        </div>

        <!-- Decorative particles -->
        <div class="particles">
            <span v-for="i in 20" :key="i" class="particle" :style="{
                '--delay': `${Math.random() * 6}s`,
                '--x': `${Math.random() * 100}vw`,
                '--duration': `${4 + Math.random() * 6}s`,
                '--size': `${2 + Math.random() * 4}px`,
            }"></span>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

* {
    font-family: 'Inter', sans-serif;
}

.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0a0e1a;
    position: relative;
    overflow: hidden;
}

/* === Animated Background === */
.bg-animation {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 0;
}

.floating-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.4;
    animation: float 8s ease-in-out infinite;
}

.orb-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #0d9488, #14b8a6);
    top: -100px;
    left: -100px;
    animation-delay: 0s;
}

.orb-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #6366f1, #818cf8);
    bottom: -80px;
    right: -80px;
    animation-delay: 2s;
}

.orb-3 {
    width: 200px;
    height: 200px;
    background: linear-gradient(135deg, #06b6d4, #22d3ee);
    top: 50%;
    right: 20%;
    animation-delay: 4s;
}

.orb-4 {
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, #8b5cf6, #a78bfa);
    bottom: 30%;
    left: 15%;
    animation-delay: 6s;
}

.grid-overlay {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
    background-size: 60px 60px;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -30px) scale(1.05); }
    66% { transform: translate(-20px, 20px) scale(0.95); }
}

/* === Particles === */
.particles {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}

.particle {
    position: absolute;
    width: var(--size);
    height: var(--size);
    background: rgba(20, 184, 166, 0.6);
    border-radius: 50%;
    left: var(--x);
    bottom: -10px;
    animation: rise var(--duration) var(--delay) linear infinite;
}

@keyframes rise {
    0% { transform: translateY(0) scale(1); opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { transform: translateY(-100vh) scale(0); opacity: 0; }
}

/* === Login Container === */
.login-container {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 420px;
    margin: 1rem;
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow:
        0 0 0 1px rgba(255,255,255,0.05),
        0 20px 50px rgba(0, 0, 0, 0.5),
        0 0 100px rgba(20, 184, 166, 0.05);
    opacity: 0;
    transform: translateY(30px) scale(0.95);
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.login-container.loaded {
    opacity: 1;
    transform: translateY(0) scale(1);
}

/* === Brand Section === */
.brand-section {
    text-align: center;
    margin-bottom: 2rem;
}

.brand-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: linear-gradient(135deg, #0d9488, #14b8a6);
    margin-bottom: 1rem;
    animation: pulse-glow 3s ease-in-out infinite;
}

.brand-svg {
    width: 32px;
    height: 32px;
    color: white;
}

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 20px rgba(20, 184, 166, 0.3); }
    50% { box-shadow: 0 0 40px rgba(20, 184, 166, 0.5), 0 0 60px rgba(20, 184, 166, 0.2); }
}

.brand-title {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    letter-spacing: -0.02em;
}

.brand-accent {
    background: linear-gradient(135deg, #14b8a6, #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.brand-subtitle {
    color: #64748b;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    font-weight: 400;
}

/* === Status Message === */
.status-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 12px;
    color: #34d399;
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
}

.status-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

/* === Form === */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.8125rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.label-icon {
    width: 16px;
    height: 16px;
    opacity: 0.6;
}

.form-input {
    width: 100%;
    padding: 0.875rem 1rem;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(100, 116, 139, 0.2);
    border-radius: 12px;
    color: #f1f5f9;
    font-size: 0.9375rem;
    outline: none;
    transition: all 0.3s ease;
}

.form-input::placeholder {
    color: #475569;
}

.form-input:focus {
    border-color: #14b8a6;
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
    background: rgba(30, 41, 59, 0.8);
}

.form-error {
    color: #f87171;
    font-size: 0.8125rem;
}

/* === Password === */
.password-wrapper {
    position: relative;
}

.password-wrapper .form-input {
    padding-right: 3rem;
}

.password-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #64748b;
    padding: 0.25rem;
    transition: color 0.2s;
}

.password-toggle:hover {
    color: #94a3b8;
}

.password-toggle svg {
    width: 20px;
    height: 20px;
}

/* === Options Row === */
.form-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.remember-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.8125rem;
    cursor: pointer;
}

.remember-checkbox {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 1px solid rgba(100, 116, 139, 0.3);
    background: rgba(30, 41, 59, 0.6);
    accent-color: #14b8a6;
}

.forgot-link {
    color: #14b8a6;
    font-size: 0.8125rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s;
}

.forgot-link:hover {
    color: #2dd4bf;
}

/* === Submit Button === */
.submit-btn {
    width: 100%;
    padding: 0.875rem;
    background: linear-gradient(135deg, #0d9488, #14b8a6);
    color: white;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    margin-top: 0.5rem;
}

.submit-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.submit-btn:hover::before {
    transform: translateX(100%);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(20, 184, 166, 0.3);
}

.submit-btn:active {
    transform: translateY(0);
}

.submit-btn.is-loading {
    pointer-events: none;
    opacity: 0.7;
}

.spinner {
    width: 24px;
    height: 24px;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* === Footer === */
.login-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    color: #64748b;
    font-size: 0.875rem;
}

.register-link {
    color: #14b8a6;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s;
}

.register-link:hover {
    color: #2dd4bf;
}

/* === Responsive === */
@media (max-width: 480px) {
    .login-container {
        padding: 1.5rem;
        border-radius: 20px;
    }

    .brand-title {
        font-size: 1.75rem;
    }
}
</style>
