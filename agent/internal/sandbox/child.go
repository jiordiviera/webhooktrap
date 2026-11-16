package sandbox

import (
	"io"
	"os"
	"runtime/debug"
	"runtime/metrics"
	"sync"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/pack"
)

// RunChild is the body of `webhooktrap sandbox-call`: one frame on stdin
// (without its length prefix), one reply line on stdout, then exit. The
// process holds the frame, and a verify call's secret, until it exits.
func RunChild(stdin io.Reader, stdout io.Writer, src pack.Source) int {
	debug.SetMemoryLimit(childSoftLimit)
	var once sync.Once
	write := func(r Reply) { once.Do(func() { _, _ = stdout.Write(encodeReply(r)) }) }

	stop := make(chan struct{})
	defer close(stop)
	go watchMemory(stop, func() {
		write(errorReply("", CodeMemoryLimit))
		os.Exit(3)
	})

	frame, err := io.ReadAll(io.LimitReader(stdin, MaxFrame+1))
	if err != nil || len(frame) == 0 {
		write(errorReply("", CodeBadFrame))
		return 1
	}
	if len(frame) > MaxFrame {
		write(errorReply("", CodeFrameTooLarge))
		return 1
	}
	r := Handle(frame, src)
	clear(frame)
	write(r)
	return 0
}

// watchMemory ends the call when the live heap passes ChildMemoryLimit
// (ENG-2: memory cap per call, independent of the container's).
func watchMemory(stop <-chan struct{}, over func()) {
	s := []metrics.Sample{{Name: "/memory/classes/heap/objects:bytes"}}
	t := time.NewTicker(10 * time.Millisecond)
	defer t.Stop()
	for {
		select {
		case <-stop:
			return
		case <-t.C:
		}
		metrics.Read(s)
		if s[0].Value.Kind() == metrics.KindUint64 && s[0].Value.Uint64() > ChildMemoryLimit {
			over()
			return
		}
	}
}
