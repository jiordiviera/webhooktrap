package scrub_test

import (
	"strings"
	"testing"
	"unicode"

	"github.com/jiordiviera/webhooktrap/agent/scrub"
)

// canaries is the TM-T6 corpus extended for AC-2: C0, DEL, C1, bidi
// controls and isolates, zero-width characters, soft hyphen, word joiner and
// invisible operators, line and paragraph separators, BOM, interlinear
// annotation and Unicode tags.
var canaries = []rune{
	0x00, 0x07, 0x08, 0x0b, 0x0c, 0x1b, 0x1f, 0x7f, 0x80, 0x85, 0x9b, 0x9f,
	0x00ad, 0x061c, 0x180e,
	0x200b, 0x200c, 0x200d, 0x200e, 0x200f,
	0x2028, 0x2029,
	0x202a, 0x202b, 0x202c, 0x202d, 0x202e,
	0x2060, 0x2061, 0x2062, 0x2063, 0x2064,
	0x2066, 0x2067, 0x2068, 0x2069,
	0xfeff, 0xfff9, 0xfffa, 0xfffb,
	0xe0001, 0xe0020, 0xe0041, 0xe007f,
}

func TestTerminalStripsEveryCanary(t *testing.T) {
	for _, r := range canaries {
		in := "invoice" + string(r) + ".paid"
		out := scrub.Terminal(in)
		for _, o := range out {
			if o < 0x20 || o >= 0x7f && o < 0xa0 || unicode.Is(unicode.Cf, o) || o == 0x2028 || o == 0x2029 || o >= 0xe0000 && o <= 0xe007f {
				t.Errorf("U+%04X survived: %q", r, out)
			}
		}
		if !strings.HasPrefix(out, "invoice") || !strings.HasSuffix(out, ".paid") {
			t.Errorf("U+%04X: text around it lost: %q", r, out)
		}
	}
}

func TestTerminalForms(t *testing.T) {
	for in, want := range map[string]string{
		"plain · text →":                       "plain · text →",
		"a\tb\nc\rd":                           "a b c d",
		"\x1b]8;;https://evil\x07click\x1b[2J": "?]8;;https://evil?click?[2J",
		"a b c":                                "a b c",
		"in​voice‮.paid⁦":                      "invoice.paid",
		"­soft":                                "soft",
		"tag\U000E0041\U000E007F":              "tag",
		"\u0085next":                           "?next",
	} {
		if got := scrub.Terminal(in); got != want {
			t.Errorf("Terminal(%q) = %q, want %q", in, got, want)
		}
	}
	if got := scrub.TerminalCap("abc​def", 4); got != "abcd…" {
		t.Errorf("TerminalCap = %q", got)
	}
	if got := scrub.TerminalCap("abc", 3); got != "abc" {
		t.Errorf("TerminalCap exact = %q", got)
	}
}
