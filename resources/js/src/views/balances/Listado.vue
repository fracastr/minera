<template>
  <div>
    <b-container>
      <b-row cols="12">
        <b-col
          md="12"
          sm="12"
        >
          <link
            href="https://fonts.googleapis.com/css?family=Roboto"
            rel="stylesheet">
          <ag-grid-vue
            id="tlistado"
            style="width: auto; height: 500px;"
            class="ag-theme-alpine"
            :column-defs="listado_fields"
            :row-data="listado_data" />
        </b-col>
      </b-row>
    </b-container>
  </div>
</template>

<script>
import {
  BRow,
  BCol,
} from 'bootstrap-vue'
import axios from 'axios'
import { AgGridVue } from 'ag-grid-vue'

import AccionValueRenderer from './accionRendererVue2.vue'

export default {
  components: {
    BRow,
    BCol,
    AgGridVue,
    accionRenderer: AccionValueRenderer,
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
      { headerName: 'Nombre', field: 'nombre' },
      { headerName: 'Tipo', field: 'tipo' },
      { headerName: 'Fecha', field: 'created_at' },
      { field: 'accion', cellRenderer: 'accionRenderer' },
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
  @import "~ag-grid-community/styles/ag-grid.css";
  @import "~ag-grid-community/styles/ag-theme-alpine.css";
</style>
