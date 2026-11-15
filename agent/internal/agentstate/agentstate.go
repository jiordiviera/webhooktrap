// Package agentstate keeps the agent's local identity per server origin
// (ADR-010 §4 rule 3): an Ed25519 key in a 0600 file inside a 0700 directory
// under the OS config directory, the agent id and pinned origin, and the
// remote-target approvals (§6.3). A key file readable by others is refused.
// Tokens are never stored.
//
// Layout: <config dir>/webhooktrap/agents/<origin dir>/{agent.json,key,approvals.json},
// where <origin dir> is a readable slug of the origin plus a hash of the full
// origin, so two origins never share a directory (AC-13).
//
// On Windows the mode bits are not meaningful and the key file's access list
// is not checked (the standard library has no ACL API); PlatformWarning says
// so. The OS keychain (macOS Keychain, Secret Service, Windows Credential
// Manager) is a later proposal: it needs a module or cgo, and the key is
// origin-bound, which limits a stolen file to one server (AC-13, accepted
// MVP deviation pending that proposal).
package agentstate

import (
	"crypto/ed25519"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"runtime"
	"sort"
	"strings"
)

// Store is the root directory of the agent's local state.
type Store struct{ Dir string }

// EnvDir overrides the state directory (tests, portable installs).
const EnvDir = "WEBHOOKTRAP_CONFIG_DIR"

// Default returns the store under the OS config directory, or $WEBHOOKTRAP_CONFIG_DIR.
func Default(getenv func(string) string) (*Store, error) {
	if d := getenv(EnvDir); d != "" {
		return &Store{Dir: d}, nil
	}
	base, err := os.UserConfigDir()
	if err != nil {
		return nil, err
	}
	return &Store{Dir: filepath.Join(base, "webhooktrap")}, nil
}

// Agent is what the agent remembers about one pairing.
type Agent struct {
	Origin         string `json:"origin"`
	AgentID        string `json:"agent_id"`
	KeyPath        string `json:"key_path"`
	Name           string `json:"name"`
	WorkspaceLabel string `json:"workspace_label"`
	Guest          bool   `json:"guest"`
	Confirmed      bool   `json:"confirmed"`
	PairedAt       string `json:"paired_at"`
	// ServerKind is pair's server.kind ("hosted" | "selfhost"): a plain
	// http:// origin is refused for a hosted server.
	ServerKind string `json:"server_kind,omitempty"`
}

// Approval is one remote-target approval keyed on (origin, agent, scheme,
// host, port); the agent id stands for the workspace it joined. It includes
// the NAME-4 side-effect acknowledgement.
type Approval struct {
	AgentID    string `json:"agent_id"`
	Scheme     string `json:"scheme"`
	Host       string `json:"host"`
	Port       int    `json:"port"`
	Path       string `json:"path"`
	ApprovedAt string `json:"approved_at"`
	SideEffect bool   `json:"side_effects_acknowledged"`
}

var slugRe = regexp.MustCompile(`[^a-z0-9.-]+`)

// Slug is the directory name for an origin: a readable slug (at most 48
// characters, for people looking at the directory) and the first 32 hex
// characters of SHA-256 over the exact origin, which alone tells origins
// apart. The readable part is not injective ("https://a.b:8443" and
// "https://a.b_8443" share it); the hash is (AC-13).
func Slug(origin string) string {
	readable := strings.Trim(slugRe.ReplaceAllString(strings.ToLower(origin), "_"), "_")
	if len(readable) > 48 {
		readable = readable[:48]
	}
	sum := sha256.Sum256([]byte(origin))
	return readable + "-" + hex.EncodeToString(sum[:16])
}

// PlatformWarning is a line to print where the key's protection can't be
// checked on this OS, or "".
func PlatformWarning() string {
	if runtime.GOOS == "windows" {
		return "Note: on Windows the agent key's access list isn't checked; keep the config directory inside your own user profile (the default, %APPDATA%\\webhooktrap)."
	}
	return ""
}

func (s *Store) originDir(origin string) string {
	return filepath.Join(s.Dir, "agents", Slug(origin))
}

// ensureDir creates dir 0700 and refuses one others can read.
func ensureDir(dir string) error {
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}
	return checkPrivate(dir, true)
}

func checkPrivate(path string, dir bool) error {
	st, err := os.Lstat(path)
	if err != nil {
		return err
	}
	if dir && !st.IsDir() || !dir && !st.Mode().IsRegular() {
		return fmt.Errorf("%s is not a regular %s", path, map[bool]string{true: "directory", false: "file"}[dir])
	}
	if runtime.GOOS != "windows" && st.Mode().Perm()&0o077 != 0 {
		return fmt.Errorf("%s is readable by other users (mode %04o); refusing to use it (ADR-010 §4)", path, st.Mode().Perm())
	}
	return nil
}

