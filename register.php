<?php
session_start();
require_once 'config.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$erreurs = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération et sécurisation des données
    $nom = securiser($_POST['nom']);
    $prenom = securiser($_POST['prenom']);
    $email = securiser($_POST['email']);
    $password = securiser($_POST['password']);
    $confirm_password = securiser($_POST['confirm_password']);
    $role = 'staff'; // Par défaut, les nouveaux utilisateurs sont du staff

    // Validation des données
    if (empty($nom)) {
        $erreurs[] = "Le nom est requis.";
    }

    if (empty($prenom)) {
        $erreurs[] = "Le prénom est requis.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "Une adresse email valide est requise.";
    }

    if (empty($password) || strlen($password) < 6) {
        $erreurs[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    if ($password !== $confirm_password) {
        $erreurs[] = "Les mots de passe ne correspondent pas.";
    }

    // Vérifier si l'email existe déjà
    if (empty($erreurs)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erreurs[] = "Cet email est déjà utilisé.";
            }
        } catch (PDOException $e) {
            $erreurs[] = "Erreur de base de données: " . $e->getMessage();
        }
    }

    // Si pas d'erreurs, créer l'utilisateur
    if (empty($erreurs)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $prenom, $email, $hashed_password, $role]);
            
            $success = "Inscription réussie! Vous pouvez maintenant vous connecter.";
            
        } catch (PDOException $e) {
            $erreurs[] = "Erreur lors de l'inscription: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Gestion Hospitalière</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #06b6d4;
            --accent-color: #10b981;
            --danger-color: #ef4444;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --white: #ffffff;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--dark-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }
        
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hospital-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .hospital-logo i {
            font-size: 3.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .register-container h2 {
            color: var(--dark-color);
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 700;
            font-size: 1.75rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 600;
            font-size: 0.9375rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem 1.25rem;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
            background-color: var(--white);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .error {
            color: var(--danger-color);
            background: rgba(239, 68, 68, 0.1);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .error ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .error li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .error li:last-child {
            margin-bottom: 0;
        }
        
        .error li i {
            font-size: 1rem;
        }
        
        .success {
            color: #047857;
            background: rgba(16, 185, 129, 0.1);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid rgba(16, 185, 129, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .success i {
            font-size: 1rem;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9375rem;
            color: var(--gray-600);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .register-container {
                padding: 1.5rem;
            }
            
            .hospital-logo i {
                font-size: 3rem;
            }
            
            .register-container h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="hospital-logo">
            <i class="fas fa-hospital"></i>
        </div>
        <h2>Inscription</h2>
        
        <?php if (!empty($erreurs)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($erreurs as $erreur): ?>
                        <li><i class="fas fa-exclamation-circle"></i> <?php echo $erreur; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom" required value="<?php echo isset($nom) ? htmlspecialchars($nom) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" required value="<?php echo isset($prenom) ? htmlspecialchars($prenom) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe (6 caractères minimum)</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i> S'inscrire
            </button>
            
            <div class="login-link">
                Déjà inscrit? <a href="index.php">Connectez-vous ici</a>
            </div>
        </form>
    </div>
</body>
</html>