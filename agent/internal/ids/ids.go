// Package ids makes run ids: UUIDv7 in external form `run_` + 26 Crockford
// base32 characters (schema.md §1.1). Randomness comes from the run's seeded
// PRNG (D-3), so the same seed and clock give the same id.
package ids

// Rand is splitmix64: small, seedable, stable across Go versions.
type Rand struct{ s uint64 }

// NewRand seeds the generator.
func NewRand(seed uint64) *Rand { return &Rand{s: seed} }

// Uint64 returns the next value.
func (r *Rand) Uint64() uint64 {
	r.s += 0x9e3779b97f4a7c15
	z := r.s
	z = (z ^ (z >> 30)) * 0xbf58476d1ce4e5b9
	z = (z ^ (z >> 27)) * 0x94d049bb133111eb
	return z ^ (z >> 31)
}

const crockford = "0123456789ABCDEFGHJKMNPQRSTVWXYZ"

// RunID returns a UUIDv7 for unix milliseconds ms, as run_<base32>.
func RunID(ms int64, r *Rand) string {
	var u [16]byte
	for i := 0; i < 6; i++ {
		u[i] = byte(uint64(ms) >> (8 * (5 - i)))
	}
	a, b := r.Uint64(), r.Uint64()
	for i := 6; i < 16; i++ {
		if i < 14 {
			u[i] = byte(a >> (8 * (i - 6)))
		} else {
			u[i] = byte(b >> (8 * (i - 14)))
		}
	}
	u[6] = 0x70 | (u[6] & 0x0f) // version 7
	u[8] = 0x80 | (u[8] & 0x3f) // variant
	return "run_" + encode(u)
}

// encode writes 128 bits as 26 Crockford base32 characters (2 pad bits first).
func encode(u [16]byte) string {
	out := make([]byte, 26)
	// Treat u as a 130-bit number with 2 leading zero bits.
	var acc uint64
	bits := 2
	idx := 0
	for _, b := range u {
		acc = acc<<8 | uint64(b)
		bits += 8
		for bits >= 5 {
			bits -= 5
			out[idx] = crockford[(acc>>uint(bits))&31]
			idx++
		}
	}
	return string(out[:idx])
}
