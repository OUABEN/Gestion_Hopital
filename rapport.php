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

// Récupérer les statistiques générales
$stats = [
    'patients' => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
    'chambres' => $pdo->query("SELECT COUNT(*) FROM chambres")->fetchColumn(),
    'consultations' => $pdo->query("SELECT COUNT(*) FROM consultations")->fetchColumn(),
    'reservations' => $pdo->query("SELECT COUNT(*) FROM reservations_chambres")->fetchColumn(),
    'produits' => $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn()
];

// Récupérer les statistiques mensuelles
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

// Récupérer les produits les plus utilisés
$top_produits = $pdo->query("
    SELECT p.nom, p.categorie, SUM(rp.quantite) as total_utilise
    FROM reservations_produits rp
    JOIN produits p ON rp.produit_id = p.id
    WHERE rp.statut = 'utilise'
    GROUP BY p.id
    ORDER BY total_utilise DESC
    LIMIT 5
")->fetchAll();

// Récupérer les médecins les plus actifs
$top_medecins = $pdo->query("
    SELECT u.nom, u.prenom, COUNT(c.id) as total_consultations
    FROM consultations c
    JOIN utilisateurs u ON c.medecin_id = u.id
    GROUP BY u.id
    ORDER BY total_consultations DESC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Gestion Hospitalière</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Charts */
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .chart-container h3 {
            color: var(--gray-800);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .chart-container h3::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
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
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--gray-600), var(--gray-800));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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
            
            .chart-container, .table-container {
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
                    <li>
                        <a href="consultations.php">
                            <i class="fas fa-stethoscope"></i>
                            <span>Consultations</span>
                        </a>
                    </li>
                    <li class="active">
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
                <h2>Rapports Statistiques</h2>
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
                                <div class="number"><?php echo $stats['patients']; ?></div>
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
                                <div class="number"><?php echo $stats['chambres']; ?></div>
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
                                <h3>Consultations</h3>
                                <div class="number"><?php echo $stats['consultations']; ?></div>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-content">
                        <div class="card-header">
                            <div>
                                <h3>Réservations</h3>
                                <div class="number"><?php echo $stats['reservations']; ?></div>
                            </div>
                            <div class="card-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques mensuelles -->
            <div class="chart-container">
                <h3><i class="fas fa-chart-line"></i> Statistiques Mensuelles (<?php echo $current_year; ?>)</h3>
                <div class="chart-wrapper">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
            
            <!-- Produits les plus utilisés -->
            <div class="table-container">
                <h3><i class="fas fa-pills"></i> Produits Médicaux les Plus Utilisés</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Quantité Utilisée</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_produits)): ?>
                            <tr>
                                <td colspan="3">Aucune donnée disponible</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($top_produits as $produit): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($produit['categorie']); ?></td>
                                    <td><?php echo $produit['total_utilise']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Médecins les plus actifs -->
            <div class="table-container">
                <h3><i class="fas fa-user-md"></i> Médecins les Plus Actifs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Médecin</th>
                            <th>Nombre de Consultations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_medecins)): ?>
                            <tr>
                                <td colspan="2">Aucune donnée disponible</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($top_medecins as $medecin): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($medecin['prenom'] . ' ' . htmlspecialchars($medecin['nom'])); ?></td>
                                    <td><?php echo $medecin['total_consultations']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Bouton d'export -->
            <div style="text-align: center; margin-top: 2rem;">
                <button class="btn btn-primary" onclick="window.location.href='export_rapport.php'">
                    <i class="fas fa-file-pdf"></i> Exporter le Rapport Complet (PDF)
                </button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique des statistiques mensuelles
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                    datasets: [
                        {
                            label: 'Patients',
                            data: [
                                <?php echo implode(',', array_column($monthly_stats, 'patients')); ?>
                            ],
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Consultations',
                            data: [
                                <?php echo implode(',', array_column($monthly_stats, 'consultations')); ?>
                            ],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Réservations',
                            data: [
                                <?php echo implode(',', array_column($monthly_stats, 'reservations')); ?>
                            ],
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Effets visuels
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
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
        });
    </script>
</body>
</html>