<template>
  <div>
    <b-form
      @submit.prevent
      @submit="onSubmit"
    >
      <b-row>
        <b-col cols="12">
          <b-form-group
            :label="$t('balances.fileUpload.label')"
            label-for="h-archivo-carga"
            label-cols-md="4"
          >
            <b-form-file
              ref="input_file1"
              v-model="file_1"
              :placeholder="$t('balances.fileUpload.placeholder')"
              :drop-placeholder="$t('balances.fileUpload.dropPlaceholder')"
              :browse-text="$t('balances.fileUpload.browseText')"
              required
            />
          </b-form-group>
        </b-col>
        <b-col
          md="8"
          offset-md="4"
        />

        <!-- submit and reset -->
        <b-col offset-md="4">
          <b-button
            v-ripple.400="'rgba(255, 255, 255, 0.15)'"
            type="submit"
            variant="primary"
            class="mr-1"
          >
            {{ $t('balances.buttons.load') }}
          </b-button>
          <b-button
            v-ripple.400="'rgba(186, 191, 199, 0.15)'"
            type="reset"
            variant="outline-secondary"
            class="mr-1"
          >
            {{ $t('balances.buttons.reset') }}
          </b-button>
          <b-button
            v-show="correr_button"
            v-ripple.400="'rgba(255, 255, 255, 0.15)'"
            type="button"
            variant="warning"
            @click="correr_tables"
          >
            {{ $t('balances.buttons.run') }}
          </b-button>
          <b-button
            v-show="exportar_button"
            type="button"
            variant="success"
            @click="exportar_excel"
          >
            {{ $t('balances.buttons.generateExcel') }}
          </b-button>
        </b-col>
      </b-row>
    </b-form>
    <br>
    <!-- <div
      v-if="isLoading"
      class="text-center"
    >
      <b-spinner label="Cargando..." />
    </div> -->
    <hr>
    <b-container v-if="show_tables">
      <b-row cols="4">
        <b-col
          md="4"
          sm="12"
        >
          <b-row align-v="center" class="table-header mb-3">
            <h5 class="ml-1 mb-0">
              {{ $t('balances.tables.balances') }}
            </h5>
            <b-button
              v-ripple.400="'rgba(113, 102, 240, 0.15)'"
              variant="outline-primary"
              class="btn-icon rounded-circle ml-auto mr-1"
              @click="tbl_expand(1)"
            >
              <feather-icon icon="SearchIcon" />
            </b-button>
          </b-row>
          <link
            href="https://fonts.googleapis.com/css?family=Roboto"
            rel="stylesheet"
          >
          <ag-grid-vue
            id="tbalances"
            style="width: 100%; height: 500px;"
            class="ag-theme-alpine"
            :column-defs="balances_fields"
            :row-data="balances_table"
            :get-row-style="getRowStyle"
            :enable-browser-tooltips="true"
            :default-col-def="defaultColDef"
            @grid-ready="onGridReadyBalancesTable"
            @bodyScroll="scrolledbalances"
          />
        </b-col>
        <b-col
          md="8"
          sm="12"
        >
          <b-row align-v="center" class="table-header mb-3">
            <h5 class="ml-1 mb-0">
              {{ $t('balances.tables.restrictions') }}
            </h5>
            <b-button
              v-ripple.400="'rgba(113, 102, 240, 0.15)'"
              variant="outline-primary"
              class="btn-icon rounded-circle ml-auto mr-1"
              @click="tbl_expand(2)"
            >
              <feather-icon icon="SearchIcon" />
            </b-button>
          </b-row>
          <link
            href="https://fonts.googleapis.com/css?family=Roboto"
            rel="stylesheet"
          >
          <ag-grid-vue
            id="trestricciones"
            style="width: auto; height: 500px;"
            class="ag-theme-alpine"
            :column-defs="restricciones_fields"
            :row-data="restricciones_table"
            :get-row-style="getRowStyle"
            :default-col-def="defaultColDef"
            @grid-ready="onGridReadyRestriccionesTable"
            @bodyScroll="scrolledrestricciones"
          />
        </b-col>
      </b-row>
      <br>
      <b-row cols="4">
        <b-col
          md="5"
          sm="12"
          offset-md="0"
        >
          <b-row align-v="center" class="table-header mb-3">
            <h5 class="ml-1 mb-0">
              {{ $t('balances.tables.nodeAdjustment') }}
            </h5>
            <b-button
              v-ripple.400="'rgba(113, 102, 240, 0.15)'"
              variant="outline-primary"
              class="btn-icon rounded-circle ml-auto mr-1"
              @click="tbl_expand(3)"
            >
              <feather-icon icon="SearchIcon" />
            </b-button>
          </b-row>
          <link
            href="https://fonts.googleapis.com/css?family=Roboto"
            rel="stylesheet"
          >
          <ag-grid-vue
            style="width: auto; height: 500px;"
            class="ag-theme-alpine"
            :column-defs="balance_nodos_fields"
            :row-data="balance_nodos"
            :default-col-def="defaultColDef"
            @grid-ready="onGridReadyNodosTable"
            @row-clicked="onRowClicked"
          />
        </b-col>
        <b-col
          md="7"
          sm="12"
          offset-md="0"
        >
          <b-row align-v="center" class="table-header mb-3">
            <h5 class="ml-1 mb-0">
              {{ $t('balances.tables.inventoryVariations') }}
            </h5>
            <b-button
              v-ripple.400="'rgba(113, 102, 240, 0.15)'"
              variant="outline-primary"
              class="btn-icon rounded-circle ml-auto mr-1"
              @click="tbl_expand(4)"
            >
              <feather-icon icon="SearchIcon" />
            </b-button>
          </b-row>
          <link
            href="https://fonts.googleapis.com/css?style=Roboto"
            rel="stylesheet"
          >
          <ag-grid-vue
            style="width: auto; height: 500px;"
            class="ag-theme-alpine"
            :column-defs="inventarios_fields"
            :row-data="inventarios_data"
            :default-col-def="defaultColDef"
            @grid-ready="onGridReadyInventariosTable"
          />
          <!-- <b-img
                :src="require('@/assets/images/avatars/image.jpeg')"
                alt="logo"
                width="800%"
                /> -->
        </b-col>
      </b-row>
      <b-modal
        id="modal_tables"
        ref="my-modal"
        :title="modalTitle"
        size="xl"
      >
        <ag-grid-vue
          v-if="this.modal_var == 1 || this.modal_var == 2"
          id="tmodal"
          style="width: auto; height: 500px;"
          class="ag-theme-alpine"
          :column-defs="modal_fields"
          :row-data="modal_data"
          :get-row-style="getRowStyle"
          :default-col-def="defaultColDef"
        />
        <ag-grid-vue
          v-else
          id="tmodal"
          style="width: auto; height: 500px;"
          class="ag-theme-alpine"
          :column-defs="modal_fields"
          :row-data="modal_data"
          :default-col-def="defaultColDef"
        />
      </b-modal>
    </b-container>
    <loading
      :active.sync="isLoading"
      :can-cancel="false"
      :is-full-page="fullPage"
      color="#7367f0"
      loader="bars"
      :height="128"
      :width="128"
    />
  </div>
