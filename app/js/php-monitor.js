(function () {
    const isElectron = typeof window !== 'undefined' && window.electronAPI;
    if (!isElectron) {
        return;
    }

    const styleContent = `
        .php-monitor-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 9999;
        }
        .php-monitor-overlay.visible {
            display: flex;
        }
        .php-monitor-modal {
            background: #ffffff;
            color: #1a202c;
            width: 100%;
            max-width: 560px;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .php-monitor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: linear-gradient(135deg, #be123c, #f97316);
            color: #ffffff;
        }
        .php-monitor-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .php-monitor-close {
            background: transparent;
            border: none;
            color: inherit;
            font-size: 20px;
            cursor: pointer;
            padding: 4px 8px;
            line-height: 1;
        }
        .php-monitor-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .php-monitor-message {
            margin: 0;
            font-size: 16px;
            font-weight: 500;
        }
        .php-monitor-detail {
            margin: 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
            max-height: 160px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .php-monitor-ports {
            display: none;
            font-size: 13px;
            color: #334155;
            background: #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .php-monitor-status {
            font-size: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #cbd5f5;
            color: #1e3a8a;
        }
        .php-monitor-status--error {
            background: #fee2e2;
            border-color: #fecaca;
            color: #b91c1c;
        }
        .php-monitor-status--success {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }
        .php-monitor-status--warning {
            background: #fef3c7;
            border-color: #fde68a;
            color: #92400e;
        }
        .php-monitor-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 20px 20px;
            background: #f8fafc;
            justify-content: flex-end;
        }
        .php-monitor-actions .btn {
            min-width: 160px;
        }
        .php-monitor-actions .btn[disabled] {
            opacity: 0.65;
            cursor: not-allowed;
        }
        @media (max-width: 640px) {
            .php-monitor-modal {
                max-width: 100%;
            }
            .php-monitor-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    `;

    function init() {
        const style = document.createElement('style');
        style.type = 'text/css';
        style.textContent = styleContent;
        document.head.appendChild(style);

        const overlay = document.createElement('div');
        overlay.className = 'php-monitor-overlay';
        overlay.innerHTML = `
            <div class="php-monitor-modal" role="dialog" aria-modal="true">
                <div class="php-monitor-header">
                    <h2>Problème critique du moteur PHP</h2>
                    <button type="button" class="php-monitor-close" aria-label="Fermer">×</button>
                </div>
                <div class="php-monitor-body">
                    <p class="php-monitor-message">
                        Le serveur PHP embarqué ne répond plus. Les actions peuvent être temporairement indisponibles.
                    </p>
                    <pre class="php-monitor-detail"></pre>
                    <div class="php-monitor-ports"></div>
                    <div class="php-monitor-status">Analyse en cours…</div>
                </div>
                <div class="php-monitor-actions">
                    <button type="button" class="btn btn-default php-monitor-close">Ignorer</button>
                    <button type="button" class="btn btn-primary php-monitor-home">Retour à l’accueil</button>
                    <button type="button" class="btn btn-warning php-monitor-restart">Relancer PHP</button>
                    <button type="button" class="btn btn-danger php-monitor-app">Redémarrer l’application</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const detailEl = overlay.querySelector('.php-monitor-detail');
        const statusEl = overlay.querySelector('.php-monitor-status');
        const messageEl = overlay.querySelector('.php-monitor-message');
        const portsEl = overlay.querySelector('.php-monitor-ports');
        const closeButtons = overlay.querySelectorAll('.php-monitor-close');
        const homeButton = overlay.querySelector('.php-monitor-home');
        const restartButton = overlay.querySelector('.php-monitor-restart');
        const appButton = overlay.querySelector('.php-monitor-app');

        let lastFatal = null;

        function setStatus(text, tone = 'info') {
            statusEl.textContent = text;
            statusEl.className = 'php-monitor-status';
            if (tone === 'error') {
                statusEl.classList.add('php-monitor-status--error');
            } else if (tone === 'success') {
                statusEl.classList.add('php-monitor-status--success');
            } else if (tone === 'warning') {
                statusEl.classList.add('php-monitor-status--warning');
            }
        }

        function updatePortInfo(payload) {
            if (!portsEl) {
                return;
            }
            if (!payload) {
                portsEl.style.display = 'none';
                portsEl.textContent = '';
                return;
            }
            const parts = [];
            if (typeof payload.port !== 'undefined' && payload.port !== null) {
                parts.push(`Port frontal: ${payload.port}`);
            }
            if (typeof payload.proxyPort !== 'undefined' && payload.proxyPort !== null && payload.proxyPort !== payload.port) {
                parts.push(`Proxy: ${payload.proxyPort}`);
            }
            if (typeof payload.phpPort !== 'undefined' && payload.phpPort !== null && payload.phpPort !== payload.port) {
                parts.push(`PHP direct: ${payload.phpPort}`);
            }
            if (parts.length) {
                portsEl.textContent = parts.join(' · ');
                portsEl.style.display = 'block';
            } else {
                portsEl.style.display = 'none';
                portsEl.textContent = '';
            }
        }

        function showOverlay() {
            overlay.classList.add('visible');
        }

        function hideOverlay() {
            overlay.classList.remove('visible');
            lastFatal = null;
        }

        window.electronAPI.onPhpFatal((payload) => {
            lastFatal = payload;
            const detailLines = [
                payload.timestamp ? `[${payload.timestamp}]` : '',
                payload.source ? `(${payload.source})` : '',
                payload.message || ''
            ].filter(Boolean);
            detailEl.textContent = detailLines.join(' ');
            messageEl.textContent = 'Le moteur PHP a rencontré une erreur critique et a été interrompu.';
            setStatus('Serveur PHP arrêté', 'error');
            updatePortInfo(payload);
            restartButton.disabled = false;
            appButton.disabled = false;
            showOverlay();
        });

        window.electronAPI.onPhpStatus((payload) => {
            if (!payload || !payload.status) {
                return;
            }

            const status = payload.status;
            updatePortInfo(payload);

            if (status === 'running') {
                setStatus('Serveur PHP opérationnel', 'success');
                restartButton.disabled = false;
                appButton.disabled = false;
                if (lastFatal) {
                    hideOverlay();
                }
                return;
            }

            if (status === 'proxy-ready') {
                const portInfo = payload.port ? ` (port ${payload.port})` : '';
                setStatus(`Proxy HTTP opérationnel${portInfo}`, 'success');
                restartButton.disabled = false;
                appButton.disabled = false;
                if (lastFatal) {
                    hideOverlay();
                }
                return;
            }

            if (status === 'starting') {
                setStatus('Démarrage du serveur PHP…', 'warning');
                showOverlay();
                return;
            }

            if (status === 'restarting') {
                const attemptInfo = payload.attempt
                    ? ` (tentative ${payload.attempt}${payload.total ? `/${payload.total}` : ''})`
                    : '';
                setStatus(`Redémarrage du serveur PHP${attemptInfo}…`, 'warning');
                showOverlay();
                return;
            }

            if (status === 'proxy-timeout') {
                const portInfo = payload.phpPort ? ` (port ${payload.phpPort})` : '';
                setStatus(`Proxy indisponible – bascule sur le serveur direct${portInfo}`, 'warning');
                showOverlay();
                return;
            }

            if (status === 'stopped') {
                const reason = payload.code !== undefined ? ` (code ${payload.code})` : '';
                setStatus(`Serveur PHP arrêté${reason}`, 'error');
                showOverlay();
                return;
            }

            if (status === 'failed') {
                setStatus('Échec du redémarrage : nombre de tentatives maximal atteint.', 'error');
                restartButton.disabled = true;
                showOverlay();
                return;
            }

            if (status === 'error') {
                const reason = payload.message ? ` : ${payload.message}` : '';
                setStatus(`Erreur lors de la relance${reason}`, 'error');
                restartButton.disabled = false;
                showOverlay();
            }
        });

        closeButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                hideOverlay();
            });
        });

        homeButton.addEventListener('click', () => {
            const target = `${window.location.origin}/?accueil`;
            window.location.href = target;
        });

        restartButton.addEventListener('click', async () => {
            restartButton.disabled = true;
            setStatus('Relance du serveur PHP en cours…', 'warning');
            try {
                await window.electronAPI.restartPhp();
            } catch (error) {
                setStatus(`Erreur lors de la relance : ${error.message || error}`, 'error');
                restartButton.disabled = false;
            }
        });

        appButton.addEventListener('click', async () => {
            appButton.disabled = true;
            setStatus('Redémarrage complet de l’application…', 'warning');
            try {
                await window.electronAPI.restartApp();
            } catch (error) {
                setStatus(`Échec du redémarrage de l’application : ${error.message || error}`, 'error');
                appButton.disabled = false;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();

