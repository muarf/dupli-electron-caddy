<?php
// Script de fusion intelligente des bases de données SQLite pour Duplicator

$appDataPath = getenv('APPDATA');
if (!$appDataPath) {
    $appDataPath = getenv('USERPROFILE') . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Roaming';
}

if (!is_dir($appDataPath)) {
    echo "Impossible de determiner le repertoire AppData.\n";
    exit(1);
}

// On définit la base de production (Main) comme la base cible de la fusion.
$targetDbPath = $appDataPath . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'duplinew.sqlite';
$targetDbDir = dirname($targetDbPath);

$sources = [
    ['name' => 'Main (Production)', 'path' => $targetDbPath],
    ['name' => 'Beta', 'path' => $appDataPath . DIRECTORY_SEPARATOR . 'Duplicator Beta' . DIRECTORY_SEPARATOR . 'duplinew.sqlite'],
    ['name' => 'Alpha', 'path' => $appDataPath . DIRECTORY_SEPARATOR . 'Duplicator Alpha' . DIRECTORY_SEPARATOR . 'duplinew.sqlite']
];

echo "=== DEBUT DU PROCESSUS DE FUSION INTELLIGENTE DES BASES (PHP) ===\n\n";
echo "Fichier cible : $targetDbPath\n\n";

// 1. Sauvegarde de securite
echo "--- 1. CREATION DES SAUVEGARDES ---\n";
if (!is_dir($targetDbDir)) {
    mkdir($targetDbDir, 0777, true);
}

foreach ($sources as $source) {
    if (file_exists($source['path'])) {
        $envSuffix = strtolower(explode(' ', $source['name'])[0]); // 'main', 'beta' ou 'alpha'
        $backupPath = $source['path'] . '.' . $envSuffix . '.' . time() . '.bak';
        if (copy($source['path'], $backupPath)) {
            echo "[Sauvegarde OK] {$source['name']} sauvegardee vers : " . basename($backupPath) . "\n";
        } else {
            echo "[Sauvegarde ECHOUEE] {$source['name']}\n";
        }
    } else {
        echo "[Non trouvee] Pas de base active pour {$source['name']}\n";
    }
}
echo "\n";

// Trouver une base de depart a utiliser si la cible principale n'existe pas encore
$activeSources = array_filter($sources, function($s) {
    return file_exists($s['path']);
});

if (empty($activeSources)) {
    echo "Aucune base de donnees existante n'a ete detectee. Rien a fusionner.\n";
    exit(0);
}

if (!file_exists($targetDbPath)) {
    echo "La base cible principale n'existe pas encore. Initialisation...\n";
    $firstSource = reset($activeSources);
    if (copy($firstSource['path'], $targetDbPath)) {
        echo "Base initialisee avec succes a partir de {$firstSource['name']}.\n\n";
    } else {
        echo "Echec de l'initialisation de la base cible.\n";
        exit(1);
    }
}

// Définition des tables d'historique nécessitant une réattribution d'ID et un dédoublonnage composite
$historyTables = [
    'print_sessions' => [
        'uniq' => ['contact', 'opened_at'],
        'pk' => 'id'
    ],
    'print_jobs' => [
        'uniq' => ['document', 'timestamp'],
        'pk' => 'id'
    ],
    'dupli' => [
        'uniq' => ['document_name', 'date', 'contact'],
        'pk' => 'id'
    ],
    'photocop' => [
        'uniq' => ['document_name', 'date', 'contact'],
        'pk' => 'id'
    ]
];

