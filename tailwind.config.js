import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ny: {
                    brand: '#064E3B',
                    interaction: '#087F5B',
                    canvas: '#F7F9F8',
                    card: '#FFFFFF',
                    ink: '#10231D',
                    muted: '#607069',
                    action: '#F4B820',
                    danger: '#9F1239',
                    warning: '#92400E',
                },
            },
            borderRadius: {
                control: '8px',
                card: '12px',
            },
            fontSize: {
                'ny-xs': '12px',
                'ny-sm': '14px',
                'ny-md': '16px',
                'ny-lg': '20px',
                'ny-xl': '28px',
            },
        },
    },

    plugins: [forms],
};
