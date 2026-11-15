import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
                '**/spikes/**',
                '**/.worktrees/**',
                '**/project/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            // Planning record, live docs site, Go engine and local-only folders are not frontend code.
            'project/**',
            'docs/**',
            '.docs/**',
            'agent/**',
            'spikes/**',
            '.worktrees/**',
            '.playwright-mcp/**',
            'storage/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            // Compose files mirror project/operations/compose-profile.md line for line.
            'compose*.yaml',
            // lerd rewrites this file with its own indentation on every worker restart.
            '.lerd.yaml',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            'vendor/**',
            'public/**',
            // Planning record, live docs site, Go engine and local-only folders are not frontend code.
            'project/**',
            'docs/**',
            '.docs/**',
            'agent/**',
            'spikes/**',
            '.worktrees/**',
            '.playwright-mcp/**',
            'storage/**',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
