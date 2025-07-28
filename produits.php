<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['user_role'];
$nom_complet = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

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
    <title>Gestion des Produits Médicaux - Hôpital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #06b6d4;
            --accent-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1e293b;
            --dark-light: #334155;
            --light-color: #f8fafc;
            --white: #ffffff;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--gray-800);
            min-height: 100vh;
        }
        
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            pointer-events: none;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.3);
            position: relative;
            z-index: 10;
        }
        
        .sidebar-header h3 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .sidebar-header h3 i {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.75rem;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
            position: relative;
            z-index: 10;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin: 0.25rem 1rem;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-menu li a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 12px;
        }
        
        .sidebar-menu li a:hover::before,
        .sidebar-menu li.active a::before {
            opacity: 1;
        }
        
        .sidebar-menu li a:hover,
        .sidebar-menu li.active a {
            color: white;
            transform: translateX(4px);
            box-shadow: var(--shadow-lg);
        }
        
        .sidebar-menu li a i {
            margin-right: 0.875rem;
            font-size: 1.125rem;
            position: relative;
            z-index: 1;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-menu li a span {
            position: relative;
            z-index: 1;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            background: rgba(248, 250, 252, 0.8);
            backdrop-filter: blur(20px);
            overflow-y: auto;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header h2 {
            color: var(--gray-800);
            font-weight: 700;
            font-size: 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info span {
            font-weight: 600;
            color: var(--gray-700);
            padding: 0.75rem 1.5rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 50px;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow);
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Alert Messages */
        .alert {
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-size: 0.9375rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--accent-color), #059669);
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Tables */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        
        .table-container h3 {
            color: var(--gray-800);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .table-container h3::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        th {
            background: linear-gradient(135deg, var(--gray-100), var(--gray-200));
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--gray-200);
        }
        
        th:first-child {
            border-top-left-radius: 12px;
        }
        
        th:last-child {
            border-top-right-radius: 12px;
        }
        
        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.3);
            color: var(--gray-700);
            font-weight: 500;
        }
        
        tr:hover td {
            background: rgba(99, 102, 241, 0.05);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }
        
        tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Status Badges */
        .status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        
        .status-available {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-available::before {
            background: #10b981;
        }
        
        .status-reserved {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-reserved::before {
            background: #f59e0b;
        }
        
        .status-occupied {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-occupied::before {
            background: #ef4444;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            margin: 5% auto;
            padding: 2.5rem;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h3 {
            color: var(--gray-800);
            font-weight: 700;
            font-size: 1.75rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .close {
            font-size: 1.75rem;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .close:hover {
            color: var(--danger-color);
            transform: rotate(90deg);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1.25rem;
            border: 1px solid rgba(203, 213, 225, 0.5);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: fixed;
                top: 0;
                left: -100%;
                z-index: 1000;
                height: 100vh;
                transition: left 0.3s ease;
            }
            
            .sidebar.active {
                left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1.5rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem;
            }
            
            .user-info {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .modal-content {
                padding: 1.5rem;
                width: 95%;
            }
        }
        
        /* Animation */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .table-container, .alert {
            animation: slideInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-hospital"></i> Gestion Hospitalière</h3>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                    <li><a href="patients.php"><i class="fas fa-procedures"></i> Patients</a></li>
                    <li><a href="chambres.php"><i class="fas fa-bed"></i> Chambres</a></li>
                    <li class="active"><a href="produits.php"><i class="fas fa-pills"></i> Produits Médicaux</a></li>
                    <?php if ($role == 'medecin' || $role == 'admin'): ?>
                        <li><a href="consultations.php"><i class="fas fa-stethoscope"></i> Consultations</a></li>
                    <?php endif; ?>
                    <?php if ($role == 'admin'): ?>
                        <li><a href="utilisateurs.php"><i class="fas fa-users-cog"></i> Utilisateurs</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h2>Gestion des Produits Médicaux</h2>
                <div class="user-info">
                    <span>Bienvenue, <?php echo $nom_complet; ?> (<?php echo ucfirst($role); ?>)</span>
                    <button class="logout-btn" onclick="window.location.href='logout.php'" style="width:180px;">
                        <i class="fas fa-sign-out-alt"></i>Déconnexion
                    </button>
                </div>
            </div>
            
            <!-- Messages d'alerte -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                    <i class="fas <?php echo $_SESSION['message_type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Liste des produits -->
            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3>Liste des Produits</h3>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Ajouter un Produit
                    </button>
                </div>
                
                <?php if (empty($produits)): ?>
                    <p>Aucun produit trouvé.</p>
                <?php else: ?>
                    <table  style="position :relative ;right:20px;">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
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
                                    <td><?php echo strlen($produit['description']) > 50 ? substr(htmlspecialchars($produit['description']), 0, 50) . '...' : htmlspecialchars($produit['description']); ?></td>
                                    <td><?php echo htmlspecialchars($produit['categorie']); ?></td>
                                    <td>
                                        <span class="status <?php echo $produit['quantite'] <= $produit['seuil_alerte'] ? ($produit['quantite'] == 0 ? 'status-occupied' : 'status-reserved') : 'status-available'; ?>">
                                            <?php echo htmlspecialchars($produit['quantite']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($produit['seuil_alerte']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary" onclick="editProduit(<?php echo $produit['id']; ?>)">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                            <button class="btn btn-danger" onclick="confirmDelete(<?php echo $produit['id']; ?>)">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
                    <input type="text" id="nom" name="nom" class="form-control" value="<?php echo $produit_edit ? htmlspecialchars($produit_edit['nom']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control"><?php echo $produit_edit ? htmlspecialchars($produit_edit['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="categorie">Catégorie</label>
                    <select id="categorie" name="categorie" class="form-control" required>
                        <option value="Médicament" <?php echo $produit_edit && $produit_edit['categorie'] == 'Médicament' ? 'selected' : ''; ?>>Médicament</option>
                        <option value="Matériel" <?php echo $produit_edit && $produit_edit['categorie'] == 'Matériel' ? 'selected' : ''; ?>>Matériel</option>
                        <option value="Consommable" <?php echo $produit_edit && $produit_edit['categorie'] == 'Consommable' ? 'selected' : ''; ?>>Consommable</option>
                        <option value="Huile" <?php echo $produit_edit && $produit_edit['categorie'] == 'Huile' ? 'selected' : ''; ?>>Huile</option>
                        <option value="Autre" <?php echo $produit_edit && $produit_edit['categorie'] == 'Autre' ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantite">Quantité en Stock</label>
                    <input type="number" id="quantite" name="quantite" class="form-control" min="0" value="<?php echo $produit_edit ? htmlspecialchars($produit_edit['quantite']) : '0'; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="seuil_alerte">Seuil d'Alerte</label>
                    <input type="number" id="seuil_alerte" name="seuil_alerte" class="form-control" min="0" value="<?php echo $produit_edit ? htmlspecialchars($produit_edit['seuil_alerte']) : '10'; ?>" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $produit_edit ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
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
        
        // Animation des éléments
        document.addEventListener('DOMContentLoaded', function() {
            // Effet de survol sur les lignes du tableau
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>