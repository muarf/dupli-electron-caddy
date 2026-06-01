<?php
$db = new PDO('sqlite:app/duplinew.sqlite');

function testFilter($tagFilter) {
    global $db;
    $sql = "SELECT filename, tags FROM bibliotheque_files WHERE 1=1";
    $params = [];
    
    $tags = explode(',', $tagFilter);
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (empty($tag)) continue;
        
        $isExclusion = false;
        if (strpos($tag, '-') === 0) {
            $isExclusion = true;
            $tag = ltrim($tag, '-');
        }
        
        $cleanTag = strtolower(str_replace(' ', '', $tag));
        $tagPattern = "%," . $cleanTag . ",%";
        
        if ($isExclusion) {
            $sql .= " AND ((tags IS NULL OR tags = '') OR ',' || LOWER(REPLACE(tags, ' ', '')) || ',' NOT LIKE ?)";
        } else {
            $sql .= " AND (',' || LOWER(REPLACE(tags, ' ', '')) || ',' LIKE ?)";
        }
        $params[] = $tagPattern;
    }
    
    $sql .= " LIMIT 5";
    echo "SQL: $sql\n";
    echo "Params: " . implode(', ', $params) . "\n";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $r) {
        echo "- " . $r['filename'] . " (Tags: " . $r['tags'] . ")\n";
    }
    echo "Total: " . count($results) . "\n\n";
}

echo "Test Inclusion 'week':\n";
testFilter("week");

echo "Test Exclusion '-week':\n";
testFilter("-week");

echo "Test Exclusion '-inexistant':\n";
testFilter("-inexistant");
