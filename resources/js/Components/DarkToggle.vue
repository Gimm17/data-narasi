<script setup>
import { useDarkMode } from '@/composables/useDarkMode';

const { isDark, toggle } = useDarkMode();
</script>

<template>
    <button
        @click="toggle"
        class="dark-toggle"
        :class="{ 'dark-toggle--active': isDark }"
        :aria-label="isDark ? 'Beralih ke mode terang' : 'Beralih ke mode gelap'"
        role="switch"
        :aria-checked="isDark"
        type="button"
    >
        <!-- Track -->
        <span class="dark-toggle__track">
            <!-- Stars (visible in dark) -->
            <span class="dark-toggle__stars">
                <span class="dark-toggle__star" style="top: 5px; left: 6px; animation-delay: 0s;"></span>
                <span class="dark-toggle__star" style="top: 12px; left: 14px; animation-delay: 0.3s;"></span>
                <span class="dark-toggle__star" style="top: 6px; left: 22px; animation-delay: 0.6s;"></span>
            </span>

            <!-- Clouds (visible in light) -->
            <span class="dark-toggle__clouds">
                <span class="dark-toggle__cloud" style="right: 6px; top: 8px;"></span>
                <span class="dark-toggle__cloud dark-toggle__cloud--sm" style="right: 14px; top: 14px;"></span>
            </span>
        </span>

        <!-- Thumb (sun/moon) -->
        <span class="dark-toggle__thumb">
            <!-- Sun rays -->
            <span class="dark-toggle__rays">
                <span class="dark-toggle__ray" v-for="i in 6" :key="i"
                    :style="{ transform: `rotate(${i * 60}deg)` }"
                ></span>
            </span>
            <!-- Moon crater -->
            <span class="dark-toggle__crater dark-toggle__crater--1"></span>
            <span class="dark-toggle__crater dark-toggle__crater--2"></span>
        </span>
    </button>
</template>

<style scoped>
.dark-toggle {
    position: relative;
    width: 56px;
    height: 28px;
    border-radius: 999px;
    border: none;
    padding: 0;
    cursor: pointer;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}

.dark-toggle:focus-visible {
    box-shadow: 0 0 0 2px white, 0 0 0 4px #0d9488;
}

/* Track */
.dark-toggle__track {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    background: linear-gradient(135deg, #87CEEB 0%, #E0F0FF 100%);
    overflow: hidden;
    transition: background 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.dark-toggle--active .dark-toggle__track {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}

/* Stars */
.dark-toggle__stars {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.4s ease 0.1s;
}

.dark-toggle--active .dark-toggle__stars {
    opacity: 1;
}

.dark-toggle__star {
    position: absolute;
    width: 3px;
    height: 3px;
    background: #fde68a;
    border-radius: 50%;
    animation: twinkle 2s ease-in-out infinite;
}

@keyframes twinkle {
    0%, 100% { opacity: 0.3; transform: scale(0.8); }
    50% { opacity: 1; transform: scale(1.2); }
}

/* Clouds */
.dark-toggle__clouds {
    position: absolute;
    inset: 0;
    opacity: 1;
    transition: opacity 0.3s ease;
}

.dark-toggle--active .dark-toggle__clouds {
    opacity: 0;
}

.dark-toggle__cloud {
    position: absolute;
    width: 14px;
    height: 8px;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 999px;
}

.dark-toggle__cloud--sm {
    width: 10px;
    height: 6px;
    opacity: 0.6;
}

/* Thumb */
.dark-toggle__thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15), inset 0 -1px 2px rgba(0,0,0,0.1);
    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.dark-toggle--active .dark-toggle__thumb {
    transform: translateX(28px);
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3), inset 0 -1px 2px rgba(0,0,0,0.15);
}

/* Sun rays */
.dark-toggle__rays {
    position: absolute;
    inset: -3px;
    transition: all 0.4s ease;
}

.dark-toggle--active .dark-toggle__rays {
    opacity: 0;
    transform: scale(0) rotate(90deg);
}

.dark-toggle__ray {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 2px;
    height: 4px;
    margin-left: -1px;
    margin-top: -14px;
    background: #fbbf24;
    border-radius: 999px;
    transform-origin: 1px 14px;
}

/* Moon craters */
.dark-toggle__crater {
    position: absolute;
    background: rgba(148, 163, 184, 0.4);
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.3s ease 0.15s;
}

.dark-toggle--active .dark-toggle__crater {
    opacity: 1;
}

.dark-toggle__crater--1 {
    width: 6px;
    height: 6px;
    top: 4px;
    right: 4px;
}

.dark-toggle__crater--2 {
    width: 4px;
    height: 4px;
    bottom: 5px;
    left: 5px;
}

/* Hover animation */
.dark-toggle:hover .dark-toggle__thumb {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    .dark-toggle__thumb,
    .dark-toggle__track,
    .dark-toggle__rays,
    .dark-toggle__stars,
    .dark-toggle__clouds,
    .dark-toggle__crater {
        transition-duration: 0.01ms !important;
    }
    .dark-toggle__star {
        animation: none !important;
    }
}
</style>
