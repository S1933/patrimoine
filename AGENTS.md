# AGENTS.md

Instructions pour les agents travaillant dans ce dépôt.

## Objectif du projet

Patrimoine est une application personnelle de suivi et de valorisation d'actifs.
Elle agrège des positions financières et non financières, récupère des prix
externes, calcule les performances, crée des snapshots quotidiens et fournit un
assistant IA contextualisé par le portefeuille.

Ce produit affiche des données indicatives et ne doit jamais formuler de conseil
financier personnalisé ni de recommandation d'achat ou de vente.

## Priorités

1. Préserver l'isolation des données entre utilisateurs.
2. Préserver l'exactitude et la traçabilité des valorisations.
3. Ne jamais exposer de secrets, clés API ou données personnelles.
4. Maintenir les contrats entre l'API Laravel et les types TypeScript.
5. Préférer une modification ciblée, testée et cohérente avec l'architecture.

## Structure

```text
backend/
  app/Domain/             Objets et contrats métier sans dépendance HTTP
  app/Application/        Cas d'usage: pricing, dashboard, snapshots, IA
  app/Infrastructure/     Providers externes et adaptateurs
  app/Http/               Contrôleurs, validation et resources API
  app/Models/             Modèles Eloquent
  app/Policies/           Autorisations par propriétaire
  app/Support/            Commandes et jobs
  database/               Migrations, factories et seeders
  routes/api.php          API versionnée sous /api/v1
  routes/console.php      Planification des prix et snapshots
  tests/                  Tests Pest unitaires et fonctionnels
frontend/
  src/app/                Routes Next.js App Router
  src/app/(app)/          Écrans authentifiés et assistant latéral
  src/lib/api/            Client HTTP et modules API
  src/lib/types.ts        Contrats TypeScript partagés
docker/                   Configuration Nginx et PHP
ops/backup.sh             Sauvegarde PostgreSQL
```

Le backend suit une séparation `Domain → Application → Infrastructure/HTTP`.
Ne placez pas la logique métier dans les contrôleurs ou les composants React.

## Environnement

Le chemin normal de développement utilise Docker Compose.

```bash
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.local.example frontend/.env.local
docker compose build
docker compose run --rm app composer install
make up
make key
make migrate-seed
```

Le montage `./backend:/var/www/html` masque les fichiers installés dans l'image:
si `backend/vendor` manque, exécuter explicitement `composer install`.

Fichiers de configuration:

- `.env`: variables interpolées par Docker Compose.
- `backend/.env`: configuration Laravel.
- `frontend/.env.local`: configuration Next.js hors Docker.
- `docker-compose.yml`: force actuellement certaines variables frontend.

Ne lisez, n'affichez, ne copiez et ne modifiez jamais les valeurs secrètes des
fichiers `.env` sauf demande explicite. Utilisez uniquement les fichiers
`.env.example` pour documenter une variable.

## Commandes

```bash
make up
make down
make logs
make ps
make migrate
make seed
make migrate-seed
make test
make test-unit
make test-feature
```

Vérifications ciblées:

```bash
docker compose exec app php artisan test tests/Feature/InvestmentCrudTest.php
docker compose exec app php artisan test --filter="nom du test"
docker compose exec app ./vendor/bin/pint --test
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
docker compose config --quiet
sh -n ops/backup.sh
```

`make fresh` et `make migrate-seed` détruisent les données locales. Ne les
exécutez pas sans nécessité explicite.

## Règles backend

- PHP 8.4 dans Docker, Laravel 13, format Laravel Pint.
- Utiliser les types, propriétés `readonly`, constructeurs injectés et classes
  `final` lorsque le code voisin suit déjà ce modèle.
- Utiliser des `FormRequest` pour la validation et la normalisation des entrées.
- Utiliser des `JsonResource` pour stabiliser les réponses API.
- Garder les contrôleurs minces; placer les calculs dans `Application`.
- Utiliser les transactions pour les écritures liées devant rester atomiques.
- Charger explicitement les relations nécessaires pour éviter les requêtes N+1.
- Les identifiants métier principaux sont des UUID via `HasUuid`.
- Les suppressions d'investissements sont logiques (`SoftDeletes`).
- Les dates persistées sont timezone-aware lorsque le schéma le permet.
- Les montants persistés utilisent des décimaux; les conversions en `float`
  appartiennent aux frontières de calcul ou de sérialisation.

### Isolation utilisateur

Toute lecture ou agrégation d'investissements doit être limitée à l'utilisateur:

```php
Investment::forUser($userId)
```

Toute opération sur une ressource liée par route doit appeler une policy ou
garantir une vérification propriétaire équivalente. Ajouter un test démontrant
qu'un autre utilisateur reçoit `403` ou ne voit aucune donnée.

### Valorisation

La logique de valeur est sensible et utilisée par:

- `Application/Valuation/InvestmentValuation.php`
- `Application/Dashboard/DashboardCalculator.php`
- `Application/Snapshots/SnapshotService.php`
- `Http/Resources/Api/V1/InvestmentResource.php`
- `Application/AI/PortfolioContext.php`

Une modification du calcul doit vérifier toutes ces projections. Éviter de
dupliquer de nouvelles règles de valorisation; centraliser dans
`InvestmentValuation` puis adapter les consommateurs.

Invariants actuels:

- seuls les investissements `active` alimentent dashboard et snapshots;
- une valeur manuelle prévaut sur un prix de marché;
- un prix externe valide est historisé dans `asset_prices`;
- chaque tentative est tracée dans `api_sync_logs`;
- si les providers échouent, le dernier prix connu reste utilisable;
- les snapshots sont idempotents par utilisateur et date;
- Livret A et LDDS appliquent actuellement un rendement fixe dans
  `InvestmentValuation`.

