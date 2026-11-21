'use strict';
// Fills the export template's "Utilidad" and "Datos Extra" sheets and writes <storage>/<id>.xlsx.
// Runs on the server's Node 10 (ES2018, CommonJS). www (v1) calls it with the first six arguments; v2 adds the
// seventh to append image sheets.
//
//   node excelnode.js <matriz json> <data_extra json> <id> <publicDir> <template> <storage> [<sheets json>]
//
// <sheets json>: [{"name": "Diagrama Sistema", "images": [{"title": "...", "path": "/abs/file.png", "maxWidth": 1600}]}]

var fs = require('fs');
var XlsxPopulate = require('xlsx-populate');
var addImages = require('./addImages');

var args = process.argv.slice(2);
var matriz = JSON.parse(args[0]);
var dataExtra = JSON.parse(args[1]);
var out = args[5] + '/' + args[2] + '.xlsx';
var template = args[4];
var sheets = args[6] ? JSON.parse(args[6]) : [];

XlsxPopulate.fromFileAsync(template)
    .then(function (workbook) {
        workbook.sheet('Utilidad').range('A1:M100').value(matriz);
        workbook.sheet('Datos Extra').range('A1:M100').value(dataExtra);
        return workbook.outputAsync();
    })
    .then(function (buf) {
        return addImages(buf, sheets.map(function (s) {
            return {
                name: s.name,
                images: s.images.map(function (i) {
                    return { title: i.title, maxWidth: i.maxWidth, png: fs.readFileSync(i.path) };
                }),
            };
        }));
    })
    .then(function (buf) {
        fs.writeFileSync(out, buf);
        console.log(out);
    })
    .catch(function (e) {
        console.error('excelnode failed: ' + (e && e.stack ? e.stack : e));
        process.exitCode = 1;
    });
