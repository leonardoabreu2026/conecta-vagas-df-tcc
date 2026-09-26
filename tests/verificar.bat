@echo off
chcp 65001 >nul
rem ============================================================
rem VERIFICACAO COMPLETA - clique duas vezes neste arquivo.
rem Confere a sintaxe de todos os PHP e roda o teste rapido (Apache e MySQL ligados no XAMPP).
rem ============================================================
cd /d "%~dp0.."
echo.
echo === Conecta Vagas DF - verificacao completa ===
echo.
C:\xampp\php\php.exe tests\lint.php
if errorlevel 1 goto erro
C:\xampp\php\php.exe tests\smoke.php
if errorlevel 1 goto erro
echo.
echo TUDO CERTO: pode usar e fazer commit.
pause
exit /b 0
:erro
echo.
echo ATENCAO: algo falhou (veja acima).
echo Para voltar a ultima versao estavel do codigo:  git checkout teste-cliente-2026-09-26
echo Para voltar o banco: veja storage\backups\...\COMO_RESTAURAR.txt
pause
exit /b 1
