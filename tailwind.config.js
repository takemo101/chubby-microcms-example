/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.latte',
  ],
  theme: {
    extend: {
      typography: {
        DEFAULT: {
          css: {
            maxWidth: '100%', // add required value here
          }
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
}
