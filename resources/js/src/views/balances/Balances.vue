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
            />
        </b-form-group>
      </b-col>
      <b-col
        md="8"
        offset-md="4"
      >
      </b-col>

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
        >
          Reset
        </b-button>
      </b-col>
    </b-row>

  </b-form>
  <b-table title="Balance" responsive="sm" :items="items"/>
  </div>
</template>

<script>
import {
  BRow, BCol, BFormGroup, BFormInput, BFormCheckbox, BForm, BButton, BFormFile, BTable
} from 'bootstrap-vue'
import Ripple from 'vue-ripple-directive'
import axios from 'axios'

export default {
    data(){
        return{
            file_1: null,
            isBusy: false,
            items: [],
        }
    },
    methods: {
      myProvider(){
          console.log("provider");
          console.log(this.items);
          return this.items;
      },
      onSubmit(event) {
        event.preventDefault()
        let formData = new FormData();
        formData.append('file', this.file_1);

        axios.post('import',
          formData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            }
          }
        ).then((response) => {
            this.items = response.data.mediciones_table;
            console.log("items after call");
            console.log(this.items);
        })
        .catch(function (e) {
          console.log('FAILURE!!', e);
        });
      },
    },
  components: {
    BRow,
    BCol,
    BFormGroup,
    BFormInput,
    BFormCheckbox,
    BForm,
    BButton,
    BFormFile,
    BTable,
  },
  directives: {
    Ripple,
  },
}
</script>
