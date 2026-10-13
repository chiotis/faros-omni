/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./system/Views/**/*.php', './system/Models/**/*.php', './system/Core/helpers.php', './public/assets/js/**/*.js'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