// 2. Fusion
echo "--- 2. FUSION DES DONNEES ---\n";
try {
    $db = new PDO("sqlite:$targetDbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA foreign_keys = OFF;"); // Desactiver temporairement pendant la fusion

    // Recuperer les tables de la base principale
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $mainTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($activeSources as $source) {
        // Ne pas fusionner la cible sur elle-meme
        if (realpath($source['path']) === realpath($targetDbPath)) {
            continue;
        }

        echo "\nFusion de la base [{$source['name']}]...\n";
        
        // Attacher la BDD source
        $db->exec("ATTACH DATABASE " . $db->quote($source['path']) . " AS source_db");

        // Recuperer la liste des tables de la base source
        $sourceTablesStmt = $db->query("SELECT name FROM source_db.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $sourceTables = $sourceTablesStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($sourceTables as $tableName) {
            // Ignorer les index et tables virtuelles FTS (gérés par triggers)
            if (preg_match('/_fts(_|$)/i', $tableName)) {
                continue;
            }

            // Si la table n'existe pas du tout sur la cible principale, la creer
            if (!in_array($tableName, $mainTables)) {
                $db->exec("CREATE TABLE IF NOT EXISTS main.`{$tableName}` AS SELECT * FROM source_db.`{$tableName}` WHERE 1=0");
                $mainTables[] = $tableName;
                echo "  [OK] Table {$tableName} creee sur la cible.\n";
            }

            // Recuperer la structure des colonnes de la table sur main
            $mainColsStmt = $db->query("PRAGMA main.table_info(`$tableName`)");
            $mainCols = [];
            foreach ($mainColsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $mainCols[$col['name']] = $col;
            }

            // Recuperer la structure des colonnes de la table sur source_db
            $sourceColsStmt = $db->query("PRAGMA source_db.table_info(`$tableName`)");
            $sourceCols = [];
            foreach ($sourceColsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $sourceCols[$col['name']] = $col;
            }

            // Mettre a niveau la structure de la table principale si la source a des colonnes en plus
            foreach ($sourceCols as $colName => $colInfo) {
                if (!isset($mainCols[$colName])) {
                    $type = $colInfo['type'] ?: 'TEXT';
                    $notNull = $colInfo['notnull'] ? 'NOT NULL' : '';
                    $dflt = $colInfo['dflt_value'] !== null ? "DEFAULT " . $colInfo['dflt_value'] : '';
                    
                    try {
                        $db->exec("ALTER TABLE main.`$tableName` ADD COLUMN `$colName` $type $notNull $dflt");
                        echo "  [Structure] Colonne `$colName` ajoutee a la table `$tableName`.\n";
                        $mainCols[$colName] = $colInfo;
                    } catch (Exception $alterEx) {
                        echo "  [ATTENTION] Impossible d'ajouter la colonne `$colName` : " . $alterEx->getMessage() . "\n";
                    }
                }
            }

            // Determiner les colonnes communes
            $commonCols = array_intersect(array_keys($mainCols), array_keys($sourceCols));
            if (empty($commonCols)) {
                continue;
            }

            // Gestion de l'insertion selon le type de table (historique vs config)
            if (isset($historyTables[$tableName])) {
                // Table d'historique : omettre la clé primaire auto-incrémentée pour qu'elle soit régénérée par SQLite
                $pk = $historyTables[$tableName]['pk'];
                $uniqKeys = $historyTables[$tableName]['uniq'];

                // Enlever la clé primaire de la liste des colonnes d'insertion
                $insertCols = array_filter($commonCols, function($c) use ($pk) { return $c !== $pk; });
                
                $colsListStr = implode(', ', array_map(function($c) { return "`$c`"; }, $insertCols));

                // Construire la clause WHERE NOT EXISTS pour dédoublonner
                $whereNotExistsParts = [];
                foreach ($uniqKeys as $key) {
                    $whereNotExistsParts[] = "main.`$tableName`.`$key` IS source_db.`$tableName`.`$key`";
                }
                $whereNotExistsStr = implode(' AND ', $whereNotExistsParts);

                $sql = "INSERT INTO main.`$tableName` ($colsListStr) 
                        SELECT $colsListStr FROM source_db.`$tableName` 
                        WHERE NOT EXISTS (
                            SELECT 1 FROM main.`$tableName` 
                            WHERE $whereNotExistsStr
                        )";
            } else {
                // Table de configuration : INSERT OR IGNORE complet pour garder les IDs et l'intégrité
                $colsListStr = implode(', ', array_map(function($c) { return "`$c`"; }, $commonCols));
                $sql = "INSERT OR IGNORE INTO main.`$tableName` ($colsListStr) SELECT $colsListStr FROM source_db.`$tableName`";
            }
            
            try {
                $affected = $db->exec($sql);
                if ($affected > 0) {
                    echo "  [OK] Table {$tableName} : +{$affected} lignes fusionnees.\n";
                }
            } catch (Exception $insertEx) {
                echo "  [ATTENTION] Erreur lors de l'insertion dans {$tableName} : " . $insertEx->getMessage() . "\n";
            }
        }

        // Detacher la BDD source
        $db->exec("DETACH DATABASE source_db");
        echo "Base [{$source['name']}] detachee.\n";
    }

    // Réactiver les clés étrangères et nettoyer FTS5 conflictuel pour la version Main stable
    $db->exec("DROP TABLE IF EXISTS main.bibliotheque_files_fts;");
    $db->exec("PRAGMA foreign_keys = ON;");

    // 3. Bilan
    echo "\n--- 3. BILAN DE LA BASE DE DONNEES UNIQUE ---\n";
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($finalTables as $tableName) {
        if (preg_match('/_fts(_|$)/i', $tableName)) continue;
        $count = $db->query("SELECT COUNT(*) FROM main.{$tableName}")->fetchColumn();
        echo "  Table " . str_pad($tableName, 25) . " : $count lignes au total.\n";
    }
    
    echo "\n=== PROCESSUS DE FUSION REUSSI ===\n";

} catch (Exception $e) {
    echo "\n[ERREUR] Une erreur fatale est survenue lors de la fusion : " . $e->getMessage() . "\n";
    exit(1);
}
