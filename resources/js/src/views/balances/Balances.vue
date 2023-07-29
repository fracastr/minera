<template>
  <div>
    <b-form @submit.prevent @submit="onSubmit">
      <b-row>
        <b-col cols="12">
          <b-form-group
            label="Archivo carga"
            label-for="h-archivo-carga"
            label-cols-md="4"
          >
            <b-form-file
              v-model="file_1"
              placeholder="Eliga su archivo"
              drop-placeholder="Suelte su archivo aqui"
              browse-text="Buscar"
              required
              ref="input_file1"
            />
          </b-form-group>
        </b-col>
        <b-col md="8" offset-md="4"> </b-col>

        <!-- submit and reset -->
        <b-col offset-md="4">
          <b-button
            v-ripple.400="'rgba(255, 255, 255, 0.15)'"
            type="submit"
            variant="primary"
            class="mr-1"
          >
            Cargar
          </b-button>
          <b-button
            v-ripple.400="'rgba(186, 191, 199, 0.15)'"
            type="reset"
            variant="outline-secondary"
            class="mr-1"
          >
            Reset
          </b-button>
          <b-button
            v-ripple.400="'rgba(255, 255, 255, 0.15)'"
            type="button"
            variant="warning"
            @click="correr_tables"
            v-show="correr_button"
          >
            Correr
          </b-button>
          <b-button
            type="button"
            variant="success"
            @click="exportar_excel"
            v-show="exportar_button"
          >
            Generar Excel Resultados
          </b-button>
        </b-col>
      </b-row>
    </b-form>
    <br>
    <div class="text-center" v-if="loadingTable">
        <b-spinner label="Cargando..."></b-spinner>
    </div>
    <hr>
    <b-container v-if="show_tables">
        <b-row cols="4">
            <b-col md="4" sm="12">
                <b-row align-v="center">
                    <h5 class="ml-1">
                        Tabla Balances
                    </h5>
                    <b-button v-ripple.400="'rgba(113, 102, 240, 0.15)'" variant="outline-primary" class="btn-icon rounded-circle ml-auto mr-1"
                    @click="tbl_expand(1)">
                        <feather-icon icon="SearchIcon" />
                    </b-button>
                </b-row>
                <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />
                <ag-grid-vue style="width: 100%; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="balances_fields"
                    :rowData="balances_table"
                    @grid-ready="onGridReadyBalancesTable"
                    :getRowStyle="getRowStyle"
                    @bodyScroll="scrolledbalances"
                    :enableBrowserTooltips="true"
                    id="tbalances"
                    :defaultColDef="defaultColDef">
                </ag-grid-vue>
            </b-col>
            <b-col md="8" sm="12">
                <b-row align-v="center">
                    <h5 class="ml-1">
                        Tabla Restricciones
                    </h5>
                    <b-button v-ripple.400="'rgba(113, 102, 240, 0.15)'" variant="outline-primary" class="btn-icon rounded-circle ml-auto mr-1"
                    @click="tbl_expand(2)">
                        <feather-icon icon="SearchIcon" />
                    </b-button>
                </b-row>
                <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />
                <ag-grid-vue style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="restricciones_fields"
                    :rowData="restricciones_table"
                    @grid-ready="onGridReadyRestriccionesTable"
                    :getRowStyle="getRowStyle"
                    @bodyScroll="scrolledrestricciones"
                    id="trestricciones"
                    :defaultColDef="defaultColDef">
                </ag-grid-vue>
            </b-col>
        </b-row>
        <br>
        <b-row cols="4">
            <b-col md="5" sm="12" offset-md="0">
                <b-row align-v="center">
                    <h5 class="ml-1">
                        Tabla Ajuste Nodos
                    </h5>
                    <b-button v-ripple.400="'rgba(113, 102, 240, 0.15)'" variant="outline-primary" class="btn-icon rounded-circle ml-auto mr-1"
                    @click="tbl_expand(3)">
                        <feather-icon icon="SearchIcon" />
                    </b-button>
                </b-row>
                <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />
                <ag-grid-vue style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="balance_nodos_fields"
                    :rowData="balance_nodos"
                    @grid-ready="onGridReadyNodosTable"
                    @row-clicked="onRowClicked"
                    :defaultColDef="defaultColDef">
                </ag-grid-vue>
            </b-col>
            <b-col md="7" sm="12" offset-md="0">
                <b-row align-v="center">
                    <h5 class="ml-1">
                        Tabla Variaciones Inventario
                    </h5>
                    <b-button v-ripple.400="'rgba(113, 102, 240, 0.15)'" variant="outline-primary" class="btn-icon rounded-circle ml-auto mr-1"
                    @click="tbl_expand(4)">
                        <feather-icon icon="SearchIcon" />
                    </b-button>
                </b-row>
                <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />
                <ag-grid-vue style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="inventarios_fields"
                    @grid-ready="onGridReadyInventariosTable"
                    :rowData="inventarios_data"
                    :defaultColDef="defaultColDef">
                </ag-grid-vue>
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
        v-bind:title="modalTitle"
        size="xl"
        >
        <ag-grid-vue v-if="this.modal_var == 1 || this.modal_var == 2" style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="modal_fields"
                    :rowData="modal_data"
                    id="tmodal"
                    :getRowStyle="getRowStyle"
                    :defaultColDef="defaultColDef">
        </ag-grid-vue>
        <ag-grid-vue v-else style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="modal_fields"
                    :rowData="modal_data"
                    id="tmodal"
                    :defaultColDef="defaultColDef">
        </ag-grid-vue>
        </b-modal>
    </b-container>
  </div>
