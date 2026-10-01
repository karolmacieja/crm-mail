import tailwindcss from 'tailwindcss'
import autoprefixer from 'autoprefixer'

/**
 * Tailwind sizes are rem-based, i.e. relative to the *host page's* <html>
 * font-size. Inside Gmail we don't control that, so convert rem -> px to
 * make the injected UI render identically everywhere.
 */
function remToPx({ rootValue = 16 } = {}) {
  return {
    postcssPlugin: 'rem-to-px',
    Declaration(decl) {
      if (decl.value.includes('rem')) {
        decl.value = decl.value.replace(/(-?\d*\.?\d+)rem\b/g, (_, n) => `${parseFloat(n) * rootValue}px`)
      }
    },
  }
}
remToPx.postcss = true

export default {
  plugins: [tailwindcss(), autoprefixer(), remToPx()],
}