func writeFile(path string, data []byte) error {
	if err := ensureDir(filepath.Dir(path)); err != nil {
		return err
	}
	tmp, err := os.CreateTemp(filepath.Dir(path), ".tmp-*")
	if err != nil {
		return err
	}
	defer os.Remove(tmp.Name())
	if err := tmp.Chmod(0o600); err != nil {
		tmp.Close()
		return err
	}
	if _, err := tmp.Write(data); err != nil {
		tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}
	return os.Rename(tmp.Name(), path)
}

func readFile(path string, limit int64) ([]byte, error) {
	if err := checkPrivate(path, false); err != nil {
		return nil, err
	}
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()
	return io.ReadAll(io.LimitReader(f, limit))
}

// KeyFor returns the origin's key, creating one if there is none (one key
// pair per server origin).
func (s *Store) KeyFor(origin string) (ed25519.PrivateKey, string, error) {
	path := filepath.Join(s.originDir(origin), "key")
	if _, err := os.Lstat(path); err == nil {
		k, err := LoadKey(path)
		return k, path, err
	}
	_, priv, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		return nil, "", err
	}
	enc := base64.StdEncoding.EncodeToString(priv.Seed())
	if err := writeFile(path, []byte(enc+"\n")); err != nil {
		return nil, "", err
	}
	return priv, path, nil
}

// LoadKey reads a key file, refusing one that others can read.
func LoadKey(path string) (ed25519.PrivateKey, error) {
	if err := checkPrivate(filepath.Dir(path), true); err != nil {
		return nil, err
	}
	raw, err := readFile(path, 1024)
	if err != nil {
		return nil, err
	}
	seed, err := base64.StdEncoding.DecodeString(strings.TrimSpace(string(raw)))
	if err != nil || len(seed) != ed25519.SeedSize {
		return nil, errors.New("the agent key file is damaged; unpair and pair again")
	}
	return ed25519.NewKeyFromSeed(seed), nil
}

// Save writes agent.json for the agent's origin.
func (s *Store) Save(a Agent) error {
	raw, err := json.MarshalIndent(a, "", "  ")
	if err != nil {
		return err
	}
	return writeFile(filepath.Join(s.originDir(a.Origin), "agent.json"), append(raw, '\n'))
}

// Load reads the pairing for origin.
func (s *Store) Load(origin string) (*Agent, error) {
	raw, err := readFile(filepath.Join(s.originDir(origin), "agent.json"), 64<<10)
	if err != nil {
		return nil, err
	}
	var a Agent
	if err := json.Unmarshal(raw, &a); err != nil {
		return nil, errors.New("agent.json is damaged")
	}
	if a.Origin != origin {
		return nil, errors.New("agent.json belongs to another origin")
	}
	return &a, nil
}

// Origins lists the origins this machine is paired with.
func (s *Store) Origins() []string {
	entries, err := os.ReadDir(filepath.Join(s.Dir, "agents"))
	if err != nil {
		return nil
	}
	var out []string
	for _, e := range entries {
		raw, err := readFile(filepath.Join(s.Dir, "agents", e.Name(), "agent.json"), 64<<10)
		if err != nil {
			continue
		}
		var a Agent
		if json.Unmarshal(raw, &a) == nil && a.Origin != "" && Slug(a.Origin) == e.Name() {
			out = append(out, a.Origin)
		}
	}
	sort.Strings(out)
	return out
}

// Delete removes the origin's key, pairing and approvals.
func (s *Store) Delete(origin string) error {
	return os.RemoveAll(s.originDir(origin))
}

// Approvals reads the origin's remote-target approvals.
func (s *Store) Approvals(origin string) []Approval {
	raw, err := readFile(filepath.Join(s.originDir(origin), "approvals.json"), 1<<20)
	if err != nil {
		return nil
	}
	var out []Approval
	_ = json.Unmarshal(raw, &out)
	return out
}

// Approved reports an approval for (agent, scheme, host, port).
func (s *Store) Approved(origin, agentID, scheme, host string, port int) bool {
	for _, a := range s.Approvals(origin) {
		if a.AgentID == agentID && a.Scheme == scheme && strings.EqualFold(a.Host, host) && a.Port == port && a.SideEffect {
			return true
		}
	}
	return false
}

// Approve records an approval.
func (s *Store) Approve(origin string, a Approval) error {
	list := s.Approvals(origin)
	list = append(list, a)
	raw, err := json.MarshalIndent(list, "", "  ")
	if err != nil {
		return err
	}
	return writeFile(filepath.Join(s.originDir(origin), "approvals.json"), append(raw, '\n'))
}

// ClearApprovals removes every approval for origin (a claim clears them,
// ADR-010 §6.3 rule 6).
func (s *Store) ClearApprovals(origin string) error {
	err := os.Remove(filepath.Join(s.originDir(origin), "approvals.json"))
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}
	return err
}

// KeyFile is where the origin's key lives.
func (s *Store) KeyFile(origin string) string {
	return filepath.Join(s.originDir(origin), "key")
}
