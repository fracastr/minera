<template>
  <b-card-code
    title="Table Responsive"
    no-body
  >
    <b-table
      responsive
      :items="items"
      :fields="fields"
      class="mb-0"
    >
      <template #cell(Phone)="data">
        <span class="text-nowrap">
          {{ data.value }}
        </span>
      </template>

      <!-- Optional default data cell scoped slot -->
      <template #cell()="data">
        {{ data.value }}
      </template>
    </b-table>

    <template #code>
      {{ codeResponsive }}
    </template>
  </b-card-code>
</template>

<script>
import BCardCode from '@core/components/b-card-code/BCardCode.vue'
import { BTable } from 'bootstrap-vue'
import { codeResponsive } from './code'

export default {
  components: {
    BCardCode,
    BTable,
  },
  data() {
    return {
      fields: [
        'id',
        'first_name',
        'email',
        'gender',
        'ip_address',
        {
          key: 'Phone', label: 'Phone',
        },
        'Country',
      ],
      items: [],
      codeResponsive,
    }
  },
}
</script>
