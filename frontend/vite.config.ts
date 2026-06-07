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
      // Dev only: forward API + health to the PHP backend.
      //
      // The SPA's client routes live under /admin/* — the same prefix as the
      // API — so a plain proxy would also forward browser *document* reloads of
      // e.g. /admin/reconciliation to PHP, which answers with the built
      // index.html whose hashed asset URLs don't exist on the dev server → blank
      // page. The `bypass` mirrors the prod SPA fallback in public_html/index.php:
      // GET navigations that explicitly want text/html (and not application/json)
      // are served the dev index.html so the SPA boots in place (and #135's
      // expired-session login renders at the same URL). Real API calls
      // (Accept: application/json) and CSV downloads (Accept: */*) still proxy.
      proxy: {
        '/admin': {
          target,
          changeOrigin: true,
          bypass(req) {
            const accept = req.headers.accept ?? ''
            if (req.method === 'GET' && accept.includes('text/html') && !accept.includes('application/json')) {
              return '/index.html'
            }
          },
        },
        '/health': { target, changeOrigin: true },
      },
    },
    // `vite preview` (used by the E2E suite) must NOT proxy: there is no PHP
    // backend, all API calls are mocked, and /admin/* document navigations rely
    // on the SPA history fallback. An explicit empty proxy stops preview from
    // inheriting server.proxy (behaviour differs across Vite/CI environments).
    preview: {
      proxy: {},
    },
    build: {
      outDir: '../public_html/assets',
      emptyOutDir: true,
    },
  }
})
