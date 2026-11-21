<script setup lang="ts">
import { CircleAlert, CircleCheck, X } from '@lucide/vue'
import {
  AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogOverlay, AlertDialogPortal,
  AlertDialogRoot, AlertDialogTitle, ToastClose, ToastDescription, ToastProvider, ToastRoot, ToastViewport,
} from 'reka-ui'
import { computed } from 'vue'
import { answer, pending, toasts } from '@/lib/feedback'

// Toasts (bottom right) and the one pending ask() dialog, mounted once in the shell.
const open = computed({ get: () => !!pending.value, set: v => !v && answer(null) })
function remove(id: number) {
  const i = toasts.findIndex(t => t.id === id)
  if (i >= 0) toasts.splice(i, 1)
}
const tone = { primary: 'btn-primary', danger: 'bg-danger text-white hover:opacity-90', quiet: 'btn-quiet' }
</script>

<template>
  <ToastProvider swipe-direction="right">
    <ToastRoot
      v-for="t in toasts" :key="t.id"
      :duration="t.tone === 'danger' ? Infinity : 4000"
      class="toast flex items-start gap-2.5 rounded-lg border border-line bg-surface p-3 pr-2 text-[0.8125rem] shadow-[0_8px_24px_rgb(16_20_40/0.16)]"
      @update:open="v => !v && remove(t.id)"
    >
      <component :is="t.tone === 'danger' ? CircleAlert : CircleCheck" class="mt-px size-4 shrink-0" :class="t.tone === 'danger' ? 'text-danger' : 'text-ok'" aria-hidden="true" />
      <ToastDescription class="flex-1 leading-snug">
        {{ t.message }}
        <a v-if="t.link" :href="t.link.href" target="_blank" rel="noopener noreferrer" class="mt-1 block font-medium text-primary underline">{{ t.link.label }}</a>
      </ToastDescription>
      <ToastClose class="grid size-6 place-items-center rounded text-ink-faint hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
        <X class="size-3.5" aria-hidden="true" />
      </ToastClose>
    </ToastRoot>
    <ToastViewport class="fixed right-0 bottom-0 z-[60] flex w-full max-w-sm flex-col gap-2 p-4 outline-none" />
  </ToastProvider>

  <AlertDialogRoot v-model:open="open">
    <AlertDialogPortal>
      <AlertDialogOverlay class="fixed inset-0 z-50 bg-navy/45" />
      <AlertDialogContent
        v-if="pending"
        class="dialog fixed top-1/2 left-1/2 z-50 w-[min(28rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 rounded-lg border border-line bg-surface p-5 shadow-[0_16px_48px_rgb(16_20_40/0.22)]"
      >
        <AlertDialogTitle class="font-display text-base font-semibold">{{ pending.title }}</AlertDialogTitle>
        <AlertDialogDescription class="mt-2 text-ink-muted">{{ pending.body }}</AlertDialogDescription>
        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <AlertDialogCancel class="btn btn-quiet">Cancelar</AlertDialogCancel>
          <!-- Plain buttons: AlertDialogAction would close first and resolve the question as cancelled. -->
          <button
            v-for="a in pending.actions" :key="a.value" type="button"
            class="btn" :class="tone[a.tone ?? 'primary']"
            @click="answer(a.value)"
          >
            {{ a.label }}
          </button>
        </div>
      </AlertDialogContent>
    </AlertDialogPortal>
  </AlertDialogRoot>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
  .toast[data-state='open'] {
    animation: toast-in 220ms var(--ease-out-expo);
  }
  .toast[data-state='closed'] {
    animation: toast-in 150ms ease-in reverse;
  }
  .dialog[data-state='open'] {
    animation: dialog-in 200ms var(--ease-out-expo);
  }
}
@keyframes toast-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
}
@keyframes dialog-in {
  from {
    opacity: 0;
    transform: translate(-50%, calc(-50% + 6px)) scale(0.98);
  }
}
</style>
