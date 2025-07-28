<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Récupérer la liste des réservations
$stmt = $pdo->query("
    SELECT rp.*, p.nom as produit_nom, u.nom as utilisateur_nom, u.prenom as utilisateur_prenom, 
           pat.nom as patient_nom, pat.prenom as patient_prenom
    FROM reservations_produits rp
    JOIN produits p ON rp.produit_id = p.id
    JOIN utilisateurs u ON rp.utilisateur_id = u.id
    LEFT JOIN patients pat ON rp.patient_id = pat.id
    ORDER BY rp.date_reservation DESC
");
$reservations = $stmt->fetchAll();

// Récupérer la liste des produits et patients pour les formulaires
$produits = $pdo->query("SELECT id, nom FROM produits WHERE quantite > 0 ORDER BY nom")->fetchAll();
$patients = $pdo->query("SELECT id, nom, prenom FROM patients ORDER BY nom")->fetchAll();

// Gestion de la suppression
if (isset($_GET['delete'])) {
    $id = securiser($_GET['delete']);
    try {
        // Récupérer la réservation pour mettre à jour le stock
        $stmt = $pdo->prepare("SELECT produit_id, quantite FROM reservations_produits WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch();
        
        if ($reservation) {
            // Mettre à jour le stock
            $pdo->prepare("UPDATE produits SET quantite = quantite + ? WHERE id = ?")
                ->execute([$reservation['quantite'], $reservation['produit_id']]);
            
            // Supprimer la réservation
            $pdo->prepare("DELETE FROM reservations_produits WHERE id = ?")->execute([$id]);
            
            $_SESSION['message'] = "Réservation annulée et stock mis à jour.";
            $_SESSION['message_type'] = "success";
        }
        header("Location: reservations_produits.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur lors de l'annulation: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

// Gestion de l'ajout/modification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? securiser($_POST['id']) : null;
    $produit_id = securiser($_POST['produit_id']);
    $quantite = securiser($_POST['quantite']);
    $patient_id = securiser($_POST['patient_id']) ?: null;
    $date_utilisation = securiser($_POST['date_utilisation']) ?: null;
    $notes = securiser($_POST['notes']);

    try {
        if ($id) {
            // Mise à jour de la réservation
            $stmt = $pdo->prepare("UPDATE reservations_produits SET produit_id = ?, quantite = ?, patient_id = ?, date_utilisation = ?, notes = ? WHERE id = ?");
            $stmt->execute([$produit_id, $quantite, $patient_id, $date_utilisation, $notes, $id]);
            $_SESSION['message'] = "Réservation mise à jour avec succès.";
        } else {
            // Vérifier le stock disponible
            $stmt = $pdo->prepare("SELECT quantite FROM produits WHERE id = ?");
            $stmt->execute([$produit_id]);
            $stock = $stmt->fetchColumn();
            
            if ($stock < $quantite) {
                throw new Exception("Stock insuffisant. Quantité disponible: $stock");
            }
            
            // Ajout d'une nouvelle réservation
            $stmt = $pdo->prepare("INSERT INTO reservations_produits (produit_id, quantite, utilisateur_id, patient_id, date_utilisation, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$produit_id, $quantite, $_SESSION['user_id'], $patient_id, $date_utilisation, $notes]);
            
            // Mettre à jour le stock
            $pdo->prepare("UPDATE produits SET quantite = quantite - ? WHERE id = ?")
                ->execute([$quantite, $produit_id]);
            
            $_SESSION['message'] = "Produit réservé avec succès.";
        }
        $_SESSION['message_type'] = "success";
        header("Location: reservations_produits.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['message'] = "Erreur: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

// Récupérer une réservation pour édition
$reservation_edit = null;
if (isset($_GET['edit'])) {
    $id = securiser($_GET['edit']);
    $stmt = $pdo->prepare("
        SELECT rp.*, p.nom as produit_nom
        FROM reservations_produits rp
        JOIN produits p ON rp.produit_id = p.id
        WHERE rp.id = ?
    ");
    $stmt->execute([$id]);
    $reservation_edit = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations de Produits</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles similaires aux précédents - à externaliser dans un fichier CSS commun */
        /* ... */
        
        .status-reserved { background-color: #fff3cd; color: #856404; }
        .status-used { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h2>Réservations de Produits Médicaux</h2>
            <div class="user-info">
                <span>Bienvenue, <?php echo $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom']; ?></span>
                <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Déconnexion</button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Liste des Réservations</h3>
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i> Nouvelle Réservation
                </button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Réservé par</th>
                        <th>Patient</th>
                        <th>Date Réservation</th>
                        <th>Date Utilisation</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reservation['produit_nom']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['quantite']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['utilisateur_prenom'] . ' ' . $reservation['utilisateur_nom']); ?></td>
                            <td>
                                <?php 
                                echo $reservation['patient_id'] ? 
                                    htmlspecialchars($reservation['patient_prenom'] . ' ' . $reservation['patient_nom']) : 
                                    'N/A'; 
                                ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($reservation['date_reservation'])); ?></td>
                            <td>
                                <?php 
                                echo $reservation['date_utilisation'] ? 
                                    date('d/m/Y H:i', strtotime($reservation['date_utilisation'])) : 
                                    'N/A'; 
                                ?>
                            </td>
                            <td>
                                <?php 
                                switch ($reservation['statut']) {
                                    case 'reserve':
                                        echo '<span class="status status-reserved">Réservé</span>';
                                        break;
                                    case 'utilise':
                                        echo '<span class="status status-used">Utilisé</span>';
                                        break;
                                    case 'annule':
                                        echo '<span class="status status-cancelled">Annulé</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="editReservation(<?php echo $reservation['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger" onclick="confirmDelete(<?php echo $reservation['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal pour ajouter/modifier une réservation -->
    <div id="reservationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo $reservation_edit ? 'Modifier' : 'Nouvelle'; ?> Réservation</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form action="reservations_produits.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $reservation_edit ? $reservation_edit['id'] : ''; ?>">
                
                <div class="form-group">
                    <label for="produit_id">Produit</label>
                    <select id="produit_id" name="produit_id" required>
                        <option value="">Sélectionner un produit</option>
                        <?php foreach ($produits as $produit): ?>
                            <option value="<?php echo $produit['id']; ?>" 
                                <?php echo $reservation_edit && $reservation_edit['produit_id'] == $produit['id'] ? 'selected' : ''; ?>
                                data-stock="<?php echo $produit['quantite']; ?>">
                                <?php echo htmlspecialchars($produit['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="stock-disponible">Stock disponible: <span>0</span></small>
                </div>
                
                <div class="form-group">
                    <label for="quantite">Quantité</label>
                    <input type="number" id="quantite" name="quantite" min="1" value="<?php echo $reservation_edit ? htmlspecialchars($reservation_edit['quantite']) : '1'; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="patient_id">Patient (optionnel)</label>
                    <select id="patient_id" name="patient_id">
                        <option value="">Sélectionner un patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                <?php echo $reservation_edit && $reservation_edit['patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['prenom'] . ' ' . htmlspecialchars($patient['nom'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_utilisation">Date d'utilisation prévue (optionnel)</label>
                    <input type="datetime-local" id="date_utilisation" name="date_utilisation" 
                           value="<?php echo $reservation_edit && $reservation_edit['date_utilisation'] ? date('Y-m-d\TH:i', strtotime($reservation_edit['date_utilisation'])) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (optionnel)</label>
                    <textarea id="notes" name="notes"><?php echo $reservation_edit ? htmlspecialchars($reservation_edit['notes']) : ''; ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary"><?php echo $reservation_edit ? 'Mettre à jour' : 'Réserver'; ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion de la modal
        const modal = document.getElementById('reservationModal');
        
        function openModal() {
            modal.style.display = 'block';
            updateStockInfo();
        }
        
        function closeModal() {
            modal.style.display = 'none';
            // Réinitialiser le formulaire si on annule une modification
            if (window.location.href.includes('edit')) {
                window.location.href = 'reservations_produits.php';
            }
        }
        
        function editReservation(id) {
            window.location.href = 'reservations_produits.php?edit=' + id;
        }
        
        function confirmDelete(id) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ? Le stock sera réapprovisionné.')) {
                window.location.href = 'reservations_produits.php?delete=' + id;
            }
        }
        
        // Mettre à jour l'info du stock disponible
        function updateStockInfo() {
            const produitSelect = document.getElementById('produit_id');
            const stockSpan = document.querySelector('#stock-disponible span');
            
            if (produitSelect.value) {
                const selectedOption = produitSelect.options[produitSelect.selectedIndex];
                const stock = selectedOption.getAttribute('data-stock');
                stockSpan.textContent = stock;
            } else {
                stockSpan.textContent = '0';
            }
        }
        
        // Écouter les changements sur le select des produits
        document.getElementById('produit_id').addEventListener('change', updateStockInfo);
        
        // Afficher la modal si on est en mode édition
        <?php if ($reservation_edit): ?>
            document.addEventListener('DOMContentLoaded', openModal);
        <?php endif; ?>
        
        // Fermer la modal si on clique en dehors
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>