<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['user_role'];
$nom_complet = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

// Récupérer la liste des patients
$patients = $pdo->query("SELECT id, nom, prenom FROM patients ORDER BY nom, prenom")->fetchAll();

// Récupérer la liste des chambres disponibles
$chambres = $pdo->query("SELECT id, numero, type, prix_journalier FROM chambres WHERE statut = 'disponible' ORDER BY numero")->fetchAll();

// Récupérer la liste des médecins (pour le médecin référent)
$medecins = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'medecin' ORDER BY nom, prenom")->fetchAll();

// Traitement du formulaire de réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = securiser($_POST['patient_id']);
    $chambre_id = securiser($_POST['chambre_id']);
    $date_debut = securiser($_POST['date_debut']);
    $date_fin = !empty($_POST['date_fin']) ? securiser($_POST['date_fin']) : null;
    $medecin_referent = !empty($_POST['medecin_referent']) ? securiser($_POST['medecin_referent']) : null;
    $notes = securiser($_POST['notes']);

    try {
        $pdo->beginTransaction();

        // Insérer la réservation
        $stmt = $pdo->prepare("
            INSERT INTO reservations_chambres 
            (patient_id, chambre_id, date_debut, date_fin, medecin_referent, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $chambre_id, $date_debut, $date_fin, $medecin_referent, $notes]);

        // Mettre à jour le statut de la chambre
        $stmt = $pdo->prepare("UPDATE chambres SET statut = 'occupee' WHERE id = ?");
        $stmt->execute([$chambre_id]);

        $pdo->commit();
        $_SESSION['success'] = "Réservation effectuée avec succès!";
        header("Location: reservations_chambres.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de la réservation: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation de Chambres - Gestion Hospitalière</title>
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
        
        /* Chambre Cards */
        .chambre-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .chambre-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(203, 213, 225, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .chambre-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .chambre-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .chambre-numero {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .chambre-type {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .type-standard {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }
        
        .type-deluxe {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
        }
        
        .type-VIP {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .chambre-prix {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0.5rem 0;
        }
        
        .chambre-prix span {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .select-chambre-btn {
            width: 100%;
            text-align: center;
            margin-top: 1rem;
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
                flex-direction: column;
                gap: 1rem;
            }
            
            .chambre-cards {
                grid-template-columns: 1fr;
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
                    <li class="active">
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
                       
                   
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h2>Réservation de Chambres</h2>
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
            
            <!-- Formulaire de réservation -->
            <div class="form-container">
                <h3><i class="fas fa-calendar-plus"></i> Nouvelle Réservation</h3>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="patient_id">Patient</label>
                                <select class="form-control" id="patient_id" name="patient_id" required>
                                    <option value="">Sélectionner un patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo htmlspecialchars($patient['nom'] . ' ' . htmlspecialchars($patient['prenom'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="medecin_referent">Médecin Référent (optionnel)</label>
                                <select class="form-control" id="medecin_referent" name="medecin_referent">
                                    <option value="">Sélectionner un médecin</option>
                                    <?php foreach ($medecins as $medecin): ?>
                                        <option value="<?php echo $medecin['id']; ?>">
                                            <?php echo htmlspecialchars($medecin['nom'] . ' ' . htmlspecialchars($medecin['prenom'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="date_debut">Date et Heure de Début</label>
                                <input type="datetime-local" class="form-control" id="date_debut" name="date_debut" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="date_fin">Date et Heure de Fin (optionnel)</label>
                                <input type="datetime-local" class="form-control" id="date_fin" name="date_fin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (optionnel)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <h4 style="margin: 1.5rem 0 1rem;">Sélectionner une Chambre</h4>
                    
                    <div class="chambre-cards">
                        <?php foreach ($chambres as $chambre): ?>
                            <div class="chambre-card">
                                <div class="chambre-header">
                                    <div class="chambre-numero"><?php echo htmlspecialchars($chambre['numero']); ?></div>
                                    <div class="chambre-type type-<?php echo strtolower($chambre['type']); ?>">
                                        <?php echo htmlspecialchars($chambre['type']); ?>
                                    </div>
                                </div>
                                <div class="chambre-prix">
                                    <?php echo number_format($chambre['prix_journalier'], 2); ?> <span>MAD/jour</span>
                                </div>
                                <button type="button" class="btn btn-primary select-chambre-btn" 
                                        onclick="selectChambre(<?php echo $chambre['id']; ?>)">
                                    <i class="fas fa-check"></i> Sélectionner
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" id="chambre_id" name="chambre_id" required>
                    
                    <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer la Réservation
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Liste des réservations récentes -->
            <div class="form-container">
                <h3><i class="fas fa-history"></i> Réservations Récentes</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Patient</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Chambre</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Date Début</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Date Fin</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Statut</th>
                             <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("
                            SELECT rc.id, p.nom, p.prenom, ch.numero, rc.date_debut, rc.date_fin, rc.statut
                            FROM reservations_chambres rc
                            JOIN patients p ON rc.patient_id = p.id
                            JOIN chambres ch ON rc.chambre_id = ch.id
                            ORDER BY rc.date_debut DESC
                            LIMIT 5
                        ");
                        $reservations = $stmt->fetchAll();
                        
                        foreach ($reservations as $reservation) {
                            echo "<tr>";
                            echo "<td style='padding: 1rem; border-bottom: 1px solid #e2e8f0;'>" . htmlspecialchars($reservation['prenom']) . " " . htmlspecialchars($reservation['nom']) . "</td>";
                            echo "<td style='padding: 1rem; border-bottom: 1px solid #e2e8f0;'>" . htmlspecialchars($reservation['numero']) . "</td>";
                            echo "<td style='padding: 1rem; border-bottom: 1px solid #e2e8f0;'>" . date('d/m/Y H:i', strtotime($reservation['date_debut'])) . "</td>";
                            echo "<td style='padding: 1rem; border-bottom: 1px solid #e2e8f0;'>" . ($reservation['date_fin'] ? date('d/m/Y H:i', strtotime($reservation['date_fin'])) : '-') . "</td>";
                            echo "<td style='padding: 1rem; border-bottom: 1px solid #e2e8f0;'>";
                            switch ($reservation['statut']) {
                                case 'confirmee':
                                    echo "<span style='padding: 0.5rem 1rem; border-radius: 50px; background: rgba(16, 185, 129, 0.1); color: #047857; border: 1px solid rgba(16, 185, 129, 0.2);'>Confirmée</span>";
                                    break;
                                case 'annulee':
                                    echo "<span style='padding: 0.5rem 1rem; border-radius: 50px; background: rgba(239, 68, 68, 0.1); color: #b91c1c; border: 1px solid rgba(239, 68, 68, 0.2);'>Annulée</span>";
                                    break;
                                case 'terminee':
                                    echo "<span style='padding: 0.5rem 1rem; border-radius: 50px; background: rgba(245, 158, 11, 0.1); color: #92400e; border: 1px solid rgba(245, 158, 11, 0.2);'>Terminée</span>";
                                    break;
                            }
                            echo "</td>";
                            echo "<td style='padding: 1rem; border-bottom: 1px solid #e2e8f0;'>";
                            echo "<a href='reservation_details.php?id=" . htmlspecialchars($reservation['id']) . "' class='btn btn-primary'>Détails</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function selectChambre(chambreId) {
            document.getElementById('chambre_id').value = chambreId;
            
            // Mettre en évidence la chambre sélectionnée
            const cards = document.querySelectorAll('.chambre-card');
            cards.forEach(card => {
                card.style.border = '1px solid rgba(203, 213, 225, 0.3)';
                const btn = card.querySelector('.select-chambre-btn');
                btn.innerHTML = '<i class="fas fa-check"></i> Sélectionner';
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-primary');
            });
            
            const selectedCard = document.querySelector(`.chambre-card button[onclick="selectChambre(${chambreId})"]`).parentElement;
            selectedCard.style.border = '2px solid #6366f1';
            const selectedBtn = selectedCard.querySelector('.select-chambre-btn');
            selectedBtn.innerHTML = '<i class="fas fa-check-circle"></i> Sélectionnée';
            selectedBtn.classList.remove('btn-primary');
            selectedBtn.classList.add('btn-secondary');
            
            // Faire défiler jusqu'au formulaire
            document.getElementById('chambre_id').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Définir la date de début par défaut à maintenant
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            document.getElementById('date_debut').value = `${year}-${month}-${day}T${hours}:${minutes}`;
        });
    </script>
</body>
</html>