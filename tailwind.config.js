/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/js/**/*.{ts,tsx}',
    './resources/views/**/*.blade.php',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        surface: {
          950: '#0a0a0b',
          900: '#131315',
          800: '#1d1d20',
          700: '#2a2a2f',
        },
      },
    },
  },
  plugins: [],
};
