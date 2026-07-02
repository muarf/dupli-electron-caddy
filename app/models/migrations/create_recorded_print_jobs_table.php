<?php
/**
 * Migration pour créer la table des jobs d'impression déjà enregistrés
 */

function migrate_create_recorded_print_jobs_table($db)
{
    error_log("[MIGRATION] Création de la table recorded_print_jobs");

    // Créer la table pour mémoriser les jobs déjà traités
    $db->exec("CREATE TABLE IF NOT EXISTS recorded_print_jobs (
        job_id TEXT NOT NULL,
        printer_name TEXT NOT NULL,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (job_id, printer_name)
    )");

    // Créer un index pour accélérer la vérification lors des notifications
    $db->exec("CREATE INDEX IF NOT EXISTS idx_recorded_jobs_lookup ON recorded_print_jobs(job_id, printer_name)");

    // Vérification post-création
    $checkQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='recorded_print_jobs'");
    if (!$checkQuery || !$checkQuery->fetch()) {
        throw new Exception("ERREUR: La table 'recorded_print_jobs' n'a pas pu être créée.");
    }
}
