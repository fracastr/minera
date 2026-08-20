import { onMounted, onBeforeUnmount } from '@vue/composition-api'

const resolveEl = value => {
  if (!value) return null
  return value.$el || value
}

export default function useCloseOnOutsidePress(elementRef, isOpen, close) {
  const onPress = event => {
    if (!isOpen.value) return

    const root = resolveEl(elementRef.value)
    if (!root || root.contains(event.target)) return

    close()
  }

  onMounted(() => {
    document.addEventListener('pointerdown', onPress, true)
    document.addEventListener('touchstart', onPress, true)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPress, true)
    document.removeEventListener('touchstart', onPress, true)
  })
}
