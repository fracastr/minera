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
file = myArgs[1] + '.xlsx'
path = myArgs[2]
console.log(typeof(myArgs2))
console.log(myArgs2)
write_excel(myArgs2);

function suma(a, b){
    return a + b
}
function write_excel (array){
    console.log("hola mundo")
    const XlsxPopulate = require('xlsx-populate');
    // fileName ='/Users/pc-mac/Desktop/Exportar_Pellet_3.xlsx'
    const fileName = path + '/Exportar.xlsx'
    console.log(process.cwd())
    // Load an existing workbook
    XlsxPopulate.fromFileAsync(fileName)
        .then(function(workbook) {
        // Modify the workbook.
        // Set all cell values to the same value:
        console.log("carga libro");
        const value = workbook.sheet("Utilidad").range("A1:F80");
        value.value(array)
        // value.value((cell, ri, ci, range) => array);
        console.log(path + "/" + file);
        return workbook.toFileAsync(path + "/" + file)
    }).catch(function (e) {
        console.log("FAILURE!!", e);
      })
}
