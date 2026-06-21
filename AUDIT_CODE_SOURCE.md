# Audit code source - patrimoine

Date de l'audit : 21 juin 2026  
Dépôt audité : `S1933/patrimoine`, worktree local `/Users/jp/Projects/patrimoine`  
Révision observée : `bdc9028`

## 1. Résumé exécutif

### État général

Le projet a une architecture lisible et adaptée à sa taille : API Laravel séparée en couches, frontend Next.js centralisant les appels HTTP, policies sur les investissements, validation par `FormRequest`, ressources JSON, historique des prix et tests backend substantiels.

Le niveau de risque global est **Élevé**. Le principal risque n'est pas une injection ou un IDOR évident, mais l'exactitude financière : les agrégats additionnent des montants de devises différentes sans conversion et les snapshots n'utilisent pas la même règle de valorisation que le dashboard. À cela s'ajoutent un contournement d'authentification trop facile à exposer, des services Docker publiés sans protection et des dépendances frontend vulnérables.

### 5 constats principaux

1. **Les devises ne sont pas normalisées avant agrégation.** Le dashboard et les snapshots étiquettent les totaux avec la devise utilisateur tout en additionnant les valeurs propres à chaque actif (`backend/app/Application/Dashboard/DashboardCalculator.php:33-55`, `backend/app/Application/Snapshots/SnapshotService.php:54-69`).
2. **Les snapshots divergent de la valorisation affichée.** Ils réimplémentent le calcul et ignorent notamment le rendement Livret A/LDDS (`backend/app/Application/Snapshots/SnapshotService.php:76-88` contre `backend/app/Application/Valuation/InvestmentValuation.php:13-27`).
3. **Le mode d'authentification local est dangereux hors poste isolé.** Le frontend Docker force le bypass et le middleware backend connecte automatiquement le premier utilisateur (`docker-compose.yml:125-127`, `backend/app/Http/Middleware/AutoLogin.php:13-17`).
4. **PostgreSQL, Redis, Mailpit et le serveur Next de développement sont publiés sur toutes les interfaces.** Redis n'a pas d'authentification et les mots de passe d'exemple sont faibles (`docker-compose.yml:55-72`, `docker-compose.yml:123-136`).
5. **L'audit npm remonte 7 vulnérabilités connues**, dont 6 élevées, affectant notamment Next.js 14.2.35 et la chaîne `react-simple-maps`/D3 (`frontend/package.json:17-20`).

### 5 actions prioritaires

1. Introduire une conversion explicite vers `User.base_currency` avant tout total, coût, P/L, snapshot et contexte IA.
2. Injecter `InvestmentValuation` dans `SnapshotService` et supprimer son calcul dupliqué.
3. Rendre les bypass opt-in, impossibles en production, et ajouter un test de démarrage refusant `AUTH_BYPASS=true` hors `local/testing`.
4. Supprimer les publications directes de PostgreSQL/Redis/Mailpit ou les lier à `127.0.0.1`; créer une configuration de production séparée.
5. Mettre à niveau Next.js/PostCSS et remplacer ou mettre à niveau la dépendance D3 vulnérable, puis relancer build et audit.

## 2. Commandes exécutées

