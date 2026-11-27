import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readdir, readFile, writeFile, unlink } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const require = createRequire(import.meta.url)

async function read(relativePath) {
  return readFile(path.join(root, relativePath), 'utf8')
}

async function sourceFiles(directory) {
  const entries = await readdir(path.join(root, directory), {
    withFileTypes: true,
  })
  const files = []

  for (const entry of entries) {
    const relativePath = path.posix.join(directory, entry.name)
    if (entry.isDirectory()) {
      files.push(...(await sourceFiles(relativePath)))
    } else if (entry.name.endsWith('.js') || entry.name.endsWith('.vue')) {
      files.push(relativePath)
    }
  }

  return files
}

const scripts = await sourceFiles('resources/scripts')
const source = (await Promise.all(scripts.map(read))).join('\n')

const tailwind = require(path.join(root, 'tailwind.config.js'))
const themes = await read('resources/sass/themes.scss')
const variables = Object.fromEntries(
  [...themes.matchAll(/--color-primary-(\d+):\s*([^;]+);/g)].map(
    ([, shade, value]) => [`--color-primary-${shade}`, value.trim()],
  ),
)

for (const [shade, color] of Object.entries(
  tailwind.theme.extend.colors.primary,
)) {
  assert.match(
    color,
    /^rgba\(var\(--color-primary-\d+\), <alpha-value>\)$/,
    `primary-${shade} must use comma-compatible RGBA syntax`,
  )
  const expanded = color
    .replace(
      /var\((--color-primary-\d+)\)/,
      (_, variable) => variables[variable],
    )
    .replace('<alpha-value>', '1')
  assert.match(expanded, /^rgba\(\d+, \d+, \d+, 1\)$/)
}

for (const utility of [
  'bg-opacity-',
  'text-opacity-',
  'ring-opacity-',
  'border-opacity-',
]) {
  assert.equal(
    source.includes(utility),
    false,
    `legacy ${utility} utility remains`,
  )
}

for (const utility of [
  'bg-gray-700/25',
  'bg-gray-500/75',
  'ring-black/5',
  'text-current/90',
  'bg-[rgba(var(--color-primary-300),0.25)]',
  'text-[rgba(var(--color-primary-400),0.9)]',
]) {
  assert.equal(
    source.includes(utility),
    true,
    `missing migrated utility ${utility}`,
  )
}

