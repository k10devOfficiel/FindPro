# Procédure de Restauration — FindPro

1. Créez la base de données : `mysql -u root -p -e "CREATE DATABASE findpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
2. Importez la sauvegarde SQL : `mysql -u root -p findpro < backups/findpro_2026-10-25.sql`
3. Restaurez la configuration : copiez `config.local.php` hors dépôt vers `config/config.local.php` (vérifiez les accès BD).
4. Restaurez les fichiers médias : copiez les dossiers d'uploads hors dépôt vers `uploads/avatars/` et `uploads/portfolio/`.
5. Validez le déploiement : accédez à `http://localhost/findpro/` et connectez-vous avec vos identifiants administrateur.
