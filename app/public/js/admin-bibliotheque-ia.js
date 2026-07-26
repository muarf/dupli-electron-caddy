// ============================================================
// admin-bibliotheque-ia.js -- Administration IA & Bibliothèque
// Extrait de admin.bibliotheque_ia.html.php
// ============================================================

/* global confirm, alert */

window.toggleToken = function () {
    const field = document.getElementById('ai_token');
    const eye = document.getElementById('token-eye');
    if (field.type === 'password') {
        field.type = 'text';
        eye.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = 'password';
        eye.classList.replace('fa-eye-slash', 'fa-eye');
    }
};

window.triggerVectorization = function (mode = 'missing') {
    let msg = mode === 'all' 
        ? '⚠️ ATTENTION : Vous allez effacer TOUS les vecteurs existants et tout recommencer à zéro.\n\nCette opération peut durer plusieurs heures.\nÊtes-vous absolument sûr ?'
        : 'Lancer la vectorisation pour compléter uniquement les blocs manquants ?\n\n(L\'opération se fera en arrière-plan).';

    if (!confirm(msg)) return;

    // Désactiver les deux boutons
    const btnAll = document.getElementById('btn-vectorize-all');
    const btnMissing = document.getElementById('btn-vectorize-missing');
    if (btnAll) btnAll.disabled = true;
    if (btnMissing) btnMissing.disabled = true;
    
    document.getElementById('vectorize-status').style.display = 'block';
    document.getElementById('vectorize-msg').innerText = 'Démarrage en cours...';

    const form = new FormData();
    form.append('mode', mode);

    fetch('?trigger_vectorization', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('vectorize-status').style.display = 'block';
                document.getElementById('vectorize-msg').textContent = data.message;
            } else {
                alert('Erreur : ' + (data.error || 'Inconnue'));
                if (btnAll) btnAll.disabled = false;
                if (btnMissing) btnMissing.disabled = false;
            }
        })
        .catch(() => {
            alert('Erreur réseau lors du déclenchement.');
            if (btnAll) btnAll.disabled = false;
            if (btnMissing) btnMissing.disabled = false;
        });
};

window.rescanLibrary = function (mode) {
    if (!confirm('⚠️ Lancer un scan complet de la bibliothèque ?')) return;

    const btn = document.getElementById('btn-rescan');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Démarrage...';

    fetch('?bibliotheque_maintenance', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rescan', params: { mode: mode } })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.job_id) {
            monitorRescan(data.job_id);
        } else {
            alert('Erreur : ' + (data.error || 'Inconnue'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-refresh"></i> Lancer un scan complet';
        }
    })
    .catch(() => {
        alert('Erreur réseau lors du lancement du scan.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Lancer un scan complet';
    });
};

function monitorRescan(jobId) {
    const btn = document.getElementById('btn-rescan');
    const statusDiv = document.getElementById('rescan-status');
    const progressBar = document.getElementById('rescan-progress-bar');
    
    if (statusDiv) statusDiv.style.display = 'block';
    if (progressBar) {
        progressBar.classList.add('active');
        progressBar.classList.remove('progress-bar-success');
    }
    
    const pollInterval = setInterval(async () => {
        try {
            const statusRes = await fetch('?get_indexing_status&job_id=' + jobId);
            const statusData = await statusRes.json();
            
            if (statusData.percent && progressBar) {
                progressBar.style.width = statusData.percent + '%';
                progressBar.textContent = statusData.percent + '%';
            }
            if (statusData.status === 'scanning' && progressBar) {
                progressBar.textContent = 'Scan... (' + (statusData.scanned_count || 0) + ')';
            }

            if (statusData.status === 'completed') {
                clearInterval(pollInterval);
                if (progressBar) {
                    progressBar.classList.remove('active');
                    progressBar.classList.add('progress-bar-success');
                    progressBar.textContent = 'Terminé !';
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-refresh"></i> Lancer un scan complet';
                }
            } else if (statusData.status === 'error' || statusData.status === 'fatal_error') {
                clearInterval(pollInterval);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-refresh"></i> Lancer un scan complet';
                }
                alert('Erreur lors du scan: ' + (statusData.error_msg || 'Inconnue'));
            }
        } catch (e) {
            console.error("Erreur polling:", e);
        }
    }, 1000);
}