const uuidSource = await read('resources/scripts/helpers/uuid.js')
const directUuidCallers = scripts.filter(
  (file) => file !== 'resources/scripts/helpers/uuid.js',
)
for (const file of directUuidCallers) {
  assert.equal(
    (await read(file)).includes('crypto.randomUUID'),
    false,
    `${file} still calls crypto.randomUUID directly`,
  )
}
const expectedUuidCallers = [
  'resources/scripts/admin/components/estimate-invoice-common/CreateItemRow.vue',
  'resources/scripts/admin/components/estimate-invoice-common/CreateTotal.vue',
  'resources/scripts/admin/components/modal-components/TaxTypeModal.vue',
  'resources/scripts/admin/stores/estimate.js',
  'resources/scripts/admin/stores/invoice.js',
  'resources/scripts/admin/stores/recurring-invoice.js',
  'resources/scripts/admin/stub/estimate.js',
  'resources/scripts/admin/stub/invoice.js',
  'resources/scripts/admin/stub/recurring-invoice.js',
  'resources/scripts/admin/views/settings/customization/NumberCustomizer.vue',
  'resources/scripts/components/base/BaseCheckbox.vue',
  'resources/scripts/components/base/BaseRadio.vue',
  'resources/scripts/stores/notification.js',
]
const actualUuidCallers = []
for (const file of scripts) {
  const content = await read(file)
  if (file !== 'resources/scripts/helpers/uuid.js' && content.includes('createUuid')) {
    actualUuidCallers.push(file)
  }
}
assert.deepEqual(
  actualUuidCallers.sort(),
  [...expectedUuidCallers].sort(),
  'UUID callers changed without updating the shared owner coverage',
)
for (const file of expectedUuidCallers) {
  const content = await read(file)
  assert.match(
    content,
    /import createUuid from ['"]@\/scripts\/helpers\/uuid['"]/,
    `${file} must import the shared UUID helper`,
  )
  assert.match(
    content,
    /createUuid\(\)/,
    `${file} must invoke the shared UUID helper`,
  )
}
const { default: createUuid } = await import(
  `data:text/javascript,${encodeURIComponent(uuidSource)}`
)
const cryptoDescriptor = Object.getOwnPropertyDescriptor(globalThis, 'crypto')

try {
  Object.defineProperty(globalThis, 'crypto', {
    configurable: true,
    value: {
      getRandomValues(bytes) {
        bytes.forEach((_, index) => {
          bytes[index] = index
        })
        return bytes
      },
    },
  })
  const fallbackUuid = createUuid()
  assert.match(
    fallbackUuid,
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
  )

  Object.defineProperty(globalThis, 'crypto', {
    configurable: true,
    value: { randomUUID: () => 'native-uuid' },
  })
  assert.equal(createUuid(), 'native-uuid')
} finally {
  if (cryptoDescriptor) {
    Object.defineProperty(globalThis, 'crypto', cryptoDescriptor)
  } else {
    delete globalThis.crypto
  }
}

const expectedStoreIds = {
  'resources/scripts/admin/stores': [
    'api-token',
    'auth',
    'backup',
    'category',
    'company',
    'custom-field',
    'customer',
    'dashboard',
    'disk',
    'estimate',
    'exchange-rate',
    'expense',
    'global',
    'installation',
    'invoice',
    'item',
    'mail-driver',
    'modules',
    'notes',
    'payment',
    'recurring-invoice',
    'reset',
    'role',
    'taxType',
    'user',
    'users',
  ],
  'resources/scripts/customer/stores': [
    'CustomerPortalGlobalStore',
    'customerAuth',
    'customerEstimateStore',
    'customerInvoiceStore',
    'customerPaymentStore',
    'customerUserStore',
    'customers',
    'dashboard',
  ],
  'resources/scripts/stores': ['dialog', 'modal', 'notification'],
}
for (const [directory, expectedIds] of Object.entries(expectedStoreIds)) {
  const actualIds = []
  for (const file of await sourceFiles(directory)) {
    const content = await read(file)
    assert.equal(
      content.includes('defineStore({'),
      false,
      `${file} uses legacy store syntax`,
    )
    const id = content.match(
      /(?:defineStoreFunc|defineStore)\(['"]([^'"]+)['"],/,
    )
    assert.ok(id, `${file} must preserve a string Pinia store id`)
    actualIds.push(id[1])
  }
  assert.deepEqual(
    actualIds.sort(),
    [...expectedIds].sort(),
    `${directory} changed its Pinia store id set`,
  )
}

const baseIcon = await read('resources/scripts/components/base/BaseIcon.vue')
const aliases = Object.fromEntries(
  [...baseIcon.matchAll(/^\s+(\w+): '(\w+)',/gm)].map(([, from, to]) => [
    from,
    to,
  ]),
)
const configuredIcons = [
  ...new Set(
    [...(await read('config/crater.php')).matchAll(/'icon'\s*=>\s*'([^']+)'/g)]
      .map(([, name]) => name)
      .filter(Boolean),
  ),
]
const heroIcons = await read('node_modules/@heroicons/vue/24/outline/index.js')
const hasIconExport = (name) =>
  heroIcons.includes(`module.exports.${name} = require(`)

for (const target of Object.values(aliases)) {
  assert.equal(
    hasIconExport(target),
    true,
    `missing Heroicons export ${target}`,
  )
}

assert.ok(configuredIcons.length > 0, 'config/crater.php must define icons')
for (const name of configuredIcons) {
  assert.equal(
    hasIconExport(aliases[name] ?? name),
    true,
    `configured icon ${name} has no Heroicons export`,
  )
}

for (const [, name] of source.matchAll(
  /<BaseIcon\b[^>]*(?<!:)name="([^"]+)"/g,
)) {
  assert.equal(
    hasIconExport(aliases[name] ?? name),
    true,
    `unresolved BaseIcon name ${name}`,
  )
}

assert.equal(source.includes(':name="item.icon"'), true)
assert.equal(baseIcon.includes('heroIcons[iconName(name)]'), true)
assert.equal(source.includes('@heroicons/vue/outline'), false)

const builtAssets = (
  await Promise.all(
    (await readdir(path.join(root, 'public/build/assets')))
      .filter((file) => file.endsWith('.css') || file.endsWith('.js'))
      .map((file) =>
        readFile(path.join(root, 'public/build/assets', file), 'utf8'),
      ),
  )
).join('\n')
function emittedComponent(marker, name, length = 1200) {
  const start = builtAssets.indexOf(marker)
  assert.notEqual(start, -1, `${name} is missing from emitted assets`)
  return builtAssets.slice(start, start + length)
}

