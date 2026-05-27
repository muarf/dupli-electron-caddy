<?php
/**
 * Migration pour ajouter la colonne print_job_id à recorded_print_jobs
 */

function migrate_add_print_job_id_to_recorded_print_jobs($db)
{
    error_log("[MIGRATION] Ajout de la colonne print_job_id à recorded_print_jobs");

    $db->exec("ALTER TABLE recorded_print_jobs ADD COLUMN print_job_id INTEGER");
}