const SIGNATURES: Array<{ mime: string; check: (buffer: Buffer) => boolean }> = [
  {
    mime: 'image/jpeg',
    check: (buffer) => buffer.length >= 2 && buffer[0] === 0xff && buffer[1] === 0xd8,
  },
  {
    mime: 'image/png',
    check: (buffer) =>
      buffer.length >= 4 &&
      buffer[0] === 0x89 &&
      buffer[1] === 0x50 &&
      buffer[2] === 0x4e &&
      buffer[3] === 0x47,
  },
  {
    mime: 'image/webp',
    check: (buffer) =>
      buffer.length >= 12 &&
      buffer.toString('ascii', 0, 4) === 'RIFF' &&
      buffer.toString('ascii', 8, 12) === 'WEBP',
  },
  {
    mime: 'application/pdf',
    check: (buffer) => buffer.length >= 4 && buffer.toString('ascii', 0, 4) === '%PDF',
  },
]

export function detectMimeType(buffer: Buffer) {
  return SIGNATURES.find((signature) => signature.check(buffer))?.mime ?? null
}

export function assertMimeMatchesBuffer(declaredMime: string, buffer: Buffer) {
  const detected = detectMimeType(buffer)
  if (!detected) {
    throw new Error('Unable to detect file type')
  }
  if (detected !== declaredMime) {
    throw new Error(`File content does not match declared type "${declaredMime}"`)
  }
}
