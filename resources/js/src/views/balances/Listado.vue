<template>
  <b-card title="Listado de balances">
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
                placeholder: 'Buscar...',
                searchFn: customSearchFn
              }"
              :pagination-options="{
                enabled: true,
                perPage: 10,
                perPageDropdown: [5, 10, 20, 50]
              }"
              :theme="currentTheme"
              styleClass="vgt-table striped"
            />
          </b-col>
        </b-row>
      </b-container>
    </div>
  </b-card>
</template>

<script>
import {
  BRow,
  BCol,
  BCard,
  BContainer,
} from 'bootstrap-vue'
import axios from 'axios'
import { VueGoodTable } from 'vue-good-table'
import useAppConfig from '@core/app-config/useAppConfig'

export default {
  components: {
    BRow,
    BCol,
    VueGoodTable,
    BCard,
    BContainer,
  },
  data() {
    return {
      listado_fields: [
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
          label: 'Proceso',
          field: this.getProcesoNombre,
          sortable: true,
          tdClass: 'text-left',
        },
        {
          label: 'Valle',
          field: this.getValleNombre,
          sortable: true,
          tdClass: 'text-left',
        },
        {
          label: 'Usuario',
          field: this.getUserName,
          sortable: true,
          tdClass: 'text-left',
        },
        {
          label: 'Fecha Creación',
          field: 'created_at',
          sortable: true,
          tdClass: 'text-left',
          formatFn: this.formatDate,
        },
      ],
      listado_data: [],
      loading: false,
    }
  },
  computed: {
    currentTheme() {
      const { skin } = useAppConfig()
      return skin.value === 'dark' ? 'nocturnal' : 'polar-bear'
    },
  },
  mounted() {
    this.loadData()
  },
  methods: {
    loadData() {
      this.loading = true
      this.$http
        .get('/api/balances/get_listado')
        .then(response => {
          if (response.data && response.data.listado) {
            this.listado_data = response.data.listado
          } else {
            console.warn('No se encontraron datos en la respuesta')
            this.listado_data = []
          }
        })
        .catch(error => {
          console.error('Error al cargar los datos:', error)
          this.listado_data = []
          // Mostrar mensaje de error al usuario si es necesario
        })
        .finally(() => {
          this.loading = false
        })
    },
    customSearchFn(row, col, cellValue, searchTerm) {
      if (!searchTerm) return true

      const term = String(searchTerm).toLowerCase()
      const values = [
        row.nombre,
        row.tipo,
        this.getProcesoNombre(row),
        this.getValleNombre(row),
        this.getUserName(row),
        row.created_at,
        cellValue,
      ]
        .filter(value => value != null && value !== '')
        .map(value => String(value).toLowerCase())

      return values.some(value => value.includes(term))
    },
    formatDate(value) {
      if (!value) return 'N/A'
      return new Date(value).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    },
    getProcesoNombre(row) {
      return row.proceso && row.proceso.nombre ? row.proceso.nombre : 'N/A'
    },
    getValleNombre(row) {
      return row.proceso && row.proceso.valle && row.proceso.valle.nombre ? row.proceso.valle.nombre : 'N/A'
    },
    getUserName(row) {
      return row.user && row.user.nombre ? row.user.nombre : 'N/A'
    },
  },
}
</script>

<style lang="scss">
  @import '~@core/scss/vue/libs/vue-good-table.scss';

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

  .vgt-table {
    overflow: visible !important;
  }
</style>
