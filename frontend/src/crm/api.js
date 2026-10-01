/**
 * HTTP client of the CRM (inside Gmail): axios whose requests are executed
 * by the extension's service worker with the staff member's bearer token.
 */
export { api, ApiError, onApiError, toApiError } from '@/extension/api.js'
