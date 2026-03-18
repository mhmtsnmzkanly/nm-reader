/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "../storage/views/**/*.php",
    "../public/assets/js/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        'surface': '#121212',
        'background': '#080808',
      }
    },
  },
  plugins: [],
}