| Commande | Résultat | Commentaire | Bloquant |
|---|---|---|---|
| `git rev-parse --show-toplevel` | Succès | Worktree racine confirmé | Non |
| `git status --short` | Succès | Propre avant création du rapport | Non |
| `git log --oneline -200` | Succès | Historique limité à 2 commits d'import | Non |
| `git log --stat --since="6 months ago"` | Succès | Pas assez d'historique pour une analyse de churn fiable | Non |
| Inventaire `find`, `rg --files`, `wc -l` | Succès | 194 fichiers, environ 12 621 lignes applicatives | Non |
| `docker compose ps` | Succès | 7 services actifs | Non |
| `docker compose config --quiet` | Succès | Syntaxe Compose valide | Non |
| `composer validate --strict --no-check-publish` | Succès | `composer.json` valide; avertissement de cache local non inscriptible | Non |
| `composer audit --locked --no-interaction` | Succès après accès réseau | Aucun advisory Composer connu | Non |
| `docker compose exec app ./vendor/bin/pint --test` | Échec | 28 fichiers avec écarts de style | Non |
| `docker compose exec app php artisan test` | Échec | 19 tests passés, 56 échecs dus à une base SQLite fichier corrompue | Oui pour le chemin standard |
| Tests avec SQLite `:memory:` forcé | Échec partiel | 73 passés, 2 échecs car le bypass d'authentification local restait actif | Oui pour le chemin standard |
| Tests avec SQLite `:memory:` et `AUTH_BYPASS=false` | Succès | 75 tests, 319 assertions | Non |
| `npm run lint` | Succès | Aucun avertissement ESLint | Non |
| `./node_modules/.bin/tsc --noEmit` | Succès | TypeScript strict compile | Non |
| `npm run build` | Succès | Build Next.js de production réussi | Non |
| `npm audit --omit=dev` | Échec sécurité | 7 vulnérabilités : 6 élevées, 1 modérée | Oui avant exposition |
| `sh -n ops/backup.sh` | Succès | Syntaxe shell valide | Non |
| Recherche `TODO/FIXME/debug/env/secrets/raw SQL/any/...` | Succès | Aucun secret réel affiché; findings intégrés ci-dessous | Non |

Outils absents et non installés : PHPStan/Larastan, Rector, Knip, Madge, Depcheck. Aucun script frontend `test` ou `typecheck` n'est déclaré dans `package.json`.

## 3. Architecture observée

### Backend

- `Domain` porte les contrats de pricing et objets de résultat.
- `Application` contient dashboard, valorisation, snapshots, stratégie et assistant IA.
- `Infrastructure` implémente OpenCode et les providers CoinGecko, Gold API, Twelve Data, Finnhub, Yahoo Finance et OpenFIGI.
- `Http` contient contrôleurs minces, `FormRequest` et `JsonResource`.
- Les entités principales sont `User`, `Investment`, `AssetPrice`, `PortfolioSnapshot`, `InvestmentSnapshot`, `PriceProvider`, `ApiSyncLog` et `InvestmentStrategyAllocation`.
- Les UUID sont utilisés pour les entités sensibles; les investissements sont supprimés logiquement.

### Frontend

- Next.js App Router avec groupes `(auth)` et `(app)`.
- Pages principales : login, dashboard, liste/détail/formulaire d'investissements et paramètres.
- TanStack Query gère l'état serveur.
- Le client HTTP partagé gère cookies, CSRF et redirection 401 (`frontend/src/lib/api/client.ts:27-109`).
- L'assistant utilise un flux SSE traité côté navigateur.

### Infrastructure

- Nginx maintient une origine unique et route `/api`/`sanctum` vers PHP-FPM.
- PostgreSQL stocke les données métier et sessions.
- Redis sert le cache et la queue.
- Un même conteneur lance scheduler et worker.
- Le frontend Compose utilise la cible de développement, tandis qu'un runner Next.js de production existe dans le Dockerfile mais n'est pas utilisé.

### Flux métier critiques

1. **Création/modification d'actif** : validation → persistance → éventuel appel pricing synchrone → ressource calculée.
2. **Pricing** : factory statique → chaîne de providers → dernier prix connu → persistance `asset_prices` + `api_sync_logs`.
3. **Dashboard** : chargement des actifs actifs → valorisation → agrégations.
4. **Snapshot** : chargement des actifs actifs → calcul local du montant → upsert portefeuille et positions.
5. **Exports** : lecture limitée à l'utilisateur → JSON ou CSV.
6. **Assistant IA** : agrégats du portefeuille → prompt système → OpenCode en streaming.

## 4. Findings prioritaires

