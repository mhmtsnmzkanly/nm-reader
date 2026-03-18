module.exports = {
  content: [
    './src/**/*.f7',
    './src/**/*.js',
    './src/**/*.css',
    './src/index.html',
  ],
  theme: {
    extend: {
      colors: {
        nmr: {
          bg: '#0a0b10',
          surface: 'rgba(255,255,255,0.06)',
          border: 'rgba(255,255,255,0.12)',
          accent: '#8b5cf6',
          accent2: '#22d3ee',
          gold: '#f2c76e',
        },
      },
      fontFamily: {
        display: ['"Playfair Display"', 'serif'],
        body: ['"Manrope"', 'sans-serif'],
      },
      boxShadow: {
        nmr: '0 20px 60px rgba(0, 0, 0, 0.45)',
      },
      keyframes: {
        'page-fade': {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'card-rise': {
          '0%': { opacity: '0', transform: 'translateY(12px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        'page-fade': 'page-fade 420ms ease-out',
        'card-rise': 'card-rise 420ms ease-out',
      },
    },
  },
  plugins: [],
};
