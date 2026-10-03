import * as InboxSDK from '@inboxsdk/core'
import { createPinia } from 'pinia'
import { reactive } from 'vue'
import { createCrmApp } from '@/crm/createCrmApp.js'
import { setGoogleAccount } from '@/crm/google/account.js'
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
 * Who the conversation is with: senders of loaded messages (newest first)
 * that aren't the current user; for outgoing-only threads, the recipients.
 * Each participant carries the newest Gmail message id from them, used to
 * log that exact email on the client's timeline.
 */
async function extractParticipants(threadView, myEmail) {
  const messages = threadView.getMessageViewsAll().filter((mv) => safely(() => mv.isLoaded(), false))
  const seen = new Set([myEmail])
  const participants = []

  for (const messageView of [...messages].reverse()) {
    const sender = safely(() => messageView.getSender())
    const email = normalize(sender?.emailAddress)
    if (!email || seen.has(email)) continue
    seen.add(email)
    participants.push({
      email,
      name: sender.name && normalize(sender.name) !== email ? sender.name : '',
      messageId: await messageView.getMessageIDAsync().catch(() => null),
      snippet: safely(() => messageView.getBodyElement().innerText.trim().replace(/\s+/g, ' ').slice(0, 300), null),
    })
  }

  if (!participants.length && messages.length) {
    const latest = messages.at(-1)
    const recipients = await latest.getRecipientsFull().catch(() => safely(() => latest.getRecipients(), []))
    for (const contact of recipients) {
      const email = normalize(contact?.emailAddress)
      if (!email || seen.has(email)) continue
      seen.add(email)
      participants.push({ email, name: contact.name ?? '', messageId: await latest.getMessageIDAsync().catch(() => null), direction: 'out' })
    }
  }

  return participants
}

function registerThreadPanel(sdk, provide) {
  const myEmail = normalize(sdk.User.getEmailAddress())

  sdk.Conversations.registerThreadViewHandler((threadView) => {
    const host = document.createElement('div')
    host.style.height = '100%'
    const state = reactive({ participants: [], subject: safely(() => threadView.getSubject(), ''), threadId: null, resolving: true })

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

    Promise.all([extractParticipants(threadView, myEmail), threadView.getThreadIDAsync().catch(() => null)])
      .then(([participants, threadId]) => {
        Object.assign(state, { participants, threadId })
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
    const sender = safely(() => row.getContacts(), []).find((c) => normalize(c.emailAddress) !== myEmail)
    const threadId = await row.getThreadIDAsync().catch(() => null)
    if (!sender || !threadId) return

    batch.push({
      threadId,
      email: normalize(sender.emailAddress),
      name: sender.name ?? '',
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