</template>

<script>
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
} from "bootstrap-vue";
import Ripple from "vue-ripple-directive";
import axios from "axios";
import { AgGridVue } from "ag-grid-vue";
function decimalFormatter(params) {
    // console.log("params decimal", params.value);
    // console.log(parseFloat(params.value));
    return (parseFloat(params.value) ).toFixed(2);
}
function decimalFormatter2(params) {
    // console.log("params decimal", params.value);
    // console.log(parseFloat(params.value));
    return (parseFloat(params.value)).toFixed(2);
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
    if(!isNaN(params.value) && parseFloat(params.value) > 0.001){
        return {
            backgroundColor: "red",
        };
    }
};

export default {
    props: {
        proceso: Number,
        show_tables: {
            type: Boolean,
            default: false
        }
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
      loadingTable: false,
      inventarios_fields: [],
      inventarios_data: [],
      proceso_id: '',
      modalTitle: '' ,
      defaultColDef: null,
    };
  },
  mounted(){
      console.log("mounted");
      console.log(this.$refs);
  },
  beforeMount() {
//     this.columnDefs = [
//     { field: 'make', editable: true },
//     { field: 'model', editable: true },
//     { field: 'price', editable: true }
// ];

//     this.rowData = [
//       { make: "Toyota", model: "Celica", price: 35000 },
//       { make: "Ford", model: "Mondeo", price: 32000 },
//       { make: "Porsche", model: "Boxter", price: 72000 },
//     ];
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
    exportar_excel(){
        axios
        .get("getExcel/"+ this.datos_entrada_id+"/"+this.proceso)
        .then((response) => {
            console.log("response excel", response);
            let url = response.data
            window.open(url, '_blank').focus();
        })
        .catch(function (e) {
          console.log("FAILURE!!", e);
        }).finally(() => {
        });
    },
    tbl_expand(table){
        this.modal_var = table;
        switch (table) {
            case 1:
                this.modal_fields = this.balances_fields;
                this.modal_data = this.balances_table;
                this.modalTitle = 'Detalle Tabla Balances'
                break;
            case 2:
                this.modalTitle = 'Detalle Tabla Restricciones'
                this.modal_fields = this.restricciones_fields;
                this.modal_data = this.restricciones_table;
                break;
            case 3:
                this.modalTitle = 'Detalle Tabla Ajuste Nodos'
                this.modal_fields = this.balance_nodos_fields;
                this.modal_data = this.balance_nodos;
                break;
            case 4:
                this.modalTitle = 'Detalle Tabla Variaciones Inventario'
                this.modal_fields = this.inventarios_fields;
                this.modal_data = this.inventarios_data;
                break;
            default:
                break;
        }
        this.$refs['my-modal'].show();
    },
    onGridReadyBalancesTable(params) {
      this.gridApiBalancesTable = params.api;
      this.gridColumnApiBalancesTable = params.columnApi;

    //   this.gridApiBalancesTable.sizeColumnsToFit();
    },
    onGridReadyRestriccionesTable(params) {
      this.gridApiRestriccionesTable = params.api;
      this.columnApiRestriccionesTable = params.columnApi;
    //   this.gridApiRestriccionesTable.sizeColumnsToFit();
    },
    onGridReadyNodosTable(params) {
      this.gridApiNodosTable = params.api;
      this.columnApiNodosTable = params.columnApi;
      this.gridApiNodosTable.sizeColumnsToFit();
    },
    onGridReadyInventariosTable(params) {
      this.gridApiInventariosTable = params.api;
      this.columnApiInventariosTable = params.columnApi;
      this.gridApiInventariosTable.sizeColumnsToFit();
    },
    getRowStyle(params){
    //console.log("rowstyle", params);
    // if (params.node.rowIndex % 2 === 0) {
    //     return { background: 'yellow' };
    // }
    //console.log("yellow", this.yellow);
    let yellow = this.yellow;
    let green = this.green;
    let resp = "";

    if(yellow.length > 0){
        yellow.map(function(value, index){
            //console.log(params.node.rowIndex, value);
            if (params.node.rowIndex === value) {
                console.log("valido");
                resp =  { background: 'yellow' };
            }
        })
    }
    if(green.length > 0){
        green.map(function(value, index){
            //console.log(params.node.rowIndex, value);
            if (params.node.rowIndex === value) {
                console.log("valido");
                resp =  { background: 'green' };
            }
        })
    }

    return resp;
    },
    scrolledbalances(e){
    //   console.log("estoy haciendo scroll",e);
    //   console.log("tabla restricciones", this.gridApiRestriccionesTable);
    const tbl1 = document.getElementById("tbalances");
    const tbl2 = document.getElementById("trestricciones");
    // console.log("tabla 2", tbl2);
    // const gridBody1 = tbl1.querySelector(".ag-body-viewport");
    const gridBody2 = tbl2.querySelector(".ag-body-viewport");
    // console.log("gridBody2", gridBody2);

    gridBody2.scrollTop = e.top;

    },
    scrolledrestricciones(e){

    },
    onRowClicked(event) {
        console.log("se ha hecho click en una fila");
        console.log(event);
        // llamado al endpoint
        axios
        .post("paint_tables", {
            "datos_entrada_id": this.datos_entrada_id,
            "rowIndex": event.rowIndex
            }, {
          headers: {
            "Content-Type": "application/json",
          },
        })
        .then((response) => {
            this.yellow = response.data.yellow;
            this.green = response.data.green;

            this.gridApiBalancesTable.redrawRows();
            this.gridApiRestriccionesTable.redrawRows();
        })
        .catch(function (e) {
          console.log("FAILURE!! correr_balance", e);
        });

    },
    correr_tables(event) {
        this.loadingTable = true;
        console.log(this.datos_entrada);
      axios
        .post("correr_balance", {
            "datos_entrada": this.datos_entrada,
            "datos_entrada_id": this.datos_entrada_id,
            "balances_table": this.balances_table,
            "restricciones_table": this.restricciones_table,
            "balance_nodos": this.balance_nodos,
            "proceso_id": this.proceso,
            "inventarios_table": this.inventarios_data
            }, {
          headers: {
            "Content-Type": "application/json",
          },
        })
        .then((response) => {
          this.balances_table = response.data.balances_table;
          this.restricciones_table = response.data.restricciones_table;
          //this.resultado_restricciones = response.data.resultado_restricciones;
          this.balance_nodos = response.data.balance_nodos;
          this.balance_nodos_fields = response.data.balance_nodos_fields;
          this.restricciones_fields = response.data.restricciones_fields;
          this.balances_fields = response.data.balances_fields;
          this.datos_entrada = response.data.data.datos_entrada;
          this.datos_entrada_id = response.data.datos_entrada_id;
          this.inventarios_fields = response.data.inventarios_fields;
          this.inventarios_data = response.data.inventarios_data;
            //console.log("response", response);
          // armar formateo dinamico de numeros
          this.balances_fields.map(function(value, index){
            //   console.log("index y value");
              if(index === 2 || index === 3){
                  value.valueFormatter = intFormatter;
              }
              if(index === 4 || index === 5){
                  value.valueFormatter = decimalFormatter;
              }
          })
        let data_restricciones = this.restricciones_fields;
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map(function(value, index){
              console.log("index y value", index, value, data_restricciones.length);
              if(index != data_restricciones.length - 1 && index > 1){
                  console.log(index);
                  value.valueFormatter = decimalFormatter;
              }
          })

          this.inventarios_fields.map(function(value, index){
              //console.log("index y value", index, value, data_restricciones.length);
              if(![0,4,8,9].includes(index)){
                  value.valueFormatter = intFormatter2;
              }
              else if([4,8,9].includes(index)){
                  value.valueFormatter = decimalFormatter2;
              }
          })

          this.balance_nodos_fields.map(function(value, index){
            value.cellStyle = nodosCellStyle;

          })
          this.exportar_button = true;
          this.loadingTable = false;
          this.show_tables = true;
        })
        .catch(function (e) {
            this.loadingTable = false;
            console.log("FAILURE!! correr_balance", e);
        });

        this.loadingTable = false;

    },
    onSubmit(event) {
      this.loadingTable = true;
      event.preventDefault();
      let formData = new FormData();
      formData.append("file", this.file_1);
      formData.append("proceso_id", this.proceso);

      axios
        .post("import", formData, {
          headers: {
            "Content-Type": "multipart/form-data",
          },
        })
        .then((response) => {
          this.balances_table = response.data.balances_table;
          this.restricciones_table = response.data.restricciones_table;
          //this.resultado_restricciones = response.data.resultado_restricciones;
          this.balance_nodos = response.data.balance_nodos;
          this.balance_nodos_fields = response.data.balance_nodos_fields;
          this.restricciones_fields = response.data.restricciones_fields;
          this.balances_fields = response.data.balances_fields;
          this.datos_entrada = response.data.data.datos_entrada;
          this.datos_entrada_id = response.data.datos_entrada_id;
          this.inventarios_fields = response.data.inventarios_fields;
          this.inventarios_data = response.data.inventarios_data;
          this.correr_button = true;
          //console.log("items after call");
        //   console.log(this.datos_entrada);

          // armar formateo dinamico de numeros
          this.balances_fields.map(function(value, index){
            //   console.log("index y value");
              if(index === 2 || index === 3){
                  value.valueFormatter = intFormatter;
              }
              if(index === 4 || index === 5){
                  value.valueFormatter = decimalFormatter;
              }
          })

        //   this.balances_table.map(function(value, index) {
        //     value.Flujos = index + 1 + "-" + value.Flujos
        //   })
        let data_restricciones = this.restricciones_fields;
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map(function(value, index){
              //console.log("index y value", index, value, data_restricciones.length);
              if(index != data_restricciones.length - 1 && index > 1){
                //   console.log(index);
                  value.valueFormatter = decimalFormatter;
              }
          })

          this.inventarios_fields.map(function(value, index){
              //console.log("index y value", index, value, data_restricciones.length);
              if(![0,4,8,9].includes(index)){
                  value.valueFormatter = intFormatter2;
              }
              else if([4,8,9].includes(index)){
                  value.valueFormatter = decimalFormatter2;
              }
          })

          this.balance_nodos_fields.map(function(value, index){
            value.cellStyle = nodosCellStyle;

          })

          this.show_tables = true;
          this.loadingTable = false;
        })
        .catch(function (e) {
            this.loadingTable = false;
            console.log("FAILURE!!", e);
        });
    },
  },
  components: {
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
    BImg
  },
  directives: {
    Ripple,
  },
};
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
</style>
