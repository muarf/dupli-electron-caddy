/**
 * Script pour générer les PDFs de test selon les 5 scénarios du plan objectif
 */

const PDFDocument = require('pdfkit');
const fs = require('fs');
const path = require('path');

// Configuration
const TEST_PDF_DIR = path.join(__dirname, 'test-pdfs');

// Scénarios de test selon le plan objectif
const TEST_SCENARIOS = [
    {
        name: 'Test 1: A4 Simplex Noir et Blanc (1 page)',
        file: 'test-A4-1.pdf',
        paperSize: 'A4', // 595.28 x 841.89 points
        duplex: false,
        color: false,
        pages: 1
    },
    {
        name: 'Test 2: A4 Duplex Couleur (2 pages)',
        file: 'test-A4-2.pdf',
        paperSize: 'A4',
        duplex: true,
        color: true,
        pages: 2
    },
    {
        name: 'Test 3: A3 Simplex Noir et Blanc (1 page)',
        file: 'test-A3-3.pdf',
        paperSize: 'A3', // 841.89 x 1190.55 points
        duplex: false,
        color: false,
        pages: 1
    },
    {
        name: 'Test 4: A3 Duplex Couleur (2 pages)',
        file: 'test-A3-4.pdf',
        paperSize: 'A3',
        duplex: true,
        color: true,
        pages: 2
    },
    {
        name: 'Test 5: A4 Simplex Couleur (3 pages)',
        file: 'test-A4-5.pdf',
        paperSize: 'A4',
        duplex: false,
        color: true,
        pages: 3
    }
];

/**
 * Générer un PDF de test
 */
function generateTestPDF(scenario) {
    return new Promise((resolve, reject) => {
        const filePath = path.join(TEST_PDF_DIR, scenario.file);

        // Définir les dimensions selon le format papier
        const dimensions = {
            A4: [595.28, 841.89],
            A3: [841.89, 1190.55]
        };

        const [width, height] = dimensions[scenario.paperSize] || dimensions.A4;

        // Créer le document PDF
        const doc = new PDFDocument({
            size: [width, height],
            margin: 50
        });

        // Pipe vers le fichier
        const stream = fs.createWriteStream(filePath);
        doc.pipe(stream);

        // Générer chaque page
        for (let pageNum = 1; pageNum <= scenario.pages; pageNum++) {
            if (pageNum > 1) {
                doc.addPage();
            }

            // Définir la couleur du texte selon le scénario
            if (scenario.color) {
                doc.fillColor('blue'); // Couleur pour les tests couleur
            } else {
                doc.fillColor('black'); // Noir pour les tests N&B
            }

            // Contenu de la page
            const titleY = 100;
            const contentY = 150;

            // Titre
            doc.fontSize(24).text(`Test d'Impression - ${scenario.paperSize}`, 50, titleY, {
                align: 'center'
            });

            // Informations du test
            doc.fontSize(14).text(`Fichier: ${scenario.file}`, 50, contentY);
            doc.text(`Format: ${scenario.paperSize}`, 50, contentY + 30);
            doc.text(`Duplex: ${scenario.duplex ? 'Oui' : 'Non'}`, 50, contentY + 50);
            doc.text(`Couleur: ${scenario.color ? 'Couleur' : 'Noir et Blanc'}`, 50, contentY + 70);
            doc.text(`Page: ${pageNum} / ${scenario.pages}`, 50, contentY + 90);

            // Numéro du test dans le nom du fichier
            const testMatch = scenario.file.match(/-(\d+)\.pdf/);
            if (testMatch) {
                const testNum = testMatch[1];
                doc.fontSize(16).text(`Test numéro: ${testNum}`, 50, contentY + 120);
            }

            // Contenu de remplissage
            doc.fontSize(10);
            let y = contentY + 160;
            for (let i = 0; i < 20; i++) {
                if (y > height - 100) break;
                doc.text(`Ligne de test ${i + 1} - Ceci est du contenu de test pour vérifier le monitoring d'impression. `.repeat(3), 50, y, {
                    width: width - 100,
                    align: 'left'
                });
                y += 15;
            }

            // Pied de page
            doc.fontSize(8).text(`Généré automatiquement pour les tests du système de monitoring - ${new Date().toISOString()}`, 50, height - 50, {
                align: 'center'
            });
        }

        // Finaliser le document
        doc.end();

        stream.on('finish', () => {
            console.log(`✅ Généré: ${scenario.file} (${scenario.pages} page(s), ${scenario.paperSize}, ${scenario.duplex ? 'Duplex' : 'Simplex'}, ${scenario.color ? 'Couleur' : 'N&B'})`);
            resolve();
        });

        stream.on('error', reject);
    });
}

/**
 * Fonction principale
 */
async function main() {
    console.log('📄 Génération des PDFs de test...\n');

    // Créer le répertoire si nécessaire
    if (!fs.existsSync(TEST_PDF_DIR)) {
        fs.mkdirSync(TEST_PDF_DIR, { recursive: true });
    }

    // Générer chaque PDF de test
    for (const scenario of TEST_SCENARIOS) {
        try {
            await generateTestPDF(scenario);
        } catch (error) {
            console.error(`❌ Erreur lors de la génération de ${scenario.file}:`, error.message);
        }
    }

    console.log('\n🎉 Tous les PDFs de test ont été générés avec succès!');
    console.log(`📁 Fichiers créés dans: ${TEST_PDF_DIR}`);

    // Lister les fichiers générés
    const files = fs.readdirSync(TEST_PDF_DIR).filter(f => f.endsWith('.pdf'));
    console.log('\n📋 Fichiers générés:');
    files.forEach(file => {
        const stats = fs.statSync(path.join(TEST_PDF_DIR, file));
        console.log(`   - ${file} (${Math.round(stats.size / 1024)} KB)`);
    });
}

// Exécuter si appelé directement
if (require.main === module) {
    main().catch(console.error);
}

module.exports = { generateTestPDF, TEST_SCENARIOS };
