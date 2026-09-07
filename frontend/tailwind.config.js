/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f0f4ff',
          100: '#e0e9fe',
          200: '#c7d7fe',
          300: '#a4bdfc',
          400: '#7a9afa',
          500: '#5272f6',
          600: '#3852eb',
          700: '#2b3fd3',
          800: '#2734ab',
          900: '#242f87',
          950: '#171c52',
        }
      }
    },
  },
  plugins: [],
}
