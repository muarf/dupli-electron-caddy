<div class="navbar navbar-default navbar-fixed-bottom">
    <div class="container" style="display: flex; align-items: center; justify-content: space-between; height: 100%;">
        <button type="button" class="btn btn-default btn-sm" onclick="history.back()" style="display: flex; align-items: center; margin-top: 6px;">
            <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span> <?php _e('footer.previous'); ?>
        </button>
        <p class="navbar-text text-center" style="margin: 0; flex: 1;"><?php _e('footer.coded_with_love'); ?> <a href="https://github.com/muarf/dupli-electron-caddy/"> <?php _e('footer.github'); ?> </a></p>
        <?php if (isset($_SESSION['admin']) && $_SESSION['admin']): ?>
        <button type="button" class="btn btn-info btn-sm" id="toggle-edit-btn" style="display: flex; align-items: center; margin-top: 6px;">
            <span class="glyphicon glyphicon-edit" aria-hidden="true"></span> <span id="toggle-edit-text"><?php _e('footer.toggle_edit'); ?></span>
        </button>
        <?php else: ?>
        <div style="width: 80px;"></div> <!-- Espace pour équilibrer -->
        <?php endif; ?>
    </div>
</div>

<?php
// Inclure le modal de sélection de session (pour détection impression)
$modal_path = __DIR__ . '/components/session-modal.html.php';
if (file_exists($modal_path)) {
    include $modal_path;
}
?>

<!-- Print Session Manager - Toast Notifications CSS -->
<link href="css/toast-notifications.css" rel="stylesheet" type="text/css">

<!-- Print Session Manager - Global JS -->
<script>
// Feature detection pour mode Electron vs Standalone
window.isElectronMode = typeof window.electronAPI !== 'undefined';

if (!window.isElectronMode) {
    console.log('[App] Mode standalone PHP - Pas de détection auto d\'impressions');
}
</script>
<script src="js/print-session-manager.js"></script>