<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['user_role'];
$nom_complet = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

// Vérifier si l'ID de réservation est passé en paramètre
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Aucune réservation spécifiée";
    header("Location: reservations_chambres.php");
    exit();
}

$reservation_id = $_GET['id'];

// Récupérer les détails de la réservation
$stmt = $pdo->prepare("
    SELECT rc.*, p.nom AS patient_nom, p.prenom AS patient_prenom, p.date_naissance, p.genre, p.telephone, p.email,
           ch.numero AS chambre_numero, ch.type AS chambre_type, ch.prix_journalier,
           u.nom AS medecin_nom, u.prenom AS medecin_prenom
    FROM reservations_chambres rc
    JOIN patients p ON rc.patient_id = p.id
    JOIN chambres ch ON rc.chambre_id = ch.id
    LEFT JOIN utilisateurs u ON rc.medecin_referent = u.id
    WHERE rc.id = ?
");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    $_SESSION['error'] = "Réservation introuvable";
    header("Location: reservations_chambres.php");
    exit();
}

// Traitement de l'annulation de la réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler'])) {
    try {
        $pdo->beginTransaction();

        // Mettre à jour le statut de la réservation
        $stmt = $pdo->prepare("UPDATE reservations_chambres SET statut = 'annulee' WHERE id = ?");
        $stmt->execute([$reservation_id]);

        // Remettre la chambre en disponible
        $stmt = $pdo->prepare("UPDATE chambres SET statut = 'disponible' WHERE id = ?");
        $stmt->execute([$reservation['chambre_id']]);

        $pdo->commit();
        $_SESSION['success'] = "Réservation annulée avec succès";
        header("Location: reservation_details.php?id=" . $reservation_id);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de l'annulation: " . $e->getMessage();
        header("Location: reservation_details.php?id=" . $reservation_id);
        exit();
    }
}

