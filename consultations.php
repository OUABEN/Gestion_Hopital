<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['user_role'];
$nom_complet = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

// Récupérer la liste des médecins pour le filtre
$medecins = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'medecin'")->fetchAll();

// Récupérer la liste des patients pour le formulaire
$patients = $pdo->query("SELECT id, nom, prenom FROM patients ORDER BY nom")->fetchAll();

// Traitement du formulaire d'ajout de consultation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_consultation'])) {
    $patient_id = securiser($_POST['patient_id']);
    $medecin_id = $role == 'medecin' ? $_SESSION['user_id'] : securiser($_POST['medecin_id']);
    $date_consultation = securiser($_POST['date_consultation']);
    $diagnostic = securiser($_POST['diagnostic']);
    $prescription = securiser($_POST['prescription']);
    $notes = securiser($_POST['notes']);

    try {
        $stmt = $pdo->prepare("INSERT INTO consultations (patient_id, medecin_id, date_consultation, diagnostic, prescription, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patient_id, $medecin_id, $date_consultation, $diagnostic, $prescription, $notes]);
        
        $_SESSION['success_message'] = "Consultation ajoutée avec succès!";
        header("Location: consultations.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de l'ajout de la consultation: " . $e->getMessage();
    }
}

// Traitement de la suppression d'une consultation
if (isset($_GET['supprimer'])) {
    $id = securiser($_GET['supprimer']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM consultations WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success_message'] = "Consultation supprimée avec succès!";
        header("Location: consultations.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de la suppression: " . $e->getMessage();
    }
}

// Récupérer les consultations selon le rôle de l'utilisateur
$where_clause = "";
$params = [];

if ($role == 'medecin') {
    $where_clause = "WHERE c.medecin_id = ?";
    $params = [$_SESSION['user_id']];
} elseif (isset($_GET['medecin']) && !empty($_GET['medecin'])) {
    $where_clause = "WHERE c.medecin_id = ?";
    $params = [securiser($_GET['medecin'])];
}

$query = "
    SELECT c.id, p.nom AS patient_nom, p.prenom AS patient_prenom, 
           u.nom AS medecin_nom, u.prenom AS medecin_prenom,
           c.date_consultation, c.diagnostic, c.prescription, c.notes
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN utilisateurs u ON c.medecin_id = u.id
    $where_clause
    ORDER BY c.date_consultation DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$consultations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Consultations - Hôpital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles from dashboard.php - keep consistent design */
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
        
        /* Sidebar Styles - same as dashboard */
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
        
        /* Form Styles */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-container h3 {
            color: var(--gray-800);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .form-container h3::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
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
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Filter Section */
        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
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
            font-size: 1.25rem;
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
        
        .status-past {
            background: rgba(203, 213, 225, 0.3);
            color: var(--gray-600);
            border: 1px solid var(--gray-300);
        }
        
        .status-past::before {
            background: var(--gray-400);
        }
        
        .status-upcoming {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-upcoming::before {
            background: #f59e0b;
        }
        
        .status-today {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-today::before {
            background: #10b981;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-container {
                padding: 1rem;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar - same as dashboard.php -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-hospital"></i> Gestion Hospitalière</h3>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Tableau de Bord</span>
                        </a>
                    </li>
                    <li>
                        <a href="patients.php">
                            <i class="fas fa-procedures"></i>
                            <span>Patients</span>
                        </a>
                    </li>
                    <li>
                        <a href="chambres.php">
                            <i class="fas fa-bed"></i>
                            <span>Chambres</span>
                        </a>
                    </li>
                    <li>
                        <a href="produits.php">
                            <i class="fas fa-pills"></i>
                            <span>Produits Médicaux</span>
                        </a>
                    </li>
                    <li>
                        <a href="reservations_chambres.php">
                            <i class="fas fa-bed"></i>
                            <span>Reservations Chambres</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="consultations.php">
                            <i class="fas fa-stethoscope"></i>
                            <span>Consultations</span>
                        </a>
                    </li>
                    <li>
                        <a href="rapports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Rapports</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h2>Gestion des Consultations</h2>
                <div class="user-info">
                    <span>Bienvenue, <?php echo $nom_complet; ?> (<?php echo ucfirst($role); ?>)</span>
                    <button class="logout-btn" onclick="window.location.href='logout.php'">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </button>
                </div>
            </div>
            
            <!-- Messages d'alerte -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Section de filtrage -->
            <?php if ($role != 'medecin'): ?>
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="medecin">Filtrer par médecin</label>
                        <select name="medecin" id="medecin" class="form-control">
                            <option value="">Tous les médecins</option>
                            <?php foreach ($medecins as $medecin): ?>
                                <option value="<?php echo $medecin['id']; ?>" <?php echo isset($_GET['medecin']) && $_GET['medecin'] == $medecin['id'] ? 'selected' : ''; ?>>
                                    <?php echo $medecin['prenom'] . ' ' . $medecin['nom']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                    <?php if (isset($_GET['medecin'])): ?>
                        <a href="consultations.php" class="btn btn-danger">
                            <i class="fas fa-times"></i> Effacer
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Formulaire d'ajout de consultation -->
            <div class="form-container">
                <h3><i class="fas fa-plus-circle"></i> Ajouter une Consultation</h3>
                <form method="POST" action="consultations.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient</label>
                            <select name="patient_id" id="patient_id" class="form-control" required>
                                <option value="">Sélectionner un patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo $patient['prenom'] . ' ' . $patient['nom']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($role != 'medecin'): ?>
                        <div class="form-group">
                            <label for="medecin_id">Médecin</label>
                            <select name="medecin_id" id="medecin_id" class="form-control" required>
                                <option value="">Sélectionner un médecin</option>
                                <?php foreach ($medecins as $medecin): ?>
                                    <option value="<?php echo $medecin['id']; ?>">
                                        <?php echo $medecin['prenom'] . ' ' . $medecin['nom']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="date_consultation">Date et Heure</label>
                            <input type="datetime-local" name="date_consultation" id="date_consultation" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="diagnostic">Diagnostic</label>
                            <textarea name="diagnostic" id="diagnostic" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="prescription">Prescription</label>
                            <textarea name="prescription" id="prescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes supplémentaires</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <button type="submit" name="ajouter_consultation" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer la Consultation
                    </button>
                </form>
            </div>
            
            <!-- Liste des consultations -->
            <div class="table-container">
                <h3><i class="fas fa-list"></i> Liste des Consultations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Médecin</th>
                            <th>Date/Heure</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($consultations)): ?>
                            <tr>
                                <td colspan="5">Aucune consultation trouvée</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($consultations as $consultation): 
                                $consultation_date = strtotime($consultation['date_consultation']);
                                $today = strtotime('today');
                                $status = '';
                                
                                if (date('Y-m-d', $consultation_date) == date('Y-m-d')) {
                                    $status = '<span class="status status-today">Aujourd\'hui</span>';
                                } elseif ($consultation_date < time()) {
                                    $status = '<span class="status status-past">Passée</span>';
                                } else {
                                    $status = '<span class="status status-upcoming">À venir</span>';
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . htmlspecialchars($consultation['patient_nom'])); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['medecin_prenom'] . ' ' . htmlspecialchars($consultation['medecin_nom'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', $consultation_date); ?></td>
                                    <td><?php echo $status; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="consultation_detail.php?id=<?php echo $consultation['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                            <a href="consultations.php?supprimer=<?php echo $consultation['id']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette consultation?');">
                                                <i class="fas fa-trash"></i> Suppr
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Scripts pour améliorer l'interactivité
        document.addEventListener('DOMContentLoaded', function() {
            // Mettre la date/heure actuelle comme valeur par défaut
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            document.getElementById('date_consultation').value = `${year}-${month}-${day}T${hours}:${minutes}`;
            
            // Ajouter des effets aux lignes du tableau
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Confirmation avant suppression
            const deleteButtons = document.querySelectorAll('.btn-danger');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer cette consultation ?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>