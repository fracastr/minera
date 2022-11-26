console.log("hellow world")
console.log(suma(5, 7))
console.log(process.argv);
const myArgs = process.argv.slice(2);
//console.log(JSON.parse(myArgs[0]));
// myArgs = JSON.parse(myArgs);
// console.log(myArgs)
console.log(typeof(myArgs[0]))
console.log(myArgs[0])
myArgs2 = JSON.parse(myArgs[0])
data_extra = JSON.parse(myArgs[1])
file = myArgs[2] + '.xlsx'
path = myArgs[3]
fileName = myArgs[4]
storage = myArgs[5]
console.log(typeof(myArgs2))
console.log(myArgs2)
write_excel(myArgs2, data_extra);

function suma(a, b){
    return a + b
}
function write_excel (array, data_extra){
    console.log("hola mundo")
    const XlsxPopulate = require('xlsx-populate');
    // fileName ='/Users/pc-mac/Desktop/Exportar_Pellet_3.xlsx'
    //const fileName = path
    console.log(process.cwd())
    // Load an existing workbook
    XlsxPopulate.fromFileAsync(fileName)
        .then(function(workbook) {
        // Modify the workbook.
        // Set all cell values to the same value:
        console.log("carga libro");
        const value = workbook.sheet("Utilidad").range("A1:F80");
        value.value(array)

        const value2 = workbook.sheet("Datos Extra").range("A1:G80");
        value2.value(data_extra)
        // value.value((cell, ri, ci, range) => array);
        console.log(storage + "/" + file);
        return workbook.toFileAsync(storage + "/" + file)
    }).catch(function (e) {
        console.log("FAILURE!!", e);
      })
}
