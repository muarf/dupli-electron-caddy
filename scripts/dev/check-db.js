const fs = require('fs');
const path = require('path');

// Trouver le chemin de la base de données
const dbPath = path.join(process.env.APPDATA || process.env.HOME, 'dupli-electron', 'duplinew.sqlite');

console.log('Database path:', dbPath);
console.log('Database exists:', fs.existsSync(dbPath));

if (!fs.existsSync(dbPath)) {
    console.error('Database not found!');
    process.exit(1);
}

// Utiliser better-sqlite3 si disponible, sinon afficher le chemin
try {
    const Database = require('better-sqlite3');
    const db = new Database(dbPath, { readonly: true });

    const rows = db.prepare(`
        SELECT 
            id,
            document, 
            paper_size, 
            duplex, 
            color_mode, 
            total_pages, 
            pages_printed,
            status,
            timestamp
        FROM print_jobs 
        ORDER BY id DESC 
        LIMIT 20
    `).all();

    console.log('\n=== Last 20 Print Jobs ===\n');
    rows.forEach(row => {
        console.log(`ID: ${row.id}`);
        console.log(`  Document: ${row.document}`);
        console.log(`  Paper Size: ${row.paper_size || 'NULL'}`);
        console.log(`  Duplex: ${row.duplex}`);
        console.log(`  Color Mode: ${row.color_mode || 'NULL'}`);
        console.log(`  Pages: ${row.pages_printed}/${row.total_pages}`);
        console.log(`  Status: ${row.status}`);
        console.log(`  Time: ${row.timestamp}`);
        console.log('');
    });

    db.close();
} catch (e) {
    console.error('Error:', e.message);
    console.log('\nPlease install better-sqlite3: npm install better-sqlite3');
    console.log('Or check the database manually at:', dbPath);
}
