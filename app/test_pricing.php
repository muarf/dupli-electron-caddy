<?php
require_once __DIR__ . '/controler/functions/pricing.php';

echo "<h1>Test de la fonction get_price()</h1>";

$prix_data = get_price();

echo "<h2>Structure complète des prix :</h2>";
echo "<pre>";
print_r($prix_data);
echo "</pre>";

echo "<h2>Test spécifique pour dupli_18 (ricoh dx4545) :</h2>";
if (isset($prix_data['dupli_18'])) {
    echo "<pre>";
    print_r($prix_data['dupli_18']);
    echo "</pre>";
    
    echo "<h3>Prix master :</h3>";
    if (isset($prix_data['dupli_18']['master'])) {
        echo "Prix master: " . $prix_data['dupli_18']['master']['unite'] . "€<br>";
    } else {
        echo "❌ Prix master non trouvé<br>";
    }
    
    echo "<h3>Prix tambour noir :</h3>";
    if (isset($prix_data['dupli_18']['tambour_noir'])) {
        echo "Prix tambour noir: " . $prix_data['dupli_18']['tambour_noir']['unite'] . "€<br>";
    } else {
        echo "❌ Prix tambour noir non trouvé<br>";
    }
    
    echo "<h3>Prix tambour rouge :</h3>";
    if (isset($prix_data['dupli_18']['tambour_rouge'])) {
        echo "Prix tambour rouge: " . $prix_data['dupli_18']['tambour_rouge']['unite'] . "€<br>";
    } else {
        echo "❌ Prix tambour rouge non trouvé<br>";
    }
} else {
    echo "❌ Clé 'dupli_18' non trouvée dans les prix<br>";
}

echo "<h2>Test pour dupli_1 :</h2>";
if (isset($prix_data['dupli_1'])) {
    echo "<pre>";
    print_r($prix_data['dupli_1']);
    echo "</pre>";
} else {
    echo "❌ Clé 'dupli_1' non trouvée dans les prix<br>";
}

echo "<h2>Prix du papier :</h2>";
if (isset($prix_data['papier'])) {
    echo "<pre>";
    print_r($prix_data['papier']);
    echo "</pre>";
} else {
    echo "❌ Prix papier non trouvé<br>";
}
?>