| ID | Sévérité | Catégorie | Fichier / zone | Problème | Impact | Recommandation | Effort |
|---|---|---|---|---|---|---|---|
| F001 | Élevée | Finance | `DashboardCalculator.php:33-55` | Addition de montants sans conversion vers la devise de référence | Totaux, allocation et P/L faux dès qu'un portefeuille est multidevise | Créer un service FX daté et convertir valeur et coût avant agrégation | L |
| F002 | Élevée | Finance | `SnapshotService.php:54-69` | Le total est étiqueté en devise utilisateur sans conversion | Historique durablement incorrect et graphiques trompeurs | Persister les montants normalisés et le taux/source FX utilisés | L |
| F003 | Élevée | Architecture | `SnapshotService.php:76-88` | Calcul de valorisation dupliqué, sans règle Livret A/LDDS | Dashboard, détail et snapshot divergent | Injecter `InvestmentValuation` | S |
| F004 | Élevée | Sécurité | `AutoLogin.php:13-17` | `AUTH_BYPASS` connecte le premier utilisateur sur toute requête API | Compromission complète si activé sur une instance accessible | Lire via `config()`, limiter à `local/testing`, refuser le boot sinon | S |
| F005 | Élevée | Sécurité | `docker-compose.yml:125-127` | `NEXT_PUBLIC_AUTH_BYPASS` est forcé à `true` | Protection frontend désactivée par défaut; configuration incohérente | Valeur `${NEXT_PUBLIC_AUTH_BYPASS:-false}` et profil dev explicite | S |
| F006 | Élevée | DevOps | `docker-compose.yml:55-72` | PostgreSQL et Redis exposés sur `0.0.0.0`; Redis sans mot de passe | Accès aux données/cache depuis le réseau hôte | Retirer `ports` ou lier à `127.0.0.1`; activer auth Redis si nécessaire | S |
| F007 | Élevée | Dépendances | `frontend/package.json:17-20` | 7 vulnérabilités npm, dont Next.js et D3 | DoS, cache poisoning, XSS/SSRF selon fonctionnalités et exposition | Mise à niveau testée; ne pas utiliser `audit fix --force` aveuglément | M |
| F008 | Élevée | Secrets | `backend/.env.example:3` | Clé Laravel réelle et réutilisable dans un fichier exemple | Déploiements copiés partageant clé de chiffrement/session | Remplacer par placeholder et toujours générer une clé unique | S |
| F009 | Élevée | Traçabilité | `PriceResult.php:36-49`, `FetchInvestmentPrice.php:43-59` | Le dernier prix connu est repersisté avec `fetched_at=now()` | Un prix ancien paraît frais; âge réel perdu | Ne pas créer de nouveau prix, ou conserver `source_fetched_at` | M |
| F010 | Élevée | Traçabilité | `FallbackChainProvider.php:43-86`, `FetchInvestmentPrice.php:62-70` | Une seule trace est écrite pour toute la chaîne de providers | Impossible d'auditer précisément chaque tentative/fallback | Émettre un résultat de tentative par provider et le journaliser | M |
| F011 | Élevée | Contrat API | `investments.ts:25`, `RefreshPriceController.php:26-36` | Type TS de refresh incompatible avec `{data, meta}` réel | Le frontend compile mais modélise mal la réponse | Définir `{ data: Investment; meta: RefreshPriceMeta }` | S |
| F012 | Élevée | Données | `backend/patrimoine` | Base SQLite binaire corrompue suivie par Git | Pollution du dépôt et échec de la commande de test standard | Retirer du suivi et ignorer les bases locales | S |
| F013 | Moyenne | Tests | `docker-compose.yml:13-18`, `phpunit.xml:20-31` | Variables Compose prennent le dessus sur PHPUnit | `make test` échoue ou peut viser une mauvaise base | Forcer les variables de test dans Make/Compose avec `-e` | S |
| F014 | Moyenne | Sécurité | `routes/api.php:19-22` | Inscription publique sur une application personnelle | Création de comptes non désirés si instance exposée | Désactiver par défaut ou protéger par invitation/config | S |
| F015 | Moyenne | Sécurité | `config/cors.php:18-32` | Les domaines Sanctum sans schéma sont réutilisés comme origines CORS | Frontend direct `:3000` potentiellement bloqué ou mal configuré | Séparer `CORS_ALLOWED_ORIGINS` avec URLs complètes | S |
| F016 | Moyenne | DevOps | `docker-compose.yml:87-89` | Scheduler en arrière-plan et worker au premier plan dans un conteneur | Mort silencieuse du scheduler; supervision imprécise | Séparer `scheduler` et `worker` en deux services | S |
| F017 | Moyenne | Idempotence | `create_investment_snapshots_table.php:26-27` | Aucun unique `(investment_id,snapshot_date)` | Doublons possibles en exécution concurrente | Ajouter contrainte unique et gérer conflit | S |
| F018 | Moyenne | Validation | `StoreInvestmentRequest.php:25-30` | Sommes pays/secteurs et doublons non validés | Allocation supérieure à 100 %, dashboard incohérent | Validation `after()` : somme 100 ± tolérance et clés distinctes | S |
| F019 | Moyenne | Finance | `InvestmentValuation.php:9-11`, `:48-55` | Taux 1,5 % codé en dur et intérêt simple journalier | Valeur réglementaire historiquement inexacte | Versionner les taux par période et documenter la méthode | M |
| F020 | Moyenne | Finance | `DashboardCalculator.php:327-329` | `purchase_currency` est ignorée dans le coût | P/L faux si prix d'achat et valorisation ont des devises différentes | Convertir le coût depuis `purchase_currency` | M |
| F021 | Moyenne | Données | `YahooFinancePriceProvider.php:97-106`, `FinnhubPriceProvider.php:106-110` | Payloads complets de graphiques/candles historisés | Croissance rapide de PostgreSQL et sauvegardes | Ne garder que données résolues et métriques utiles | S |
| F022 | Moyenne | Architecture | `PriceProviderFactory.php:42-49`, `PriceProviderSeeder.php:12-83` | Factory ignore `is_active`, `priority`, `base_url` stockés en base | Configuration provider décorative et comportement surprenant | Choisir une source de vérité : config codée ou registre DB effectif | M |
| F023 | Moyenne | Performance | `InvestmentController.php:58-70`, `:81-89` | Appels externes synchrones pendant création/mise à jour | Latence, timeout, resoumission utilisateur | Persister puis dispatcher un job; retourner état `pending` | M |
| F024 | Moyenne | Résilience | `SyncPricesCommand.php:27-47` | Chargement complet et traitement séquentiel de tous les actifs | Temps d'exécution et mémoire augmentent avec le portefeuille | `chunkById()` et jobs unitaires avec rate limiting | M |
| F025 | Moyenne | Frontend | `investments/new/page.tsx:81-99` | `setState` pendant le rendu pour hydrater le formulaire | Rendus supplémentaires et comportement fragile en Strict Mode | Utiliser `useEffect` ou `reset` de formulaire | S |
| F026 | Moyenne | Frontend | `dashboard/page.tsx:35-39`, `:94-171` | Erreurs de requêtes non distinguées des états vides | Une panne API est présentée comme absence de données | Afficher états loading/error par bloc ou requête agrégée | S |
| F027 | Moyenne | Disponibilité | `dashboard/page.tsx:32`, `:190` | Carte dépend d'un JSON CDN au runtime | Carte indisponible hors ligne; dépendance tierce non maîtrisée | Héberger l'asset statique localement | S |
| F028 | Moyenne | Sécurité | `docker/nginx/default.conf:1-60` | Pas d'en-têtes de sécurité HTTP | Défense en profondeur limitée | Ajouter CSP adaptée, nosniff, frame-ancestors et politique referrer | S |
| F029 | Moyenne | Tests | `frontend/package.json:5-9` | Aucun test frontend | Régressions auth, formulaires, contrats et SSE non détectées | Ajouter Vitest/RTL et quelques tests Playwright critiques | M |
| F030 | Moyenne | CI | Racine du dépôt | Aucun workflow CI détecté | Build, tests et audit peuvent régresser sans signal | CI lint/build/tests/Pint/audits | M |
| F031 | Faible | API | `InvestmentController.php:30-55` | Filtres, `per_page` et recherche non validés par FormRequest | Erreurs 500 possibles avec valeurs extrêmes; contrat implicite | Request dédiée avec bornes et enums | S |
| F032 | Faible | Qualité | Résultat Pint | 28 fichiers non conformes | Bruit de diff et standard non appliqué | Lancer Pint puis l'imposer en CI | S |

