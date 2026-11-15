v=$(node -e 'fetch("http://127.0.0.1:4510/__orders").then(r=>r.text()).then(t=>console.log(t)).catch(()=>process.exit(1))') || exit 1
echo "$(date -u +%H:%M:%S.%N | cut -c1-12) $v" >> /wt/probe-readings.log; echo "$v"
