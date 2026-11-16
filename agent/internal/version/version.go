// Package version holds build metadata, set at link time:
//
//	go build -ldflags "-X github.com/jiordiviera/webhooktrap/agent/internal/version.Version=1.2.3 \
//	  -X github.com/jiordiviera/webhooktrap/agent/internal/version.Commit=$GITHUB_SHA"
//
// Version is bare semver, never "v1.2.3": the server compares it with its
// min_agent_version, and a leading "v" sorts below every release (refused as
// too old). Release builds come from agent/release/build.sh.
package version

import (
	"encoding/json"
	"runtime"
)

// Set by -ldflags at release time. Defaults identify a development build.
var (
	Version = "0.0.0-dev"
	Commit  = "unknown"
)

// ProtocolVersion is the agent protocol major version (ADR-010 §8, contracts/agent/v1).
const ProtocolVersion = "v1"

// Info is the machine-readable form printed by `webhooktrap version --json`.
type Info struct {
	Version  string `json:"version"`
	Commit   string `json:"commit"`
	Protocol string `json:"protocol"`
	Go       string `json:"go"`
	OS       string `json:"os"`
	Arch     string `json:"arch"`
}

// Current returns the build information of this binary.
func Current() Info {
	return Info{
		Version:  Version,
		Commit:   Commit,
		Protocol: ProtocolVersion,
		Go:       runtime.Version(),
		OS:       runtime.GOOS,
		Arch:     runtime.GOARCH,
	}
}

// String is the one-line human form.
func (i Info) String() string {
	return "webhooktrap " + i.Version + " (" + i.Commit + ", protocol " + i.Protocol + ", " + i.OS + "/" + i.Arch + ")"
}

// JSON is the machine form.
func (i Info) JSON() ([]byte, error) {
	return json.Marshal(i)
}
