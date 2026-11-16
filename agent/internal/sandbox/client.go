package sandbox

import (
	"bufio"
	"context"
	"encoding/json"
	"errors"
	"net"
	"time"
)

// Call sends one request to the sandbox at path and reads its reply line.
// It is the reference client of the protocol (the app's EngineProcess
// adapter speaks the same frames) and the body of `sandbox-ping`.
func Call(ctx context.Context, path string, req Request) (Reply, error) {
	frame, err := json.Marshal(req)
	if err != nil {
		return Reply{}, err
	}
	var d net.Dialer
	c, err := d.DialContext(ctx, "unix", path)
	if err != nil {
		return Reply{}, err
	}
	defer c.Close()
	if dl, ok := ctx.Deadline(); ok {
		_ = c.SetDeadline(dl)
	} else {
		_ = c.SetDeadline(time.Now().Add(CallTimeout + 3*time.Second))
	}
	stop := context.AfterFunc(ctx, func() { _ = c.SetDeadline(time.Unix(1, 0)) })
	defer stop()
	if err := WriteFrame(c, frame); err != nil {
		return Reply{}, err
	}
	if uc, ok := c.(*net.UnixConn); ok {
		_ = uc.CloseWrite()
	}
	line, err := bufio.NewReader(&limited{c, MaxReply + 1}).ReadBytes('\n')
	if err != nil {
		return Reply{}, err
	}
	var r Reply
	if err := json.Unmarshal(line, &r); err != nil {
		return Reply{}, err
	}
	if r.Proto != Proto {
		return Reply{}, errors.New("sandbox answered another protocol version")
	}
	return r, nil
}

// limited caps a reply read.
type limited struct {
	c net.Conn
	n int
}

func (l *limited) Read(p []byte) (int, error) {
	if l.n <= 0 {
		return 0, errors.New("sandbox reply too large")
	}
	if len(p) > l.n {
		p = p[:l.n]
	}
	n, err := l.c.Read(p)
	l.n -= n
	return n, err
}
