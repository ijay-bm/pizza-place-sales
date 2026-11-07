import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), vueDevTools()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  // The dev server runs in the node container behind nginx: it listens on every
  // interface so nginx can reach it, and the browser opens the hot module
  // replacement websocket on nginx's port, HTTP_PORT, not on 5173.
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    hmr: {
      clientPort: Number(process.env.HTTP_PORT ?? 5173),
    },
  },
})
