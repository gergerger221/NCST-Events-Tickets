import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import basicSsl from '@vitejs/plugin-basic-ssl'
import { stateSyncPlugin } from './scripts/devStateSyncPlugin.ts'

const enableHttps = process.argv.includes('--https') || process.env.HTTPS === 'true'

// https://vite.dev/config/
export default defineConfig({
  base: './',
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: false,
    allowedHosts: true,
    watch: {
      ignored: ['**/*.json'],
    },
  },
  plugins: [
    vue(),
    tailwindcss(),
    ...(enableHttps ? [basicSsl()] : []),
    stateSyncPlugin(),
  ],
})

