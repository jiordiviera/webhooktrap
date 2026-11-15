package scrub

import (
	"strings"
	"unicode"
)

// Terminal makes text safe to print in a terminal (S3-16, TM-41, decision
// 33). It is the agent's only text cleaner: every string that came from a
// capture, the server, a pack, a file, a flag or another process goes
// through it at print time. It matches the server's TerminalText set:
//
//   - tab, line feed and carriage return become a space;
//   - every other control character (C0, DEL, C1: \p{Cc}) becomes '?', so
//     an escape sequence can't drive the terminal but shows it was there;
//   - format characters (\p{Cf}: bidi controls and isolates, zero-width
//     space and joiners, word joiner and invisible operators U+2060–U+2064,
//     soft hyphen, BOM, interlinear annotation, Unicode tags) are dropped;
//   - the line and paragraph separators U+2028 and U+2029 become a space.
func Terminal(s string) string {
	clean := true
	for _, r := range s {
		if dirty(r) {
			clean = false
			break
		}
	}
	if clean {
		return s
	}
	var b strings.Builder
	for _, r := range s {
		switch {
		case r == '\t' || r == '\n' || r == '\r' || r == 0x2028 || r == 0x2029:
			b.WriteByte(' ')
		case unicode.Is(unicode.Cc, r):
			b.WriteByte('?')
		case unicode.Is(unicode.Cf, r) || r >= 0xe0000 && r <= 0xe007f:
		default:
			b.WriteRune(r)
		}
	}
	return b.String()
}

func dirty(r rune) bool {
	return r < 0x20 || r >= 0x7f && r < 0xa0 || r == 0x2028 || r == 0x2029 ||
		r >= 0xa0 && (unicode.Is(unicode.Cf, r) || r >= 0xe0000 && r <= 0xe007f)
}

// TerminalCap is Terminal capped at n runes, with "…" when cut.
func TerminalCap(s string, n int) string {
	s = Terminal(s)
	count := 0
	for i := range s {
		if count == n {
			return s[:i] + "…"
		}
		count++
	}
	return s
}
