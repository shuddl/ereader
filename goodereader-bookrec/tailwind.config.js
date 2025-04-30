/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './goodereader-bookrec.php',
    './includes/**/*.php',
    './admin/views/**/*.php',
    './frontend/src/**/*.{js,jsx,ts,tsx}',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#000000',
          hover: '#333333',
        },
        secondary: {
          DEFAULT: '#ffffff',
          hover: '#f5f5f5',
        },
      },
      fontFamily: {
        sans: [
          'system-ui',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Arial',
          'sans-serif',
        ],
      },
      boxShadow: {
        'chat': '0 2px 10px rgba(0, 0, 0, 0.1)',
      },
    },
  },
  plugins: [],
};