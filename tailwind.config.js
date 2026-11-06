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
                panel: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ny: {
                    brand: '#064E3B',
                    interaction: '#087F5B',
                    deep: '#0F6E56',
                    menu: '#062C21',
                    canvas: '#F6F8F7',
                    card: '#FFFFFF',
                    line: '#E6ECE9',
                    ink: '#10231D',
                    muted: '#607069',
                    action: '#F4B820',
                    danger: '#9F1239',
                    warning: '#92400E',
                    group: {
                        home: '#BA7517',
                        company: '#185FA5',
                        channels: '#D85A30',
                        finance: '#3B6D11',
                        operation: '#0F6E56',
                        users: '#534AB7',
                        content: '#993556',
                        help: '#5F5E5A',
                        marketplace: '#534AB7',
                    },
                },
            },
            borderRadius: {
                control: '8px',
                card: '12px',
            },
            fontSize: {
                'ny-xs': '12.5px',
                'ny-sm': '14px',
                'ny-md': '16px',
                'ny-lg': '20px',
                'ny-xl': '28px',
            },
        },
    },

    plugins: [forms],
};
