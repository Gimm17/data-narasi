import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                cream: {
                    50: '#FAF6F0',
                    100: '#F2EBE0',
                    200: '#EBD5AB',
                    300: '#DFD0B8',
                    400: '#D2C1B6',
                    500: '#C4B09A',
                    600: '#A89279',
                    700: '#8C7660',
                    800: '#705C49',
                    900: '#584838',
                },
            },
        },
    },

    plugins: [forms],
};