// ─── Migration Markdown ───────────────────────────────────────────────────────

let mdPollInterval = null;

function refreshMarkdownCounts() {
    fetch('?get_markdown_migration_status')
        .then(r => r.json())
        .then(data => {
            if (!data.counts) return;
            const c = data.counts;
            document.getElementById('md-count-raw').textContent        = c.raw + (c.null ? '+'+c.null : '');
            document.getElementById('md-count-processing').textContent = c.processing;
            document.getElementById('md-count-done').textContent       = c.done;
            document.getElementById('md-count-error').textContent      = c.error;

            // Reprendre le polling si une migration est en cours
            if (data.running && !mdPollInterval) {
                document.getElementById('markdown-status').style.display = 'block';
                document.getElementById('btn-markdown-stop').style.display = 'inline-block';
                mdPollInterval = setInterval(pollMarkdownStatus, 3000);
            }
        }).catch(() => {});
}

window.stopMarkdownMigration = function () {
    if (!confirm('Voulez-vous vraiment stopper la migration Markdown en cours ?')) return;
    fetch('?stop_markdown_migration', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Migration stoppée.');
                pollMarkdownStatus();
            }
        }).catch(() => alert('Erreur réseau lors de l\'arrêt.'));
};

window.triggerMarkdownMigration = function (mode) {
    const labels = { all: 'tous les PDFs non traités', retry: 'les fichiers en erreur', force: 'TOUS les fichiers (y compris déjà traités)' };
    if (!confirm('⚠️ Lancer la migration Markdown Docling pour ' + (labels[mode] || mode) + ' ?\n\nOpération longue — le RAG reste fonctionnel pendant l\'opération.')) return;

    ['btn-markdown-all', 'btn-markdown-retry', 'btn-markdown-force'].forEach(id => {
        const b = document.getElementById(id);
        if (b) b.disabled = true;
    });
    document.getElementById('btn-markdown-stop').style.display = 'inline-block';
    document.getElementById('btn-markdown-all').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Lancement…';

    const form = new FormData();
    form.append('mode', mode);

    fetch('?trigger_markdown_migration', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('markdown-status').style.display = 'block';
                document.getElementById('markdown-msg').textContent = data.message;
                if (!mdPollInterval) {
                    mdPollInterval = setInterval(pollMarkdownStatus, 3000);
                }
            } else {
                alert('Erreur : ' + (data.error || 'Inconnue'));
                resetMarkdownButtons();
            }
        })
        .catch(() => {
            alert('Erreur réseau lors du déclenchement.');
            resetMarkdownButtons();
        });
};

