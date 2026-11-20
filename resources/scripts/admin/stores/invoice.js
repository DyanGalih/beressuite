import axios from 'axios'
import moment from 'moment'
import _ from 'lodash'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import invoiceItemStub from '../stub/invoice-item'
import taxStub from '../stub/tax'
import invoiceStub from '../stub/invoice'

import { useNotificationStore } from '@/scripts/stores/notification'
import { useCustomerStore } from './customer'
import { useTaxTypeStore } from './tax-type'
import { useCompanyStore } from './company'
import { useItemStore } from './item'
import { useUserStore } from './user'
import createUuid from '@/scripts/helpers/uuid'

const PAID_STATUS_VALUES = new Set(['PAID', 'UNPAID', 'PARTIALLY_PAID'])
const EXPORT_POLL_INTERVAL_MS = 1000
const EXPORT_POLL_MAX_ATTEMPTS = 30

function buildExportPayload(filters = {}) {
  const payload = {}

  if (filters.status) {
    if (PAID_STATUS_VALUES.has(filters.status)) {
      payload.paid_status = filters.status
    } else {
      payload.status = filters.status
    }
  }

  if (filters.customer_id !== '' && filters.customer_id !== null && filters.customer_id !== undefined) {
    const customerId = Number(filters.customer_id)
    if (Number.isInteger(customerId) && customerId > 0) {
      payload.customer_id = customerId
    }
  }

  if (filters.from_date && filters.to_date) {
    payload.from_date = filters.from_date
    payload.to_date = filters.to_date
  }

  if (filters.invoice_number) {
    payload.invoice_number = filters.invoice_number
  }

  return payload
}

function sanitizeExportFileName(fileName) {
  if (typeof fileName !== 'string' || !fileName.trim()) {
    return 'invoices-export.xlsx'
  }

  const baseName = fileName.replace(/^.*[\\/]/, '').replace(/[^a-zA-Z0-9._-]/g, '_')
  if (!baseName) {
    return 'invoices-export.xlsx'
  }

  return baseName.toLowerCase().endsWith('.xlsx') ? baseName : `${baseName}.xlsx`
}

function triggerBlobDownload(blob, fileName) {
  const blobUrl = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = blobUrl
  link.setAttribute('download', sanitizeExportFileName(fileName))
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(blobUrl)
}

