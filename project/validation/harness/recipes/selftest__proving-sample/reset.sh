node -e 'fetch("http://127.0.0.1:4510/__reset",{method:"POST"}).then(r=>r.text()).catch(()=>process.exit(1))' && echo "$(date -u +%H:%M:%S) reset" >> /wt/probe-readings.log
