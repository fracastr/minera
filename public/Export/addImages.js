'use strict';
// Adds sheets with PNG images to an .xlsx by patching the zip (OOXML) directly. Every existing part is left
// byte-for-byte as it was, so the templates' hand-drawn diagram sheets (pictures + shapes) survive untouched.
// Runs on the server's Node 10: ES2018 only (no optional chaining, no ??), CommonJS.

var path = require('path');
var JSZip;
try {
    JSZip = require('jszip');
} catch (e) {
    // xlsx-populate depends on jszip; reach it through xlsx-populate if npm did not hoist it.
    JSZip = require(require.resolve('jszip', { paths: [path.dirname(require.resolve('xlsx-populate'))] }));
}

var NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
var REL_SHEET = NS_R + '/worksheet';
var REL_DRAWING = NS_R + '/drawing';
var REL_IMAGE = NS_R + '/image';
var CT_SHEET = 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml';
var CT_DRAWING = 'application/vnd.openxmlformats-officedocument.drawing+xml';
var EMU_PER_PX = 9525; // 914400 EMU per inch at 96 px per inch
var ROW_PX = 20; // default row height (15 pt) of a sheet without sheetFormatPr

function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/** Width and height from the PNG IHDR chunk. Throws on anything that is not a PNG. */
function pngSize(buf) {
    var sig = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
    if (!Buffer.isBuffer(buf) || buf.length < 24) throw new Error('not a PNG');
    for (var i = 0; i < 8; i++) if (buf[i] !== sig[i]) throw new Error('not a PNG');
    if (buf.toString('ascii', 12, 16) !== 'IHDR') throw new Error('PNG without IHDR');
    var width = buf.readUInt32BE(16);
    var height = buf.readUInt32BE(20);
    if (!width || !height) throw new Error('PNG with zero size');
    return { width: width, height: height };
}

/** Next unused number N for parts named `<prefix>N<suffix>` (e.g. xl/media/image7.png). */
function nextIndex(zip, re) {
    var max = 0;
    Object.keys(zip.files).forEach(function (name) {
        var m = re.exec(name);
        if (m) max = Math.max(max, Number(m[1]));
    });
    return max + 1;
}

function nextRelId(relsXml) {
    var max = 0;
    var re = /Id="rId(\d+)"/g;
    var m;
    while ((m = re.exec(relsXml))) max = Math.max(max, Number(m[1]));
    return 'rId' + (max + 1);
}

function insertBefore(xml, closing, fragment) {
    var at = xml.lastIndexOf(closing);
    if (at < 0) throw new Error('missing ' + closing);
    return xml.slice(0, at) + fragment + xml.slice(at);
}

function relsXml(rels) {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
        rels.map(function (r) { return '<Relationship Id="' + r.id + '" Type="' + r.type + '" Target="' + r.target + '"/>'; }).join('') +
        '</Relationships>';
}

/** Excel sheet names: max 31 chars, no []:*?/\ , unique ignoring case. */
function uniqueSheetName(workbookXml, wanted) {
    var base = String(wanted).replace(/[\[\]:*?\/\\]/g, ' ').slice(0, 31);
    var taken = {};
    var re = /<sheet\b[^>]*\bname="([^"]*)"/g;
    var m;
    while ((m = re.exec(workbookXml))) taken[m[1].toLowerCase()] = true;
    var name = base;
    for (var n = 2; taken[esc(name).toLowerCase()]; n++) name = base.slice(0, 31 - String(n).length - 3) + ' (' + n + ')';
    return name;
}

/**
 * @param {Buffer} xlsx    the workbook
 * @param {Array<{name: string, images: Array<{title?: string, png: Buffer, maxWidth?: number}>}>} sheets
 *        new sheets, appended after the existing ones (so definedNames' localSheetId indexes stay valid)
 * @returns {Promise<Buffer>}
 */
