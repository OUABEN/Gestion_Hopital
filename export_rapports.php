<?php
session_start();
require_once 'config.php';

// Vérifier l'authentification et le rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Récupérer les données pour l'export
$patients = $pdo->query("SELECT * FROM patients ORDER BY nom, prenom")->fetchAll();
$consultations = $pdo->query("
    SELECT c.*, p.nom AS patient_nom, p.prenom AS patient_prenom, 
           u.nom AS medecin_nom, u.prenom AS medecin_prenom
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN utilisateurs u ON c.medecin_id = u.id
    ORDER BY c.date_consultation DESC
")->fetchAll();
$reservations = $pdo->query("
    SELECT rc.*, p.nom AS patient_nom, p.prenom AS patient_prenom,
           ch.numero AS chambre_numero, ch.type AS chambre_type,
           u.nom AS medecin_nom, u.prenom AS medecin_prenom
    FROM reservations_chambres rc
    JOIN patients p ON rc.patient_id = p.id
    JOIN chambres ch ON rc.chambre_id = ch.id
    LEFT JOIN utilisateurs u ON rc.medecin_referent = u.id
    ORDER BY rc.date_debut DESC
")->fetchAll();
$produits = $pdo->query("SELECT * FROM produits ORDER BY quantite ASC")->fetchAll();

// Générer le contenu CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=rapports_hopital_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// En-tête général
fputcsv($output, ['Rapports Hospitaliers - ' . date('d/m/Y')], ';');

// Section Patients
fputcsv($output, [], ';');
fputcsv($output, ['PATIENTS (' . count($patients) . ')'], ';');
fputcsv($output, ['ID', 'Nom', 'Prénom', 'Date Naissance', 'Genre', 'Téléphone', 'Email', 'Groupe Sanguin'], ';');
foreach ($patients as $patient) {
    fputcsv($output, [
        $patient['id'],
        $patient['nom'],
        $patient['prenom'],
        date('d/m/Y', strtotime($patient['date_naissance'])),
        $patient['genre'],
        $patient['telephone'],
        $patient['email'],
        $patient['groupe_sanguin']
    ], ';');
}

// Section Consultations
fputcsv($output, [], ';');
fputcsv($output, ['CONSULTATIONS (' . count($consultations) . ')'], ';');
fputcsv($output, ['ID', 'Patient', 'Médecin', 'Date', 'Statut', 'Diagnostic'], ';');
foreach ($consultations as $consultation) {
    fputcsv($output, [
        $consultation['id'],
        $consultation['patient_prenom'] . ' ' . $consultation['patient_nom'],
        $consultation['medecin_prenom'] . ' ' . $consultation['medecin_nom'],
        date('d/m/Y H:i', strtotime($consultation['date_consultation'])),
        $consultation['statut'],
        substr($consultation['diagnostic'] ?? '', 0, 50) . (strlen($consultation['diagnostic'] ?? '') > 50 ? '...' : '')
    ], ';');
}

// Section Réservations
fputcsv($output, [], ';');
fputcsv($output, ['RESERVATIONS (' . count($reservations) . ')'], ';');
fputcsv($output, ['ID', 'Patient', 'Chambre', 'Type', 'Date Début', 'Date Fin', 'Statut'], ';');
foreach ($reservations as $reservation) {
    fputcsv($output, [
        $reservation['id'],
        $reservation['patient_prenom'] . ' ' . $reservation['patient_nom'],
        $reservation['chambre_numero'],
        $reservation['chambre_type'],
        date('d/m/Y H:i', strtotime($reservation['date_debut'])),
        $reservation['date_fin'] ? date('d/m/Y H:i', strtotime($reservation['date_fin'])) : '-',
        $reservation['statut']
    ], ';');
}

// Section Produits
fputcsv($output, [], ';');
fputcsv($output, ['PRODUITS (' . count($produits) . ')'], ';');
fputcsv($output, ['ID', 'Nom', 'Catégorie', 'Quantité', 'Seuil Alerte'], ';');
foreach ($produits as $produit) {
    fputcsv($output, [
        $produit['id'],
        $produit['nom'],
        $produit['categorie'],
        $produit['quantite'],
        $produit['seuil_alerte']
    ], ';');
}

fclose($output);
exit();