</template>

<script>
// Import component
import Loading from 'vue-loading-overlay'
// Import stylesheet
import 'vue-loading-overlay/dist/vue-loading.css'
import {
  BRow,
  BCol,
  BFormGroup,
  BFormInput,
  BFormCheckbox,
  BForm,
  BButton,
  BFormFile,
  BTable,
  BTableLite,
  BModal,
  BSpinner,
  BImg,
  BContainer,
} from 'bootstrap-vue'
import Ripple from 'vue-ripple-directive'
import axios from 'axios'
import { AgGridVue } from 'ag-grid-vue'
import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'

function decimalFormatter(params) {
  // console.log("params decimal", params.value);
  // console.log(parseFloat(params.value));
  return (parseFloat(params.value)).toFixed(2)
}
function decimalFormatter2(params) {
  // console.log("params decimal", params.value);
  // console.log(parseFloat(params.value));
  return (parseFloat(params.value)).toFixed(2)
}
function intFormatter(params) {
  // console.log("params int", params.value);
  // console.log(parseInt(params.value));
  return parseInt(params.value.replace(/,/g, ''), 10)
  // return (parseFloat(params.value) * 100).toFixed(2);
}
function intFormatter2(params) {
  // console.log("params int", params.value);
  // console.log(parseInt(params.value));
  return parseInt(params.value, 10)
  // return (parseFloat(params.value) * 100).toFixed(2);
}

