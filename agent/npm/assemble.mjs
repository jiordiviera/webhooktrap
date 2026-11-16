#!/usr/bin/env node
// Assembles the npm packages of a release (project/operations/agent-release.md):
//   @webhooktrap/agent                     launcher, from agent/npm/agent/
//   @webhooktrap/agent-<os>-<cpu>          one per target, generated here, binary under bin/
//
//   node agent/npm/assemble.mjs --version 1.2.3 --bin-dir DIR --out OUT
//
// DIR holds <goos>-<goarch>/webhooktrap[.exe] for all six targets (extracted from the
// checksummed release archives). OUT receives node_modules/@webhooktrap/<name>/, so the
// launcher in OUT resolves its sibling platform package exactly as after `npm install`.
// Every version and optionalDependencies pin is set to --version; nothing else varies.
import { chmodSync, copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');

// [goos, goarch, npm os, npm cpu]
export const TARGETS = [
    ['linux', 'amd64', 'linux', 'x64'],
    ['linux', 'arm64', 'linux', 'arm64'],
    ['darwin', 'amd64', 'darwin', 'x64'],
    ['darwin', 'arm64', 'darwin', 'arm64'],
    ['windows', 'amd64', 'win32', 'x64'],
    ['windows', 'arm64', 'win32', 'arm64'],
];

function arg(name) {
    const i = process.argv.indexOf('--' + name);
    if (i < 0 || !process.argv[i + 1]) {
        console.error('usage: assemble.mjs --version X.Y.Z --bin-dir DIR --out OUT');
        process.exit(2);
    }
    return process.argv[i + 1];
}

const version = arg('version');
const binDir = path.resolve(arg('bin-dir'));
const out = path.resolve(arg('out'));

if (!/^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$/.test(version)) {
    console.error(`assemble: version must be bare semver such as 1.2.3 (got '${version}')`);
    process.exit(2);
}

const main = JSON.parse(readFileSync(path.join(here, 'agent', 'package.json'), 'utf8'));
const scope = path.join(out, 'node_modules', '@webhooktrap');
rmSync(scope, { recursive: true, force: true });
mkdirSync(scope, { recursive: true });

const write = (file, data) => writeFileSync(file, JSON.stringify(data, null, 2) + '\n');
const optional = {};

for (const [goos, goarch, os, cpu] of TARGETS) {
    const name = `@webhooktrap/agent-${os}-${cpu}`;
    const exe = os === 'win32' ? 'webhooktrap.exe' : 'webhooktrap';
    const src = path.join(binDir, `${goos}-${goarch}`, exe);
    if (!existsSync(src)) {
        console.error(`assemble: missing binary ${src}`);
        process.exit(1);
    }
    const dir = path.join(scope, `agent-${os}-${cpu}`);
    mkdirSync(path.join(dir, 'bin'), { recursive: true });
    copyFileSync(src, path.join(dir, 'bin', exe));
    chmodSync(path.join(dir, 'bin', exe), 0o755);
    copyFileSync(path.join(root, 'LICENSE'), path.join(dir, 'LICENSE'));
    writeFileSync(
        path.join(dir, 'README.md'),
        `# ${name}\n\nThe \`webhooktrap\` binary for ${os} ${cpu}. Install [\`@webhooktrap/agent\`](https://www.npmjs.com/package/@webhooktrap/agent) instead: it picks this package for you.\n`,
    );
    write(path.join(dir, 'package.json'), {
        name,
        version,
        description: `The webhooktrap binary for ${os} ${cpu} (used by @webhooktrap/agent).`,
        homepage: main.homepage,
        bugs: main.bugs,
        repository: { ...main.repository, directory: 'agent/npm' },
        license: main.license,
        os: [os],
        cpu: [cpu],
        files: [`bin/${exe}`, 'LICENSE', 'README.md'],
        preferUnplugged: true,
        publishConfig: main.publishConfig,
    });
    optional[name] = version;
}

const launcher = path.join(scope, 'agent');
mkdirSync(path.join(launcher, 'bin'), { recursive: true });
copyFileSync(path.join(here, 'agent', 'bin', 'webhooktrap.js'), path.join(launcher, 'bin', 'webhooktrap.js'));
chmodSync(path.join(launcher, 'bin', 'webhooktrap.js'), 0o755);
copyFileSync(path.join(here, 'agent', 'README.md'), path.join(launcher, 'README.md'));
copyFileSync(path.join(root, 'LICENSE'), path.join(launcher, 'LICENSE'));
const pinned = Object.fromEntries(Object.keys(main.optionalDependencies).sort().map((k) => [k, version]));
if (JSON.stringify(Object.keys(pinned)) !== JSON.stringify(Object.keys(optional).sort())) {
    console.error('assemble: agent/npm/agent/package.json optionalDependencies do not match the target table');
    process.exit(1);
}
write(path.join(launcher, 'package.json'), { ...main, version, optionalDependencies: pinned });

// Publish order: platform packages first, the launcher last.
const order = [...Object.keys(optional).map((n) => n.replace('@webhooktrap/', '')), 'agent'];
writeFileSync(path.join(out, 'publish-order.txt'), order.join('\n') + '\n');
console.log(`assembled ${order.length} packages at ${version} in ${scope}`);
