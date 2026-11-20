<script setup>
const { notifications } = useNotify()

const remove = id => {
  notifications.value = notifications.value.filter(n => n.id !== id)
}
</script>

<template>
  <VSnackbar
    v-for="(n, i) in notifications"
    :key="n.id"
    v-model="n.show"
    :color="n.color"
    :timeout="n.timeout"
    location="top end"
    :style="{ marginTop: `${i * 64}px` }"
    @update:model-value="v => !v && remove(n.id)"
  >
    <div
      v-if="n.title"
      class="font-weight-medium"
    >
      {{ n.title }}
    </div>
    {{ n.text }}
  </VSnackbar>
</template>
