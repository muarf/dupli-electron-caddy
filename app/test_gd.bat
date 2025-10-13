@echo off
REM Script de test pour vérifier l'extension GD sur Windows
REM Usage: Cliquer sur test_gd.bat ou lancer dans cmd

echo ========================================
echo Test de l'extension PHP GD
echo ========================================
echo.

REM Vérifier si php.exe existe
if exist "php\php.exe" (
    echo [OK] PHP trouve dans le projet
    set PHP_BIN=php\php.exe
) else (
    echo [!] PHP non trouve dans php\php.exe
    echo     Utilisation du PHP systeme...
    set PHP_BIN=php
)

echo.
echo Execution du test GD...
echo ----------------------------------------
echo.

%PHP_BIN% test_gd.php

echo.
echo ========================================
echo Test termine
echo ========================================
echo.

REM Vérifier si l'extension est présente physiquement
echo Verification des fichiers...
if exist "php\ext\php_gd2.dll" (
    echo [OK] php_gd2.dll trouve
) else if exist "php\ext\php_gd.dll" (
    echo [OK] php_gd.dll trouve
) else (
    echo [!] Extension GD non trouvee dans php\ext\
    echo.
    echo Action requise:
    echo 1. Telecharger PHP depuis https://windows.php.net/download/
    echo 2. Extraire php_gd2.dll ou php_gd.dll
    echo 3. Copier dans php\ext\
    echo 4. Ajouter 'extension=gd2.dll' dans php.ini
    echo.
)

echo.
pause