## 5. Sécurité

### Authentification, CSRF et isolation

Les routes métier sont bien groupées sous `auth:sanctum` (`backend/routes/api.php:26-77`). Les mutations utilisent un client frontend qui récupère le cookie CSRF et envoie `X-XSRF-TOKEN` (`frontend/src/lib/api/client.ts:27-79`). Les actions sur un investissement appellent une policy et les agrégations utilisent `forUser`.

Le bypass reste le risque principal. Il est exécuté sur toute la pile API et utilise `env()` directement, ce qui est aussi incompatible avec une configuration Laravel mise en cache. Une garde de démarrage doit interdire le bypass hors environnement local/test. Le test local a confirmé l'impact : avec le bypass actif, les scénarios « mauvais mot de passe » et « endpoint non authentifié » échouent.

L'inscription publique est techniquement correcte mais discutable pour une application personnelle. Elle doit être un choix explicite.

### Secrets et données sensibles

- `Investment.notes` et `User.opencode_api_key` utilisent des casts chiffrés et la clé IA est masquée de la sérialisation (`backend/app/Models/Investment.php:48-59`, `backend/app/Models/User.php:15-34`).
- Le fichier exemple backend contient toutefois une vraie valeur `APP_KEY`, à remplacer immédiatement.
- Les réponses et logs de providers peuvent conserver des corps externes ou messages d'erreur détaillés (`CoinGeckoPriceProvider.php:68-73`, `OpenCodeAIProvider.php:39-48`). Limiter et nettoyer ces contenus avant journalisation.
- Le CSV exporte des cellules utilisateur sans neutraliser les préfixes `=`, `+`, `-`, `@` (`backend/app/Http/Controllers/Api/V1/ExportController.php:67-85`). Pour ouverture Excel, préfixer les cellules textuelles dangereuses afin d'éviter la CSV injection.

