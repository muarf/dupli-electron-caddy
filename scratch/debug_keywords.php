<?php
require_once __DIR__ . '/../app/controler/conf.php';
require_once __DIR__ . '/../app/controler/func.php';
require_once __DIR__ . '/../app/models/BibliothequeManager.php';

$manager = new BibliothequeManager();

// Reflection pour accéder à la méthode privée
$method = new ReflectionMethod('BibliothequeManager', 'extractKeywords');
$method->setAccessible(true);

$text = "Blanqui était un révolutionnaire français. L'insurrection est un art. République, socialisme et barricades.";
$keywords = $method->invoke($manager, $text);

echo "Texte de test : $text\n";
echo "Mots-clés extraits : " . implode(', ', $keywords) . "\n";

// Test avec le texte de la base
$db = pdo_connect();
$stmt = $db->prepare("SELECT extracted_text FROM bibliotheque_files WHERE filename LIKE '%Blanqui%'");
$stmt->execute();
$fullText = $stmt->fetchColumn();

echo "\nExtraction sur le texte réel (1000 premiers caractères) :\n";
$realKeywords = $method->invoke($manager, substr($fullText, 0, 1000));
echo "Mots-clés extraits : " . implode(', ', $realKeywords) . "\n";
