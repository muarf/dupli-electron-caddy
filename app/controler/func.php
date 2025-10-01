<?php
/**
 * Fichier principal des fonctions pour l'application Duplicator
 * 
 * Ce fichier inclut les modules de fonctions et fournit la compatibilité
 * avec l'ancien code. Pour les nouvelles fonctionnalités, utilisez
 * les classes et fonctions des modules.
 */

// Configuration sera incluse dynamiquement selon les besoins
// include('conf.php');

// Inclure les modules de fonctions
require_once __DIR__ . '/functions/init.php';
require_once __DIR__ . '/functions/database.php';
require_once __DIR__ . '/functions/news.php';
require_once __DIR__ . '/functions/email.php';
require_once __DIR__ . '/functions/stats.php';
require_once __DIR__ . '/functions/pricing.php';
require_once __DIR__ . '/functions/machines.php';
require_once __DIR__ . '/functions/tirage.php';
require_once __DIR__ . '/functions/consommation.php';
require_once __DIR__ . '/functions/utilities.php';

// Définir le mode développement
define('DEVELOPMENT_MODE', true);

// Log de chargement
log_info('Fichier func.php chargé', 'func.php');
// Classe Pdotest supprimée - utiliser pdo_connect() à la place






