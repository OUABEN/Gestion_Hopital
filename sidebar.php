<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Récupérer la liste des produits
$stmt = $pdo->query("SELECT * FROM produits ORDER BY nom");
$produits = $stmt->fetchAll();

// Gestion de la suppression
if (isset($_GET['delete'])) {
    $id = securiser($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM produits WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Produit supprimé avec succès.";
        $_SESSION['message_type'] = "success";
        header("Location: produits.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur lors de la suppression: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

// Gestion de l'ajout/modification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? securiser($_POST['id']) : null;
    $nom = securiser($_POST['nom']);
    $description = securiser($_POST['description']);
    $categorie = securiser($_POST['categorie']);
    $quantite = securiser($_POST['quantite']);
    $seuil_alerte = securiser($_POST['seuil_alerte']);

    try {
        if ($id) {
            // Mise à jour
            $stmt = $pdo->prepare("UPDATE produits SET nom = ?, description = ?, categorie = ?, quantite = ?, seuil_alerte = ? WHERE id = ?");
            $stmt->execute([$nom, $description, $categorie, $quantite, $seuil_alerte, $id]);
            $_SESSION['message'] = "Produit mis à jour avec succès.";
        } else {
            // Ajout
            $stmt = $pdo->prepare("INSERT INTO produits (nom, description, categorie, quantite, seuil_alerte) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $description, $categorie, $quantite, $seuil_alerte]);
            $_SESSION['message'] = "Produit ajouté avec succès.";
        }
        $_SESSION['message_type'] = "success";
        header("Location: produits.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

// Récupérer un produit pour édition
$produit_edit = null;
if (isset($_GET['edit'])) {
    $id = securiser($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$id]);
    $produit_edit = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits Médicaux</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background-color: var(--dark-color);
            color: white;
            padding: 1rem 0;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 0 1rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h3 {
            color: white;
            text-align: center;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: var(--light-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar-menu li a i {
            margin-right: 0.5rem;
        }
        
        .sidebar-menu li.active a {
            background-color: var(--primary-color);
            color: white;
        }
        
        .main-content {
            flex: 1;
            padding: 1rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .logout-btn {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
        }
        
        .table-container {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-reserved {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-occupied {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 50%;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #aaa;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .modal-content {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar intégrée directement -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Gestion Hospitalière</h3>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                    <li><a href="patients.php"><i class="fas fa-procedures"></i> Patients</a></li>
                    <li><a href="chambres.php"><i class="fas fa-bed"></i> Chambres</a></li>
                    <li class="active"><a href="produits.php"><i class="fas fa-pills"></i> Produits Médicaux</a></li>
                    <?php if ($_SESSION['user_role'] == 'medecin' || $_SESSION['user_role'] == 'admin'): ?>
                        <li><a href="consultations.php"><i class="fas fa-stethoscope"></i> Consultations</a></li>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                        <li><a href="utilisateurs.php"><i class="fas fa-users-cog"></i> Utilisateurs</a></li>
                        <li><a href="rapports.php"><i class="fas fa-chart-bar"></i> Rapports</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Gestion des Produits Médicaux</h2>
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
                    <h3>Liste des Produits</h3>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Ajouter un Produit
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Quantité</th>
                            <th>Seuil Alerte</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $produit): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                <td><?php echo htmlspecialchars($produit['categorie']); ?></td>
                                <td>
                                    <span class="status <?php echo $produit['quantite'] <= $produit['seuil_alerte'] ? ($produit['quantite'] == 0 ? 'status-occupied' : 'status-reserved') : 'status-available'; ?>">
                                        <?php echo htmlspecialchars($produit['quantite']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($produit['seuil_alerte']); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="editProduit(<?php echo $produit['id']; ?>)">
                                        <i class="fas fa-edit"></i> Modifier
                                    </button>
                                    <button class="btn btn-danger" onclick="confirmDelete(<?php echo $produit['id']; ?>)">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal pour ajouter/modifier un produit -->
    <div id="produitModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo $produit_edit ? 'Modifier' : 'Ajouter'; ?> un Produit</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form action="produits.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $produit_edit ? $produit_edit['id'] : ''; ?>">
                
                <div class="form-group">
                    <label for="nom">Nom du Produit</label>
                    <input type="text" id="nom" name="nom" value="<?php echo $produit_edit ? htmlspecialchars($produit_edit['nom']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?php echo $produit_edit ? htmlspecialchars($produit_edit['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="categorie">Catégorie</label>
                    <select id="categorie" name="categorie" required>
                        <option value="Médicament" <?php echo $produit_edit && $produit_edit['categorie'] == 'Médicament' ? 'selected' : ''; ?>>Médicament</option>
                        <option value="Matériel" <?php echo $produit_edit && $produit_edit['categorie'] == 'Matériel' ? 'selected' : ''; ?>>Matériel</option>
                        <option value="Consommable" <?php echo $produit_edit && $produit_edit['categorie'] == 'Consommable' ? 'selected' : ''; ?>>Consommable</option>
                        <option value="Huile" <?php echo $produit_edit && $produit_edit['categorie'] == 'Huile' ? 'selected' : ''; ?>>Huile</option>
                        <option value="Autre" <?php echo $produit_edit && $produit_edit['categorie'] == 'Autre' ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantite">Quantité en Stock</label>
                    <input type="number" id="quantite" name="quantite" min="0" value="<?php echo $produit_edit ? htmlspecialchars($produit_edit['quantite']) : '0'; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="seuil_alerte">Seuil d'Alerte</label>
                    <input type="number" id="seuil_alerte" name="seuil_alerte" min="0" value="<?php echo $produit_edit ? htmlspecialchars($produit_edit['seuil_alerte']) : '10'; ?>" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary"><?php echo $produit_edit ? 'Mettre à jour' : 'Ajouter'; ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion de la modal
        const modal = document.getElementById('produitModal');
        
        function openModal() {
            modal.style.display = 'block';
        }
        
        function closeModal() {
            modal.style.display = 'none';
            // Réinitialiser le formulaire si on annule une modification
            if (window.location.href.includes('edit')) {
                window.location.href = 'produits.php';
            }
        }
        
        function editProduit(id) {
            window.location.href = 'produits.php?edit=' + id;
        }
        
        function confirmDelete(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
                window.location.href = 'produits.php?delete=' + id;
            }
        }
        
        // Afficher la modal si on est en mode édition
        <?php if ($produit_edit): ?>
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