function pollMarkdownStatus() {
    fetch('?get_markdown_migration_status')
        .then(r => r.json())
        .then(data => {
            const bar = document.getElementById('markdown-progress-bar');
            const msg = document.getElementById('markdown-msg');

            // Mettre à jour les compteurs
            if (data.counts) {
                const c = data.counts;
                document.getElementById('md-count-raw').textContent        = c.raw + (c.null ? '+'+c.null : '');
                document.getElementById('md-count-processing').textContent = c.processing;
                document.getElementById('md-count-done').textContent       = c.done;
                document.getElementById('md-count-error').textContent      = c.error;
            }

            if (data.running) {
                const total     = data.total || 1;
                const processed = data.processed || 0;
                const pct       = total > 0 ? Math.round((processed / total) * 100) : 0;
                if (bar) {
                    bar.style.width = Math.max(10, pct) + '%';
                    bar.textContent = processed + '/' + total + ' fichiers (' + pct + '%)';
                }
                if (msg && data.current) {
                    msg.textContent = '⚙️ ' + (data.current.name || '');
                }
                
                // --- AJOUT LOGS ---
                document.getElementById('markdown-logs-container').style.display = 'block';
                fetch('?get_markdown_migration_logs')
                    .then(r => r.text())
                    .then(logs => {
                        const logsEl = document.getElementById('markdown-logs');
                        if (logsEl) {
                            const isScrolledToBottom = logsEl.scrollHeight - logsEl.clientHeight <= logsEl.scrollTop + 10;
                            logsEl.scrollTop = logsEl.scrollHeight;
                            logsEl.textContent = logs;
                            if (isScrolledToBottom) {
                                logsEl.scrollTop = logsEl.scrollHeight;
                            }
                        }
                    }).catch(() => {});
                // ------------------
                
            } else {
                // Migration terminée
                clearInterval(mdPollInterval);
                mdPollInterval = null;
                if (bar) {
                    bar.classList.remove('active');
                    bar.classList.add('progress-bar-success');
                    bar.style.width = '100%';
                    const p = data.processed || 0;
                    const e = data.errors    || 0;
                    bar.textContent = '✅ Terminé : ' + p + ' fichiers traités' + (e > 0 ? ', ' + e + ' erreur(s)' : '');
                }
                if (msg) msg.textContent = 'Terminé le ' + (data.finished_at || '');
                resetMarkdownButtons();
            }
        }).catch(() => {});
}

function resetMarkdownButtons() {
    const btnAll = document.getElementById('btn-markdown-all');
    if (btnAll) btnAll.innerHTML = '<i class="fa fa-magic"></i> Migrer tous les PDFs (raw)';
    const btnRetry = document.getElementById('btn-markdown-retry');
    if (btnRetry) btnRetry.innerHTML = '<i class="fa fa-refresh"></i> Relancer les erreurs';
    const btnStop = document.getElementById('btn-markdown-stop');
    if (btnStop) btnStop.style.display = 'none';
    ['btn-markdown-all', 'btn-markdown-retry', 'btn-markdown-force'].forEach(id => {
        const b = document.getElementById(id);
        if (b) b.disabled = false;
    });
}

window.installLocalAi = function () {
    const path = document.getElementById('ai_local_path').value.trim();
    if (!confirm('Cette action va ouvrir un terminal et télécharger environ 1.5 Go de données. Voulez-vous continuer ?')) return;

    fetch('?install_local_ai', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'target_dir=' + encodeURIComponent(path)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('L\'installation a été lancée dans un nouveau terminal ! Veuillez patienter jusqu\'à la fermeture de la fenêtre.');
        } else {
            alert('Erreur : ' + (data.error || 'Inconnue'));
        }
    })
    .catch(() => alert('Erreur réseau lors du lancement de l\'installation.'));
};

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('ai-settings-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('btn-save');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sauvegarde...';

            const formData = new FormData(this);

            fetch('?save_ai_settings', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    const alertEl = document.getElementById('ai-save-alert');
                    if (data.success) {
                        alertEl.className = 'alert alert-success';
                        alertEl.innerHTML = '<i class="fa fa-check"></i> ' + data.message;
                    } else {
                        alertEl.className = 'alert alert-danger';
                        alertEl.innerHTML = '<i class="fa fa-times"></i> Erreur : ' + (data.error || 'Inconnue');
                    }
                    alertEl.style.display = 'block';
                    window.scrollTo(0, 0);
                })
                .catch(err => {
                    const alertEl = document.getElementById('ai-save-alert');
                    alertEl.className = 'alert alert-danger';
                    alertEl.innerHTML = '<i class="fa fa-times"></i> Erreur réseau : ' + err;
                    alertEl.style.display = 'block';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-save"></i> Sauvegarder les réglages IA';
                });
        });
    }

    fetch('?get_indexing_status&job_id=latest')
        .then(res => res.json())
        .then(data => {
            if (data && (data.status === 'indexing' || data.status === 'scanning')) {
                const btn = document.getElementById('btn-rescan');
                if (btn) btn.disabled = true;
                monitorRescan(data.job_id);
            }
        }).catch(() => {});

    // Charger les compteurs Markdown au chargement de la page
    refreshMarkdownCounts();
});
