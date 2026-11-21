<script setup lang="ts">
import { CircleAlert, CircleCheck, Info } from '@lucide/vue'

const props = defineProps<{ tone: 'info' | 'ok' | 'danger' }>()
const icon = { info: Info, ok: CircleCheck, danger: CircleAlert }[props.tone]
const style = {
  info: 'bg-primary-soft text-ink [&_svg]:text-primary',
  ok: 'bg-ok-soft text-ink [&_svg]:text-ok',
  danger: 'bg-danger-soft text-ink [&_svg]:text-danger',
}[props.tone]
</script>

<template>
  <div :role="tone === 'danger' ? 'alert' : 'status'" class="flex gap-2.5 rounded-md px-3 py-2.5 text-[0.8125rem] leading-snug" :class="style">
    <component :is="icon" class="mt-px size-4 shrink-0" aria-hidden="true" />
    <div><slot /></div>
  </div>
</template>
