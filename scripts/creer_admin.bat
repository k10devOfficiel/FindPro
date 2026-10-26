@echo off
REM Lanceur de création d'administrateur FindPro avec le binaire PHP de WAMP

if exist "C:\wamp64\bin\php\php8.2.29\php.exe" (
    "C:\wamp64\bin\php\php8.2.29\php.exe" "%~dp0creer_admin.php" %*
) else (
    php "%~dp0creer_admin.php" %*
)
