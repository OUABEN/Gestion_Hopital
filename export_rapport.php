<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // Assurez-vous d'avoir installé TCPDF via composer

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Créer un nouveau document PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Information du document
$pdf->SetCreator('Gestion Hospitalière');
$pdf->SetAuthor('Hôpital XYZ');
$pdf->SetTitle('Rapport Statistique');
$pdf->SetSubject('Statistiques Hospitalières');

// Marges
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// En-tête et pied de page
$pdf->setHeaderFont(Array('helvetica', '', 10));
$pdf->setFooterFont(Array('helvetica', '', 8));

// Suppression de l'en-tête et pied de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Police par défaut
$pdf->SetDefaultMonospacedFont('courier');

// Marge automatique
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Ajouter une page
$pdf->AddPage();

// Logo de l'hôpital
$pdf->Image('images/logo_hopital.png', 15, 15, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

// Titre du rapport
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 30, 'Rapport Statistique', 0, 1, 'C');
$pdf->Ln(10);

// Date du rapport
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 0, 'Date : ' . date('d/m/Y'), 0, 1, 'R');
$pdf->Ln(15);

// Statistiques générales
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 0, 'Statistiques Générales', 0, 1);
$pdf->Ln(10);

// Récupérer les statistiques
$stats = [
    'patients' => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
    'chambres' => $pdo->query("SELECT COUNT(*) FROM chambres")->fetchColumn(),
    'consultations' => $pdo->query("SELECT COUNT(*) FROM consultations")->fetchColumn(),
    'reservations' => $pdo->query("SELECT COUNT(*) FROM reservations_chambres")->fetchColumn(),
    'produits' => $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn()
];

// Tableau des statistiques
$pdf->SetFont('helvetica', '', 12);
$html = '<table border="1" cellpadding="5">
    <thead>
        <tr style="background-color:#f2f2f2;">
            <th width="50%">Statistique</th>
            <th width="50%">Valeur</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Nombre total de patients</td>
            <td>'.$stats['patients'].'</td>
        </tr>
        <tr>
            <td>Nombre total de chambres</td>
            <td>'.$stats['chambres'].'</td>
        </tr>
        <tr>
            <td>Nombre total de consultations</td>
            <td>'.$stats['consultations'].'</td>
        </tr>
        <tr>
            <td>Nombre total de réservations</td>
            <td>'.$stats['reservations'].'</td>
        </tr>
        <tr>
            <td>Nombre total de produits médicaux</td>
            <td>'.$stats['produits'].'</td>
        </tr>
    </tbody>
</table>';

$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln(15);

// Statistiques mensuelles
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 0, 'Statistiques Mensuelles ('.date('Y').')', 0, 1);
$pdf->Ln(10);

$current_year = date('Y');
$monthly_stats = [];
for ($i = 1; $i <= 12; $i++) {
    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
    $start_date = "$current_year-$month-01";
    $end_date = "$current_year-$month-31";
    
    $monthly_stats[$i] = [
        'patients' => $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(date_creation) BETWEEN '$start_date' AND '$end_date'")->fetchColumn(),
        'consultations' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE DATE(date_consultation) BETWEEN '$start_date' AND '$end_date'")->fetchColumn(),
        'reservations' => $pdo->query("SELECT COUNT(*) FROM reservations_chambres WHERE DATE(date_debut) BETWEEN '$start_date' AND '$end_date'")->fetchColumn()
    ];
}

// Tableau des statistiques mensuelles
$html = '<table border="1" cellpadding="5">
    <thead>
        <tr style="background-color:#f2f2f2;">
            <th width="20%">Mois</th>
            <th width="20%">Nouveaux Patients</th>
            <th width="20%">Consultations</th>
            <th width="20%">Réservations</th>
        </tr>
    </thead>
    <tbody>';

$months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
foreach ($monthly_stats as $month => $data) {
    $html .= '<tr>
        <td>'.$months[$month-1].'</td>
        <td>'.$data['patients'].'</td>
        <td>'.$data['consultations'].'</td>
        <td>'.$data['reservations'].'</td>
    </tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln(15);

// Produits les plus utilisés
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 0, 'Produits Médicaux les Plus Utilisés', 0, 1);
$pdf->Ln(10);

$top_produits = $pdo->query("
    SELECT p.nom, p.categorie, SUM(rp.quantite) as total_utilise
    FROM reservations_produits rp
    JOIN produits p ON rp.produit_id = p.id
    WHERE rp.statut = 'utilise'
    GROUP BY p.id
    ORDER BY total_utilise DESC
    LIMIT 5
")->fetchAll();

$html = '<table border="1" cellpadding="5">
    <thead>
        <tr style="background-color:#f2f2f2;">
            <th width="40%">Produit</th>
            <th width="30%">Catégorie</th>
            <th width="30%">Quantité Utilisée</th>
        </tr>
    </thead>
    <tbody>';

foreach ($top_produits as $produit) {
    $html .= '<tr>
        <td>'.htmlspecialchars($produit['nom']).'</td>
        <td>'.htmlspecialchars($produit['categorie']).'</td>
        <td>'.$produit['total_utilise'].'</td>
    </tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Ln(15);

// Médecins les plus actifs
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 0, 'Médecins les Plus Actifs', 0, 1);
$pdf->Ln(10);

$top_medecins = $pdo->query("
    SELECT u.nom, u.prenom, COUNT(c.id) as total_consultations
    FROM consultations c
    JOIN utilisateurs u ON c.medecin_id = u.id
    GROUP BY u.id
    ORDER BY total_consultations DESC
    LIMIT 5
")->fetchAll();

$html = '<table border="1" cellpadding="5">
    <thead>
        <tr style="background-color:#f2f2f2;">
            <th width="60%">Médecin</th>
            <th width="40%">Nombre de Consultations</th>
        </tr>
    </thead>
    <tbody>';

foreach ($top_medecins as $medecin) {
    $html .= '<tr>
        <td>'.htmlspecialchars($medecin['prenom'].' '.htmlspecialchars($medecin['nom'])).'</td>
        <td>'.$medecin['total_consultations'].'</td>
    </tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, false, false, '');

// Pied de page personnalisé
$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Généré le '.date('d/m/Y à H:i').' par '.$_SESSION['user_nom'].' '.$_SESSION['user_prenom'], 0, 0, 'C');

// Générer le PDF et le télécharger
$pdf->Output('rapport_statistique_'.date('Ymd_His').'.pdf', 'D');
?>