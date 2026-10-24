/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/Views/**/*.php",
    "./public/js/**/*.js",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#0061ff',
          dark: '#0056e0',
        },
        surface: 'rgba(255, 255, 255, 0.75)',
      },
      borderRadius: {
        '2xl': '1.25rem',
        '3xl': '1.75rem',
        '4xl': '2.25rem',
      },
      fontFamily: {
        sans: ['Outfit', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
