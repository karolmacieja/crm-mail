/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{vue,js,html}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Google Sans"', 'Roboto', 'Arial', 'sans-serif'],
      },
      // GastroFlowx palette (from the HTML/Tailwind mockup).
      colors: {
        primary: { DEFAULT: '#4f46e5', hover: '#4338ca' },
        sidebar: { DEFAULT: '#0f172a', active: '#1e293b' },
        gastro: '#f97316',
      },
    },
  },
  plugins: [],
}