// Traitement de la modification des dates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_dates'])) {
    $date_debut = securiser($_POST['date_debut']);
    $date_fin = !empty($_POST['date_fin']) ? securiser($_POST['date_fin']) : null;

    try {
        $stmt = $pdo->prepare("
            UPDATE reservations_chambres 
            SET date_debut = ?, date_fin = ?
            WHERE id = ?
        ");
        $stmt->execute([$date_debut, $date_fin, $reservation_id]);
        
        $_SESSION['success'] = "Dates de réservation mises à jour avec succès";
        header("Location: reservation_details.php?id=" . $reservation_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        header("Location: reservation_details.php?id=" . $reservation_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Réservation - Gestion Hospitalière</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Utilisez le même CSS que dans votre dashboard.php */
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-card {
            background: rgba(248, 250, 252, 0.8);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .info-card h4 {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        
        .info-card p {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status-confirmee {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-confirmee::before {
            background: #10b981;
        }
        
        .status-annulee {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-annulee::before {
            background: #ef4444;
        }
        
        .status-terminee {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-terminee::before {
            background: #f59e0b;
        }
        
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
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
            box-shadow: var(--shadow);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-col {
            flex: 1;
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
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 1rem;
            }
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
                       
                    <?php if ($role == 'medecin' || $role == 'admin'): ?>
                        <li>
                            <a href="consultations.php">
                                <i class="fas fa-stethoscope"></i>
                                <span>Consultations</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($role == 'admin'): ?>
                        <li>
                            <a href="utilisateurs.php">
                                <i class="fas fa-users-cog"></i>
                                <span>Utilisateurs</span>
                            </a>
                        </li>
                        <li>
                            <a href="rapports.php">
                                <i class="fas fa-chart-bar"></i>
                                <span>Rapports</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h2>Détails de la Réservation</h2>
                <div class="user-info">
                    <span>Bienvenue, <?php echo $nom_complet; ?> (<?php echo ucfirst($role); ?>)</span>
                    <button class="logout-btn" onclick="window.location.href='logout.php'">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </button>
                </div>
            </div>
            
            <!-- Messages d'alerte -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Informations sur la réservation -->
            <div class="form-container">
                <h3><i class="fas fa-info-circle"></i> Informations sur la Réservation</h3>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h4>Patient</h4>
                        <p><?php echo htmlspecialchars($reservation['patient_prenom'] . ' ' . htmlspecialchars($reservation['patient_nom'])); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Chambre</h4>
                        <p>N° <?php echo htmlspecialchars($reservation['chambre_numero']); ?> (<?php echo htmlspecialchars($reservation['chambre_type']); ?>)</p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Statut</h4>
                        <?php
                        $status_class = 'status-' . $reservation['statut'];
                        echo "<span class='status-badge $status_class'>" . ucfirst($reservation['statut']) . "</span>";
                        ?>
                    </div>
                    
                    <div class="info-card">
                        <h4>Prix Journalier</h4>
                        <p><?php echo number_format($reservation['prix_journalier'], 2); ?> MAD</p>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h4>Date de Début</h4>
                        <p><?php echo date('d/m/Y H:i', strtotime($reservation['date_debut'])); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Date de Fin</h4>
                        <p><?php echo $reservation['date_fin'] ? date('d/m/Y H:i', strtotime($reservation['date_fin'])) : 'Non spécifiée'; ?></p>
                    </div>
                    
                    <?php if ($reservation['medecin_nom']): ?>
                    <div class="info-card">
                        <h4>Médecin Référent</h4>
                        <p><?php echo htmlspecialchars($reservation['medecin_prenom'] . ' ' . htmlspecialchars($reservation['medecin_nom'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Notes -->
                <?php if ($reservation['notes']): ?>
                <div class="info-card" style="margin-top: 1.5rem;">
                    <h4>Notes</h4>
                    <p><?php echo nl2br(htmlspecialchars($reservation['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Actions -->
            <div class="form-container">
                <h3><i class="fas fa-cogs"></i> Actions</h3>
                
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <!-- Bouton Retour -->
                    <button class="btn btn-secondary" onclick="window.location.href='reservations_chambres.php'">
                        <i class="fas fa-arrow-left"></i> Retour aux Réservations
                    </button>
                    
                    <!-- Formulaire de modification des dates -->
                    <form method="POST" style="flex: 1; min-width: 300px;">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="date_debut">Nouvelle Date de Début</label>
                                    <input type="datetime-local" class="form-control" id="date_debut" name="date_debut" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($reservation['date_debut'])); ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="date_fin">Nouvelle Date de Fin (optionnel)</label>
                                    <input type="datetime-local" class="form-control" id="date_fin" name="date_fin" 
                                           value="<?php echo $reservation['date_fin'] ? date('Y-m-d\TH:i', strtotime($reservation['date_fin'])) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                            <button type="submit" name="modifier_dates" class="btn btn-primary">
                                <i class="fas fa-calendar-check"></i> Modifier les Dates
                            </button>
                              <?php if ($reservation['statut'] != 'annulee'): ?>
                    <form method="POST" style="margin-top: 1.5rem;">
                        <button type="submit" name="annuler" class="btn btn-danger" 
                                onclick="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ?');">
                            <i class="fas fa-times-circle"></i> Annuler la Réservation
                        </button>
                    </form>
                    <?php endif; ?>
                        </div>

                  
                    </form>
                    
                    
                </div>
            </div>
            
            <!-- Informations sur le patient -->
            <div class="form-container">
                <h3><i class="fas fa-user-injured"></i> Informations sur le Patient</h3>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h4>Nom Complet</h4>
                        <p><?php echo htmlspecialchars($reservation['patient_prenom'] . ' ' . htmlspecialchars($reservation['patient_nom'])); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Date de Naissance</h4>
                        <p><?php echo date('d/m/Y', strtotime($reservation['date_naissance'])); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Genre</h4>
                        <p>
                            <?php 
                            switch ($reservation['genre']) {
                                case 'M': echo 'Masculin'; break;
                                case 'F': echo 'Féminin'; break;
                                case 'Autre': echo 'Autre'; break;
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Téléphone</h4>
                        <p><?php echo $reservation['telephone'] ? htmlspecialchars($reservation['telephone']) : 'Non renseigné'; ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h4>Email</h4>
                        <p><?php echo $reservation['email'] ? htmlspecialchars($reservation['email']) : 'Non renseigné'; ?></p>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <button class="btn btn-primary" 
                            onclick="window.location.href='patient_details.php?id=<?php echo $reservation['patient_id']; ?>'">
                        <i class="fas fa-user"></i> Voir le dossier complet du patient
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Définir la date minimale pour les champs de date
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            // Définir la date minimale pour la date de début à maintenant
            document.getElementById('date_debut').min = `${year}-${month}-${day}T${hours}:${minutes}`;
            
            // Mettre à jour la date minimale pour la date de fin lorsqu'on change la date de début
            document.getElementById('date_debut').addEventListener('change', function() {
                document.getElementById('date_fin').min = this.value;
            });
        });
    </script>
</body>
</html>