### Docker exposé

La configuration est acceptable pour un poste local isolé, pas pour un serveur personnel exposé. Lier les ports de développement à `127.0.0.1`, ne publier que Nginx en production, désactiver Mailpit et le serveur Next dev, utiliser TLS et secrets distincts.

## 6. Backend Laravel

### Points solides

- Contrôleurs majoritairement minces.
- Validation centralisée.
- Policies et scoping utilisateur présents.
- Transactions autour du pricing persistant et des snapshots.
- Mode Eloquent strict hors production (`backend/app/Providers/AppServiceProvider.php:14-19`).
- Providers testés avec `Http::fake()`.

### Corrections requises

- Centraliser toute valorisation dans `InvestmentValuation`.
- Ajouter un service de conversion monétaire; le paramètre `currency` de `summary()` ne fait aujourd'hui que changer l'étiquette.
- Ne pas repersister un prix de fallback avec la date courante.
- Journaliser chaque tentative provider, pas seulement le résultat final de la chaîne.
- Valider la date `--date` de la commande snapshot.
- Ajouter l'unicité DB des snapshots de position.
- Découper le pricing planifié en jobs unitaires et limiter les appels par provider.
- Ajouter PHPStan/Larastan à un niveau progressif.

## 7. Frontend Next.js

### Architecture et état

Le client HTTP est correctement centralisé, sauf le flux SSE qui justifie son `fetch` dédié. TanStack Query est employé de façon cohérente et les types sont stricts.

### Problèmes principaux

