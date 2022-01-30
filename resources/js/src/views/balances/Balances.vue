<template>
  <div>
    <b-alert
      variant="primary"
      show
    >
      <div class="alert-body">
        <p>
          <strong>Info: </strong>
          <span>This layout can be useful for getting started with empty content section.</span>
        </p>
      </div>
    </b-alert>
    <div>
        <b-form-file
        v-model="file"
        placeholder="Eliga su archivo"
        drop-placeholder="Suelte su archivo aqui"
        />
    </div>
  </div>

</template>

<script>
import { BAlert, BFormFile } from 'bootstrap-vue'

export default {
  components: {
    BAlert,
    BFormFile,
  },
}
</script>
