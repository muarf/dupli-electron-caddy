<?php
/**
 * Migration pour ajouter la colonne print_job_id à recorded_print_jobs
 */

function migrate_add_print_job_id_to_recorded_print_jobs($db)
{
    error_log("[MIGRATION] Ajout de la colonne print_job_id à recorded_print_jobs");

    $db->exec("ALTER TABLE recorded_print_jobs ADD COLUMN print_job_id INTEGER");

    // Vérification post-création
    $checkQuery = $db->query("PRAGMA table_info(`recorded_print_jobs`)");
    $cols = $checkQuery ? $checkQuery->fetchAll(PDO::FETCH_ASSOC) : [];
    $found = false;
    foreach($cols as $c) { if($c['name'] === 'print_job_id') $found = true; }
    if (!$found) {
        throw new Exception("ERREUR: La colonne 'print_job_id' n'a pas pu être ajoutée à 'recorded_print_jobs'.");
    }
}