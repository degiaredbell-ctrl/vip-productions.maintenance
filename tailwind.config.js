import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    // Nama kelas yang dirakit dinamis di JS (mis. `neu-dot-${warna}`)
    // tidak terlihat oleh scanner Tailwind, jadi harus didaftarkan manual.
    safelist: [
        'neu-dot-green',
        'neu-dot-blue',
        'neu-dot-orange',
        'neu-dot-red',
        'neu-dot-muted',
        'neu-dot-live',
    ],

    theme: {
        extend: {
            colors: {
                neu: {
                    bg: '#E8ECF1',
                    light: '#FFFFFF',
                    dark: '#C3CAD6',
                    text: '#2B3445',
                    sub: '#5A6475',
                    accent: '#0F6E56',
                    info: '#1F6FEB',
                    warn: '#A86B0B',
                    bad: '#B93A2E',
                },
            },
            boxShadow: {
                'neu-up': '6px 6px 14px #C3CAD6, -6px -6px 14px #FFFFFF',
                'neu-up-sm': '3px 3px 8px #C3CAD6, -3px -3px 8px #FFFFFF',
                'neu-in': 'inset 4px 4px 9px #C3CAD6, inset -4px -4px 9px #FFFFFF',
            },
            borderRadius: {
                neu: '22px',
                'neu-sm': '14px',
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
            },
        },
    },

    plugins: [forms],
};
