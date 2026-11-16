// Package agentapi is the agent side of the /agent/v1 protocol (contracts.md
// §5, ADR-010 §4–§6): the pinned server origin, challenge-response
// authentication, and one typed call per endpoint. It follows no redirects
// (S-4), requires TLS except for an explicit local development origin, caps
// every response body, and never logs or prints a token.
package agentapi

import (
	"errors"
	"net"
	"net/url"
	"strconv"
	"strings"
)

// ErrInsecureOrigin is returned for http:// outside a local development origin.
var ErrInsecureOrigin = errors.New("the server must use https://; plain http:// is allowed only for a local development origin (localhost, *.localhost, *.test) with --insecure-dev-origin")

// NormalizeOrigin returns the canonical origin (scheme + host + port,
// lowercase, default port omitted; contracts §5.2). allowHTTP permits http://
// only for a development host (DevHost).
func NormalizeOrigin(raw string, allowHTTP bool) (string, error) {
	u, err := url.Parse(strings.TrimSpace(raw))
	if err != nil || u.Host == "" || u.Opaque != "" {
		return "", errors.New("the server must be a URL such as https://app.webhooktrap.dev")
	}
	if u.User != nil || u.RawQuery != "" || u.Fragment != "" || (u.Path != "" && u.Path != "/") {
		return "", errors.New("the server must be an origin only (scheme and host, no path, query or credentials)")
	}
	scheme := strings.ToLower(u.Scheme)
	host := strings.ToLower(u.Hostname())
	if host == "" {
		return "", errors.New("the server URL has no host")
	}
	switch scheme {
	case "https":
	case "http":
		if !allowHTTP || !DevHost(host) {
			return "", ErrInsecureOrigin
		}
	default:
		return "", errors.New("the server must use https://")
	}
	port := u.Port()
	if port != "" {
		n, err := strconv.Atoi(port)
		if err != nil || n < 1 || n > 65535 {
			return "", errors.New("the server port is not valid")
		}
		if (scheme == "https" && n == 443) || (scheme == "http" && n == 80) {
			port = ""
		} else {
			port = strconv.Itoa(n)
		}
	}
	h := host
	if strings.Contains(h, ":") {
		h = "[" + h + "]"
	}
	if port != "" {
		h += ":" + port
	}
	return scheme + "://" + h, nil
}

// DevHost reports whether host is a local development host: localhost,
// *.localhost, *.test or a loopback address.
func DevHost(host string) bool {
	host = strings.TrimSuffix(strings.ToLower(host), ".")
	if host == "localhost" || strings.HasSuffix(host, ".localhost") || strings.HasSuffix(host, ".test") {
		return true
	}
	ip := net.ParseIP(host)
	return ip != nil && ip.IsLoopback()
}

// RevalidateOrigin checks an origin read back from local state: it must
// already be canonical, and http:// only for a development host.
func RevalidateOrigin(origin string) error {
	got, err := NormalizeOrigin(origin, true)
	if err != nil {
		return err
	}
	if got != origin {
		return errors.New("stored server origin is not canonical")
	}
	return nil
}
