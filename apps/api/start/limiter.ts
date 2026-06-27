import limiter from '@adonisjs/limiter/services/main'

export const throttle = limiter.define('global', () => {
  return limiter.allowRequests(10).every('1 minute')
})

export const otpRequestThrottle = limiter.define('otp_request', () => {
  return limiter.allowRequests(1).every('1 minute')
})

export const otpVerifyThrottle = limiter.define('otp_verify', () => {
  return limiter.allowRequests(5).every('1 minute')
})