assert.equal(builtAssets.includes('rgba(rgba('), false)
assert.equal(builtAssets.includes('var(--tw-text-opacity)'), false)
assert.match(
  builtAssets,
  /\.text-current\\\/90\{color:currentColor\}/,
  'emitted text opacity utility is missing',
)
assert.match(
  builtAssets,
  /color-mix\(in oklab, currentcolor 90%, transparent\)/,
  'emitted text opacity value is invalid',
)
assert.match(
  builtAssets,
  /\.bg-gray-700\\\/25\{background-color:[^}]*\/\.25\)\}/,
  'emitted background opacity utility is missing or invalid',
)
assert.match(
  builtAssets,
  /\.border-gray-200\\\/60\{border-color:[^}]*\/\.6\)\}/,
  'emitted border opacity utility is missing or invalid',
)
assert.match(
  builtAssets,
  /\.ring-black\\\/5\{--tw-ring-color:[^}]*\/\.05\)\}/,
  'emitted ring opacity utility is missing or invalid',
)
assert.equal(
  builtAssets.includes(
    '.bg-\\[rgba\\(var\\(--color-primary-300\\)\\,0\\.25\\)\\]{background-color:rgba(var(--color-primary-300),.25)}',
  ),
  true,
  'emitted custom primary background is missing or invalid',
)
assert.equal(
  builtAssets.includes(
    '.text-\\[rgba\\(var\\(--color-primary-400\\)\\,0\\.9\\)\\]{color:rgba(var(--color-primary-400),.9)}',
  ),
  true,
  'emitted custom primary text is missing or invalid',
)
assert.match(
  builtAssets,
  /\.from-primary-500\{--tw-gradient-from:rgba\(var\(--color-primary-500\), 1\)/,
  'emitted primary gradient start is missing or invalid',
)
assert.match(
  builtAssets,
  /\.to-primary-400\{--tw-gradient-to:rgba\(var\(--color-primary-400\), 1\)/,
  'emitted primary gradient end is missing or invalid',
)
assert.match(
  builtAssets,
  /\.backdrop-blur\{--tw-backdrop-blur:blur\(8px\);[^}]*backdrop-filter:/,
  'emitted backdrop declaration is missing or invalid',
)

const loginBackground = emittedComponent(
  'M116.21 472.5C55.1239 693.5 78.5219 837.5 114.349 1023H1030.5V-1L0 -106C147.5 21.5 172.311 269.536 116.21 472.5Z',
  'LoginBackground',
)
assert.match(
  loginBackground,
  /stop-color.*rgba\(var\(--color-primary-500\), 1\)/,
  'LoginBackground primary-500 alpha is missing or invalid',
)
assert.match(
  loginBackground,
  /stop-color.*rgba\(var\(--color-primary-400\), 1\)/,
  'LoginBackground primary-400 alpha is missing or invalid',
)

const loginBackgroundOverlay = emittedComponent(
  'M226.002 466.5C164.935 687.5 188.326 831.5 224.141 1017H1140V-7L0 -109.5C142.5 -7.5 282.085 263.536 226.002 466.5Z',
  'LoginBackgroundOverlay',
)
assert.match(
  loginBackgroundOverlay,
  /"fill-opacity":`0\.1`/,
  'LoginBackgroundOverlay opacity is missing or invalid',
)
assert.match(
  loginBackgroundOverlay,
  /stop-color.*rgba\(var\(--color-primary-500\), 1\)/,
  'LoginBackgroundOverlay primary-500 alpha is missing or invalid',
)
assert.match(
  loginBackgroundOverlay,
  /stop-color.*rgba\(var\(--color-primary-400\), 1\)/,
  'LoginBackgroundOverlay primary-400 alpha is missing or invalid',
)

const mainLogo = emittedComponent('__name:`MainLogo`', 'MainLogo')
assert.match(
  mainLogo,
  /darkColor:\{type:String,default:`rgba\(var\(--color-primary-500\), 1\)`\}/,
  'MainLogo primary-500 alpha is missing or invalid',
)
assert.match(
  mainLogo,
  /lightColor:\{type:String,default:`rgba\(var\(--color-primary-400\), 1\)`\}/,
  'MainLogo primary-400 alpha is missing or invalid',
)

for (const errorHandlerPath of [
  'resources/scripts/helpers/error-handling.js',
  'resources/scripts/customer/helpers/error-handling.js',
]) {
  const errorHandler = await read(errorHandlerPath)
  assert.equal(
    errorHandler.includes('err.response.status === 401'),
    true,
    `${errorHandlerPath} must detect 401 responses by numeric status`,
  )
  assert.equal(
    errorHandler.includes('showToaster(msg, false)'),
    true,
    `${errorHandlerPath} must pass raw 401 messages without vue-i18n key lookup`,
  )
  assert.equal(
    errorHandler.includes('authStore.logout('),
    false,
    `${errorHandlerPath} must not call authStore.logout() on 401`,
  )
}

const invoiceIndex = await read('resources/scripts/admin/views/invoices/Index.vue')
assert.equal(
  invoiceIndex.includes('userStore.hasAbilities(abilities.VIEW_INVOICE)'),
  true,
  'Invoice Index.vue must gate Export XLSX on abilities.VIEW_INVOICE',
)
assert.equal(
  invoiceIndex.includes("$t('invoices.export_xlsx')"),
  true,
  'Invoice Index.vue must render the Export XLSX action button',
)
assert.equal(
  invoiceIndex.includes("$t('invoices.export_filtered')"),
  true,
  'Invoice Index.vue must provide Export Filtered action',
)
assert.equal(
  invoiceIndex.includes("$t('invoices.export_all')"),
  true,
  'Invoice Index.vue must provide Export All action',
)
assert.equal(
  invoiceIndex.includes('wrapper-class="inline-flex items-center text-left"'),
  true,
  'Invoice Index.vue must vertically align the Export XLSX dropdown with sibling header buttons',
)

const invoiceStoreSource = await read('resources/scripts/admin/stores/invoice.js')
assert.equal(
  invoiceStoreSource.includes('/api/v1/invoices/export'),
  true,
  'Invoice store must invoke /api/v1/invoices/export',
)
assert.equal(
  invoiceStoreSource.includes("responseType: 'blob'"),
  true,
  'Invoice store must download XLSX via authenticated Axios blob response',
)
assert.equal(
  invoiceStoreSource.includes('payload.paid_status'),
  true,
  'Invoice store must map paid status filters to paid_status',
)
assert.equal(
  invoiceStoreSource.includes('payload.search'),
  true,
  'Invoice store must map search filters to search',
)

const customerErrorHandler = await read(
  'resources/scripts/customer/helpers/error-handling.js',
)
assert.equal(
  customerErrorHandler.includes('window.location.assign('),
  true,
  'Customer 401 handler must use full-page redirect to clear Pinia stores and in-flight requests',
)

const customerAuthStore = await read('resources/scripts/customer/stores/auth.js')
assert.equal(
  customerAuthStore.includes("import router from '@/scripts/router'"),
  true,
  'Customer auth store must import the Vue Router instance for router.resolve()',
)

const customerLoginView = await read('resources/scripts/customer/views/auth/Login.vue')
assert.equal(
  customerLoginView.includes("customer_auth_flash"),
  true,
  'Customer Login view must surface and clear one-time 401 flash messages',
)

const axiosPlugin = await read('resources/scripts/plugins/axios.js')
assert.equal(
  axiosPlugin.includes('axios.interceptors.response.use('),
  true,
  'Axios plugin must register a global response interceptor',
)
assert.equal(
  axiosPlugin.includes('handleUnauthorizedRedirect'),
  true,
  'Axios plugin must delegate 401 responses to handleUnauthorizedRedirect',
)

const adminErrorHandler = await read('resources/scripts/helpers/error-handling.js')
assert.equal(
  adminErrorHandler.includes('handleUnauthorizedRedirect'),
  true,
  'Admin 401 handler must delegate to the shared handleUnauthorizedRedirect helper',
)
assert.equal(
  adminErrorHandler.includes("window.router?.push('/login')"),
  false,
  'Admin 401 handler must not use soft SPA router.push(/login)',
)

const adminLoginView = await read('resources/scripts/admin/views/auth/Login.vue')
assert.equal(
  adminLoginView.includes('ADMIN_AUTH_FLASH_KEY'),
  true,
  'Admin Login view must consume and clear one-time 401 flash messages',
)

const authRedirectSource = await read('resources/scripts/helpers/auth-redirect.js')
const {
  ADMIN_AUTH_FLASH_KEY,
  CUSTOMER_AUTH_FLASH_KEY,
  handleUnauthorizedRedirect,
  isPublicAuthPath,
  resetUnauthorizedRedirectState,
  resolveCustomerLoginPath,
} = await import(`data:text/javascript,${encodeURIComponent(authRedirectSource)}`)

assert.equal(ADMIN_AUTH_FLASH_KEY, 'admin_auth_flash')
assert.equal(CUSTOMER_AUTH_FLASH_KEY, 'customer_auth_flash')
assert.equal(isPublicAuthPath('/'), true)
assert.equal(isPublicAuthPath('/login'), true)
assert.equal(isPublicAuthPath('/login/'), true)
assert.equal(isPublicAuthPath('/forgot-password'), true)
assert.equal(isPublicAuthPath('/reset-password/token-123'), true)
assert.equal(isPublicAuthPath('/on-boarding'), true)
assert.equal(isPublicAuthPath('/acme/customer'), true)
assert.equal(isPublicAuthPath('/acme/customer/'), true)
assert.equal(isPublicAuthPath('/acme/customer/login'), true)
assert.equal(isPublicAuthPath('/acme/customer/reset/password/token-123'), true)
assert.equal(isPublicAuthPath('/admin/invoices'), false)
assert.equal(isPublicAuthPath('/acme/customer/invoices'), false)
assert.equal(resolveCustomerLoginPath('/acme/customer/invoices'), '/acme/customer/login')
assert.equal(resolveCustomerLoginPath('/admin/invoices'), null)

const createMockStorage = (initial = {}) => {
  const map = new Map(Object.entries(initial))
  return {
    getItem: (key) => (map.has(key) ? map.get(key) : null),
    setItem: (key, value) => map.set(key, String(value)),
    removeItem: (key) => map.delete(key),
  }
}

// Public auth routes must not redirect or set flash
resetUnauthorizedRedirectState()
{
  const assigned = []
  const localStorage = createMockStorage({ 'auth.token': 'Bearer stale' })
  const sessionStorage = createMockStorage()
  const redirected = handleUnauthorizedRedirect('Unauthenticated.', {
    location: { pathname: '/login', assign: (url) => assigned.push(url) },
    localStorage,
    sessionStorage,
  })
  assert.equal(redirected, false)
  assert.deepEqual(assigned, [])
  assert.equal(sessionStorage.getItem('admin_auth_flash'), null)
  assert.equal(localStorage.getItem('auth.token'), 'Bearer stale')
}

// Admin route 401 clears token, sets flash, redirects once across concurrent calls
resetUnauthorizedRedirectState()
{
  const assigned = []
  const localStorage = createMockStorage({ 'auth.token': 'Bearer stale' })
  const sessionStorage = createMockStorage()
  const env = {
    location: { pathname: '/admin/invoices', assign: (url) => assigned.push(url) },
    localStorage,
    sessionStorage,
  }
  assert.equal(handleUnauthorizedRedirect('Unauthenticated.', env), true)
  assert.equal(handleUnauthorizedRedirect('Second 401', env), true)
  assert.deepEqual(assigned, ['/login'])
  assert.equal(localStorage.getItem('auth.token'), null)
  assert.equal(sessionStorage.getItem('admin_auth_flash'), 'Unauthenticated.')
}

// Customer portal route 401 redirects to customer login with customer_auth_flash
resetUnauthorizedRedirectState()
{
  const assigned = []
  const localStorage = createMockStorage()
  const sessionStorage = createMockStorage()
  const redirected = handleUnauthorizedRedirect('Unauthenticated.', {
    location: { pathname: '/acme-co/customer/invoices', assign: (url) => assigned.push(url) },
    localStorage,
    sessionStorage,
  })
  assert.equal(redirected, true)
  assert.deepEqual(assigned, ['/acme-co/customer/login'])
  assert.equal(sessionStorage.getItem('customer_auth_flash'), 'Unauthenticated.')
}

// Throwing storage getters must not block navigation or leave redirect state stuck
resetUnauthorizedRedirectState()
{
  const assigned = []
  const env = {
    location: { pathname: '/admin/invoices', assign: (url) => assigned.push(url) },
  }
  Object.defineProperty(env, 'localStorage', {
    get() {
      throw new Error('SecurityError')
    },
  })
  Object.defineProperty(env, 'sessionStorage', {
    get() {
      throw new Error('SecurityError')
    },
  })
  assert.equal(handleUnauthorizedRedirect('Unauthenticated.', env), true)
  assert.deepEqual(assigned, ['/login'])
}

const craterConfig = await read('config/crater.php')
assert.equal(
  craterConfig.includes("'/admin/settings/api-tokens'"),
  true,
  'config/crater.php must include /admin/settings/api-tokens in setting_menu',
)

const adminRouter = await read('resources/scripts/admin/admin-router.js')
assert.match(
  adminRouter,
  /path:\s*'api-tokens'[\s\S]*?meta:\s*\{\s*isOwner:\s*true\s*\}/,
  'admin-router.js must guard api-tokens route with meta.isOwner',
)

const apiTokenStoreSource = await read('resources/scripts/admin/stores/api-token.js')
assert.equal(
  apiTokenStoreSource.includes('this.plainTextToken') ||
    apiTokenStoreSource.includes('this.plain_text_token'),
  false,
  'api-token store must not persist plain_text_token in Pinia state',
)
assert.equal(
  apiTokenStoreSource.includes("Ls.get('selectedCompany') === requestCompanyId"),
  true,
  'api-token store must discard stale list responses from a previous company',
)

const apiTokenModalSource = await read(
  'resources/scripts/admin/components/modal-components/ApiTokenModal.vue',
)
assert.equal(
  apiTokenModalSource.includes('v-html'),
  false,
  'ApiTokenModal.vue must not use v-html',
)
assert.equal(
  apiTokenModalSource.includes("plainTextToken.value = ''"),
  true,
  'ApiTokenModal.vue must clear one-time plainTextToken ref on close/unmount',
)
assert.equal(
  apiTokenModalSource.includes('activeSession !== sessionCounter'),
  true,
  'ApiTokenModal.vue must bind token creation to the active modal session',
)

const apiTokensSettingSource = await read(
  'resources/scripts/admin/views/settings/ApiTokensSetting.vue',
)
assert.equal(
  apiTokensSettingSource.includes('dialogStore.openDialog('),
  true,
  'ApiTokensSetting.vue must confirm rotate and revoke via dialogStore',
)
assert.equal(
  apiTokensSettingSource.includes('isRotating.value'),
  true,
  'ApiTokensSetting.vue must guard against concurrent token rotations',
)
assert.match(
  apiTokensSettingSource,
  /function openCreateModal\(\)\s*\{\s*if\s*\(isRotating\.value\)\s*\{\s*return\s*\}/,
  'ApiTokensSetting.vue must block opening Create Token modal while a rotation is pending',
)

// --- Browser Form Draft Persistence Regressions ---
const useFormDraftSource = await read(
  'resources/scripts/admin/composables/useFormDraft.js',
)
assert.equal(
  useFormDraftSource.includes('export function useFormDraft('),
  true,
  'useFormDraft.js must export useFormDraft function',
)
assert.equal(
  useFormDraftSource.includes('crater_draft:${companyId}:${userId}:${docType}:${mode}'),
  true,
  'useFormDraft.js must use tenant- and user-scoped storage key pattern',
)
assert.equal(
  useFormDraftSource.includes("window.addEventListener('beforeunload', handleBeforeUnload)"),
  true,
  'useFormDraft.js must register beforeunload listener for browser exit protection',
)
assert.equal(
  useFormDraftSource.includes('onBeforeRouteLeave('),
  true,
  'useFormDraft.js must intercept in-app SPA navigation with onBeforeRouteLeave',
)

const draftBannerSource = await read(
  'resources/scripts/admin/components/draft/FormDraftBanner.vue',
)
assert.equal(
  draftBannerSource.includes('draft_found_title'),
  true,
  'FormDraftBanner.vue must render draft found title',
)
assert.equal(
  draftBannerSource.includes("restore"),
  true,
  'FormDraftBanner.vue must emit restore event',
)

const draftBadgeSource = await read(
  'resources/scripts/admin/components/draft/FormSaveStatusBadge.vue',
)
assert.equal(
  draftBadgeSource.includes('unsaved_changes'),
  true,
  'FormSaveStatusBadge.vue must display unsaved changes state',
)
assert.equal(
  draftBadgeSource.includes('draft_saved_at'),
  true,
  'FormSaveStatusBadge.vue must display draft saved timestamp state',
)

const invoiceCreateSource = await read(
  'resources/scripts/admin/views/invoices/create/InvoiceCreate.vue',
)
assert.equal(
  invoiceCreateSource.includes('FormDraftBanner'),
  true,
  'InvoiceCreate.vue must include FormDraftBanner',
)
assert.equal(
  invoiceCreateSource.includes('FormSaveStatusBadge'),
  true,
  'InvoiceCreate.vue must include FormSaveStatusBadge',
)
assert.equal(
  invoiceCreateSource.includes("docType: 'invoice'"),
  true,
  'InvoiceCreate.vue must initialize useFormDraft with invoice docType',
)

const estimateCreateSource = await read(
  'resources/scripts/admin/views/estimates/create/EstimateCreate.vue',
)
assert.equal(
  estimateCreateSource.includes('FormDraftBanner'),
  true,
  'EstimateCreate.vue must include FormDraftBanner',
)
assert.equal(
  estimateCreateSource.includes('FormSaveStatusBadge'),
  true,
  'EstimateCreate.vue must include FormSaveStatusBadge',
)
assert.equal(
  estimateCreateSource.includes("docType: 'estimate'"),
  true,
  'EstimateCreate.vue must initialize useFormDraft with estimate docType',
)

const paymentCreateSource = await read(
  'resources/scripts/admin/views/payments/Create.vue',
)
assert.equal(
  paymentCreateSource.includes('FormDraftBanner'),
  true,
  'Create.vue (payment) must include FormDraftBanner',
)
assert.equal(
  paymentCreateSource.includes('FormSaveStatusBadge'),
  true,
  'Create.vue (payment) must include FormSaveStatusBadge',
)
assert.equal(
  paymentCreateSource.includes("docType: 'payment'"),
  true,
  'Create.vue (payment) must initialize useFormDraft with payment docType',
)

const enLocale = JSON.parse(await read('resources/scripts/locales/en.json'))
assert.equal(typeof enLocale.general?.unsaved_changes, 'string')
assert.equal(typeof enLocale.general?.draft_saved_at, 'string')
assert.equal(typeof enLocale.general?.draft_found_title, 'string')
assert.equal(typeof enLocale.general?.restore_draft, 'string')
assert.equal(typeof enLocale.general?.discard_draft, 'string')
assert.equal(typeof enLocale.general?.unsaved_changes_leave_confirm, 'string')

// --- Behavioral Unit Regressions for Form Draft Persistence ---
// Load and test actual useFormDraft composable module
{
  const composableSource = await read('resources/scripts/admin/composables/useFormDraft.js')
  let transformed = composableSource
    .replace(/import Ls from [^\n]+/, 'const Ls = { get: () => "1" };')
    .replace(/import { useCompanyStore } from [^\n]+/, 'const useCompanyStore = () => ({ selectedCompany: { id: 1 } });')
    .replace(/import { useUserStore } from [^\n]+/, 'const useUserStore = () => ({ currentUser: { id: 2 } });')
    .replace(/import { onBeforeRouteLeave } from [^\n]+/, 'const onBeforeRouteLeave = () => {};')
    .replace(/import { useI18n } from [^\n]+/, 'const useI18n = () => ({ t: (k) => k });')

  const tmpModulePath = 'scripts/.tmp-draft-integration-test.mjs'
  await writeFile(tmpModulePath, transformed, 'utf8')

  const mockStorageMap = new Map()
  const mockWebStorage = {
    getItem: (k) => (mockStorageMap.has(k) ? mockStorageMap.get(k) : null),
    setItem: (k, v) => {
      mockStorageMap.set(k, String(v))
      mockWebStorage[k] = String(v)
    },
    removeItem: (k) => {
      mockStorageMap.delete(k)
      delete mockWebStorage[k]
    },
    clear: () => {
      mockStorageMap.clear()
      for (const k of Object.keys(mockWebStorage)) {
        if (typeof mockWebStorage[k] === 'string') delete mockWebStorage[k]
      }
    },
  }

  globalThis.window = { addEventListener() {}, removeEventListener() {} }
  globalThis.localStorage = mockWebStorage

  const { useFormDraft, clearAllFormDrafts } = await import(`./.tmp-draft-integration-test.mjs`)
  await unlink(tmpModulePath)

  // 1. Storage key generation with company, user, and mode scoping
  let currentPayload = { invoice_number: 'INV-100', customer_id: 1, total: 500 }
  let appliedPayload = null
  const draft = useFormDraft({
    docType: 'invoice',
    getFormPayload: () => currentPayload,
    applyFormPayload: (p) => {
      appliedPayload = p
    },
    isEdit: false,
    debounceMs: 50,
  })

  assert.equal(draft.storageKey.value, 'crater_draft:1:2:invoice:create')

  // 2. Baseline initialization
  draft.initBaseline()
  assert.equal(draft.isDirty.value, false)
  assert.equal(draft.saveStatus.value, 'clean')

  // 3. Auto-save writes payload to storage
  currentPayload = { invoice_number: 'INV-100', customer_id: 1, total: 999 }
  draft.saveDraftToStorage()
  assert.equal(draft.saveStatus.value, 'draft_saved')
  const savedItem = JSON.parse(mockWebStorage.getItem(draft.storageKey.value))
  assert.equal(savedItem?.payload?.total, 999)

  // 4. Return to baseline clears storage and resets clean status
  currentPayload = { invoice_number: 'INV-100', customer_id: 1, total: 500 }
  draft.discardDraft()
  assert.equal(mockWebStorage.getItem(draft.storageKey.value), null)
  assert.equal(draft.saveStatus.value, 'clean')

  const editDraft = useFormDraft({
    docType: 'invoice',
    getFormPayload: () => currentPayload,
    applyFormPayload: (p) => {
      appliedPayload = p
    },
    isEdit: true,
    recordId: 42,
    recordUpdatedAt: '2026-11-27T12:00:00Z',
  })

  // 5. Restore draft in edit mode strips server-owned identity, timestamps, and fields relation
  mockWebStorage.setItem(
    editDraft.storageKey.value,
    JSON.stringify({
      savedAt: '2026-11-27T13:00:00Z',
      baseUpdatedAt: '2026-11-27T11:00:00Z',
      payload: {
        id: 9999,
        updated_at: '2026-11-20T00:00:00Z',
        created_at: '2026-11-01T00:00:00Z',
        fields: [{ custom_field_id: 1, default_answer: 'server-val' }],
        invoice_number: 'INV-RESTORED',
        total: 1234,
      },
    })
  )

  editDraft.initBaseline()
  assert.equal(editDraft.hasExistingDraft.value, true)
  assert.equal(editDraft.existingDraftInfo.value.isStale, true, 'Draft based on older revision must be flagged as stale')
  editDraft.restoreDraft()

  assert.equal(appliedPayload?.id, undefined, 'Restored payload must strip server id in edit mode')
  assert.equal(appliedPayload?.updated_at, undefined, 'Restored payload must strip updated_at in edit mode')
  assert.equal(appliedPayload?.created_at, undefined, 'Restored payload must strip created_at in edit mode')
  assert.equal(appliedPayload?.fields, undefined, 'Restored payload must strip fields relation in edit mode')
  assert.equal(appliedPayload?.invoice_number, 'INV-RESTORED')
  assert.equal(appliedPayload?.total, 1234)

  // 6. Server save eviction
  editDraft.markSavedToServer()
  assert.equal(mockWebStorage.getItem(editDraft.storageKey.value), null)
  assert.equal(editDraft.saveStatus.value, 'saved_to_server')

  // 7. clearAllFormDrafts purges all crater_draft:* keys on logout and disarms saving
  mockWebStorage.setItem('crater_draft:1:1:payment:create', 'data')
  mockWebStorage.setItem('crater_draft:1:2:estimate:edit_5', 'data')
  mockWebStorage.setItem('auth.token', 'Bearer session-token')
  mockWebStorage.setItem('company_id', '1')

  clearAllFormDrafts()
  assert.equal(mockWebStorage.getItem('crater_draft:1:1:payment:create'), null)
  assert.equal(mockWebStorage.getItem('crater_draft:1:2:estimate:edit_5'), null)
  assert.equal(mockWebStorage.getItem('auth.token'), 'Bearer session-token')
  assert.equal(mockWebStorage.getItem('company_id'), '1')

  // Disarmed saveDraftToStorage must not write back to storage after logout
  draft.saveDraftToStorage()
  assert.equal(mockWebStorage.getItem(draft.storageKey.value), null, 'Draft saving must be disarmed after clearAllFormDrafts')
}

// 8. Server resource modification timestamp exposure for accurate stale checks
const invoiceResourceSource = await read('app/Http/Resources/InvoiceResource.php')
assert.equal(invoiceResourceSource.includes("'updated_at' =>"), true, 'InvoiceResource must expose updated_at')

const estimateResourceSource = await read('app/Http/Resources/EstimateResource.php')
assert.equal(estimateResourceSource.includes("'updated_at' =>"), true, 'EstimateResource must expose updated_at')

const paymentResourceSource = await read('app/Http/Resources/PaymentResource.php')
assert.equal(paymentResourceSource.includes("'updated_at' =>"), true, 'PaymentResource must expose updated_at')

// 9. Exchange rate auto-fetch suppression during draft restoration
const exchangeRateConverterSource = await read('resources/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue')
assert.equal(
  exchangeRateConverterSource.includes('!props.store?.isRestoringDraft'),
  true,
  'ExchangeRateConverter must suppress auto-fetching rates when store is restoring a draft'
)

console.log('Frontend regression checks passed')
