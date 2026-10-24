<?php
/**
 * Prépare le profil pro@findpro.ci au statut 'a_corriger' avec une décision enregistrée dans validation_profil
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$admin = $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = 'k10developpeur@gmail.com'")->fetch();
$pro = $pdo->query("SELECT id_utilisateur, prenom, nom FROM utilisateur WHERE email = 'pro@findpro.ci'")->fetch();

$idAdmin = (int) $admin['id_utilisateur'];
$idPro = (int) $pro['id_utilisateur'];

$idProfil = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idPro}")->fetchColumn();

$motifCorrection = "Veuillez préciser vos technologies backend (version de PHP, frameworks) et ajouter au moins une capture d'écran dans votre portfolio.";

// Enregistrer la décision dans validation_profil et mettre à jour profil_professionnel
$pdo->beginTransaction();

$pdo->prepare('
    UPDATE profil_professionnel SET
        statut_validation = "a_corriger",
        date_validation = NOW()
    WHERE id_profil = :id_profil
')->execute(['id_profil' => $idProfil]);

$pdo->prepare('
    INSERT INTO validation_profil (
        id_profil,
        id_administrateur,
        decision,
        motif,
        date_decision
    ) VALUES (
        :id_profil,
        :id_admin,
        "a_corriger",
        :motif,
        NOW()
    )
')->execute([
    'id_profil' => $idProfil,
    'id_admin'  => $idAdmin,
    'motif'     => $motifCorrection,
]);

$pdo->commit();

echo "Profil #{$idProfil} ({$pro['prenom']} {$pro['nom']}) positionné au statut 'a_corriger' avec succès !\n";
echo "Motif consigné : {$motifCorrection}\n";
