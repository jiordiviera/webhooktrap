set -euo pipefail
cd /work
if [ ! -d src ]; then git clone --depth 1 --single-branch "https://github.com/$REPO.git" src; fi
cd src
echo "commit: $(git rev-parse HEAD)"
