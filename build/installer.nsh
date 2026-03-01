!macro customHeader
  RequestExecutionLevel admin
!macroend

!macro customInstall
  ; ── Visual C++ Redistributable 2015-2022 x64 ──────────────────────────────
  ; Vérifie si VC++ Redist 14.x x64 est déjà installé via le registre Windows.
  ; La clé est présente dès que la version 2015, 2017, 2019 ou 2022 est installée.
  ClearErrors
  ReadRegDWORD $0 HKLM "SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64" "Installed"
  ${If} ${Errors}
  ${OrIf} $0 <> 1
    ; VC++ Redist absent → installer silencieusement depuis le bundle
    DetailPrint "Installation de Visual C++ Redistributable 2022 x64..."
    File "/oname=$PLUGINSDIR\vc_redist.x64.exe" "${BUILD_RESOURCES_DIR}\vc_redist.x64.exe"
    ExecWait '"$PLUGINSDIR\vc_redist.x64.exe" /install /quiet /norestart' $1
    ${If} $1 <> 0
    ${AndIf} $1 <> 3010  ; 3010 = succès mais redémarrage requis (non bloquant)
      MessageBox MB_OK|MB_ICONEXCLAMATION "L'installation de Visual C++ Redistributable a échoué (code $1).$\nL'application peut ne pas fonctionner correctement.$\nVeuillez l'installer manuellement depuis : https://aka.ms/vs/17/release/vc_redist.x64.exe"
    ${EndIf}
    DetailPrint "Visual C++ Redistributable installé (code retour: $1)"
  ${Else}
    DetailPrint "Visual C++ Redistributable 2022 x64 déjà installé."
  ${EndIf}
  ; ──────────────────────────────────────────────────────────────────────────
!macroend
