<?php
// update_external_images_in_db.php

// Ce script lit deux images locales (image1.png, image2.png),
// les compresse (max 800px de large, JPEG qualité 80%),
// les encode en base64 (data:image/jpeg;base64,...) et
// remplace leurs URLs externes dans la colonne `reponse` de la table `aide_machines_qa`.

ini_set('display_errors', '1');
error_reporting(E_ALL);

$dbFile = __DIR__ . '/duplinew.sqlite';
$maxWidth = 800;   // largeur maxi
$quality = 0.8;    // qualité JPEG (0..1)

$imagesToReplace = [
    [
        'local' => __DIR__ . '/image1.png',
        'url'   => 'https://www.kreyoly.com/uploads/allimg/20230306/1-23030611253G51.jpg',
        'like'  => '%1-23030611253G51.jpg%'
    ],
    [
        'local' => __DIR__ . '/image2.png',
        'url'   => 'https://www.kreyoly.com/uploads/allimg/20230306/1-23030611253T59.jpg',
        'like'  => '%1-23030611253T59.jpg%'
    ],
];

function pdo_connect(string $dbFile): PDO {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function optimizeToBase64(string $path, int $maxWidth, float $quality): string {
    if (!file_exists($path)) {
        throw new RuntimeException("Image introuvable: $path");
    }

    $info = getimagesize($path);
    if (!$info) {
        throw new RuntimeException("Impossible de lire les informations d'image: $path");
    }

    switch ($info['mime']) {
        case 'image/jpeg':
            $img = imagecreatefromjpeg($path);
            break;
        case 'image/png':
            $img = imagecreatefrompng($path);
            break;
        case 'image/gif':
            $img = imagecreatefromgif($path);
            break;
        default:
            throw new RuntimeException('Type non supporté: ' . $info['mime']);
    }

    if (!$img) {
        throw new RuntimeException("Echec de création GD: $path");
    }

    $w = imagesx($img);
    $h = imagesy($img);

    if ($w > $maxWidth) {
        $newW = $maxWidth;
        $newH = (int) round($h * ($maxWidth / $w));
        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    ob_start();
    imagejpeg($img, null, (int) round($quality * 100));
    $data = ob_get_clean();
    imagedestroy($img);

    return 'data:image/jpeg;base64,' . base64_encode($data);
}

try {
    $db = pdo_connect($dbFile);
    echo "✅ Connecté à la base: $dbFile\n";

    // Vérifier présence des lignes avant remplacement
    foreach ($imagesToReplace as $img) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM aide_machines_qa WHERE reponse LIKE ?");
        $stmt->execute([$img['like']]);
        $count = (int) $stmt->fetchColumn();
        echo "🔎 Occurrences avant: {$img['url']} => $count\n";
    }

    // Appliquer les remplacements
    foreach ($imagesToReplace as $img) {
        echo "\n🖼️ Traitement: {$img['local']}\n";
        $base64 = optimizeToBase64($img['local'], $maxWidth, $quality);

        $sql = "UPDATE aide_machines_qa SET reponse = REPLACE(reponse, ?, ?) WHERE reponse LIKE ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$img['url'], $base64, $img['like']]);

        echo "✅ Remplacement effectué pour {$img['url']}\n";
    }

    // Vérifier après
    foreach ($imagesToReplace as $img) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM aide_machines_qa WHERE reponse LIKE ?");
        $stmt->execute([$img['like']]);
        $count = (int) $stmt->fetchColumn();
        echo "🔁 Occurrences après: {$img['url']} => $count\n";
    }

    // Vérification globale d'URL résiduelles de ce domaine
    $stmt = $db->query("SELECT COUNT(*) FROM aide_machines_qa WHERE reponse LIKE '%kreyoly.com/uploads/allimg/%'");
    $left = (int) $stmt->fetchColumn();
    echo "\n📊 URLs 'kreyoly.com' restantes dans aide_machines_qa: $left\n";

    echo "\n🎉 Terminé.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "❌ Erreur: " . $e->getMessage() . "\n");
    exit(1);
}

?>


