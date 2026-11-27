import { createApp } from 'vue'
import App from '@/scripts/App.vue'
import { createI18n } from 'vue-i18n'
import messages from '@/scripts/locales/locales'
import router from '@/scripts/router/index'
import { defineGlobalComponents } from './global-components'
import utils from '@/scripts/helpers/utilities.js'
import _ from 'lodash'
import { vTooltip } from 'floating-vue'
import { createPinia } from 'pinia'

const app = createApp(App)

export default class Crater {
  constructor() {
    this.bootingCallbacks = []
    this.messages = messages
  }

  booting(callback) {
    this.bootingCallbacks.push(callback)
  }

  executeCallbacks() {
    this.bootingCallbacks.forEach((callback) => {
      callback(app, router)
    })
  }

  addMessages(moduleMessages = []) {
    _.merge(this.messages, moduleMessages)
  }

  start() {
    this.executeCallbacks()

    defineGlobalComponents(app)

    app.provide('$utils', utils)

    const i18n = createI18n({
      locale: 'en',
      fallbackLocale: 'en',
      globalInjection: true,
      messages: this.messages,
    })

    const tcShim = (key, count, options) => {
      return typeof count === 'number'
        ? i18n.global.t(key, count, options)
        : i18n.global.t(key, options)
    }

    i18n.global.tc = tcShim
    i18n.global.$tc = tcShim
    app.config.globalProperties.$tc = tcShim
    app.config.globalProperties.tc = tcShim

    window.i18n = i18n

    app.use(router)
    app.use(i18n)
    app.use(createPinia())
    app.provide('utils', utils)
    app.directive('tooltip', vTooltip)

    app.mount('body')
  }
}
