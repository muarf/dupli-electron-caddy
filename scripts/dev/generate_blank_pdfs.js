const PDFDocument = require('pdfkit');
const fs = require('fs');
const path = require('path');

function createBlankPdf(filename, size, pages) {
    const doc = new PDFDocument({ autoFirstPage: false });
    const filePath = path.join(__dirname, filename);
    const stream = fs.createWriteStream(filePath);

    doc.pipe(stream);

    for (let i = 0; i < pages; i++) {
        doc.addPage({ size: size });
    }

    doc.end();

    return new Promise((resolve) => {
        stream.on('finish', () => {
            console.log(`Created ${filename} (${size}, ${pages} pages)`);
            resolve();
        });
    });
}

async function generate() {
    console.log("Generating blank PDFs...");
    await createBlankPdf('blank_A4_4pages.pdf', 'A4', 4);
    await createBlankPdf('blank_A3_4pages.pdf', 'A3', 4);
    console.log("Done.");
}

generate();
