import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

export default defineConfig(({ mode }) => {
  // Load env from project root (one level up from frontend/) so .env values
  // are available without duplicating them in frontend/.env.
  const projectEnv = loadEnv(mode, resolve(__dirname, '..'), '')

  const backendPort = projectEnv['NENE_CLEAR_PORT'] ?? '8080'
  const target = `http://localhost:${backendPort}`

  // Fixed dev-server port for nene-clear: 5380.
  // Override with NENE_CLEAR_FRONTEND_PORT in the project-root .env to avoid
  // conflicts if another project already uses 5380 on this machine.
  const frontendPort = parseInt(projectEnv['NENE_CLEAR_FRONTEND_PORT'] ?? '5380', 10)

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: { '@': resolve(__dirname, 'src') },
    },
    server: {
      port: frontendPort,
      strictPort: true, // fail fast instead of silently bumping to next port
      proxy: {
        '/admin': { target, changeOrigin: true },
        '/health': { target, changeOrigin: true },
      },
    },
    build: {
      outDir: '../public_html/assets',
      emptyOutDir: true,
    },
  }
})
