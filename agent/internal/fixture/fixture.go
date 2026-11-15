// Package fixture reads local fixture files (webhooktrap/fixtures/<provider>/<event type>.json,
// NAME-2) into plan captures. Fixture files are always re-signed at replay (SCR-5).
package fixture

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/plan"
)

// MaxBody is the body cap for a fixture (ADR-010 §6: 5 MiB).
const MaxBody = 5 << 20

// File is the on-disk fixture format.
type File struct {
	Provider     string      `json:"provider"`
	EventType    string      `json:"event_type"`
	CaptureOrder int         `json:"capture_order"`
	Source       string      `json:"source,omitempty"`
	Headers      [][2]string `json:"headers"`
	Signature    string      `json:"signature"` // always "resign"
	BodySHA256   string      `json:"body_sha256"`
	Body         string      `json:"body"`
}

var pathRe = regexp.MustCompile(`^[A-Za-z0-9_./-]{1,200}\.json$`)

// Load reads one fixture. Only regular files are accepted; the body hash must match.
func Load(path string) (plan.Capture, error) {
	clean := filepath.ToSlash(filepath.Clean(path))
	if !pathRe.MatchString(clean) || strings.Contains(clean, "..") {
		return plan.Capture{}, fmt.Errorf("fixture path %q is not allowed", path)
	}
	st, err := os.Lstat(path)
	if err != nil {
		return plan.Capture{}, err
	}
	if !st.Mode().IsRegular() {
		return plan.Capture{}, fmt.Errorf("fixture %s is not a regular file", clean)
	}
	f, err := os.Open(path)
	if err != nil {
		return plan.Capture{}, err
	}
	defer f.Close()
	raw, err := io.ReadAll(io.LimitReader(f, MaxBody*2))
	if err != nil {
		return plan.Capture{}, err
	}
	var fx File
	dec := json.NewDecoder(strings.NewReader(string(raw)))
	dec.DisallowUnknownFields()
	if err := dec.Decode(&fx); err != nil {
		return plan.Capture{}, fmt.Errorf("fixture %s: %w", clean, err)
	}
	if fx.Provider == "" || len(fx.Body) > MaxBody {
		return plan.Capture{}, fmt.Errorf("fixture %s: provider missing or body too large", clean)
	}
	if fx.Signature != "" && fx.Signature != "resign" {
		return plan.Capture{}, fmt.Errorf("fixture %s: signature must be \"resign\"", clean)
	}
	sum := sha256.Sum256([]byte(fx.Body))
	if fx.BodySHA256 != "" && fx.BodySHA256 != hex.EncodeToString(sum[:]) {
		return plan.Capture{}, fmt.Errorf("fixture %s: body_sha256 doesn't match the body", clean)
	}
	return plan.Capture{Ref: clean, Provider: fx.Provider, CaptureOrder: fx.CaptureOrder, Headers: fx.Headers, Body: []byte(fx.Body)}, nil
}

// Discover lists fixture files under dir/<provider>/, sorted.
func Discover(dir, provider string) ([]string, error) {
	entries, err := os.ReadDir(filepath.Join(dir, provider))
	if err != nil {
		return nil, err
	}
	var out []string
	for _, e := range entries {
		if e.Type().IsRegular() && strings.HasSuffix(e.Name(), ".json") {
			out = append(out, filepath.Join(dir, provider, e.Name()))
		}
	}
	sort.Strings(out)
	if len(out) == 0 {
		return nil, errors.New("no fixtures in " + filepath.Join(dir, provider))
	}
	return out, nil
}