function addImages(xlsx, sheets) {
    sheets = (sheets || []).filter(function (s) { return s && s.images && s.images.length; });
    if (!sheets.length) return Promise.resolve(xlsx);

    var zip;
    return JSZip.loadAsync(xlsx).then(function (z) {
        zip = z;
        return Promise.all(['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', '[Content_Types].xml'].map(function (p) {
            var f = zip.file(p);
            if (!f) throw new Error('not an xlsx: missing ' + p);
            return f.async('string');
        }));
    }).then(function (parts) {
        var workbook = parts[0];
        var wbRels = parts[1];
        var types = parts[2];

        if (!/<Default\b[^>]*Extension="png"/i.test(types)) {
            types = insertBefore(types, '</Types>', '<Default Extension="png" ContentType="image/png"/>');
        }

        sheets.forEach(function (sheet) {
            var sheetN = nextIndex(zip, /^xl\/worksheets\/sheet(\d+)\.xml$/);
            var drawingN = nextIndex(zip, /^xl\/drawings\/drawing(\d+)\.xml$/);
            var sheetIds = [0];
            workbook.replace(/<sheet\b[^>]*\bsheetId="(\d+)"/g, function (_, id) { sheetIds.push(Number(id)); return _; });
            var sheetId = Math.max.apply(null, sheetIds) + 1;
            var wbRelId = nextRelId(wbRels);

            var rows = [];
            var anchors = [];
            var drawingRels = [];
            var row = 0; // 0-based
            sheet.images.forEach(function (img, i) {
                var size = pngSize(img.png);
                var maxW = img.maxWidth || 1000;
                var k = Math.min(1, maxW / size.width);
                var w = Math.round(size.width * k);
                var h = Math.round(size.height * k);
                if (img.title) {
                    rows.push('<row r="' + (row + 1) + '"><c r="A' + (row + 1) + '" t="inlineStr"><is><t>' + esc(img.title) + '</t></is></c></row>');
                    row++;
                }
                var imageN = nextIndex(zip, /^xl\/media\/image(\d+)\.\w+$/);
                zip.file('xl/media/image' + imageN + '.png', img.png);
                var relId = 'rId' + (i + 1);
                drawingRels.push({ id: relId, type: REL_IMAGE, target: '../media/image' + imageN + '.png' });
                var cx = w * EMU_PER_PX;
                var cy = h * EMU_PER_PX;
                anchors.push(
                    '<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' + row + '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>' +
                    '<xdr:ext cx="' + cx + '" cy="' + cy + '"/>' +
                    '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' + (i + 2) + '" name="Imagen ' + (i + 1) + '" descr="' + esc(img.title || sheet.name) + '"/>' +
                    '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>' +
                    '<xdr:blipFill><a:blip r:embed="' + relId + '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>' +
                    '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' + cx + '" cy="' + cy + '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>' +
                    '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>'
                );
                row += Math.ceil(h / ROW_PX) + 2; // the image plus one blank row before the next title
            });

            zip.file('xl/drawings/drawing' + drawingN + '.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="' + NS_R + '">' +
                anchors.join('') + '</xdr:wsDr>');
            zip.file('xl/drawings/_rels/drawing' + drawingN + '.xml.rels', relsXml(drawingRels));
            zip.file('xl/worksheets/sheet' + sheetN + '.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="' + NS_R + '">' +
                '<sheetData>' + rows.join('') + '</sheetData><drawing r:id="rId1"/></worksheet>');
            zip.file('xl/worksheets/_rels/sheet' + sheetN + '.xml.rels', relsXml([{ id: 'rId1', type: REL_DRAWING, target: '../drawings/drawing' + drawingN + '.xml' }]));

            var name = uniqueSheetName(workbook, sheet.name);
            // Uses the root's r: prefix when it is the relationships namespace (it is in every template); else declares it locally.
            var rDecl = workbook.indexOf('xmlns:r="' + NS_R + '"') >= 0 ? '' : ' xmlns:r="' + NS_R + '"';
            workbook = insertBefore(workbook, '</sheets>', '<sheet' + rDecl + ' name="' + esc(name) + '" sheetId="' + sheetId + '" r:id="' + wbRelId + '"/>');
            wbRels = insertBefore(wbRels, '</Relationships>', '<Relationship Id="' + wbRelId + '" Type="' + REL_SHEET + '" Target="worksheets/sheet' + sheetN + '.xml"/>');
            types = insertBefore(types, '</Types>',
                '<Override PartName="/xl/worksheets/sheet' + sheetN + '.xml" ContentType="' + CT_SHEET + '"/>' +
                '<Override PartName="/xl/drawings/drawing' + drawingN + '.xml" ContentType="' + CT_DRAWING + '"/>');
        });

        zip.file('xl/workbook.xml', workbook);
        zip.file('xl/_rels/workbook.xml.rels', wbRels);
        zip.file('[Content_Types].xml', types);
        return zip.generateAsync({ type: 'nodebuffer', compression: 'DEFLATE' });
    });
}

module.exports = addImages;
module.exports.pngSize = pngSize;
