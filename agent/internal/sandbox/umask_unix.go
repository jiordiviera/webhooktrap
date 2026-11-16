//go:build unix

package sandbox

import (
	"net"
	"syscall"
)

// listenUnix creates the socket with mode 0660 from the start: no window in
// which another user could connect (ADR-010 §1 rule 1).
func listenUnix(path string) (net.Listener, error) {
	old := syscall.Umask(0o117)
	defer syscall.Umask(old)
	return net.Listen("unix", path)
}
