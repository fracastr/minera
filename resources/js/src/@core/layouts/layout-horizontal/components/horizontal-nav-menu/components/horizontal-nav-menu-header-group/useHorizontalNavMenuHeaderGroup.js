import { ref } from '@vue/composition-api'
import { isNavGroupActive } from '@core/layouts/utils'
import useCloseOnOutsidePress from '@core/layouts/useCloseOnOutsidePress'

export default function useHorizontalNavMenuHeaderGroup(item) {
  // ------------------------------------------------
  // isOpen
  // ------------------------------------------------
  const isOpen = ref(false)
  const groupEl = ref(null)

  const updateGroupOpen = val => {
    // eslint-disable-next-line no-use-before-define
    isOpen.value = val
  }

  useCloseOnOutsidePress(groupEl, isOpen, () => updateGroupOpen(false))

  // ------------------------------------------------
  // isActive
  // ------------------------------------------------
  const isActive = ref(false)

  const updateIsActive = () => {
    isActive.value = isNavGroupActive(item.children)
  }

  return {
    isOpen,
    isActive,
    groupEl,
    updateGroupOpen,
    updateIsActive,
  }
}
