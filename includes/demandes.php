<?php
/**
 * Gestion du cycle de vie et transitions des demandes de service — FindPro
 *
 * Implémente strictement la matrice d'états et de transitions :
 * - envoyee    -> acceptee ou refusee (professionnel destinataire)
 * - envoyee    -> annulee (client auteur)
 * - acceptee   -> en_cours (professionnel destinataire)
 * - acceptee   -> annulee (client auteur)
 * - en_cours   -> a_confirmer (professionnel destinataire)
 * - a_confirmer -> terminee (client auteur) ou en_cours (client auteur)
 *
 * Règles complémentaires :
 * - Relecture avec verrouillage pessimiste : SELECT ... FOR UPDATE au sein d'une transaction.
 * - Contrôle strict du rôle ET de la propriété de la ressource.
 * - Clôture automatique : date_cloture renseignée pour 'terminee', 'refusee', 'annulee'.
 * - Confirmation : date_confirmation renseignée pour 'terminee'.
 * - Verrouillage conversation : conversation liée passée à 'lecture_seule' pour 'refusee' ou 'annulee'.
 */

declare(strict_types=1);

/**
 * Matrice stricte des transitions autorisées.
 * Structure : [statut_initial => [statut_cible => role_autorise]]
 */
const MATRICE_TRANSITIONS = [
    'envoyee' => [
        'acceptee' => 'professionnel',
        'refusee'  => 'professionnel',
        'annulee'  => 'client',
    ],
    'acceptee' => [
        'en_cours' => 'professionnel',
        'annulee'  => 'client',
    ],
    'en_cours' => [
        'a_confirmer' => 'professionnel',
    ],
    'a_confirmer' => [
        'terminee' => 'client',
        'en_cours' => 'client',
    ],
    'terminee' => [],
    'refusee'  => [],
    'annulee'  => [],
];

/**
 * Métadonnées visuelles et textuelles associées aux transitions disponibles pour l'interface.
 */
const METADONNEES_TRANSITIONS = [
    'acceptee' => [
        'label'        => 'Accepter la demande',
        'btn_class'    => 'btn-success',
        'icon'         => 'bi-check-lg',
        'confirmation' => 'Confirmez-vous l\'acceptation de cette demande ? Vous vous engagez à réaliser la prestation.',
    ],
    'refusee' => [
        'label'        => 'Refuser la demande',
        'btn_class'    => 'btn-outline-danger',
        'icon'         => 'bi-x-lg',
        'confirmation' => 'Êtes-vous sûr de vouloir refuser cette demande ? Cette action est irréversible et clôturera les échanges.',
    ],
    'annulee' => [
        'label'        => 'Annuler la demande',
        'btn_class'    => 'btn-outline-danger',
        'icon'         => 'bi-trash3',
        'confirmation' => 'Êtes-vous sûr de vouloir annuler votre demande de prestation ?',
    ],
    'en_cours' => [
        'label'        => 'Démarrer la prestation',
        'btn_class'    => 'btn-primary',
        'icon'         => 'bi-play-fill',
        'confirmation' => 'Confirmez-vous le début de la réalisation des travaux ?',
    ],
    'a_confirmer' => [
        'label'        => 'Marquer les travaux terminés (à confirmer)',
        'btn_class'    => 'btn-warning text-dark',
        'icon'         => 'bi-check2-circle',
        'confirmation' => 'Indiquez au client que vos travaux sont terminés pour qu\'il puisse valider la réception.',
    ],
    'terminee' => [
        'label'        => 'Confirmer la fin des travaux',
        'btn_class'    => 'btn-success',
        'icon'         => 'bi-check-all',
        'confirmation' => 'Validez-vous la réception complète et satisfaisante de cette prestation ?',
    ],
];

/**
 * Modifie le statut d'une demande de service en appliquant STRICTEMENT la matrice de transitions.
 *
 * @param PDO    $pdo          Instance active de connexion PDO
 * @param int    $id_demande   Identifiant unique de la demande
 * @param string $nouveau      Statut cible demandé
 * @param array  $utilisateur  Données de l'utilisateur effectuant l'action (id/id_utilisateur, role)
 *
 * @return array{succes: bool, message: string, statut?: string}
 */
