<?php
/**
 * Script pour synchroniser les tables d'aide depuis la version distante
 * Crée les tables seulement si elles n'existent pas localement
 */

require_once __DIR__ . '/controler/conf.php';
require_once __DIR__ . '/controler/functions/database.php';

echo "=== Synchronisation des tables d'aide ===\n";

try {
    // Connexion à la base locale
    $db = pdo_connect();
    echo "✓ Connexion à la base locale réussie\n";
    
    // Vérifier si la table aide_machines existe
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='aide_machines'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "⚠ Table aide_machines n'existe pas, création...\n";
        
        // Créer la table aide_machines
        $create_table_sql = "CREATE TABLE IF NOT EXISTS aide_machines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            machine TEXT NOT NULL UNIQUE,
            contenu_aide TEXT NOT NULL,
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($create_table_sql);
        echo "✓ Table aide_machines créée\n";
        
        // Insérer les données initiales d'aide ComColor
        $aide_comcolor = '<div class="alert alert-info">
            <p align="center">Pour connaitre le nombre à entrer, aller sur la machine :</p>
            <p align="center">Appuyer sur F1.</p>
            <p align="center">et imprimer la liste, notez sur la feuille quelle cartouche vous avez changé.</p>
            <p align="center">si c\'est une cartouche de couleur, entrez le chiffre total full color sinon total monochrome</p>
            <p align="center">Pour les tambours et unités de développement, entrez le nombre total de copies depuis le dernier changement</p>
        </div>
        <div align="center">
            <img src="img/compteur.png" width="80%">
        </div>';
        
        $stmt = $db->prepare("INSERT OR IGNORE INTO aide_machines (machine, contenu_aide) VALUES (?, ?)");
        $stmt->execute(['ComColor', $aide_comcolor]);
        echo "✓ Données initiales d'aide insérées\n";
        
    } else {
        echo "✓ Table aide_machines existe déjà, pas de création nécessaire\n";
    }
    
    // Vérifier si la table aide_machines_qa existe (pour les Q&A)
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='aide_machines_qa'");
    $qa_table_exists = $stmt->fetch();
    
    if (!$qa_table_exists) {
        echo "⚠ Table aide_machines_qa n'existe pas, création...\n";
        
        // Créer la table aide_machines_qa pour les Q&A
        $create_qa_table_sql = "CREATE TABLE IF NOT EXISTS aide_machines_qa (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            machine TEXT NOT NULL,
            question TEXT NOT NULL,
            reponse TEXT NOT NULL,
            ordre INTEGER DEFAULT 0,
            categorie TEXT DEFAULT 'general',
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($create_qa_table_sql);
        echo "✓ Table aide_machines_qa créée\n";
        
    } else {
        echo "✓ Table aide_machines_qa existe déjà, pas de création nécessaire\n";
    }
    
    // Afficher le résumé des tables d'aide
    echo "\n=== Résumé des tables d'aide ===\n";
    
    // Compter les aides existantes
    $stmt = $db->query("SELECT COUNT(*) FROM aide_machines");
    $count_aides = $stmt->fetchColumn();
    echo "Tables d'aide: $count_aides entrées\n";
    
    // Compter les Q&A existantes
    if ($qa_table_exists) {
        $stmt = $db->query("SELECT COUNT(*) FROM aide_machines_qa");
        $count_qa = $stmt->fetchColumn();
        echo "Q&A d'aide: $count_qa entrées\n";
    }
    
    // Lister les machines avec aide
    $stmt = $db->query("SELECT machine FROM aide_machines ORDER BY machine");
    $machines = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($machines)) {
        echo "Machines avec aide: " . implode(', ', $machines) . "\n";
    }
    
    echo "\n✓ Synchronisation terminée avec succès!\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
