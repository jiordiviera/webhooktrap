import pkg from '@next/env'
import path from 'path'
import { fileURLToPath } from 'url'

const { loadEnvConfig } = pkg

const __dirname = path.dirname(fileURLToPath(import.meta.url))
loadEnvConfig(path.resolve(__dirname, '../../../'))