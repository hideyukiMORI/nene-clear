import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

const backendPort = process.env.NENE_CLEAR_PORT ?? '8080'
const target = `http://localhost:${backendPort}`

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: { '@': resolve(__dirname, 'src') },
  },
  server: {
    port: 5173,
    proxy: {
      '/admin': { target, changeOrigin: true },
      '/health': { target, changeOrigin: true },
    },
  },
  build: {
    outDir: '../public_html/assets',
    emptyOutDir: true,
  },
})
