/**
 * tauri-bridge.js
 *
 * Ce script est injecté par Tauri (initialization_script) AVANT le chargement
 * de toute page web. Il reconstruit l'objet window.electronAPI avec les appels
 * Tauri v2 équivalents.
 *
 * PRINCIPE DE CONCEPTION :
 *  - Zéro modification des fichiers JS existants (print-session-manager.js, etc.)
 *  - Les méthodes de commande (invoke) sont transparentes.
 *  - Les méthodes d'événement (listen) utilisent un registre interne pour
 *    absorber le décalage synchrone/asynchrone entre Electron et Tauri v2.
 *
 * COMPATIBILITÉ :
 *  - Ce script ne fait rien si window.__TAURI__ n'est pas défini (env Electron).
 */
(function () {
  'use strict';

  // Guard : n'exécuter que sous Tauri
  if (typeof window.__TAURI__ === 'undefined') return;

  const { invoke } = window.__TAURI__.core;
  const { listen } = window.__TAURI__.event;

  // ===========================================================================
  // Registre d'événements
  //
  // Gère la différence de paradigme :
  //   Electron :  on('event', cb) → synchrone, cb enregistré immédiatement
  //   Tauri v2 :  listen('event', cb) → asynchrone, retourne Promise<UnlistenFn>
  //
  // Le registre permet à l'abonnement d'être synchrone pour le frontend tout
  // en gérant le cycle de vie asynchrone Tauri en tâche de fond.
  //
  // Chaque entrée est : { callbacks: [], unlisten: Function|null }
  // ===========================================================================
  const _eventRegistry = {};

  /**
   * Enregistre un callback sur un événement Tauri.
   * Retourne une fonction de désabonnement synchrone.
   *
   * @param {string} eventName  - Nom de l'événement Tauri (ex: 'php-log')
   * @param {Function} callback - Callback à appeler à chaque réception
   * @returns {Function}        - unlisten() synchrone
   */
  function _registerEvent(eventName, callback) {
    if (!_eventRegistry[eventName]) {
      _eventRegistry[eventName] = { callbacks: [], unlisten: null };

      // Abonnement asynchrone Tauri — fire-and-forget pour le frontend
      listen(eventName, (tauriEvent) => {
        const entry = _eventRegistry[eventName];
        if (entry) {
          entry.callbacks.forEach((cb) => {
            try { cb(tauriEvent.payload); }
            catch (e) { console.error(`[tauri-bridge] Erreur dans le callback '${eventName}':`, e); }
          });
        }
      }).then((unlistenFn) => {
        if (_eventRegistry[eventName]) {
          _eventRegistry[eventName].unlisten = unlistenFn;
        } else {
          // Le canal a été supprimé avant que la promesse se résolve
          unlistenFn();
        }
      }).catch((e) => {
        console.error(`[tauri-bridge] Impossible de s'abonner à '${eventName}':`, e);
      });
    }

    _eventRegistry[eventName].callbacks.push(callback);

    // Retourne une fonction de désabonnement synchrone pour le frontend
    return function unlisten() {
      const entry = _eventRegistry[eventName];
      if (!entry) return;

      entry.callbacks = entry.callbacks.filter((cb) => cb !== callback);

      // Si plus aucun abonné, nettoyer le canal Tauri pour libérer les ressources
      if (entry.callbacks.length === 0) {
        if (entry.unlisten) {
          entry.unlisten();
        }
        delete _eventRegistry[eventName];
      }
    };
  }

  // ===========================================================================
  // Construction de window.electronAPI — Carte exhaustive
  //
  // 25 méthodes identifiées dans les fichiers du frontend :
  //   checkAdminStatus, checkForUpdates, cleanupTmpFiles, deletePrintJob,
  //   deletePrinter, downloadUpdate, getPrinterCapabilities,
  //   getPrinterMonitorStatus, getPrinters, installUpdate,
  //   onConsoleLog, onDownloadProgress, onPrintJobDetected,
  //   onUpdateAvailable, onUpdateDownloaded, onUpdateError,
  //   onUpdateNotAvailable, openExternalFile, openFile, printFile,
  //   printJob, reanalyzePrintJob, restartAsAdmin, showOpenDialog,
  //   togglePrinterMonitor
  //
  // + méthodes internes préservées depuis preload.js Electron :
  //   getDatabasePath, getAppVersion, restartPhp, restartApp,
  //   onPhpLog, onPhpFatal, onPhpStatus, onPrintMonitorError,
  //   onPrintMonitorStarted
  // ===========================================================================
  window.electronAPI = {

    // ─────────────────────────────────────────────────────────────────
    // Fichiers & Boîtes de dialogue
    // ─────────────────────────────────────────────────────────────────

    /** Ouvre un fichier avec l'application système par défaut */
    openFile: (filePath) =>
      invoke('open_file', { filePath }),

    /** Supprime les fichiers temporaires de l'application */
    cleanupTmpFiles: () =>
      invoke('cleanup_tmp_files'),

    /** Affiche une boîte de dialogue de sélection de fichier */
    showOpenDialog: (options) =>
      invoke('show_open_dialog', { options }),

    /** Ouvre un fichier avec l'application système (URL encodée) */
    openExternalFile: (fileUrl) =>
      invoke('open_external_file', { fileUrl }),

    // ─────────────────────────────────────────────────────────────────
    // Informations de l'application
    // ─────────────────────────────────────────────────────────────────

    /** Retourne le chemin de la base de données SQLite */
    getDatabasePath: () =>
      invoke('get_database_path'),

    /** Retourne la version de l'application */
    getAppVersion: () =>
      invoke('get_app_version'),

    // ─────────────────────────────────────────────────────────────────
    // Mises à jour (Auto-Updater)
    // ─────────────────────────────────────────────────────────────────

    /** Vérifie si une mise à jour est disponible */
    checkForUpdates: () =>
      invoke('check_for_updates'),

    /** Télécharge la mise à jour disponible */
    downloadUpdate: () =>
      invoke('download_update'),

    /** Installe la mise à jour téléchargée (relance l'app) */
    installUpdate: () =>
      invoke('install_update'),

    // Événements Updater
    onUpdateAvailable:    (cb) => _registerEvent('update-available',    cb),
    onUpdateNotAvailable: (cb) => _registerEvent('update-not-available', cb),
    onDownloadProgress:   (cb) => _registerEvent('download-progress',   cb),
    onUpdateDownloaded:   (cb) => _registerEvent('update-downloaded',   cb),
    onUpdateError:        (cb) => _registerEvent('update-error',        cb),

    // ─────────────────────────────────────────────────────────────────
    // Gestion des serveurs (PHP, Caddy)
    // ─────────────────────────────────────────────────────────────────

    /** Redémarre PHP-FPM sans toucher à Caddy */
    restartPhp: () =>
      invoke('restart_php'),

    /** Redémarre l'application complète */
    restartApp: () =>
      invoke('restart_app'),

    // Événements PHP (logs en temps réel)
    onPhpLog:    (cb) => _registerEvent('log-message', (payload) => {
      // Filtre les messages du source 'php' uniquement
      if (payload && payload.source === 'php') cb(payload);
    }),
    onPhpFatal:  (cb) => _registerEvent('php-fatal',  cb),
    onPhpStatus: (cb) => _registerEvent('php-process-status', cb),

    // Log générique de la console de debug
    onConsoleLog: (cb) => _registerEvent('console-log', cb),

    // ─────────────────────────────────────────────────────────────────
    // Imprimantes & Spouleur d'impression
    // ─────────────────────────────────────────────────────────────────

    /** Retourne la liste des imprimantes disponibles */
    getPrinters: () =>
      invoke('get_printers'),

    /** Active ou désactive la supervision du spouleur */
    togglePrinterMonitor: (start) =>
      invoke('toggle_printer_monitor', { start }),

    /** Retourne le statut actuel de la supervision */
    getPrinterMonitorStatus: () =>
      invoke('get_printer_monitor_status'),

    /** Supprime une imprimante de la liste */
    deletePrinter: (printerName) =>
      invoke('delete_printer', { printerName }),

    /** Supprime un job d'impression en attente */
    deletePrintJob: (printerName, jobId) =>
      invoke('delete_print_job', { printerName: printerName || '', jobId: Number(jobId) }),

    /** Réanalyse un job d'impression existant */
    reanalyzePrintJob: async (jobId, documentName, format, splPath, driverColor) => {
      const numericJobId = Number(jobId);

      // 1. Récupérer les infos du spouleur Windows via Rust (total_pages, is_grayscale, is_duplex, paper_size)
      const res = await invoke('reanalyze_print_job', {
        jobId: numericJobId,
        documentName: documentName || '',
        format: format || '',
        splPath: splPath || '',
        driverColor: !!driverColor
      });

      // 2. Déclencher la conversion SPL → PNG (miniatures) via les API PHP locales
      //    On essaie EMF d'abord (plus fidèle), puis PCL en fallback.
      let thumbnailUrl = null;
      let totalPages = res.totalPages || 0;
      let pages = [];

      try {
        const endpoints = [
          `/?convert_emf_to_png&job_id=${numericJobId}`,
          `/?convert_pcl_to_png&job_id=${numericJobId}`,
        ];

        for (const endpoint of endpoints) {
          try {
            const resp = await fetch(endpoint);
            if (resp.ok) {
              const data = await resp.json();
              if (data && data.success) {
                // PHP retourne base_url avec le port hardcodé 8001 — on construit l'URL relative à la place
                thumbnailUrl = `/thumbnails/${numericJobId}/page_0.png?t=${Date.now()}`;
                if (data.page_count) totalPages = data.page_count;
                pages = data.pages || [];
                break; // succes, pas besoin du fallback
              }
            }
          } catch (_) { /* tentative suivante */ }
        }
      } catch (e) {
        console.warn('[tauri-bridge] Erreur conversion PHP:', e);
      }

      // Fonction pour analyser une image via Canvas et récupérer son taux de remplissage / couleur
      const analyzeImagePage = (url) => {
        return new Promise((resolve) => {
          const img = new Image();
          img.crossOrigin = "anonymous";
          img.onload = () => {
            try {
              const canvas = document.createElement('canvas');
              const ctx = canvas.getContext('2d');
              canvas.width = 200;
              canvas.height = 200;
              ctx.drawImage(img, 0, 0, 200, 200);
              
              const imgData = ctx.getImageData(0, 0, 200, 200);
              const data = imgData.data;
              const totalPixels = 200 * 200;
              let totalDensity = 0;
              let coloredPixels = 0;

              for (let i = 0; i < data.length; i += 4) {
                const r = data[i];
                const g = data[i+1];
                const b = data[i+2];
                
                const rP = r / 255;
                const gP = g / 255;
                const bP = b / 255;
                
                // Simulation CMYK à partir de RGB pour correspondre à Ghostscript
                const k = 1 - Math.max(rP, gP, bP);
                const c = k === 1 ? 0 : (1 - rP - k) / (1 - k);
                const m = k === 1 ? 0 : (1 - gP - k) / (1 - k);
                const y = k === 1 ? 0 : (1 - bP - k) / (1 - k);
                
                totalDensity += (c + m + y + k);
                
                // Seuil de couleur pour détection isColor
                if (Math.abs(r - g) > 25 || Math.abs(g - b) > 25 || Math.abs(r - b) > 25) {
                  coloredPixels++;
                }
              }

              const fillRate = (totalDensity / totalPixels) * 100;
              const isColor = (coloredPixels / totalPixels) > 0.005;
              resolve({ fillRate, isColor });
            } catch (e) {
              console.warn('[tauri-bridge] Erreur analyse canvas:', e);
              resolve({ fillRate: 0, isColor: false });
            }
          };
          img.onerror = () => {
            resolve({ fillRate: 0, isColor: false });
          };
          img.src = url;
        });
      };

      // Analyser toutes les pages pour calculer la moyenne de remplissage et détection couleur
      let totalFillRate = 0;
      let foundRealColor = false;
      let pageCount = pages.length || totalPages;
      let analyzedCount = 0;

      if (pageCount > 0) {
        for (let i = 0; i < pageCount; i++) {
          const relativeUrl = `/thumbnails/${numericJobId}/page_${i}.png?t=${Date.now()}`;
          const analysis = await analyzeImagePage(relativeUrl);
          totalFillRate += analysis.fillRate;
          if (analysis.isColor) {
            foundRealColor = true;
          }
          analyzedCount++;
        }
      }

      const calculatedFillRate = analyzedCount > 0 ? (totalFillRate / analyzedCount) : 0;
      const finalIsGrayscale = res.isGrayscale || !foundRealColor;

      // 3. Retourner le format attendu par print-session-manager.js
      return {
        success:      res.found,
        isGrayscale:  finalIsGrayscale,     // depuis DEVMODE.dmColor + analyse pixels
        isDuplex:     res.isDuplex,          // depuis DEVMODE.dmDuplex
        paperSize:    res.paperSize,         // depuis DEVMODE.dmPaperSize
        fillRate:     calculatedFillRate,    // moyenne calculée pixel-by-pixel via canvas
        thumbnailUrl: thumbnailUrl,          // null si aucune conversion n'a réussi
        totalPages:   totalPages,
      };
    },

    /** Retourne les capacités d'une imprimante (couleur, duplex, etc.) */
    getPrinterCapabilities: (printerName) =>
      invoke('get_printer_capabilities', { printerName }),

    /** Lance l'impression d'un fichier PDF */
    printJob: (pdfPath, options) =>
      invoke('print_job', { pdfPath, options }),

    /** Imprime depuis une URL de fichier */
    printFile: (fileUrl, printOptions) =>
      invoke('print_file', { filePath: fileUrl, printerName: printOptions?.printerName || '' }),

    // Événements du moniteur d'impression
    onPrintJobDetected:   (cb) => _registerEvent('print-job-detected', (payload) => {
      if (payload) {
        cb({
          JobId:         payload.jobId,
          Document:      payload.document,
          PrinterName:   payload.printerName,
          Status:        payload.status,
          StatusLabel:   payload.statusLabel,
          TotalPages:    payload.totalPages,
          SizeBytes:     payload.sizeBytes,
          TimeSubmitted: payload.timeSubmitted || new Date().toISOString(),
          IsDuplex:      payload.isDuplex    || false,
          PaperSize:     payload.paperSize   || 'A4',
          IsGrayscale:   payload.isGrayscale || false,
        });
      } else {
        cb(payload);
      }
    }),
    onPrintMonitorError:  (cb) => _registerEvent('print-monitor-error',  cb),
    onPrintMonitorStarted:(cb) => _registerEvent('print-monitor-started', cb),

    // ─────────────────────────────────────────────────────────────────
    // Administration & Droits élevés
    // ─────────────────────────────────────────────────────────────────

    /** Vérifie si l'application tourne en tant qu'administrateur */
    checkAdminStatus: () =>
      invoke('check_admin_status'),

    /** Relance l'application avec une invite d'élévation UAC */
    restartAsAdmin: () =>
      invoke('restart_as_admin'),
  };

  console.info('[tauri-bridge] window.electronAPI initialisé avec les commandes Tauri v2.');

})();
