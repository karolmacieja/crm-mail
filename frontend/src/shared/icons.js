import { config, library } from '@fortawesome/fontawesome-svg-core'
import {
  faBell as farBell,
  faClock as farClock,
  faEnvelope as farEnvelope,
  faPenToSquare as farPenToSquare,
} from '@fortawesome/free-regular-svg-icons'
import {
  faAddressBook,
  faArrowLeft,
  faBriefcase,
  faBuilding,
  faCakeCandles,
  faCalendarCheck,
  faChartPie,
  faCheck,
  faChevronRight,
  faCircleExclamation,
  faClockRotateLeft,
  faEnvelope,
  faFilter,
  faFire,
  faKey,
  faLayerGroup,
  faListCheck,
  faMagnifyingGlass,
  faPen,
  faPhone,
  faPhoneVolume,
  faPlus,
  faRightFromBracket,
  faReply,
  faStar,
  faTrash,
  faUser,
  faUsers,
  faUserTie,
  faUtensils,
  faXmark,
  faFileInvoice,
  faArrowUpRightFromSquare,
} from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

// Styles come from tailwind.css (so they reach shadow roots); don't inject into <head>.
config.autoAddCss = false

library.add(
  farBell, farClock, farEnvelope, farPenToSquare,
  faAddressBook, faArrowLeft, faBriefcase, faBuilding, faCakeCandles, faCalendarCheck, faChartPie, faCheck,
  faChevronRight, faCircleExclamation, faClockRotateLeft, faEnvelope, faFilter, faFire, faKey, faLayerGroup,
  faListCheck, faMagnifyingGlass, faPen, faPhone, faPhoneVolume, faPlus, faRightFromBracket, faReply, faStar,
  faTrash, faUser, faUsers, faUserTie, faUtensils, faXmark, faFileInvoice, faArrowUpRightFromSquare,
)

/**
 * <Icon icon="users" />, <Icon :icon="['far', 'envelope']" />
 * Only the icons registered above end up in the bundle.
 */
export const Icon = FontAwesomeIcon