### Providers de prix

Tous les providers implémentent `Domain\Pricing\PriceProvider` et renvoient un
`PriceResult` normalisé. Une intégration externe doit:

- lire sa clé via `config/services.php`, jamais directement depuis `env()` dans
  la logique métier;
- implémenter `supports()`, `code()` et `fetch()`;
- gérer timeout, réponse invalide, rate limit et absence de clé;
- préserver le fallback vers les providers suivants et le dernier prix connu;
- conserver uniquement les données brutes utiles dans `raw_payload`;
- utiliser `Http::fake()` dans les tests, sans appel réseau réel;
- être enregistrée dans le service provider, la factory et les seeders si
  nécessaire.

Si l'ordre des providers change, mettre à jour les tests de factory et la
documentation.

### API

- Toutes les routes applicatives restent sous `/api/v1`.
- Les routes métier sont protégées par `auth:sanctum`.
- Les mutations utilisent la protection CSRF de la SPA stateful.
- Conserver la forme Laravel Resource `{ "data": ... }` et la pagination
  `{ data, links, meta }`.
- Une évolution de payload doit modifier ensemble validation, resource, tests,
  client frontend et `frontend/src/lib/types.ts`.
- Le chat utilise SSE. Ne pas réactiver le buffering Nginx sur `/api/v1/chat`
  et conserver `X-Accel-Buffering: no`.

## Règles frontend

- Next.js 14 App Router, React 18, TypeScript strict.
- Utiliser l'alias `@/` pour les imports depuis `src`.
- Ajouter `"use client"` uniquement aux composants nécessitant état, effets,
  contexte navigateur ou TanStack Query.
- Centraliser les appels HTTP dans `src/lib/api`; ne pas appeler `fetch`
  directement depuis les pages sauf besoin de streaming non couvert.
- Utiliser le client partagé pour conserver cookies, CSRF et redirection `401`.
- Définir ou mettre à jour les contrats dans `src/lib/types.ts`.
- Utiliser TanStack Query pour l'état serveur et invalider les clés concernées
  après mutation.
- Réutiliser `extractError`, `formatCurrency`, `formatPercent`, `formatDate` et
  `cn` au lieu de recréer ces comportements.
- Respecter le style existant Tailwind, les états clair/sombre et les layouts
  responsives.
- Le texte utilisateur est actuellement en français.
- Ne jamais exposer une clé serveur via une variable `NEXT_PUBLIC_*`.

### Authentification

L'authentification est une session Sanctum same-origin derrière Nginx.
`AUTH_BYPASS` et `NEXT_PUBLIC_AUTH_BYPASS` sont réservés au développement local
et doivent rester cohérents. Ne jamais les activer dans une configuration
exposée ou de production.

Le cookie utilisé par le middleware frontend et le mécanisme réel de session
doivent rester alignés. Toute modification d'authentification nécessite au
minimum les tests de login, logout, utilisateur courant, accès non authentifié
et isolation multi-utilisateur.

## Base de données

- Toute modification de schéma passe par une nouvelle migration; ne réécrivez
  pas une migration déjà partagée sauf demande explicite.
- Fournir un `down()` cohérent.
- Ajouter index et contraintes étrangères pour les nouveaux accès fréquents.
- PostgreSQL est la cible de production; les tests utilisent SQLite en mémoire.
  Éviter les fonctionnalités SQL impossibles à tester ou prévoir une stratégie
  de compatibilité explicite.
- Mettre à jour modèle, casts, `$fillable`, factory, seeder et types frontend
  selon le changement.
- Ne jamais stocker une clé API dans `price_providers`; seul le nom de variable
  d'environnement peut y être enregistré.
- `Investment.notes` et `User.opencode_api_key` doivent rester chiffrés au repos
  et masqués lors de la sérialisation.

## Tests

Les tests backend utilisent Pest:

- `tests/Feature`: requêtes HTTP, auth, policies, persistance et cas d'usage;
- `tests/Unit`: providers, factories et logique isolée;
- SQLite `:memory:`, cache `array`, queue `sync`;
- `RefreshDatabase` est appliqué aux tests Feature.

Pour chaque correction de bug, ajouter d'abord ou avec le correctif un test de
régression reproduisant le comportement. Tester les cas d'échec importants:
utilisateur tiers, entrée invalide, provider indisponible, absence de prix,
réponse externe malformée et état vide.

Ne faites aucun appel réseau réel dans les tests. Figez le temps avec Carbon
lorsqu'un calcul dépend d'une date, puis restaurez-le dans un `finally`.

Le frontend ne possède actuellement pas de suite de tests dédiée. Au minimum,
valider les changements frontend avec TypeScript/Next via `npm run build` et
avec le lint lorsqu'il est fonctionnel.

## Critères de fin

Avant de terminer:

1. Vérifier le diff et ne pas écraser de modifications sans rapport.
2. Exécuter les tests les plus proches du changement.
3. Exécuter la suite backend pour une modification métier transversale.
4. Exécuter le build frontend si un contrat ou composant TypeScript change.
5. Vérifier migrations, seeders et exemples d'environnement si la
   configuration change.
6. Signaler clairement les vérifications non exécutées et leur raison.

## Git et limites du dépôt

Vérifier le véritable worktree avant toute commande Git. Dans certains
environnements, `frontend/` peut apparaître comme un dépôt Git imbriqué alors
que la racine de travail n'en est pas un. Ne pas supposer la racine Git, ne pas
réinitialiser de fichiers et ne pas supprimer les modifications existantes.