function nodosCellStyle(params) {
  // console.log("nodosStyle", params);
  // console.log(!isNaN(params.value));
  // console.log(parseFloat(params.value));
  if (!isNaN(params.value) && parseFloat(params.value) > 0.001) {
    return {
      backgroundColor: 'red',
    }
  }
}

export default {
  components: {
    ToastificationContent,
    Loading,
    BModal,
    BRow,
    BCol,
    BFormGroup,
    BFormInput,
    BFormCheckbox,
    BForm,
    BButton,
    BFormFile,
    BTable,
    BTableLite,
    AgGridVue,
    BSpinner,
    BImg,
    BContainer,
  },
  directives: {
    Ripple,
  },
  props: {
    proceso: Number,
    show_tables: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      file_1: null,
      isBusy: false,
      balances_table: [],
      restricciones_table: [],
      resultado_restricciones: [],
      balance_nodos: [],
      balance_nodos_fields: [],
      restricciones_fields: [],
      balances_fields: [],
      correr_button: false,
      datos_entrada: {},
      columnDefs: null,
      rowData: null,
      datos_entrada_id: null,
      yellow: [],
      green: [],
      gridApiBalancesTable: null,
      gridColumnApiBalancesTable: null,
      gridApiRestriccionesTable: null,
      columnApiRestriccionesTable: null,
      gridApiNodosTable: null,
      columnApiNodosTable: null,
      gridApiInventariosTable: null,
      columnApiInventariosTable: null,
      modal_var: 0,
      modal_fields: [],
      modal_data: [],
      exportar_button: false,
      isLoading: false,
      fullPage: true,
      inventarios_fields: [],
      inventarios_data: [],
      proceso_id: '',
      modalTitle: '',
      defaultColDef: null,
    }
  },
  mounted() {
    console.log('mounted')
    console.log(this.$refs)
  },
  beforeMount() {
    this.defaultColDef = {
      flex: 0,
      resizable: false,
      wrapText: false,
      autoHeight: false,
      wrapHeaderText: true,
      autoHeaderHeight: true,
    }
  },
  methods: {
    exportar_excel() {
      this.isLoading = true
      axios
        .get(`/api/balances/getExcel/${this.datos_entrada_id}/${this.proceso}`)
        .then(response => {
          this.isLoading = false
          console.log('response excel', response)
          const url = response.data
          window.open(url, '_blank').focus()
        })
        .catch(e => {
          this.isLoading = false
          console.log('FAILURE!!', e)
        }).finally(() => {
        })
    },
    tbl_expand(table) {
      this.modal_var = table
      switch (table) {
        case 1:
          this.modal_fields = this.balances_fields
          this.modal_data = this.balances_table
          this.modalTitle = this.$t('balances.modals.balancesDetail')
          break
        case 2:
          this.modalTitle = this.$t('balances.modals.restrictionsDetail')
          this.modal_fields = this.restricciones_fields
          this.modal_data = this.restricciones_table
          break
        case 3:
          this.modalTitle = this.$t('balances.modals.nodeAdjustmentDetail')
          this.modal_fields = this.balance_nodos_fields
          this.modal_data = this.balance_nodos
          break
        case 4:
          this.modalTitle = this.$t('balances.modals.inventoryVariationsDetail')
          this.modal_fields = this.inventarios_fields
          this.modal_data = this.inventarios_data
          break
        default:
          break
      }
      this.$refs['my-modal'].show()
    },
    onGridReadyBalancesTable(params) {
      this.gridApiBalancesTable = params.api
      this.gridColumnApiBalancesTable = params.columnApi

    //   this.gridApiBalancesTable.sizeColumnsToFit();
    },
    onGridReadyRestriccionesTable(params) {
      this.gridApiRestriccionesTable = params.api
      this.columnApiRestriccionesTable = params.columnApi
    //   this.gridApiRestriccionesTable.sizeColumnsToFit();
    },
    onGridReadyNodosTable(params) {
      this.gridApiNodosTable = params.api
      this.columnApiNodosTable = params.columnApi
      this.gridApiNodosTable.sizeColumnsToFit()
    },
    onGridReadyInventariosTable(params) {
      this.gridApiInventariosTable = params.api
      this.columnApiInventariosTable = params.columnApi
      this.gridApiInventariosTable.sizeColumnsToFit()
    },
    getRowStyle(params) {
    // console.log("rowstyle", params);
    // if (params.node.rowIndex % 2 === 0) {
    //     return { background: 'yellow' };
    // }
    // console.log("yellow", this.yellow);
      const { yellow } = this
      const { green } = this
      let resp = ''

      if (yellow.length > 0) {
        yellow.map((value, index) => {
          // console.log(params.node.rowIndex, value);
          if (params.node.rowIndex === value) {
            console.log('valido')
            resp = { background: 'yellow' }
          }
        })
      }
      if (green.length > 0) {
        green.map((value, index) => {
          // console.log(params.node.rowIndex, value);
          if (params.node.rowIndex === value) {
            console.log('valido')
            resp = { background: 'green' }
          }
        })
      }

      return resp
    },
    scrolledbalances(e) {
    //   console.log("estoy haciendo scroll",e);
    //   console.log("tabla restricciones", this.gridApiRestriccionesTable);
      const tbl1 = document.getElementById('tbalances')
      const tbl2 = document.getElementById('trestricciones')
      // console.log("tabla 2", tbl2);
      // const gridBody1 = tbl1.querySelector(".ag-body-viewport");
      const gridBody2 = tbl2.querySelector('.ag-body-viewport')
      // console.log("gridBody2", gridBody2);

      gridBody2.scrollTop = e.top
    },
    scrolledrestricciones(e) {

    },
    onRowClicked(event) {
      console.log('se ha hecho click en una fila')
      console.log(event)
      // llamado al endpoint
      this.$http
        .post('/api/balances/paint_tables', {
          datos_entrada_id: this.datos_entrada_id,
          rowIndex: event.rowIndex,
        }, {
          headers: {
            'Content-Type': 'application/json',
          },
        })
        .then(response => {
          this.yellow = response.data.yellow
          this.green = response.data.green

          this.gridApiBalancesTable.redrawRows()
          this.gridApiRestriccionesTable.redrawRows()
        })
        .catch(e => {
          this.isLoading = false
          console.log('FAILURE!!', e)
          this.$toast({
            component: ToastificationContent,
            props: {
              title: this.$t('balances.toast.runError'),
              icon: 'AlertTriangleIcon',
              variant: 'danger',
            },
          })
        })
    },
    correr_tables(event) {
      this.isLoading = true
      event.preventDefault()
      console.log(this.datos_entrada)
      this.$http
        .post('/api/balances/correr_balance', {
          datos_entrada: this.datos_entrada,
          datos_entrada_id: this.datos_entrada_id,
          balances_table: this.balances_table,
          restricciones_table: this.restricciones_table,
          balance_nodos: this.balance_nodos,
          proceso_id: this.proceso,
          inventarios_table: this.inventarios_data,
        }, {
          headers: {
            'Content-Type': 'application/json',
          },
        })
        .then(response => {
          this.balances_table = response.data.balances_table
          this.restricciones_table = response.data.restricciones_table
          // this.resultado_restricciones = response.data.resultado_restricciones;
          this.balance_nodos = response.data.balance_nodos
          this.balance_nodos_fields = response.data.balance_nodos_fields
          this.restricciones_fields = response.data.restricciones_fields
          this.balances_fields = response.data.balances_fields
          this.datos_entrada = response.data.data.datos_entrada
          this.datos_entrada_id = response.data.datos_entrada_id
          this.inventarios_fields = response.data.inventarios_fields
          this.inventarios_data = response.data.inventarios_data
          // console.log("response", response);
          // armar formateo dinamico de numeros
          this.balances_fields.map((value, index) => {
            //   console.log("index y value");
            if (index === 2 || index === 3) {
              value.valueFormatter = intFormatter
            }
            if (index === 4 || index === 5) {
              value.valueFormatter = decimalFormatter
            }
          })
          const data_restricciones = this.restricciones_fields
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map((value, index) => {
            console.log('index y value', index, value, data_restricciones.length)
            if (index != data_restricciones.length - 1 && index > 1) {
              console.log(index)
              value.valueFormatter = decimalFormatter
            }
          })

          this.inventarios_fields.map((value, index) => {
            // console.log("index y value", index, value, data_restricciones.length);
            if (![0, 4, 8, 9].includes(index)) {
              value.valueFormatter = intFormatter2
            } else if ([4, 8, 9].includes(index)) {
              value.valueFormatter = decimalFormatter2
            }
          })

          this.balance_nodos_fields.map((value, index) => {
            value.cellStyle = nodosCellStyle
          })
          this.exportar_button = true
          this.isLoading = false
          this.show_tables = true
        })
        .catch(function (e) {
          this.isLoading = false
          console.log('FAILURE!! correr_balance', e)
        })
    },
    onSubmit(event) {
      this.isLoading = true
      event.preventDefault()
      const formData = new FormData()
      formData.append('file', this.file_1)
      formData.append('proceso_id', this.proceso)

      this.$http
        .post('/api/balances/import', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        })
        .then(response => {
          this.balances_table = response.data.balances_table
          this.restricciones_table = response.data.restricciones_table
          // this.resultado_restricciones = response.data.resultado_restricciones;
          this.balance_nodos = response.data.balance_nodos
          this.balance_nodos_fields = response.data.balance_nodos_fields
          this.restricciones_fields = response.data.restricciones_fields
          this.balances_fields = response.data.balances_fields
          this.datos_entrada = response.data.data.datos_entrada
          this.datos_entrada_id = response.data.datos_entrada_id
          this.inventarios_fields = response.data.inventarios_fields
          this.inventarios_data = response.data.inventarios_data
          this.correr_button = true
          // console.log("items after call");
          //   console.log(this.datos_entrada);

          // armar formateo dinamico de numeros
          this.balances_fields.map((value, index) => {
            //   console.log("index y value");
            if (index === 2 || index === 3) {
              value.valueFormatter = intFormatter
            }
            if (index === 4 || index === 5) {
              value.valueFormatter = decimalFormatter
            }
          })

          //   this.balances_table.map(function(value, index) {
          //     value.Flujos = index + 1 + "-" + value.Flujos
          //   })
          const data_restricciones = this.restricciones_fields
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map((value, index) => {
            // console.log("index y value", index, value, data_restricciones.length);
            if (index != data_restricciones.length - 1 && index > 1) {
              //   console.log(index);
              value.valueFormatter = decimalFormatter
            }
          })

          this.inventarios_fields.map((value, index) => {
            // console.log("index y value", index, value, data_restricciones.length);
            if (![0, 4, 8, 9].includes(index)) {
              value.valueFormatter = intFormatter2
            } else if ([4, 8, 9].includes(index)) {
              value.valueFormatter = decimalFormatter2
            }
          })

          this.balance_nodos_fields.map((value, index) => {
            value.cellStyle = nodosCellStyle
          })

          this.show_tables = true
          this.isLoading = false
        })
        .catch(e => {
          this.isLoading = false
          console.log('FAILURE!!', e)
          this.$toast({
            component: ToastificationContent,
            props: {
              title: this.$t('balances.toast.importError'),
              icon: 'AlertTriangleIcon',
              variant: 'danger',
            },
          })
        })
    },
  },
}
</script>

<style lang="scss">
  @import "~ag-grid-community/styles/ag-grid.css";
  @import "~ag-grid-community/styles/ag-theme-alpine.css";

  .balance {
      background-color: rgb(71, 209, 255);
  }
  .calculated {
      background-color: rgb(71, 209, 255);
  }

  .ag-theme-alpine {
  --ag-grid-size: 3px;
  --ag-list-item-height: 20px;
}

    // Estilos para los headers de las tablas
  .table-header {
    margin-bottom: 1rem !important;

    h5 {
      margin-bottom: 0;
      font-weight: 600;
      color: #5e5873;
      transition: color 0.3s ease;

      // Dark mode support - mismo color que "Configuración de Balances"
      .dark-layout & {
        color: #d0d2d6;
      }
    }

    .btn-icon {
      transition: all 0.3s ease;

      &:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(113, 102, 240, 0.2);
      }
    }
  }

  // Espaciado adicional entre tablas
  .ag-grid-vue {
    margin-top: 0.5rem;
  }
</style>