- Le type de réponse du refresh est faux malgré un build réussi.
- Les montants sont convertis en `number` dès les formulaires (`frontend/src/app/(app)/investments/new/page.tsx:117-129`). Pour préserver les décimales, transmettre des chaînes décimales jusqu'à l'API ou définir une politique d'arrondi explicite.
- Plusieurs champs n'ont pas d'association label/input robuste; les boutons icône reposent sur `title`, et le modal de clé n'expose pas clairement les attributs dialog/aria (`frontend/src/app/(app)/_chat/ChatApiKeyModal.tsx:39-111`).
- Le dashboard masque les erreurs réseau derrière des états vides.
- La carte charge une ressource tierce au runtime.
- Aucun test frontend n'est présent.

## 8. API

L'API est correctement versionnée sous `/api/v1`, utilise les statuts usuels et les ressources Laravel. La pagination de la liste suit `{data,links,meta}`.

Points à corriger :

- Stabiliser formellement les erreurs API; le frontend suppose `message` et `errors`, tandis que le SSE renvoie un événement ad hoc.
- Corriger le contrat TypeScript du refresh.
- Introduire des requests pour les paramètres de liste et de performance.
- Ajouter une spécification OpenAPI minimale ou des tests de contrat générant/validant les types TS.
- Le endpoint santé métier est placé dans le groupe authentifié (`backend/routes/api.php:75-76`), tandis que `/up` est public. Conserver un seul contrat opérationnel clair.

## 9. Données financières et calculs

### Devises

C'est le défaut le plus grave. Une position USD et une position EUR sont additionnées directement. Le prix provider est demandé dans `Investment.currency`, pas dans `User.base_currency` (`backend/app/Application/Pricing/FetchInvestmentPrice.php:25-35`). Le coût ignore `purchase_currency`. Les snapshots écrivent ensuite le total sous la devise utilisateur.

Correction recommandée :

- définir `Money(amount decimal, currency)` ou un objet équivalent;
- obtenir un taux FX horodaté;
- convertir valeur et coût vers la devise utilisateur;
- persister taux, paire, source et date dans le snapshot;
- refuser ou signaler explicitement un agrégat si un taux manque.

### Décimales et arrondis

La persistance PostgreSQL utilise correctement `decimal`, mais le domaine et le frontend convertissent rapidement en `float`/`number`. Pour cette application indicative, cela reste acceptable aux frontières d'affichage, pas pour la chaîne complète de calcul. Utiliser des chaînes décimales ou BCMath pour valeur, coût et conversion; arrondir une seule fois selon la précision de devise.

### Snapshots et traçabilité

- Ajouter l'unicité de position/date.
- Réutiliser `InvestmentValuation`.
- Conserver l'âge réel d'un dernier prix connu.
- Ne pas enregistrer une tentative fallback comme nouvelle observation de marché.
- Ajouter un statut de qualité au snapshot : `fresh`, `stale`, `manual`, `missing`.

### Livrets

Le taux et la méthode sont simplifiés. La documentation doit indiquer cette approximation ou le calcul doit être versionné par périodes de taux et règles de quinzaine.

## 10. Tests

### Tests existants

Le backend contient 75 tests couvrant auth, isolation utilisateur, CRUD, dashboard, export, stratégie, providers, pricing et snapshots. La suite passe avec un environnement de test explicitement assaini.

### Défaut du chemin standard

`make test` appelle le conteneur dont les variables DB écrasent `phpunit.xml`. Un fichier SQLite `backend/patrimoine` suivi par Git est alors utilisé et est corrompu. Le bypass local perturbe aussi les tests d'authentification.

### Scénarios prioritaires à ajouter

1. Portefeuille EUR + USD avec taux FX connu.
2. Prix d'achat dans une devise différente de la valorisation.
3. Snapshot Livret A identique au dashboard à temps figé.
4. Fallback conservant la date du dernier prix connu.
5. Tentative concurrente de snapshot.
6. Allocations pays/secteur dépassant 100 % et doublons.
7. CSV avec cellule commençant par `=`.
8. Démarrage refusé avec bypass actif en production.
9. Tests frontend du refresh, des erreurs dashboard, du formulaire et du SSE.

