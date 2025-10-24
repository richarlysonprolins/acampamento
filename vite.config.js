import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import vuetify from 'vite-plugin-vuetify'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), vuetify({ autoImport: true }), vueDevTools()],
  resolve: {
    alias: {
      vue: 'vue/dist/vue.esm-bundler.js',
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: true, // ✅ Permite conexões externas
    port: 5173,
    // REMOVA o proxy - não é mais necessário para produção
  },
  build: {
    outDir: 'dist', // ✅ Pasta de build para Netlify
    assetsDir: 'assets', // ✅ Organiza assets
  },
})
