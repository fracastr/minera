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
                <ag-grid-vue style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="balances_fields"
                    :rowData="balances_table"
                    @grid-ready="onGridReadyBalancesTable"
                    :getRowStyle="getRowStyle"
                    @bodyScroll="scrolledbalances"
                    id="tbalances">
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
                    id="trestricciones">
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
                    @row-clicked="onRowClicked">
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
                    :rowData="inventarios_data">
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
                    :getRowStyle="getRowStyle">
        </ag-grid-vue>
        <ag-grid-vue v-else style="width: auto; height: 500px;"
                    class="ag-theme-alpine"
                    :columnDefs="modal_fields"
                    :rowData="modal_data"
                    id="tmodal">
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
    return (parseFloat(params.value) * 100).toFixed(2);
}
function intFormatter(params) {
    // console.log("params int", params.value);
    // console.log(parseInt(params.value));
    return parseInt(params.value.replace(/,/g, ''), 10)
    // return (parseFloat(params.value) * 100).toFixed(2);
}

function nodosCellStyle(params) {
    console.log("nodosStyle", params);
    console.log(!isNaN(params.value));
    console.log(parseFloat(params.value));
    if(!isNaN(params.value) && parseFloat(params.value) > 0.001){
        return {
            backgroundColor: "red",
        };
    }
};

export default {
    props: ['proceso'],
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
      show_tables: false,
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
      modal_var: 0,
      modal_fields: [],
      modal_data: [],
      exportar_button: false,
      show_tables: false,
      loadingTable: false,
      inventarios_fields: [],
      inventarios_data: [],
      proceso_id: '',
      modalTitle: '' ,
    };
  },
  mounted(){
      console.log("mounted");
      console.log(this.$refs);
  },
  beforeMount() {
    this.columnDefs = [
    { field: 'make', editable: true },
    { field: 'model', editable: true },
    { field: 'price', editable: true }
];

    this.rowData = [
      { make: "Toyota", model: "Celica", price: 35000 },
      { make: "Ford", model: "Mondeo", price: 32000 },
      { make: "Porsche", model: "Boxter", price: 72000 },
    ];
  },
  methods: {
    exportar_excel(){
        axios
        .get("getExcel/"+ this.datos_entrada_id+"/"+this.proceso, {responseType: 'blob'})
        .then((response) => {
            console.log("response excel", response);
            const fileURL = window.URL.createObjectURL(new Blob([response.data]))
            var filelink = document.createElement('a')
            filelink.href = fileURL
            filelink.setAttribute('download', "Resultados.xlsx")
            document.body.appendChild(filelink)
            filelink.click()
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
                let field = {field: 'Flujo', editable: false, resizable: true};
                let restricciones = this.restricciones_fields;
                var myData = Object.keys(restricciones).map(key => {
                    return restricciones[key];
                })
                console.log("restricciones",restricciones, myData);
                restricciones = myData.unshift(field);
                console.log("new restricciones", restricciones);
                this.modal_fields = restricciones;
                let flujos = this.datos_entrada.flujos;
                let data = this.restricciones_table
                // console.log("la data", data);
                // data.forEach((element, index) => {
                //     element.Flujo = flujos[index];
                // });
                this.modal_data = data;
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
    },
    onGridReadyRestriccionesTable(params) {
      this.gridApiRestriccionesTable = params.api;
      this.columnApiRestriccionesTable = params.columnApi;
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
    //   console.log("estoy haciendo scroll",e);
    //   console.log("tabla restricciones", this.gridApiRestriccionesTable);
    // const tbl1 = document.getElementById("tbalances");
    // const tbl2 = document.getElementById("trestricciones");
    // // console.log("tabla 2", tbl2);
    // const gridBody1 = tbl1.querySelector(".ag-body-viewport");
    // const gridBody2 = tbl2.querySelector(".ag-body-viewport");
    // console.log("gridBody2", gridBody2);

    // gridBody1.scrollTop = e.top;

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
              if(index === 1 || index === 2){
                  value.valueFormatter = intFormatter;
              }
              if(index === 3 || index === 4){
                  value.valueFormatter = decimalFormatter;
              }
          })
        let data_restricciones = this.restricciones_fields;
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map(function(value, index){
              console.log("index y value", index, value, data_restricciones.length);
              if(index != data_restricciones.length - 1){
                  console.log(index);
                  value.valueFormatter = decimalFormatter;
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
          console.log(this.datos_entrada);

          // armar formateo dinamico de numeros
          this.balances_fields.map(function(value, index){
            //   console.log("index y value");
              if(index === 1 || index === 2){
                  value.valueFormatter = intFormatter;
              }
              if(index === 3 || index === 4){
                  value.valueFormatter = decimalFormatter;
              }
          })
        let data_restricciones = this.restricciones_fields;
          // armar formateo dinamico de numeros tabla 2
          this.restricciones_fields.map(function(value, index){
              //console.log("index y value", index, value, data_restricciones.length);
              if(index != data_restricciones.length - 1){
                  console.log(index);
                  value.valueFormatter = decimalFormatter;
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
  @import "~ag-grid-community/dist/styles/ag-grid.css";
  @import "~ag-grid-community/dist/styles/ag-theme-material.css";
  @import "~ag-grid-community/dist/styles/ag-theme-alpine.css";

  .balance {
      background-color: rgb(71, 209, 255);
  }
  .calculated {
      background-color: rgb(71, 209, 255);
  }
</style>
