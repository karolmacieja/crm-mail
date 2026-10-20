import * as InboxSDK from '@inboxsdk/core'
import { createPinia } from 'pinia'
import { reactive } from 'vue'
import { createCrmApp } from '@/crm/createCrmApp.js'
import { setGoogleAccount } from '@/crm/google/account.js'
import { forwardedSender } from '@/crm/lib/senders.js'
import { useInboxStore } from '@/crm/stores/inbox.js'
import ThreadPanelApp from '@/crm/ThreadPanelApp.vue'
import { DASHBOARD_ROUTE_ID, INBOXSDK_APP_ID } from '@/shared/lib/config.js'
import { initLocale, t } from '@/shared/lib/i18n.js'
import { mountAppIsolated, mountIsolated } from './mount.js'

const LOG_PREFIX = '[GastroFlowx]'
const iconUrl = chrome.runtime.getURL('icons/icon-48.png')

// One Pinia instance for every Vue app in this tab: the full-page CRM and the
// thread panel share the same client/tasks/dashboard state.
const pinia = createPinia()

const normalize = (email) => String(email ?? '').trim().toLowerCase()

/**
 * Threads opened via "Odpowiedz" on the CRM timeline: once Gmail shows the
 * thread, its reply box is opened. InboxSDK has no reply API, so this clicks
 * Gmail's own "Reply" button (stable .ams.bkH class); if Gmail changes it,
 * the thread simply stays open.
 */
const pendingReplies = new Set()
const REPLY_BUTTON = '.ams.bkH'

function openReplyBox() {
  let tries = 0
  const timer = setInterval(() => {
    const button = [...document.querySelectorAll(REPLY_BUTTON)].findLast((el) => el.offsetParent !== null)
    if (button || ++tries > 25) {
      clearInterval(timer)
      button?.click()
    }
  }, 200)
}

function safely(fn, fallback = null) {
  try {
    return fn()
  } catch {
    return fallback
  }
}

/**
 * Loaded messages of a thread, newest first: sender, Gmail message id, a
 * snippet and – for forwards – the sender named in the forwarded header
 * block; recipients of the newest one (for outgoing-only threads).
 * Who the client is gets decided in ThreadPanelApp (resolveParticipants),
 * which knows the person's internal addresses.
 */
async function extractMessages(threadView) {
  const views = threadView.getMessageViewsAll().filter((mv) => safely(() => mv.isLoaded(), false)).reverse()
  const messages = []

  for (const [index, messageView] of views.entries()) {
    const sender = safely(() => messageView.getSender())
    const body = safely(() => messageView.getBodyElement().innerText, '') ?? ''
    messages.push({
      email: normalize(sender?.emailAddress),
      name: sender?.name ?? '',
      messageId: await messageView.getMessageIDAsync().catch(() => null),
      snippet: body.trim().replace(/\s+/g, ' ').slice(0, 300) || null,
      forwarded: forwardedSender(body),
      recipients: index === 0
        ? (await messageView.getRecipientsFull().catch(() => safely(() => messageView.getRecipients(), [])) ?? [])
          .map((contact) => ({ email: normalize(contact?.emailAddress), name: contact?.name ?? '' }))
        : [],
    })
  }

  return messages
}

function registerThreadPanel(sdk, provide) {
  const myEmail = normalize(sdk.User.getEmailAddress())

  sdk.Conversations.registerThreadViewHandler((threadView) => {
    const host = document.createElement('div')
    host.style.height = '100%'
    const state = reactive({ messages: [], myEmail, subject: safely(() => threadView.getSubject(), ''), threadId: null, resolving: true })

    const panel = threadView.addSidebarContentPanel({
      id: 'gastroflowx-client',
      title: 'GastroFlowx',
      iconUrl,
      appName: 'GastroFlowx',
      appIconUrl: iconUrl,
      el: host,
    })

    const mounted = mountIsolated(host, ThreadPanelApp, { props: state, pinia, provide })
    const cleanup = () => mounted.unmount()
    threadView.on('destroy', cleanup)
    panel.on('destroy', cleanup)

    Promise.all([extractMessages(threadView), threadView.getThreadIDAsync().catch(() => null)])
      .then(([messages, threadId]) => {
        Object.assign(state, { messages, threadId })
        if (threadId && pendingReplies.delete(threadId)) openReplyBox()
      })
      .catch((error) => console.error(LOG_PREFIX, 'Could not read thread participants', error))
      .finally(() => {
        state.resolving = false
      })
  })
}