export const useInvoiceStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n
  const notificationStore = useNotificationStore()

  return defineStoreFunc('invoice', {
    state: () => ({
      templates: [],
      invoices: [],
      selectedInvoices: [],
      selectAllField: false,
      invoiceTotalCount: 0,
      showExchangeRate: false,
      isFetchingInitialSettings: false,
      isFetchingInvoice: false,
      isExporting: false,

      newInvoice: {
        ...invoiceStub(),
      },
    }),

    getters: {
      getInvoice: (state) => (id) => {
        let invId = parseInt(id)
        return state.invoices.find((invoice) => invoice.id === invId)
      },

      getSubTotal() {
        return this.newInvoice.items.reduce(function (a, b) {
          return a + b['total']
        }, 0)
      },

      getTotalSimpleTax() {
        return _.sumBy(this.newInvoice.taxes, function (tax) {
          if (!tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalCompoundTax() {
        return _.sumBy(this.newInvoice.taxes, function (tax) {
          if (tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalTax() {
        if (
          this.newInvoice.tax_per_item === 'NO' ||
          this.newInvoice.tax_per_item === null
        ) {
          return this.getTotalSimpleTax + this.getTotalCompoundTax
        }
        return _.sumBy(this.newInvoice.items, function (tax) {
          return tax.tax
        })
      },

      getSubtotalWithDiscount() {
        return this.getSubTotal - this.newInvoice.discount_val
      },

      getTotal() {
        return this.getSubtotalWithDiscount + this.getTotalTax
      },

      isEdit: (state) => (state.newInvoice.id ? true : false),
    },

    actions: {
      resetCurrentInvoice() {
        this.newInvoice = {
          ...invoiceStub(),
        }
      },

      previewInvoice(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/${params.id}/send/preview`, { params })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoices(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices`, { params })
            .then((response) => {
              this.invoices = response.data.data
              this.invoiceTotalCount = response.data.meta.invoice_total_count
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/${id}`)
            .then((response) => {
              Object.assign(this.newInvoice, response.data.data)
              this.newInvoice.customer = response.data.data.customer
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addSalesTaxUs() {
        const taxTypeStore = useTaxTypeStore()
        let salesTax = { ...taxStub }
        let found = this.newInvoice.taxes.find(
          (_t) => _t.name === 'Sales Tax' && _t.type === 'MODULE',
        )
        if (found) {
          for (const key in found) {
            if (Object.prototype.hasOwnProperty.call(salesTax, key)) {
              salesTax[key] = found[key]
            }
          }
          salesTax.id = found.tax_type_id
          taxTypeStore.taxTypes.push(salesTax)
        }
      },

      sendInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/send`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.invoice_sent_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/invoices', data)
            .then((response) => {
              this.invoices = [...this.invoices, response.data.invoice]

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.created_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/delete`, id)
            .then((response) => {
              let index = this.invoices.findIndex(
                (invoice) => invoice.id === id,
              )
              this.invoices.splice(index, 1)

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.deleted_message', 1),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleInvoices(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/delete`, { ids: this.selectedInvoices })
            .then((response) => {
              this.selectedInvoices.forEach((invoice) => {
                let index = this.invoices.findIndex(
                  (_inv) => _inv.id === invoice.id,
                )
                this.invoices.splice(index, 1)
              })
              this.selectedInvoices = []

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.deleted_message', 2),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/invoices/${data.id}`, data)
            .then((response) => {
              let pos = this.invoices.findIndex(
                (invoice) => invoice.id === response.data.data.id,
              )
              this.invoices[pos] = response.data.data

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.updated_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      cloneInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/clone`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.cloned_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      markAsSent(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/status`, data)
            .then((response) => {
              let pos = this.invoices.findIndex(
                (invoices) => invoices.id === data.id,
              )

              if (this.invoices[pos]) {
                this.invoices[pos].status = 'SENT'
              }

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.mark_as_sent_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      getNextNumber(params, setState = false) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/next-number?key=invoice`, { params })
            .then((response) => {
              if (setState) {
                this.newInvoice.invoice_number = response.data.nextNumber
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      searchInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices?${data}`)
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectInvoice(data) {
        this.selectedInvoices = data
        if (this.selectedInvoices.length === this.invoices.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllInvoices() {
        if (this.selectedInvoices.length === this.invoices.length) {
          this.selectedInvoices = []
          this.selectAllField = false
        } else {
          let allInvoiceIds = this.invoices.map((invoice) => invoice.id)
          this.selectedInvoices = allInvoiceIds
          this.selectAllField = true
        }
      },

      selectCustomer(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/customers/${id}`)
            .then((response) => {
              this.newInvoice.customer = response.data.data
              this.newInvoice.customer_id = response.data.data.id
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoiceTemplates(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/templates`, { params })
            .then((response) => {
              this.templates = response.data.invoiceTemplates
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectNote(data) {
        this.newInvoice.selectedNote = null
        this.newInvoice.selectedNote = data
      },

      setTemplate(data) {
        this.newInvoice.template_name = data
      },

      resetSelectedCustomer() {
        this.newInvoice.customer = null
        this.newInvoice.customer_id = null
      },

      addItem() {
        this.newInvoice.items.push({
          ...invoiceItemStub,
          id: createUuid(),
          taxes: [{ ...taxStub, id: createUuid() }],
        })
      },

      updateItem(data) {
        Object.assign(this.newInvoice.items[data.index], { ...data })
      },

      removeItem(index) {
        this.newInvoice.items.splice(index, 1)
      },

      deselectItem(index) {
        this.newInvoice.items[index] = {
          ...invoiceItemStub,
          id: createUuid(),
          taxes: [{ ...taxStub, id: createUuid() }],
        }
      },

      resetSelectedNote() {
        this.newInvoice.selectedNote = null
      },

      // On Load actions
      async fetchInvoiceInitialSettings(isEdit) {
        const companyStore = useCompanyStore()
        const customerStore = useCustomerStore()
        const itemStore = useItemStore()
        const taxTypeStore = useTaxTypeStore()
        const route = useRoute()
        const userStore = useUserStore()

        this.isFetchingInitialSettings = true

        this.newInvoice.selectedCurrency = companyStore.selectedCompanyCurrency

        if (route.query.customer) {
          let response = await customerStore.fetchCustomer(route.query.customer)
          this.newInvoice.customer = response.data.data
          this.newInvoice.customer_id = response.data.data.id
        }

        let editActions = []

        if (!isEdit) {
          this.newInvoice.tax_per_item =
            companyStore.selectedCompanySettings.tax_per_item
          this.newInvoice.sales_tax_type =
            companyStore.selectedCompanySettings.sales_tax_type
          this.newInvoice.sales_tax_address_type =
            companyStore.selectedCompanySettings.sales_tax_address_type
          this.newInvoice.discount_per_item =
            companyStore.selectedCompanySettings.discount_per_item
          this.newInvoice.invoice_date = moment().format('YYYY-MM-DD')
          if (
            companyStore.selectedCompanySettings
              .invoice_set_due_date_automatically === 'YES'
          ) {
            this.newInvoice.due_date = moment()
              .add(
                companyStore.selectedCompanySettings.invoice_due_date_days,
                'days',
              )
              .format('YYYY-MM-DD')
          }
        } else {
          editActions = [this.fetchInvoice(route.params.id)]
        }

        Promise.all([
          itemStore.fetchItems({
            filter: {},
            orderByField: '',
            orderBy: '',
          }),
          this.resetSelectedNote(),
          this.fetchInvoiceTemplates(),
          this.getNextNumber(),
          taxTypeStore.fetchTaxTypes({ limit: 'all' }),
          ...editActions,
        ])
          .then(async ([res1, res2, res3, res4, res5, res6]) => {
            if (!isEdit) {
              if (res4.data) {
                this.newInvoice.invoice_number = res4.data.nextNumber
              }

              if (res3.data) {
                this.setTemplate(this.templates[0].name)
                this.newInvoice.template_name = userStore.currentUserSettings
                  .default_invoice_template
                  ? userStore.currentUserSettings.default_invoice_template
                  : this.newInvoice.template_name
              }
            }
            if (isEdit) {
              this.addSalesTaxUs()
            }

            this.isFetchingInitialSettings = false
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      },

      async exportInvoices(filters = {}) {
        if (this.isExporting) {
          return null
        }

        this.isExporting = true

        try {
          const payload = buildExportPayload(filters)
          const createResponse = await axios.post('/api/v1/invoices/export', payload)
          let exportRecord = createResponse.data?.data ?? createResponse.data

          // ponytail: client polling caps at 30s; upgrade to an exports history page or push notification if multi-minute exports are needed.
          const pollDeadline = Date.now() + (EXPORT_POLL_MAX_ATTEMPTS * EXPORT_POLL_INTERVAL_MS)
          while (
            exportRecord &&
            (exportRecord.status === 'pending' || exportRecord.status === 'processing') &&
            Date.now() < pollDeadline
          ) {
            const remainingMs = pollDeadline - Date.now()
            if (remainingMs <= 0) break
            await new Promise((resolve) => setTimeout(resolve, Math.min(EXPORT_POLL_INTERVAL_MS, remainingMs)))
            const statusResponse = await axios.get(`/api/v1/invoices/export/${exportRecord.id}`, {
              timeout: Math.max(1000, remainingMs),
            })
            exportRecord = statusResponse.data?.data ?? statusResponse.data
          }

          if (!exportRecord || exportRecord.status === 'failed') {
            notificationStore.showNotification({
              type: 'error',
              message: exportRecord?.error_message || global.t('invoices.export_failed'),
            })
            return null
          }

          if (exportRecord.status !== 'completed') {
            notificationStore.showNotification({
              type: 'error',
              message: global.t('invoices.export_timeout'),
            })
            return null
          }

          const downloadResponse = await axios.get(
            `/api/v1/invoices/export/${exportRecord.id}/download`,
            { responseType: 'blob' }
          )

          triggerBlobDownload(downloadResponse.data, exportRecord.file_name)

          notificationStore.showNotification({
            type: 'success',
            message: global.t('invoices.export_success'),
          })

          return exportRecord
        } catch (err) {
          handleError(err)
          return null
        } finally {
          this.isExporting = false
        }
      },
    },
  })()
}
