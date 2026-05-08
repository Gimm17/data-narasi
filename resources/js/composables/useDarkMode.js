import { ref, watch, onMounted } from 'vue';

const isDark = ref(false);

function applyTheme(dark) {
    if (dark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

export function useDarkMode() {
    onMounted(() => {
        // Read from localStorage, fallback to system preference
        const stored = localStorage.getItem('dn-theme');
        if (stored === 'dark') {
            isDark.value = true;
        } else if (stored === 'light') {
            isDark.value = false;
        } else {
            isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        applyTheme(isDark.value);
    });

    watch(isDark, (val) => {
        localStorage.setItem('dn-theme', val ? 'dark' : 'light');
        applyTheme(val);
    });

    const toggle = () => {
        isDark.value = !isDark.value;
    };

    return { isDark, toggle };
}
