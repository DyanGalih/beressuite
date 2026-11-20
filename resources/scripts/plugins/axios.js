import axios from 'axios'
import Ls from '@/scripts/services/ls.js'
import { handleUnauthorizedRedirect } from '@/scripts/helpers/auth-redirect'

window.Ls = Ls
window.axios = axios
axios.defaults.withCredentials = true

axios.defaults.headers.common = {
  'X-Requested-With': 'XMLHttpRequest',
}

/**
 * Interceptors
 */

axios.interceptors.request.use(function (config) {
  // Pass selected company to header on all requests
  const companyId = Ls.get('selectedCompany')

  const authToken = Ls.get('auth.token')

  if (authToken) {
    config.headers.Authorization = authToken
  }

  if (companyId) {
    config.headers.company = companyId
  }

  return config
})

axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      const msg = error.response.data?.message || 'Unauthorized'
      handleUnauthorizedRedirect(msg)
    }

    return Promise.reject(error)
  }
)
