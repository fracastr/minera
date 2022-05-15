<template>
    <span>
        <b-button
            type="button"
            variant="success"
            class="mr-1"
            @click="exportarExcel(params)"
        >
            Exportar Excel
        </b-button>
    </span>
</template>

<script>
import {
  BButton,
} from 'bootstrap-vue'
export default {
    data(){
        return{
            propiedades: '',
        }
    },
    components:{
        BButton,
    },
    methods:{
        exportarExcel(params){
            console.log("params")
            console.log(params.data);
        }
    },
    setup(props){
        console.log("propiedades");
        console.log(props);
    }
};
</script>

<style scoped>
</style>
