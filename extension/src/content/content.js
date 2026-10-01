import * as InboxSDK from '@inboxsdk/core'
import { createPinia } from 'pinia'
import { reactive } from 'vue'
import { DASHBOARD_ROUTE_ID, INBOXSDK_APP_ID } from '@/lib/config.js'
import DashboardApp from '@/dashboard/DashboardApp.vue'
import SidebarApp from '@/sidebar/SidebarApp.vue'
import { mountIsolated } from './mount.js'

const LOG_PREFIX = '[Gmail CRM]'
const iconUrl = chrome.runtime.getURL('icons/icon-48.png')

// One Pinia instance for every Vue app in this tab -> sidebar and dashboard share state.
const pinia = createPinia()

const normalize = (email) => String(email ?? '').trim().toLowerCase()

function safely(fn, fallback = null) {
  try {
    return fn()
  } catch {
    return fallback
  }
}

/**
 * Work out who the conversation is *with*:
 *  1. senders of loaded messages (newest first) that aren't the current user;
 *  2. if the user sent every message, the recipients of the latest one.
 * Returns a de-duplicated list, most relevant first.
 */
async function extractParticipants(threadView, myEmail) {
  const messages = threadView.getMessageViewsAll().filter((mv) => safely(() => mv.isLoaded(), false))
  const seen = new Set([myEmail])
  const participants = []

  const add = (contact) => {
    const email = normalize(contact?.emailAddress)
    if (!email || seen.has(email)) return
    seen.add(email)
    participants.push({ email, name: contact.name && normalize(contact.name) !== email ? contact.name : '' })
  }

  for (const messageView of [...messages].reverse()) {
    add(safely(() => messageView.getSender()))
  }

  // Outgoing-only threads: fall back to recipients (To/CC) of the newest messages.
  for (const messageView of [...messages].reverse()) {
    if (participants.length) break
    const recipients = await messageView.getRecipientsFull().catch(() => safely(() => messageView.getRecipients(), []))
    recipients.forEach(add)
  }

  return participants
}

function registerSidebar(sdk, provide) {
  const myEmail = normalize(sdk.User.getEmailAddress())

  sdk.Conversations.registerThreadViewHandler((threadView) => {
    // Wrapper element owned by InboxSDK; our UI lives in its shadow root.
    const host = document.createElement('div')
    const state = reactive({ participants: [], resolving: true })

    const panel = threadView.addSidebarContentPanel({
      id: 'gmail-crm-contact',
      title: 'CRM',
      iconUrl,
      appName: 'Gmail CRM',
      appIconUrl: iconUrl,
      el: host,
    })

    const mounted = mountIsolated(host, SidebarApp, { props: state, pinia, provide })

    const cleanup = () => mounted.unmount()
    threadView.on('destroy', cleanup)
    panel.on('destroy', cleanup)

    extractParticipants(threadView, myEmail)
      .then((participants) => {
        state.participants = participants
      })
      .catch((error) => console.error(LOG_PREFIX, 'Could not read thread participants', error))
      .finally(() => {
        state.resolving = false
      })
  })
}

function registerDashboard(sdk, provide) {
  sdk.Router.handleCustomRoute(DASHBOARD_ROUTE_ID, (routeView) => {
    routeView.setFullWidth(true)

    const host = document.createElement('div')
    host.style.cssText = 'height:100%;overflow:auto;'
    routeView.getElement().appendChild(host)

    const mounted = mountIsolated(host, DashboardApp, { pinia, provide })
    routeView.on('destroy', () => mounted.unmount())
  })

  // Left navigation entry …
  sdk.NavMenu.addNavItem({
    name: 'CRM Dashboard',
    routeID: DASHBOARD_ROUTE_ID,
    iconUrl,
    orderHint: 0,
  })

  // … and a global toolbar button (top right) that opens the same route.
  sdk.Toolbars.addToolbarButtonForApp({
    title: 'CRM',
    iconUrl,
    onClick: ({ dropdown }) => {
      dropdown?.close()
      sdk.Router.goto(DASHBOARD_ROUTE_ID)
    },
  })
}

async function main() {
  const sdk = await InboxSDK.load(2, INBOXSDK_APP_ID, {
    appName: 'Gmail CRM',
    appIconUrl: iconUrl,
  })

  // Gmail-specific helpers exposed to components via inject('gmail').
  const gmail = {
    openDashboard: () => sdk.Router.goto(DASHBOARD_ROUTE_ID),
    searchEmail: (email) =>
      sdk.Router.goto(sdk.Router.NativeRouteIDs.SEARCH, { query: `from:${email} OR to:${email}`, page: 1 }),
  }
  const provide = { gmail }

  registerSidebar(sdk, provide)
  registerDashboard(sdk, provide)
}

main().catch((error) => {
  console.error(LOG_PREFIX, 'Failed to initialise InboxSDK', error)
})
