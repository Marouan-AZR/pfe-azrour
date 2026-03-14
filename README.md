# Golden Logistics - Gestion Stock Frigorifique

Application de gestion d'entrepôt frigorifique développée avec Symfony 7.

## Prérequis

- PHP 8.2+
- Composer
- MySQL 8.0+
- Extensions PHP : pdo_mysql, intl, mbstring

## Installation

### 1. Extraire le projet et installer les dépendances

```bash
cd gestion-stock
composer install
```

### 2. Créer la base de données MySQL

```sql
CREATE DATABASE golden_logistics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'golden'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
GRANT ALL PRIVILEGES ON golden_logistics.* TO 'golden'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configurer la connexion base de données

Créer/modifier le fichier `.env.local` :

```env
DATABASE_URL="mysql://golden:votre_mot_de_passe@127.0.0.1:3306/golden_logistics?serverVersion=8.0"
```

### 4. Créer les tables

```bash
php bin/console doctrine:schema:create
```

### 5. Créer les utilisateurs de test

```bash
php bin/console app:create-test-users
```

Cela crée les comptes suivants (mot de passe: `password123`) :
- chef@golden-logistics.ma (Chef de Stock)
- controleur@golden-logistics.ma (Contrôleur)
- directeur@golden-logistics.ma (Directeur)
- patron@golden-logistics.ma (Patron)
- client@golden-logistics.ma (Client)

### 6. Lancer le serveur

```bash
php -S localhost:8000 -t public
```

Accéder à : http://localhost:8000

## Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| Chef de Stock | Accès complet, validation, gestion utilisateurs |
| Contrôleur | Saisie entrées/sorties, consultation |
| Directeur | Validation factures, rapports |
| Patron | Vue stratégique, rapports (lecture seule) |
| Client | Consultation de son propre stock et factures |

## Structure du projet

```
src/
├── Controller/     # Contrôleurs
├── Entity/         # Entités Doctrine
├── Enum/           # Énumérations (rôles, statuts)
├── Form/           # Formulaires
├── Repository/     # Repositories
├── Security/       # Authentification et Voters
└── Service/        # Services métier

templates/
├── dashboard/      # Tableaux de bord par rôle
├── stock_entry/    # Entrées de stock + Bon de réception
├── stock_exit/     # Sorties + Bon de livraison + Fiche de charge
├── invoice/        # Factures + PDF
├── cold_room/      # Chambres froides
├── client/         # Gestion clients
└── ...
```

## Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Mettre à jour la BDD après modification d'entités
php bin/console doctrine:schema:update --force

# Créer un admin manuellement
php bin/console app:create-admin
```
