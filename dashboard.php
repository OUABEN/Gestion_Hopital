<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['user_role'];
$nom_complet = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Gestion Hospitalière</title>
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
        
        /* Cards Grid */
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(16, 185, 129, 0.02));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card-content {
            position: relative;
            z-index: 1;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: var(--shadow-lg);
        }
        
        .card:nth-child(2) .card-icon {
            background: linear-gradient(135deg, var(--accent-color), #059669);
        }
        
        .card:nth-child(3) .card-icon {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
        }
        
        .card:nth-child(4) .card-icon {
            background: linear-gradient(135deg, var(--secondary-color), #0891b2);
        }
        
        .card h3 {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        
        .card .number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            line-height: 1;
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
        
        /* Doctor View */
        .doctor-view {
            display: <?php echo $role == 'medecin' ? 'block' : 'none'; ?>;
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
            
            .cards {
                grid-template-columns: 1fr;
            }
            
            .card {
                padding: 1.5rem;
            }
            
            .table-container {
                padding: 1rem;
                overflow-x: auto;
            }
        }
        
        /* Loading Animation */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        .loading {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Smooth entrance animations */
        .card, .table-container, .header {
            animation: slideInUp 0.6s ease-out;
        }
        
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
        
        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }
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
                    <li class="active">
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
                    <li>
                        <a href="consultations.php">
                            <i class="fas fa-stethoscope"></i>
                            <span>Consultations</span>
                        </a>
                    </li>
                        <li>
                            <a href="rapport.php">
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
                <h2>Tableau de Bord</h2>
                <div class="user-info">
                    <span>Bienvenue, <?php echo $nom_complet; ?> (<?php echo ucfirst($role); ?>)</span>
                    <button class="logout-btn" onclick="window.location.href='logout.php'">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </button>
                </div>
            </div>
            
            <!-- Cards -->
            <div class="cards">
                <div class="card">
                    <div class="card-content">
                        <div class="card-header">
                            <div>
                                <h3>Patients</h3>
                                <div class="number">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </div>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-procedures"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-content">
                        <div class="card-header">
                            <div>
                                <h3>Chambres</h3>
                                <div class="number">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM chambres");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </div>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-content">
                        <div class="card-header">
                            <div>
                                <h3>Produits</h3>
                                <div class="number">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM produits");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </div>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-pills"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-content">
                        <div class="card-header">
                            <div>
                                <h3>Réservations</h3>
                                <div class="number">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM reservations_chambres WHERE date_fin >= NOW()");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </div>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vue Médecin -->
            <div class="doctor-view">
                <div class="table-container">
                    <h3>Mes Consultations Aujourd'hui</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Heure</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($role == 'medecin') {
                                $today = date('Y-m-d');
                                $stmt = $pdo->prepare("
                                    SELECT c.id, p.nom, p.prenom, c.date_consultation 
                                    FROM consultations c
                                    JOIN patients p ON c.patient_id = p.id
                                    WHERE c.medecin_id = ? AND DATE(c.date_consultation) = ?
                                    ORDER BY c.date_consultation
                                ");
                                $stmt->execute([$_SESSION['user_id'], $today]);
                                $consultations = $stmt->fetchAll();
                                
                                foreach ($consultations as $consultation) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($consultation['prenom']) . " " . htmlspecialchars($consultation['nom']) . "</td>";
                                    echo "<td>" . date('H:i', strtotime($consultation['date_consultation'])) . "</td>";
                                    echo "<td><span class='status status-available'>Planifiée</span></td>";
                                    echo "<td>
                                            <button class='btn btn-primary' onclick=\"window.location.href='consultation.php?id=" . $consultation['id'] . "'\">
                                                <i class='fas fa-eye'></i> Voir
                                            </button>
                                          </td>";
                                    echo "</tr>";
                                }
                                
                                if (empty($consultations)) {
                                    echo "<tr><td colspan='4'>Aucune consultation prévue aujourd'hui</td></tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Dernières Réservations de Chambres -->
            <div class="table-container">
                <h3>Dernières Réservations de Chambres</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Chambre</th>
                            <th>Date Début</th>
                            <th>Date Fin</th>
                            <th>Statut</th>
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
                            echo "<td>" . htmlspecialchars($reservation['prenom']) . " " . htmlspecialchars($reservation['nom']) . "</td>";
                            echo "<td>" . htmlspecialchars($reservation['numero']) . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($reservation['date_debut'])) . "</td>";
                            echo "<td>" . ($reservation['date_fin'] ? date('d/m/Y H:i', strtotime($reservation['date_fin'])) : '-') . "</td>";
                            echo "<td>";
                            switch ($reservation['statut']) {
                                case 'confirmee':
                                    echo "<span class='status status-available'>Confirmée</span>";
                                    break;
                                case 'annulee':
                                    echo "<span class='status status-occupied'>Annulée</span>";
                                    break;
                                case 'terminee':
                                    echo "<span class='status status-reserved'>Terminée</span>";
                                    break;
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Produits Faible Stock -->
            <div class="table-container">
                <h3>Produits en Faible Stock</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Quantité</th>
                            <th>Seuil Alerte</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("
                            SELECT nom, categorie, quantite, seuil_alerte
                            FROM produits
                            WHERE quantite <= seuil_alerte
                            ORDER BY quantite ASC
                            LIMIT 5
                        ");
                        $produits = $stmt->fetchAll();
                        
                        foreach ($produits as $produit) {
                            $class = $produit['quantite'] == 0 ? 'status-occupied' : 'status-reserved';
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($produit['nom']) . "</td>";
                            echo "<td>" . htmlspecialchars($produit['categorie']) . "</td>";
                            echo "<td><span class='status $class'>" . htmlspecialchars($produit['quantite']) . "</span></td>";
                            echo "<td>" . htmlspecialchars($produit['seuil_alerte']) . "</td>";
                            echo "</tr>";
                        }
                        
                        if (empty($produits)) {
                            echo "<tr><td colspan='4'>Aucun produit en faible stock</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Add smooth scrolling and interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading effect to cards
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Add hover effects to table rows
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
            
            // Fonction pour afficher/masquer la vue médecin
            function toggleDoctorView() {
                const role = "<?php echo $role; ?>";
                if (role === 'medecin') {
                    document.querySelector('.doctor-view').style.display = 'block';
                }
            }
            
            toggleDoctorView();
        });
    </script>
</body>
</html>