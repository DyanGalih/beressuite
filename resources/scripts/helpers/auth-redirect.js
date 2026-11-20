export const ADMIN_AUTH_FLASH_KEY = 'admin_auth_flash'
export const CUSTOMER_AUTH_FLASH_KEY = 'customer_auth_flash'
export const ADMIN_LOGIN_PATH = '/login'

let isRedirectingOn401 = false

function normalizePathname(pathname) {
  if (typeof pathname !== 'string' || !pathname.trim()) {
    return '/'
  }

  return pathname.replace(/\/+$/, '') || '/'
}

export function isPublicAuthPath(pathname) {
  const normalized = normalizePathname(pathname)

  if (
    normalized === '/' ||
    normalized === '/login' ||
    normalized === '/forgot-password' ||
    normalized === '/reset-password' ||
    normalized.startsWith('/reset-password/') ||
    normalized === '/on-boarding' ||
    normalized.startsWith('/on-boarding/')
  ) {
    return true
  }

  return /^\/[^/]+\/customer\/(login|forgot-password|reset-password(\/.*)?)$/.test(
    normalized
  )
}

export function resolveCustomerLoginPath(pathname) {
  const normalized = normalizePathname(pathname)
  const match = normalized.match(/^\/([A-Za-z0-9._-]+)\/customer(?:\/|$)/)

  if (!match) {
    return null
  }

  return `/${match[1]}/customer/login`
}

export function resetUnauthorizedRedirectState() {
  isRedirectingOn401 = false
}

export function handleUnauthorizedRedirect(message = 'Unauthorized', env = {}) {
  const loc =
    env.location ?? (typeof window !== 'undefined' ? window.location : null)

  if (!loc) {
    return false
  }

  const pathname = loc.pathname || '/'

  if (isPublicAuthPath(pathname)) {
    return false
  }

  if (isRedirectingOn401) {
    return true
  }

  isRedirectingOn401 = true

  const customerLoginPath = resolveCustomerLoginPath(pathname)
  const targetPath = customerLoginPath || ADMIN_LOGIN_PATH
  const flashKey = customerLoginPath
    ? CUSTOMER_AUTH_FLASH_KEY
    : ADMIN_AUTH_FLASH_KEY

  const sanitizedMessage =
    typeof message === 'string' && message.trim()
      ? message.trim()
      : 'Unauthorized'

  const localStore =
    env.localStorage ??
    (typeof window !== 'undefined' ? window.localStorage : null)
  const sessionStore =
    env.sessionStorage ??
    (typeof window !== 'undefined' ? window.sessionStorage : null)

  try {
    localStore?.removeItem('auth.token')
  } catch {
    // Ignore storage errors in restricted browsing contexts
  }

  try {
    sessionStore?.setItem(flashKey, sanitizedMessage)
  } catch {
    // Ignore storage errors in restricted browsing contexts
  }

  loc.assign(targetPath)

  return true
}