## 11. DevOps / Docker

- Créer `compose.dev.yml` et une configuration de production utilisant le runner Next.js et des images immuables.
- Séparer worker et scheduler.
- Ajouter healthchecks applicatifs pour PHP-FPM/Nginx/frontend/worker.
- Retirer les ports PostgreSQL/Redis/Mailpit en production.
- Épingler l'image Mailpit au lieu de `latest`.
- Ajouter limites de ressources et rotation des logs.
- Le backup est fonctionnel mais non chiffré et aucune restauration automatisée n'est testée (`ops/backup.sh:21-25`). Ajouter chiffrement optionnel, vérification `pg_restore --list` et procédure de restauration.
- `onOneServer()` dépend du cache partagé; Redis le permet dans la configuration scheduler actuelle, mais ce prérequis doit être documenté.

## 12. Quick wins

- [ ] Remplacer la clé de `backend/.env.example` par un placeholder.
- [ ] Retirer et ignorer `backend/patrimoine`.
- [ ] Corriger le type `RefreshResponse`.
- [ ] Injecter `InvestmentValuation` dans `SnapshotService`.
- [ ] Ajouter l'unique `(investment_id, snapshot_date)`.
- [ ] Forcer `AUTH_BYPASS=false` et SQLite `:memory:` dans les commandes de test.
- [ ] Rendre le bypass frontend `false` par défaut.
- [ ] Lier les ports de développement à `127.0.0.1`.
- [ ] Séparer les origines CORS des domaines Sanctum.
- [ ] Valider les sommes d'allocations.
- [ ] Héberger le world atlas localement.
- [ ] Appliquer Pint et l'ajouter à la CI.

## 13. Roadmap de correction

### Court terme — 1 à 2 jours

1. Neutraliser les bypass et ports exposés.
2. Corriger le chemin de test standard.
3. Retirer la base SQLite suivie et la clé d'exemple.
4. Corriger le contrat refresh.
5. Centraliser la valorisation snapshot.
6. Ajouter unicité snapshot et validation allocations.
7. Mettre à jour les dépendances vulnérables compatibles.

### Moyen terme — 1 à 2 semaines

1. Concevoir et implémenter le service FX traçable.
2. Migrer dashboard, snapshots, exports et contexte IA vers des montants normalisés.
3. Revoir le modèle de fallback et les logs par tentative provider.
4. Séparer scheduler/worker et créer une configuration production.
5. Ajouter CI, Larastan et tests frontend critiques.
6. Réduire les payloads provider persistés.

### Long terme

1. Versionner les règles de rendement réglementé.
2. Introduire un contrat de montant décimal partagé API/TypeScript.
3. Générer ou valider les types frontend depuis une spécification API.
4. Ajouter observabilité : durée/succès par provider, âge des prix, queue lag, échec snapshot.
5. Automatiser sauvegarde chiffrée et exercice de restauration.

## 14. Patches suggérés

Ces exemples sont indicatifs; aucun fichier source n'a été modifié.

### A. Centraliser la valorisation des snapshots

```diff
 final class SnapshotService
 {
+    public function __construct(
+        private readonly InvestmentValuation $valuation,
+    ) {}
+
     private function currentValue(Investment $investment): float
     {
-        if ($investment->manual_value !== null) {
-            return (float) $investment->manual_value;
-        }
-
         $latest = $investment->latestPrice?->first();
-        return $latest
-            ? (float) $investment->quantity * (float) $latest->price
-            : 0.0;
+        return $this->valuation->currentValue(
+            $investment,
+            $latest?->price !== null ? (float) $latest->price : null,
+        );
     }
 }
```

### B. Verrouiller le bypass

```diff
-if (env('AUTH_BYPASS', false) && ! $request->user()) {
+if (config('auth.bypass', false)) {
+    abort_unless(app()->environment(['local', 'testing']), 500);
+}
+
+if (config('auth.bypass', false) && ! $request->user()) {
     Auth::login(User::query()->firstOrFail());
 }
```

