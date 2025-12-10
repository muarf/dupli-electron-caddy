@echo off
call "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat"
if not exist build mkdir build
if not exist build\Release mkdir build\Release

echo Building DupliPrintProcessor.dll...
cl.exe /nologo /LD /MT /O2 /D "WIN32" /D "NDEBUG" /D "_WINDOWS" /D "_USRDLL" /D "DUPLIPRINTPROCESSOR_EXPORTS" /Fe"build\Release\DupliPrintProcessor.dll" DupliPrintProcessor.cpp /link /DEF:DupliPrintProcessor.def /MACHINE:X64 winspool.lib gdi32.lib user32.lib

if %ERRORLEVEL% EQU 0 (
    echo Build SUCCESS!
) else (
    echo Build FAILED!
)
