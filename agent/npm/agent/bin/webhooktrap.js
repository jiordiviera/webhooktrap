#!/usr/bin/env node
// Launcher for the webhooktrap engine (ADR-010 "Distribution and supply chain" item 1).
// The binary ships in one platform package per OS and CPU, installed by npm as an
// optional dependency pinned to this package's exact version. Nothing is downloaded
// at install or run time, and there is no postinstall script.
'use strict';

const { execFileSync } = require('node:child_process');
const path = require('node:path');

const SUPPORTED = {
    'linux-x64': true,
    'linux-arm64': true,
    'darwin-x64': true,
    'darwin-arm64': true,
    'win32-x64': true,
    'win32-arm64': true,
};

const target = process.platform + '-' + process.arch;
const pkg = '@webhooktrap/agent-' + target;
const releases = 'https://github.com/jiordiviera/webhooktrap/releases';

function fail(lines) {
    process.stderr.write('webhooktrap: ' + lines.join('\n  ') + '\n');
    process.exit(1);
}

if (!SUPPORTED[target]) {
    fail([
        'no prebuilt binary for ' + target + '.',
        'Supported: linux, darwin and win32 on x64 and arm64.',
        'Other platforms can build from source: ' + 'https://github.com/jiordiviera/webhooktrap/tree/v2/agent',
    ]);
}

let binary;
try {
    const dir = path.dirname(require.resolve(pkg + '/package.json'));
    binary = path.join(dir, 'bin', process.platform === 'win32' ? 'webhooktrap.exe' : 'webhooktrap');
} catch {
    fail([
        'the platform package ' + pkg + ' is not installed.',
        'npm skips it when optional dependencies are turned off (--no-optional, --omit=optional,',
        'or optional=false in .npmrc). Reinstall with optional dependencies:',
        '  npm i -g @webhooktrap/agent --include=optional',
        'or install the binary from ' + releases + ' (agent/install.sh verifies it).',
    ]);
}

// Ctrl-C reaches the whole process group: the engine handles it and exits on its
// own, and this launcher stays alive to pass its exit status through.
process.on('SIGINT', () => {});

try {
    execFileSync(binary, process.argv.slice(2), { stdio: 'inherit', windowsHide: false });
    process.exit(0);
} catch (err) {
    if (err && typeof err.status === 'number') {
        process.exit(err.status);
    }
    if (err && err.signal) {
        process.removeAllListeners('SIGINT');
        process.kill(process.pid, err.signal);
        process.exit(128 + (require('node:os').constants.signals[err.signal] || 0));
    }
    if (err && (err.code === 'ENOENT' || err.code === 'EACCES')) {
        fail([
            'cannot run ' + binary + ' (' + err.code + ').',
            'Reinstall @webhooktrap/agent, or install from ' + releases + '.',
        ]);
    }
    fail([String((err && err.message) || err)]);
}
