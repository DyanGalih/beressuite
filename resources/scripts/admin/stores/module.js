import { defineStore } from 'pinia'

export const useModuleStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore

  return defineStoreFunc('modules', {
    state: () => ({
      enableModules: [],
    }),

    getters: {
      salesTaxUSEnabled: (state) => state.enableModules.includes('SalesTaxUS'),
    },
  })
}
