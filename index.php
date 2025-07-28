<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = securiser($_POST['email']);
    $password = securiser($_POST['password']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $erreur = "Email ou mot de passe incorrect.";
        }
    } catch (PDOException $e) {
        $erreur = "Erreur de connexion: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion Hospitalière</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInUp 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(16, 185, 129, 0.02));
            pointer-events: none;
        }
        
        .hospital-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .hospital-logo i {
            font-size: 3.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-container h2 {
            color: var(--gray-800);
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.75rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.9375rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 1px solid rgba(203, 213, 225, 0.5);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .btn {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
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
        
        .error {
            color: var(--danger-color);
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 12px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9375rem;
            color: var(--gray-600);
        }
        
        .register-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            .hospital-logo i {
                font-size: 3rem;
            }
            
            .login-container h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="hospital-logo">
            <i class="fas fa-hospital"></i>
        </div>
        <h2>Connexion</h2>
        <?php if ($erreur): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $erreur; ?>
            </div>
        <?php endif; ?>
        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
            <div class="register-link">
                Pas encore de compte? <a href="register.php">S'inscrire</a>
            </div>
        </form>
    </div>
    
    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Effet de focus sur le premier champ
            const emailInput = document.getElementById('email');
            if (emailInput) {
                setTimeout(() => {
                    emailInput.focus();
                }, 300);
            }
        });
    </script>
</body>
</html>