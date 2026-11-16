//go:build !unix

package sandbox

import (
	"errors"
	"net"
)

// listenUnix: the sandbox service runs on Linux containers only.
func listenUnix(string) (net.Listener, error) {
	return nil, errors.New("serve-sandbox needs a Unix system")
}
