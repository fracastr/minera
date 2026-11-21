'use strict';
// node public/Export/addImages.test.js — Node 10 compatible, plain assert, no test runner.
// For every export template: inject images (directly, and after the xlsx-populate fill excelnode.js does), re-open
// with xlsx-populate, and check the new sheets exist, every original drawing/media part is byte-identical and all
// XML parts are well-formed.

var assert = require('assert');
var fs = require('fs');
var path = require('path');
var sax = require('sax');
var JSZip = require('jszip');
var XlsxPopulate = require('xlsx-populate');
var addImages = require('./addImages');

// 3×2 red PNG
var PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAIAAAASFvFNAAAAEklEQVR4nGP4z8AARCASyGAAAEbaAf89lckrAAAAAElFTkSuQmCC', 'base64');
var SHEETS = [
    { name: 'Diagrama Sistema', images: [{ title: 'Diagrama del proceso', png: PNG, maxWidth: 1600 }] },
    { name: 'Gráficos Sistema', images: [{ title: 'Desbalance <por> nodo & "algo"', png: PNG }, { title: 'Uso de tolerancia', png: PNG }] },
];

function wellFormed(name, xml) {
    var p = sax.parser(true);
    p.onerror = function (e) { throw new Error(name + ': ' + e.message); };
    p.write(xml).close();
}

function parts(zip, re) {
    return Object.keys(zip.files).filter(function (n) { return re.test(n) && !zip.files[n].dir; }).sort();
}

function bytes(zip, names) {
    return Promise.all(names.map(function (n) { return zip.file(n).async('nodebuffer'); }));
}

function check(label, original, injected) {
    var orig;
    var inj;
    return Promise.all([JSZip.loadAsync(original), JSZip.loadAsync(injected)]).then(function (z) {
        orig = z[0];
        inj = z[1];
        var keep = parts(orig, /^xl\/(drawings|media)\//);
        assert.ok(keep.length > 0, label + ': template has drawings');
        return Promise.all([bytes(orig, keep), bytes(inj, keep)]).then(function (b) {
            keep.forEach(function (n, i) { assert.ok(b[0][i].equals(b[1][i]), label + ': ' + n + ' changed'); });
        });
    }).then(function () {
        var xml = parts(inj, /\.(xml|rels|vml)$/);
        return Promise.all(xml.map(function (n) {
            return inj.file(n).async('string').then(function (s) { wellFormed(label + ' ' + n, s); });
        }));
    }).then(function () {
        return XlsxPopulate.fromDataAsync(injected);
    }).then(function (wb) {
        var names = wb.sheets().map(function (s) { return s.name(); });
        assert.strictEqual(names[names.length - 2], 'Diagrama Sistema', label);
        assert.strictEqual(names[names.length - 1], 'Gráficos Sistema', label);
        assert.strictEqual(wb.sheet('Gráficos Sistema').cell('A1').value(), 'Desbalance <por> nodo & "algo"', label);
        // Original sheets keep their place, so definedNames' localSheetId still points at the same sheets.
        return XlsxPopulate.fromDataAsync(original).then(function (o) {
            assert.deepStrictEqual(names.slice(0, o.sheets().length), o.sheets().map(function (s) { return s.name(); }), label);
        });
    }).then(function () {
        return inj.file('[Content_Types].xml').async('string');
    }).then(function (types) {
        assert.strictEqual((types.match(/Extension="png"/gi) || []).length, 1, label + ': one png Default');
    });
}

assert.deepStrictEqual(addImages.pngSize(PNG), { width: 3, height: 2 });
assert.throws(function () { addImages.pngSize(Buffer.from('GIF89a........................')); }, /not a PNG/);

var dir = __dirname;
var templates = fs.readdirSync(dir).filter(function (f) { return /^Exportar_.*\.xlsx$/.test(f); });
assert.ok(templates.length >= 7, 'templates found');

templates.reduce(function (chain, f) {
    return chain.then(function () {
        var original = fs.readFileSync(path.join(dir, f));
        return addImages(original, SHEETS)
            .then(function (out) { return check(f + ' (direct)', original, out); })
            .then(function () { return XlsxPopulate.fromDataAsync(original); })
            .then(function (wb) {
                wb.sheet('Utilidad').range('A1:B2').value([[1, 2], [3, 4]]);
                return wb.outputAsync();
            })
            .then(function (filled) { return addImages(filled, SHEETS); })
            .then(function (out) { return check(f + ' (filled)', original, out); })
            .then(function () { console.log('ok ' + f); });
    });
}, Promise.resolve()).then(function () {
    return addImages(Buffer.from('x'), []).then(function (b) { assert.strictEqual(b.toString(), 'x', 'no images: input untouched'); });
}).then(function () {
    console.log('all ok');
}).catch(function (e) {
    console.error(e);
    process.exitCode = 1;
});
