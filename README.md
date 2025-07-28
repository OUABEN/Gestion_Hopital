# 🏥 Gestion Hospitalière - PHP & MySQL

Un système complet de gestion hospitalière développé avec **PHP**, **MySQL**, et une interface moderne en **HTML/CSS**. Ce projet permet la gestion des patients, chambres, produits médicaux, consultations, et réservations.

---

## 🔧 Fonctionnalités principales

### 👤 Utilisateurs
- Connexion sécurisée (rôle : **admin**, **médecin**, **staff**)
- Gestion des rôles et des permissions

### 🏥 Patients
- Ajout, modification et consultation des dossiers patients
- Historique des consultations
- Gestion des antécédents médicaux

### 🛏️ Chambres
- Réservation de chambres
- Statuts : `disponible`, `occupée`, `nettoyage`, `maintenance`
- Capacités et tarification

### 💊 Produits médicaux
- Stock et seuils d’alerte
- Réservation et suivi de l'utilisation

### 🩺 Consultations
- Planification des consultations par les médecins
- Diagnostic, prescription et notes

### 📊 Tableau de bord
- Vue d’ensemble dynamique (cartes, tableaux)
- Statistiques clés en temps réel
- Vue spécifique pour les médecins

---

## 🗂️ Structure des fichiers

/gestion_hopital
├── config.php # Connexion à la base de données
├── dashboard.php # Page principale avec statistiques
├── patients.php # Gestion des patients
├── chambres.php # Gestion des chambres
├── produits.php # Produits médicaux
├── consultations.php # Consultations médicales
├── rapport.php # Rapports d'activités                                                                                                                                       
---

## 🧠 Technologies utilisées

| Technologie | Usage |
|------------|-------|
| PHP        | Back-end, logique métier |
| MySQL      | Base de données relationnelle |
| HTML/CSS   | Interface utilisateur (UI) |
| FontAwesome| Icônes UI |
| XAMPP      | Serveur local (Apache + MySQL) |
| PDO        | Connexion sécurisée à la base de données |

---

## ⚙️ Installation locale

1. **Cloner le projet :**
```bash
git clone https://github.com/OUABEN/Gestion_Hopital.git
