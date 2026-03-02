!macro customHeader
  RequestExecutionLevel admin
!macroend

!macro customInstall
  ; ── Visual C++ Redistributable 2015-2022 x64 ──────────────────────────────
  ; Toujours forcer l'installation de The Redistributable de toute manière en mode silencieux.
  ; L'installeur Microsoft gère la mise à jour tout seul de manière très safe et ignore s'il est déjà à jour.
  DetailPrint "Installation/Mise à jour de Visual C++ Redistributable 2022 x64..."
  File "/oname=$PLUGINSDIR\vc_redist.x64.exe" "${BUILD_RESOURCES_DIR}\vc_redist.x64.exe"
  ExecWait '"$PLUGINSDIR\vc_redist.x64.exe" /install /quiet /norestart' $1
  ${If} $1 <> 0
  ${AndIf} $1 <> 3010  ; 3010 = succès mais redémarrage requis (non bloquant)
  ${AndIf} $1 <> 1638  ; 1638 = Une autre version de ce produit est déjà installée
    MessageBox MB_OK|MB_ICONEXCLAMATION "L'installation de Visual C++ Redistributable a échoué (code $1).$\nL'application peut ne pas fonctionner correctement.$\nVeuillez l'installer manuellement depuis : https://aka.ms/vs/17/release/vc_redist.x64.exe"
  ${EndIf}
  DetailPrint "Visual C++ Redistributable vérifié/installé (code retour: $1)"
  ; ──────────────────────────────────────────────────────────────────────────
!macroend
