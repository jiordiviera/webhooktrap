# Egress proxy used ONLY during the setup phase (clone + package install).
# Trusted base image; contains no third-party repository code.
FROM alpine:3.20
RUN apk add --no-cache tinyproxy && adduser -D -u 10002 proxy2
COPY tinyproxy.conf /etc/tinyproxy/tinyproxy.conf
COPY allowlist /etc/tinyproxy/allowlist
USER 10002
EXPOSE 8888
CMD ["tinyproxy", "-d", "-c", "/etc/tinyproxy/tinyproxy.conf"]
LABEL wt.proxyrun="1"
