import { test } from '@japa/runner'
import { assertSafeReplayTarget, SsrfBlockedError } from '#support/ssrf_guard'

test.group('assertSafeReplayTarget', () => {
  test('blocks loopback addresses', async ({ assert }) => {
    await assert.rejects(() => assertSafeReplayTarget('http://127.0.0.1/hook'), SsrfBlockedError)
    await assert.rejects(() => assertSafeReplayTarget('http://[::1]/hook'), SsrfBlockedError)
  })

  test('blocks link-local and cloud metadata addresses', async ({ assert }) => {
    await assert.rejects(
      () => assertSafeReplayTarget('http://169.254.169.254/latest/meta-data/'),
      SsrfBlockedError
    )
  })

  test('blocks RFC1918 private ranges', async ({ assert }) => {
    await assert.rejects(() => assertSafeReplayTarget('http://10.0.0.5/hook'), SsrfBlockedError)
    await assert.rejects(() => assertSafeReplayTarget('http://172.20.0.5/hook'), SsrfBlockedError)
    await assert.rejects(() => assertSafeReplayTarget('http://192.168.1.1/hook'), SsrfBlockedError)
  })

  test('blocks the literal localhost hostname', async ({ assert }) => {
    await assert.rejects(
      () => assertSafeReplayTarget('http://localhost:3000/hook'),
      SsrfBlockedError
    )
  })

  test('blocks non-http(s) protocols', async ({ assert }) => {
    await assert.rejects(() => assertSafeReplayTarget('ftp://93.184.216.34/hook'), SsrfBlockedError)
    await assert.rejects(() => assertSafeReplayTarget('not a url'), SsrfBlockedError)
  })

  test('allows public IPv4 targets', async ({ assert }) => {
    await assert.doesNotReject(() => assertSafeReplayTarget('https://93.184.216.34/hook'))
  })
})
