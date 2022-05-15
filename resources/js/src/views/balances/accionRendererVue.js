import {
    BButton,
  } from 'bootstrap-vue'
export default {
    BButton,
    template: `
        <span>
              <button @click="buttonClicked()">button</button>
          </span>
    `,
    setup(props) {
        //const cellValue = props.params.valueFormatted ? props.params.valueFormatted : props.params.value;
        const buttonClicked = () => alert(props);

        // props.params contains the cell & row information and is made available to this component at creation time
        // see ICellRendererParams for more details
        return {
            buttonClicked
        }
    }
};
