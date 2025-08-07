<template>
    <b-card-code title="Listado de balances">
  <div>
    <b-container>
      <b-row cols="12">
        <b-col
          md="12"
          sm="12"
        >
          <vue-good-table
            :columns="listado_fields"
            :rows="listado_data"
            :search-options="{
              enabled: true,
              placeholder: 'Buscar...'
            }"
            :pagination-options="{
              enabled: true,
              perPage: 10
            }"
            theme="default"
            styleClass="vgt-table"
          />
        </b-col>
      </b-row>
    </b-container>
  </div>
  </b-card-code>
</template>

<script>
import {
  BRow,
  BCol,
} from 'bootstrap-vue'
import axios from 'axios'
import { VueGoodTable } from 'vue-good-table'
import BCardCode from '@core/components/b-card-code/BCardCode.vue'

export default {
  components: {
    BRow,
    BCol,
    VueGoodTable,
    BCardCode,
  },
  data() {
    return {
      listado_fields: [],
      listado_data: [],
    }
  },
  mounted() {
    axios
      .get('get_listado')
      .then(response => {
        this.listado_data = response.data.listado
      })
      .catch(e => {
        console.log('FAILURE!!', e)
      }).finally(() => {

      })
  },
  beforeMount() {
    this.listado_fields = [
      {
        label: 'Nombre',
        field: 'nombre',
        sortable: true,
        tdClass: 'text-left',
      },
      {
        label: 'Tipo',
        field: 'tipo',
        sortable: true,
        tdClass: 'text-left',
      },
      {
        label: 'Fecha',
        field: 'created_at',
        sortable: true,
        tdClass: 'text-left',
      },
    ]

    this.rowData = [
      { make: 'Toyota', model: 'Celica', price: 35000 },
      { make: 'Ford', model: 'Mondeo', price: 32000 },
      { make: 'Porsche', model: 'Boxter', price: 72000 },
    ]
  },
  methods: {
  },
}
</script>

<style lang="scss">
  @import '~@core/scss/vue/libs/vue-good-table.scss';

  // Fix for vertical scrolling issue
  html, body {
    overflow-y: auto !important;
    height: auto !important;
  }

  .app-content {
    overflow: visible !important;

    .content-area-wrapper {
      overflow: visible !important;
    }

    .content-wrapper {
      overflow: visible !important;
    }

    .content-body {
      overflow: visible !important;
    }
  }

  // Ensure the card allows scrolling
  .card {
    overflow: visible !important;
  }

  // Fix for vue-good-table container
  .vgt-table {
    overflow: visible !important;
  }
</style>