function changer_statut_demande(PDO $pdo, int $id_demande, string $nouveau, array $utilisateur): array
{
    if ($id_demande <= 0) {
        return ['succes' => false, 'message' => "Identifiant de demande invalide."];
    }

    $nouveau = trim($nouveau);
    if ($nouveau === '') {
        return ['succes' => false, 'message' => "Nouveau statut non spécifié."];
    }

    $idUtilisateur = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur'] ?? 0);
    $roleUtilisateur = (string) ($utilisateur['role'] ?? '');

    if ($idUtilisateur <= 0 || $roleUtilisateur === '') {
        return ['succes' => false, 'message' => "Utilisateur non authentifié ou profil incomplet."];
    }

    $gererTransaction = !$pdo->inTransaction();
    if ($gererTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // 1. Relecture de la demande sous verrou pessimiste (SELECT ... FOR UPDATE)
        $stmt = $pdo->prepare('
            SELECT 
                d.id_demande,
                d.id_client,
                d.id_profil,
                d.statut,
                d.date_creation,
                d.date_cloture,
                d.date_confirmation,
                p.id_utilisateur AS pro_id_utilisateur
            FROM demande_service d
            JOIN profil_professionnel p ON d.id_profil = p.id_profil
            WHERE d.id_demande = :id
            FOR UPDATE
        ');
        $stmt->execute(['id' => $id_demande]);
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$demande) {
            if ($gererTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['succes' => false, 'message' => "La demande de service demandée n'existe pas."];
        }

        $statutActuel = (string) $demande['statut'];
        $idClient = (int) $demande['id_client'];
        $idProUser = (int) $demande['pro_id_utilisateur'];

        // 2. Vérification stricte du rôle ET de la propriété
        $estClientAuteur = ($roleUtilisateur === 'client' && $idClient === $idUtilisateur);
        $estProDestinataire = ($roleUtilisateur === 'professionnel' && $idProUser === $idUtilisateur);

        if (!$estClientAuteur && !$estProDestinataire) {
            if ($gererTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'succes' => false, 
                'message' => "Action non autorisée : vous n'avez pas les droits de modification sur cette demande."
            ];
        }

        // 3. Vérification de la transition autorisée dans la matrice
        $transitionsDuStatut = MATRICE_TRANSITIONS[$statutActuel] ?? [];
        if (!array_key_exists($nouveau, $transitionsDuStatut)) {
            if ($gererTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'succes' => false,
                'message' => "La transition depuis le statut '{$statutActuel}' vers '{$nouveau}' n'est pas autorisée.",
            ];
        }

        $roleRequis = $transitionsDuStatut[$nouveau];
        $roleActeur = $estClientAuteur ? 'client' : 'professionnel';

        if ($roleActeur !== $roleRequis) {
            if ($gererTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'succes' => false,
                'message' => "Seul le {$roleRequis} peut faire passer la demande de '{$statutActuel}' à '{$nouveau}'.",
            ];
        }

        // 4. Préparation des mises à jour d'horodatages
        // Renseigne date_cloture pour terminee, refusee ou annulee
        $doitCloturer = in_array($nouveau, ['terminee', 'refusee', 'annulee'], true);
        // Renseigne date_confirmation quand le client confirme (terminee)
        $doitConfirmer = ($nouveau === 'terminee');

        // Cas de retour de 'a_confirmer' vers 'en_cours' (ajustements demandés par le client)
        $estRetourEnCours = ($statutActuel === 'a_confirmer' && $nouveau === 'en_cours');

        $champsUpdate = ['statut = :statut'];
        $paramsUpdate = [
            'statut' => $nouveau,
            'id'     => $id_demande,
        ];

        if ($doitCloturer) {
            $champsUpdate[] = 'date_cloture = NOW()';
        } elseif ($estRetourEnCours) {
            // Remise à zéro propre de la date de clôture si précédemment fixée
            $champsUpdate[] = 'date_cloture = NULL';
        }

        if ($doitConfirmer) {
            $champsUpdate[] = 'date_confirmation = NOW()';
        }

        // 5. Mise à jour de la table demande_service
        $sqlDemande = 'UPDATE demande_service SET ' . implode(', ', $champsUpdate) . ' WHERE id_demande = :id';
        $stmtUp = $pdo->prepare($sqlDemande);
        $stmtUp->execute($paramsUpdate);

        // 6. Gestion du canal de conversation lié
        // Passe la conversation en 'lecture_seule' quand la demande est refusee ou annulee
        if (in_array($nouveau, ['refusee', 'annulee'], true)) {
            $stmtConv = $pdo->prepare('UPDATE conversation SET statut = :statut WHERE id_demande = :id');
            $stmtConv->execute([
                'statut' => 'lecture_seule',
                'id'     => $id_demande,
            ]);
        }

        if ($gererTransaction) {
            $pdo->commit();
        }

        $messagesSucces = [
            'acceptee'    => "La demande de prestation a été acceptée avec succès.",
            'refusee'     => "La demande a été refusée. La conversation est désormais en lecture seule.",
            'annulee'     => "La demande a été annulée. La conversation est désormais en lecture seule.",
            'en_cours'    => $statutActuel === 'a_confirmer' 
                ? "La prestation a été replacée en cours de réalisation pour ajustements." 
                : "La prestation est désormais marquée en cours de réalisation.",
            'a_confirmer' => "Les travaux ont été marqués comme achevés. Le client a été notifié pour confirmation.",
            'terminee'    => "Prestation validée et terminée avec succès. Merci pour votre confirmation.",
        ];

        return [
            'succes'  => true,
            'message' => $messagesSucces[$nouveau] ?? "Le statut de la demande a été mis à jour avec succès vers '{$nouveau}'.",
            'statut'  => $nouveau,
        ];

    } catch (Throwable $e) {
        if ($gererTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[changer_statut_demande] Erreur: " . $e->getMessage());
        return [
            'succes'  => false,
            'message' => "Une erreur imprévue est survenue lors de la mise à jour du statut.",
        ];
    }
}

/**
 * Retourne la liste des transitions permises pour l'utilisateur connecté sur la demande donnée.
 * Utilisé par les vues pour afficher UNIQUEMENT les boutons d'actions légitimes.
 *
 * @param string $statutActuel  Statut courant de la demande
 * @param array  $utilisateur   Utilisateur connecté (id, role)
 * @param array  $demande       Données de la demande (id_client, pro_id_utilisateur)
 *
 * @return array<int, array{statut: string, label: string, btn_class: string, icon: string, confirmation: string}>
 */
function transitions_possibles(string $statutActuel, array $utilisateur, array $demande): array
{
    $idUtilisateur = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur'] ?? 0);
    $roleUtilisateur = (string) ($utilisateur['role'] ?? '');

    $idClient = (int) ($demande['id_client'] ?? 0);
    $idProUser = (int) ($demande['pro_id_utilisateur'] ?? 0);

    $estClientAuteur = ($roleUtilisateur === 'client' && $idClient === $idUtilisateur);
    $estProDestinataire = ($roleUtilisateur === 'professionnel' && $idProUser === $idUtilisateur);

    if (!$estClientAuteur && !$estProDestinataire) {
        return [];
    }

    $roleActeur = $estClientAuteur ? 'client' : 'professionnel';
    $transitions = MATRICE_TRANSITIONS[$statutActuel] ?? [];

    $actions = [];
    foreach ($transitions as $cible => $roleRequis) {
        if ($roleRequis === $roleActeur) {
            $meta = METADONNEES_TRANSITIONS[$cible] ?? [
                'label'        => ucfirst(str_replace('_', ' ', $cible)),
                'btn_class'    => 'btn-secondary',
                'icon'         => 'bi-arrow-right',
                'confirmation' => "Confirmez-vous le passage au statut {$cible} ?",
            ];

            // Personnalisation spécifique pour le retour en cours depuis a_confirmer
            if ($statutActuel === 'a_confirmer' && $cible === 'en_cours') {
                $meta['label'] = 'Demander des ajustements / Non conforme';
                $meta['btn_class'] = 'btn-outline-warning text-dark';
                $meta['icon'] = 'bi-arrow-repeat';
                $meta['confirmation'] = 'Souhaitez-vous demander au professionnel de poursuivre ou d\'ajuster la prestation ?';
            }

            $actions[] = array_merge(['statut' => $cible], $meta);
        }
    }

    return $actions;
}
