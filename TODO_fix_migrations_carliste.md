# Fix migrations + carliste (fiches_decharge)

## Constats
- Doctrine error: unknown column `fiches_decharge.carliste`.
- La migration attendue `Version20260610130000` ajoute bien `carliste`.
- Mais `doctrine:migrations:migrate` échoue sur une migration précédente: `Version20260507165821` (Integrity constraint violation 1451).

## Étapes
1. Corriger `migrations/Version20260507165821.php` pour éviter les `DROP TABLE clients` alors qu’il existe des FK vers `clients`.
2. Relancer `php bin/console doctrine:migrations:migrate --no-interaction`.
3. Vérifier ensuite que `carliste` existe bien dans la table `fiches_decharge`.
4. Relancer l’action qui déclenche l’erreur (exitIndex / OperationController).

