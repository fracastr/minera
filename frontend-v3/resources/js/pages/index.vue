<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Auth',
  },
})

const userData = useCookie('userData')
</script>

<template>
  <VCard>
    <VCardText>
      <h4 class="text-h4 mb-2">
        Hola, {{ userData?.fullName }}
      </h4>
      <p class="mb-4">
        Esta es la nueva interfaz de Sondek (Vue 3), en construcción. Las pantallas se irán moviendo aquí de a una;
        mientras tanto, todo el trabajo sigue en la versión actual.
      </p>
      <VBtn
        href="/"
        variant="tonal"
        prepend-icon="tabler-arrow-back-up"
      >
        Ir a la versión actual
      </VBtn>
    </VCardText>
  </VCard>
</template>
