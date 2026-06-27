export const apps = [
    {
        name: 'hookscope-api',
        script: 'build/bin/server.js',
        cwd: __dirname,
        instances: 1,
        exec_mode: 'fork',
        env: {
            NODE_ENV: 'production',
            PORT: 3333,
            HOST: '0.0.0.0',
        },
        error_file: '/var/log/hookscope/api-error.log',
        out_file: '/var/log/hookscope/api-out.log',
        merge_logs: true,
        log_date_format: 'YYYY-MM-DD HH:mm:ss',
        max_restarts: 10,
        restart_delay: 5000,
        shutdown_with_message: true,
        kill_timeout: 10000,
        listen_timeout: 8000,
    },
];
