<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: patients.php");
    exit();
}

$patient_id = $_GET['id'];
$role = $_SESSION['user_role'];
$nom_complet = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

// Récupérer les informations du patient
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $_SESSION['message'] = "Patient non trouvé";
    $_SESSION['message_type'] = "danger";
    header("Location: patients.php");
    exit();
}

// Récupérer les réservations de chambre du patient
$stmt = $pdo->prepare("
    SELECT rc.*, ch.numero, ch.type, u.nom AS medecin_nom, u.prenom AS medecin_prenom
    FROM reservations_chambres rc
    JOIN chambres ch ON rc.chambre_id = ch.id
    LEFT JOIN utilisateurs u ON rc.medecin_referent = u.id
    WHERE rc.patient_id = ?
    ORDER BY rc.date_debut DESC
");
$stmt->execute([$patient_id]);
$reservations = $stmt->fetchAll();

// Récupérer les consultations du patient
$stmt = $pdo->prepare("
    SELECT c.*, u.nom AS medecin_nom, u.prenom AS medecin_prenom
    FROM consultations c
    JOIN utilisateurs u ON c.medecin_id = u.id
    WHERE c.patient_id = ?
    ORDER BY c.date_consultation DESC
");
$stmt->execute([$patient_id]);
$consultations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Patient - Hôpital</title>
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
        
        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.3);
        }
        
        .card-title {
            color: var(--gray-800);
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-value {
            padding: 0.875rem;
            background: rgba(241, 245, 249, 0.5);
            border-radius: 12px;
            font-weight: 500;
            border: 1px solid rgba(203, 213, 225, 0.3);
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1.5rem;
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
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-active::before {
            background: #10b981;
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-inactive::before {
            background: #ef4444;
        }
        
        .status-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-warning::before {
            background: #f59e0b;
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
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
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
        
        .card {
            animation: slideInUp 0.6s ease-out;
        }
        
        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
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
                    <li><a href="produits.php"><i class="fas fa-pills"></i> Produits Médicaux</a></li>
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
                <h2>Détails du Patient</h2>
                <div class="user-info">
                    <span>Bienvenue, <?php echo $nom_complet; ?> (<?php echo ucfirst($role); ?>)</span>
                    <button class="logout-btn" onclick="window.location.href='logout.php'">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </button>
                </div>
            </div>
            
            <!-- Bouton Retour -->
            <a href="patients.php" class="btn btn-primary" style="margin-bottom: 1.5rem;">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
            
            <!-- Informations du patient -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informations Personnelles</h3>
                    <?php if ($role == 'admin' || $role == 'medecin'): ?>
                        <a href="modifier_patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-success">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nom Complet</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['prenom']) . ' ' . htmlspecialchars($patient['nom']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Date de Naissance</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($patient['date_naissance'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Âge</div>
                        <div class="info-value">
                            <?php 
                            $birthDate = new DateTime($patient['date_naissance']);
                            $today = new DateTime();
                            $age = $today->diff($birthDate)->y;
                            echo $age . ' ans';
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Genre</div>
                        <div class="info-value">
                            <?php 
                            switch($patient['genre']) {
                                case 'M': echo 'Masculin'; break;
                                case 'F': echo 'Féminin'; break;
                                default: echo htmlspecialchars($patient['genre']);
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Groupe Sanguin</div>
                        <div class="info-value"><?php echo $patient['groupe_sanguin'] ? htmlspecialchars($patient['groupe_sanguin']) : 'Inconnu'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Téléphone</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['telephone']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['email']); ?></div>
                    </div>
                    
                    <div class="info-item" style="grid-column: 1 / -1">
                        <div class="info-label">Adresse</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($patient['adresse'])); ?></div>
                    </div>
                    
                    <div class="info-item" style="grid-column: 1 / -1">
                        <div class="info-label">Antécédents Médicaux</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($patient['antecedents'])); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Réservations de chambre -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Historique des Hospitalisations</h3>
                    <?php if ($role == 'admin' || $role == 'medecin'): ?>
                        <a href="reserver_chambre.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nouvelle Réservation
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($reservations)): ?>
                    <p>Aucune hospitalisation enregistrée pour ce patient.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Chambre</th>
                                    <th>Type</th>
                                    <th>Date Début</th>
                                    <th>Date Fin</th>
                                    <th>Médecin Référent</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reservation['numero']); ?></td>
                                        <td><?php echo htmlspecialchars($reservation['type']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($reservation['date_debut'])); ?></td>
                                        <td><?php echo $reservation['date_fin'] ? date('d/m/Y H:i', strtotime($reservation['date_fin'])) : '-'; ?></td>
                                        <td>
                                            <?php if ($reservation['medecin_nom']): ?>
                                                <?php echo htmlspecialchars($reservation['medecin_prenom']) . ' ' . htmlspecialchars($reservation['medecin_nom']); ?>
                                            <?php else: ?>
                                                Non spécifié
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = 'status-active';
                                            $statusText = 'Confirmée';
                                            
                                            switch($reservation['statut']) {
                                                case 'annulee':
                                                    $statusClass = 'status-inactive';
                                                    $statusText = 'Annulée';
                                                    break;
                                                case 'terminee':
                                                    $statusClass = 'status-warning';
                                                    $statusText = 'Terminée';
                                                    break;
                                            }
                                            ?>
                                            <span class="status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="reservation_details.php?id=<?php echo $reservation['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($role == 'admin' || $role == 'medecin'): ?>
                                                    <?php if ($reservation['statut'] == 'confirmee'): ?>
                                                        <a href="modifier_reservation.php?id=<?php echo $reservation['id']; ?>" class="btn btn-success">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Consultations médicales -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Historique des Consultations</h3>
                    <?php if ($role == 'admin' || $role == 'medecin'): ?>
                        <a href="nouvelle_consultation.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nouvelle Consultation
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($consultations)): ?>
                    <p>Aucune consultation enregistrée pour ce patient.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Médecin</th>
                                    <th>Diagnostic</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $consultation): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?></td>
                                        <td><?php echo htmlspecialchars($consultation['medecin_prenom']) . ' ' . htmlspecialchars($consultation['medecin_nom']); ?></td>
                                        <td>
                                            <?php 
                                            $diagnostic = $consultation['diagnostic'];
                                            echo strlen($diagnostic) > 50 ? substr(htmlspecialchars($diagnostic), 0, 50) . '...' : htmlspecialchars($diagnostic);
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="consultation_details.php?id=<?php echo $consultation['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($role == 'admin' || $role == 'medecin'): ?>
                                                    <a href="modifier_consultation.php?id=<?php echo $consultation['id']; ?>" class="btn btn-success">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Animation des éléments au chargement
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
            
            // Animation des cartes avec délai
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>