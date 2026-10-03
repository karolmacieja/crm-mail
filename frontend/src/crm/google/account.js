/**
 * The Google account of the Gmail tab the CRM runs in (set by the content
 * script from InboxSDK). Google requests are authorised for this account, not
 * for the account Chrome itself is signed in with.
 */
let account = null

export function setGoogleAccount(email) {
  account = String(email ?? '').trim().toLowerCase() || null
}

export const googleAccount = () => account