/** Feed "Ostatnie maile" with the rows Gmail renders in the inbox. */
function registerInboxCapture(sdk) {
  const myEmail = normalize(sdk.User.getEmailAddress())
  const inbox = useInboxStore(pinia)
  let batch = []
  let timer = null

  sdk.Lists.registerThreadRowViewHandler(async (row) => {
    if (!/^#inbox/.test(window.location.hash || '#inbox')) return
    // All people of the row; the inbox store skips internal ones (info@, colleagues).
    const contacts = (safely(() => row.getContacts(), []) ?? [])
      .map((c) => ({ email: normalize(c.emailAddress), name: c.name ?? '' }))
      .filter((c) => c.email && c.email !== myEmail)
    const threadId = await row.getThreadIDAsync().catch(() => null)
    if (!threadId) return

    batch.push({
      threadId,
      contacts,
      myEmail,
      subject: safely(() => row.getSubject(), ''),
      date: safely(() => row.getDateString(), ''),
      // Best effort: Gmail marks unread rows with the "zE" class.
      unread: safely(() => row.getElement().classList.contains('zE'), false),
    })
    clearTimeout(timer)
    timer = setTimeout(() => {
      inbox.addThreads(batch)
      batch = []
    }, 200)
  })
}

function registerCrmRoute(sdk, provide) {
  let nextPath = null
  let lastPath = '/dashboard'

  sdk.Router.handleCustomRoute(DASHBOARD_ROUTE_ID, (routeView) => {
    routeView.setFullWidth(true)

    const host = document.createElement('div')
    host.style.cssText = 'height:100%;'
    routeView.getElement().appendChild(host)

    const app = createCrmApp({ pinia, gmail: provide.gmail, initialPath: nextPath ?? lastPath })
    nextPath = null
    const mounted = mountAppIsolated(host, app)
    const router = app.config.globalProperties.$router

    routeView.on('destroy', () => {
      // Coming back to the CRM restores the page the user was on.
      lastPath = router.currentRoute.value.fullPath
      mounted.unmount()
    })
  })

  sdk.NavMenu.addNavItem({ name: 'GastroFlowx', routeID: DASHBOARD_ROUTE_ID, iconUrl, orderHint: 0 })

  sdk.Toolbars.addToolbarButtonForApp({
    title: 'GastroFlowx',
    iconUrl,
    onClick: ({ dropdown }) => {
      dropdown?.close()
      sdk.Router.goto(DASHBOARD_ROUTE_ID)
    },
  })

  return {
    /** Open the full-page CRM at a path, e.g. "/clients/5". */
    open(path) {
      nextPath = path
      return sdk.Router.goto(DASHBOARD_ROUTE_ID)
    },
  }
}

async function main() {
  await initLocale()

  const sdk = await InboxSDK.load(2, INBOXSDK_APP_ID, { appName: 'GastroFlowx', appIconUrl: iconUrl })
  // Google integration acts as the account of this Gmail tab (not Chrome's profile account).
  setGoogleAccount(sdk.User.getEmailAddress())

  // Gmail helpers for components: inject('gmail').
  const gmail = {
    goInbox: () => sdk.Router.goto(sdk.Router.NativeRouteIDs.INBOX, { page: 1 }),
    openThread: (threadId) => sdk.Router.goto(sdk.Router.NativeRouteIDs.THREAD, { threadID: threadId }),
    replyToThread: (threadId) => {
      pendingReplies.add(threadId)
      return sdk.Router.goto(sdk.Router.NativeRouteIDs.THREAD, { threadID: threadId })
    },
    compose: async (email) => {
      const composeView = await sdk.Compose.openNewComposeView()
      composeView.setToRecipients([email])
      return composeView
    },
    searchEmail: (email) => sdk.Router.goto(sdk.Router.NativeRouteIDs.SEARCH, { query: `from:${email} OR to:${email}`, page: 1 }),
    openCrm: (path) => crmRoute.open(path),
  }
  const provide = { gmail }

  const crmRoute = registerCrmRoute(sdk, provide)
  registerThreadPanel(sdk, provide)
  registerInboxCapture(sdk)

  console.info(LOG_PREFIX, t('crm.layout.ready'))
}

main().catch((error) => {
  console.error(LOG_PREFIX, 'Failed to initialise InboxSDK', error)
})
