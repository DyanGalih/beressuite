import axios from 'axios'
import { defineStore } from 'pinia'
import { handleError } from '@/scripts/helpers/error-handling'

// One-time plaintext secrets returned by create/rotate are never kept in
// store state; they are only returned to the caller (CWE-200 / CWE-312).
export const useApiTokenStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore

  return defineStoreFunc('api-token', {
    state: () => ({
      apiTokens: [],
      availableAbilities: {},
    }),

    actions: {
      fetchApiTokens() {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/api-tokens')
            .then((response) => {
              this.apiTokens = response.data.data
              this.availableAbilities = response.data.available_abilities || {}
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      createApiToken(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/api-tokens', data)
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      rotateApiToken(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/api-tokens/${id}/rotate`)
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      revokeApiToken(id) {
        return new Promise((resolve, reject) => {
          axios
            .delete(`/api/v1/api-tokens/${id}`)
            .then((response) => {
              this.apiTokens = this.apiTokens.filter((token) => token.id !== id)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },
    },
  })()
}
