<style>
/* Modale globale flottante pour le suivi des tâches */
#global-task-manager {
    position: fixed;
    bottom: 60px; /* Au-dessus du footer */
    right: 20px;
    width: 350px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    z-index: 1050;
    display: none; /* Masqué par défaut */
    flex-direction: column;
    overflow: hidden;
    transition: all 0.3s ease;
}

#global-task-manager.minimized {
    height: 40px;
}

#global-task-manager.minimized .task-manager-body,
#global-task-manager.minimized .task-manager-logs,
#global-task-manager.minimized #task-manager-logs-wrapper {
    display: none !important;
}

.task-manager-header {
    background: #f8f9fa;
    padding: 10px 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: bold;
    color: #333;
}

.task-manager-header i {
    color: #555;
}

.task-manager-body {
    padding: 15px;
    font-size: 13px;
    flex-grow: 1;
}

.task-item {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.task-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.task-title {
    font-weight: bold;
    margin-bottom: 5px;
    word-break: break-all;
}

.task-status {
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
}

.task-logs-container {
    background: #1e1e1e;
    color: #00ff00;
    font-family: monospace;
    font-size: 11px;
    padding: 10px;
    height: 150px;
    overflow-y: auto;
    border-top: 1px solid #333;
    white-space: pre-wrap;
}

.task-download-btn {
    margin-top: 10px;
}
</style>

<div id="global-task-manager">
    <div class="task-manager-header" onclick="toggleTaskManager()">
        <span><i class="fa fa-tasks"></i><?php _e("global_task_manager.title", [], false); ?><span id="task-manager-count">0</span>)</span>
        <i id="task-manager-toggle-icon" class="fa fa-chevron-down"></i>
    </div>
    <div class="task-manager-body" id="task-manager-body">
        <!-- Les tâches seront injectées ici par JS -->
    </div>
    <div id="task-manager-logs-wrapper" class="task-manager-logs" style="display: none; border-top: 1px solid #333;">
        <div style="background: #252526; padding: 4px 10px; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #aaa;">
            <span><i class="fa fa-terminal"></i> <?php _e("global_task_manager.logs", [], false); ?></span>
            <button type="button" id="task-manager-copy-btn" class="btn btn-default btn-xs" style="padding: 1px 7px; font-size: 10px; background: #333; color: #eee; border: 1px solid #555; border-radius: 3px;" onclick="copyTaskLogs(event)" data-copied-label="<?php _e('global_task_manager.copied', [], false); ?>">
                <i class="fa fa-copy"></i> <span id="task-manager-copy-text"><?php _e("global_task_manager.copy_logs", [], false); ?></span>
            </button>
        </div>
        <div class="task-logs-container" id="task-manager-logs" style="height: 140px; overflow-y: auto; padding: 10px; background: #1e1e1e; color: #00ff00; font-family: monospace; font-size: 11px; white-space: pre-wrap; border-top: none;">
            <!-- Les logs seront injectés ici -->
        </div>
    </div>
</div>

<script src="js/components/task-manager.js" defer></script>