```diff
-NEXT_PUBLIC_AUTH_BYPASS: "true"
+NEXT_PUBLIC_AUTH_BYPASS: "${NEXT_PUBLIC_AUTH_BYPASS:-false}"
```

### C. Corriger le contrat refresh

```diff
-type RefreshResponse = Investment & { meta?: RefreshPriceMeta };
+type RefreshResponse = {
+  data: Investment;
+  meta: RefreshPriceMeta;
+};
```

### D. Préserver la fraîcheur du fallback

```diff
-return PriceResult::fallback(
-    price: (float) $lastPrice->price,
-    ...
-);
+return PriceResult::fallback(
+    price: (float) $lastPrice->price,
+    fetchedAt: $lastPrice->fetched_at,
+    ...
+);
```

Puis ne pas créer une nouvelle ligne `asset_prices` pour un résultat `fallback`; journaliser seulement la tentative.

### E. Durcir Compose local

```diff
 postgres:
-  ports:
-    - "5432:5432"
+  ports:
+    - "127.0.0.1:5432:5432"

 redis:
-  ports:
-    - "6379:6379"
+  ports:
+    - "127.0.0.1:6379:6379"
```

En production, supprimer complètement ces publications.

### F. Fiabiliser la commande de test

```diff
 test:
-	docker compose exec app php artisan test
+	docker compose exec \
+	  -e AUTH_BYPASS=false \
+	  -e DB_CONNECTION=sqlite \
+	  -e DB_DATABASE=:memory: \
+	  -e CACHE_STORE=array \
+	  -e QUEUE_CONNECTION=sync \
+	  app php artisan test
```

## 15. Limites de l'audit

- Audit statique et exécution locale, pas de pentest dynamique.
- Aucun appel réel aux providers financiers ou OpenCode.
- Aucune valeur secrète des fichiers `.env` n'a été lue ou affichée.
- PHPStan/Larastan, Rector, Knip, Madge et Depcheck étaient absents et n'ont pas été installés.
- Pas de mesure de couverture : aucun driver/commande de couverture n'a été configuré pour cet audit.
- L'historique Git ne contient que deux commits d'import; l'analyse de churn est donc non significative.
- Le comportement CORS direct `localhost:3000` n'a pas été testé dans un navigateur.
- Les vulnérabilités npm sont celles signalées le 21 juin 2026; leur exploitabilité exacte dépend du mode de déploiement et des fonctionnalités utilisées.
- La restauration d'une sauvegarde PostgreSQL n'a pas été exécutée.

## 16. Éléments examinés mais non retenus comme défauts

- Les `float` dans la sérialisation et l'affichage ne sont pas, seuls, un défaut critique : la persistance reste en `decimal`. Le risque apparaît parce que les calculs métier complets utilisent aussi ces floats.
- L'usage direct de `fetch` pour le chat est justifié par le streaming SSE et ne constitue pas une duplication arbitraire du client HTTP.
- `ilike` est spécifique PostgreSQL, mais PostgreSQL est explicitement la cible de production. Le défaut associé est surtout l'absence de test du filtre de recherche sous SQLite.
- Les notes et la clé OpenCode sont correctement chiffrées par casts Eloquent et masquées à la sérialisation.
- La relation `latestPrice` est chargée explicitement sur les flux principaux; aucun N+1 critique évident n'a été constaté sur les lectures auditées.

## 17. Questions ouvertes

- L'inscription libre doit-elle réellement rester disponible sur une instance personnelle ?
- Le support multidevise est-il fonctionnellement attendu dès maintenant, ou faut-il temporairement interdire toute devise différente de la devise utilisateur ?
- Les taux Livret A/LDDS visent-ils une estimation simple ou une fidélité réglementaire ?
- La table `price_providers` doit-elle piloter réellement l'ordre/activation, ou seulement documenter les providers codés ?
- Les exports contenant email et notes sont-ils destinés à une sauvegarde complète ou à un partage ? Le niveau de données devrait différer selon l'usage.
