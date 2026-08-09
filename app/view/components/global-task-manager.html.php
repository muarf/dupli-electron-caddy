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
#global-task-manager.minimized .task-manager-logs {
    display: none;
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
    <div class="task-logs-container" id="task-manager-logs" style="display: none;">
        <!-- Les logs seront injectés ici -->
    </div>
</div>

<script src="js/components/task-manager.js" defer></script>

