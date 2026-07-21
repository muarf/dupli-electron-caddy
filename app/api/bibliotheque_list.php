<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
/**
 * API pour charger la liste HTML de la bibliothèque (Version Corrigée)
 */
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';
require_once __DIR__ . '/../models/SettingsManager.php';

$db = pdo_connect();
$settingsManager = new SettingsManager($db);
$ai_enabled = (int)$settingsManager->get('ai_enabled', 0);

// Debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

$search = $_GET['query'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Par défaut : Pertinence (rank) si recherche, sinon Date (created_at)
$defaultSort = !empty($search) ? 'rank' : 'created_at';
$defaultOrder = ($defaultSort === 'rank') ? 'ASC' : 'DESC';

$sort_by = $_GET['sort_by'] ?? $defaultSort;
$sort_order = $_GET['sort_order'] ?? $defaultOrder;

$filters = [
    'format' => $_GET['format'] ?? null,
    'color' => $_GET['color'] ?? null,
    'tag' => $_GET['tag'] ?? null,
    'sort_by' => $sort_by,
    'sort_order' => $sort_order,
    'limit' => $limit,
    'offset' => $offset
];

$manager = new BibliothequeManager();
$totalFiles = $manager->countAllFiles($search, '', $filters);
$files = $manager->getAllFiles($search, '', $filters);
$totalPages = ceil($totalFiles / $limit);

if (empty($files)) {
    echo '<div class="alert alert-info mt-4">Aucun document trouvé correspondant à vos critères.</div>';
    exit;
}

// Fonction pour générer les liens de tri
function getSortLink($column, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($currentSort === $column) {
        $icon = $currentOrder === 'ASC' ? ' <i class="fa fa-sort-up"></i>' : ' <i class="fa fa-sort-down"></i>';
    } else {
        $icon = ' <i class="fa fa-sort text-muted" style="opacity:0.3"></i>';
    }
    return "onclick=\"loadLibrary(1, '$column', '$newOrder')\" style=\"cursor:pointer;\" title=\"Trier par $column\"";
}
?>

<div class="table-responsive mt-3">
    <table class="table table-hover bg-white shadow-sm" style="border-radius: 12px; border-collapse: separate; border-spacing: 0;">
        <thead class="bg-light">
            <tr>
                <?php if ($ai_enabled): ?>
                <th style="border-top:none; width: 40px; text-align: center;">
                    <input type="checkbox" id="selectAllPdfs" onclick="toggleAllPdfs(this)" title="Tout sélectionner">
                </th>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                <th <?= getSortLink('rank', $sort_by, $sort_order) ?> style="border-top:none; text-align:center; width: 100px;">Pertinence <?= ($sort_by === 'rank' ? ($sort_order === 'ASC' ? '<i class="fa fa-sort-up"></i>' : '<i class="fa fa-sort-down"></i>') : '<i class="fa fa-sort text-muted" style="opacity:0.3"></i>') ?></th>
                <?php endif; ?>
                <th <?= getSortLink('filename', $sort_by, $sort_order) ?> style="border-top:none; width: 400px;">Fichier <?= ($sort_by === 'filename' ? ($sort_order === 'ASC' ? '<i class="fa fa-sort-up"></i>' : '<i class="fa fa-sort-down"></i>') : '<i class="fa fa-sort text-muted" style="opacity:0.3"></i>') ?></th>
                <th <?= getSortLink('page_count', $sort_by, $sort_order) ?> style="border-top:none; text-align:center;">Format / Pages <?= ($sort_by === 'page_count' ? ($sort_order === 'ASC' ? '<i class="fa fa-sort-up"></i>' : '<i class="fa fa-sort-down"></i>') : '<i class="fa fa-sort text-muted" style="opacity:0.3"></i>') ?></th>
                <th style="border-top:none;">Couleur</th>
                <th style="border-top:none;">Imposition</th>
                <th style="border-top:none;">Tags</th>
                <th <?= getSortLink('created_at', $sort_by, $sort_order) ?> style="border-top:none; text-align:right;">Date <?= ($sort_by === 'created_at' ? ($sort_order === 'ASC' ? '<i class="fa fa-sort-up"></i>' : '<i class="fa fa-sort-down"></i>') : '<i class="fa fa-sort text-muted" style="opacity:0.3"></i>') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): 
                $metadata = json_decode($file['metadata_json'] ?? '{}', true);
                $format = $metadata['format'] ?? 'A4';
                $isColor = $metadata['is_color'] ?? false;
                $imposition = $metadata['imposition'] ?? 'PPP';
                $pages = $file['page_count'] ?? 1;
                $size = $file['file_size'] ?? 0;
                
                // Chemin de la miniature
                $thumb = !empty($file['thumbnail_path']) ? '?get_bibliotheque_thumbnail&file=' . urlencode($file['thumbnail_path']) : null;
            ?>
            <tr style="cursor: pointer;" onclick="if(event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && event.target.tagName !== 'I') editFile(<?= $file['id'] ?>)" title="Cliquer pour éditer les métadonnées">
                <?php if ($ai_enabled): ?>
                <td class="align-middle text-center" onclick="event.stopPropagation();">
                    <input type="checkbox" class="pdf-select-cb" value="<?= $file['id'] ?>" onclick="updatePdfSelection()">
                </td>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                <td class="align-middle text-center">
                    <div class="badge badge-primary px-2" style="background: #6366f1; opacity: <?= max(0.3, 1 - ($file['rank'] ?? 0)/10) ?>"><?= round(10 - ($file['rank'] ?? 0), 1) ?></div>
                </td>
                <?php endif; ?>
                <td class="align-middle">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 overflow-hidden border rounded bg-light d-flex align-items-center justify-content-center" 
                             style="width: 50px; height: 65px; min-width: 50px; cursor: pointer;" 
                             onclick="event.stopPropagation(); openPdfViewer(<?= $file['id'] ?>, '<?= addslashes($file['filename']) ?>')"
                             title="Cliquez pour visualiser">
                            <?php if (!empty($file['thumbnail_path'])): ?>
                                <img src="?get_bibliotheque_thumbnail&file=<?= urlencode($file['thumbnail_path']) ?>" class="img-fluid" style="max-height: 100%;">
                            <?php else: ?>
                                <i class="fa fa-file-pdf-o text-muted fa-2x"></i>
                            <?php endif; ?>
                        </div>
                        <div style="max-width: 320px;">
                            <div class="font-weight-bold text-dark text-truncate" title="<?= htmlspecialchars($file['filename']) ?>"><?= htmlspecialchars($file['filename']) ?></div>
                            <small class="text-muted"><?= round($size / 1024 / 1024, 2) ?> MB • <?= strtoupper($file['file_type'] ?? 'PDF') ?></small>
                            
                            <?php if (!empty($file['match_contexts'])): ?>
                                <div class="file-match-contexts">
                                    <?php foreach ($file['match_contexts'] as $ctx): ?>
                                        <div class="context-item font-italic">...<?= $ctx ?>...</div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="align-middle text-center">
                    <div class="badge badge-light border px-2 py-1 font-weight-bold"><?= htmlspecialchars($format) ?></div>
                    <div class="small text-muted mt-1"><?= $pages ?> pages</div>
                </td>
                <td class="align-middle">
                    <?php if ($isColor): ?>
                        <span class="badge badge-warning text-white px-2 py-1" style="font-size: 0.7rem; background:#f59e0b;">COULEUR</span>
                    <?php else: ?>
                        <span class="badge badge-secondary px-2 py-1" style="font-size: 0.7rem; background:#64748b;">N&B</span>
                    <?php endif; ?>
                </td>
                <td class="align-middle">
                    <span class="badge badge-info text-white px-2 py-1" style="font-size: 0.7rem; background:#0ea5e9;"><?= htmlspecialchars($imposition) ?></span>
                </td>
                <td class="align-middle">
                    <?php 
                    $tags = explode(',', $file['tags'] ?? '');
                    $count = 0;
                    foreach ($tags as $tag): 
                        if (empty(trim($tag)) || $count >= 3) continue;
                        $count++;
                        $cleanTag = trim($tag);
                    ?>
                        <span class="badge badge-light border text-muted mr-1" 
                              style="font-size: 0.7rem; font-weight:normal; cursor:pointer;" 
                              onclick="event.stopPropagation(); if(typeof filterByTag === 'function') filterByTag('<?= addslashes($cleanTag) ?>')"
                              title="Filtrer par ce tag">
                            <?= htmlspecialchars($cleanTag) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($tags) > 3): ?><span class="text-muted small">...</span><?php endif; ?>
                </td>
                <td style="vertical-align: middle; text-align: right;">
                    <div class="d-flex justify-content-end align-items-center" onclick="event.stopPropagation();">
                        <!-- 1. OUVRIR -->
                        <button class="btn btn-sm btn-outline-primary mr-1" onclick="window.open('?get_bibliotheque_file&id=<?= $file['id'] ?>', '_blank')" title="Ouvrir">
                            <i class="fa fa-external-link"></i>
                        </button>

                        <!-- 2. IMPRIMER -->
                        <button class="btn btn-sm btn-outline-info mr-1" onclick="printLibraryFile(<?= $file['id'] ?>)" title="Imprimer">
                            <i class="fa fa-print"></i>
                        </button>

                        <!-- 3. ÉDITER DANS LE STUDIO -->
                        <button class="btn btn-sm btn-outline-warning mr-1" onclick="window.location.href='?studio&file_id=<?= $file['id'] ?>'" title="Éditer dans le Studio">
                            <i class="fa fa-magic"></i>
                        </button>

                        <!-- 4. SUPPRIMER -->
                        <button class="btn btn-sm btn-outline-danger" onclick="openDeleteModal(<?= $file['id'] ?>, <?= htmlspecialchars(json_encode($file['filename'])) ?>)" title="Supprimer">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                    <div class="text-muted mt-1 small" style="font-size: 0.65rem;">
                        Ajouté le <?= date('d/m/y', strtotime($file['created_at'])) ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3 bg-white p-3 shadow-sm rounded border">
    <div class="text-muted small">
        Affichage de <?= $offset + 1 ?> à <?= min($offset + $limit, $totalFiles) ?> sur <?= $totalFiles ?> documents
    </div>
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-primary" onclick="loadLibrary(<?= $page - 1 ?>)" <?= ($page <= 1) ? 'disabled' : '' ?>>
            <i class="fa fa-chevron-left"></i> Précédent
        </button>
        <div class="btn btn-sm btn-white border-top border-bottom px-3 font-weight-bold">
            Page <?= $page ?> / <?= $totalPages ?>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="loadLibrary(<?= $page + 1 ?>)" <?= ($page >= $totalPages) ? 'disabled' : '' ?>>
            Suivant <i class="fa fa-chevron-right"></i>
        </button>
    </div>
</div>
<?php endif; ?>
