@echo off
echo.
echo ========================================================
echo   Duplicator Studio - Installation IA Locale (Windows)
echo ========================================================
echo.
echo Ce processus va telecharger et installer les modeles IA locaux (environ 1.5 Go).
echo Veuillez patienter, cela peut prendre quelques minutes selon votre connexion...
echo.

set TARGET_DIR=%~1
if "%TARGET_DIR%"=="" set TARGET_DIR=%~dp0\ai_models_cache

:: Get absolute path to python.exe relative to the batch script
set PYTHON_EXE=%~dp0\python\python.exe
set INSTALL_SCRIPT=%~dp0\..\..\app\api\scripts\install.py

if not exist "%PYTHON_EXE%" (
    echo [Erreur] Python embarque introuvable a %PYTHON_EXE%
    pause
    exit /b 1
)

"%PYTHON_EXE%" "%INSTALL_SCRIPT%" "%TARGET_DIR%"

echo.
echo Installation terminee ! Vous pouvez fermer cette fenetre.
pause
