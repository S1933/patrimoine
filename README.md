# Patrimoine

Application web personnelle de suivi, valorisation et analyse de patrimoine.

Patrimoine centralise les actifs financiers et non financiers, récupère leurs
prix auprès de plusieurs fournisseurs, calcule leur performance et conserve
des snapshots quotidiens pour suivre leur évolution.

> Les données affichées sont indicatives. Cette application ne fournit aucun
> conseil financier.

## Fonctionnalités

- Gestion des actions, ETF, ETN crypto, cryptomonnaies, or, immobilier,
  liquidités, Livret A, LDDS et actifs personnalisés
- Valorisation automatique ou manuelle avec conservation du dernier prix connu
- Résolution des instruments par ticker ou ISIN
- Dashboard: valeur totale, coût d'achat, plus-value, allocation, historique et
  répartition géographique
- Historique des prix et snapshots quotidiens du portefeuille
- Export du portefeuille en JSON ou CSV
- Authentification SPA par session avec Laravel Sanctum
- Assistant IA OpenCode avec contexte du portefeuille et réponse en streaming
- Synchronisation planifiée des prix et exécution asynchrone via Redis

## Stack

| Couche | Technologies |
|---|---|
| Frontend | Next.js 14, React 18, TypeScript, Tailwind CSS, TanStack Query, Recharts |
| Backend | Laravel 13, PHP 8.4, Sanctum, Pest |
| Données | PostgreSQL 16, Redis 7 |
| Infrastructure | Docker Compose, Nginx, Mailpit |

## Démarrage rapide

### Prérequis

- Docker avec le plugin Compose
- Make

### Installation

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

L'application est ensuite disponible sur:

- Interface: <http://localhost:8080>
- API: <http://localhost:8080/api/v1>
- Frontend direct: <http://localhost:3000>
- Mailpit: <http://localhost:8025>
- Endpoint de santé: <http://localhost:8080/up>

Le compte initial est créé par le seeder avec `ADMIN_EMAIL`,
`ADMIN_PASSWORD` et `ADMIN_NAME`, définis dans `backend/.env`.

### Authentification en développement

Le frontend Docker active actuellement `NEXT_PUBLIC_AUTH_BYPASS=true`. Pour
utiliser l'auto-connexion locale, ajouter également ceci dans `backend/.env`:

```dotenv
AUTH_BYPASS=true
```

Les deux flags doivent rester cohérents. Pour tester l'authentification réelle,
définir `AUTH_BYPASS=false` côté backend et
`NEXT_PUBLIC_AUTH_BYPASS=false` pour le service `frontend`, puis reconstruire
ce service:

```bash
docker compose up -d --build frontend
```

Ne jamais activer le contournement d'authentification dans un environnement
exposé.

## Configuration

Les variables Docker globales se trouvent dans `.env`. Laravel lit
`backend/.env`; Next.js lit `frontend/.env.local` hors Docker.

Variables principales:

| Variable | Usage |
|---|---|
| `APP_PORT` | Port Nginx, `8080` par défaut |
| `FRONTEND_PORT` | Port Next.js direct, `3000` par défaut |
| `DB_*` | Connexion PostgreSQL |
| `REDIS_*` | Cache et file de travaux |
| `ADMIN_*` | Compte créé par le seeder |
| `BASE_CURRENCY` | Devise de référence, `EUR` par défaut |
| `PROVIDER_COINGECKO_KEY` | Prix des cryptomonnaies |
| `PROVIDER_GOLDAPI_KEY` | Prix de l'or |
| `PROVIDER_TWELVEDATA_KEY` | Prix principal des actions, ETF et ETN |
| `PROVIDER_FINNHUB_KEY` | Prix de secours et résolution d'ISIN |
| `PROVIDER_OPENFIGI_KEY` | Résolution complémentaire d'ISIN |
| `OPENCODE_API_KEY` | Clé serveur facultative pour l'assistant IA |
| `OPENCODE_PROVIDER` | Fournisseur OpenCode: `zen` ou `go` |

Les clés de prix sont facultatives si les actifs concernés sont valorisés
manuellement. Une clé OpenCode peut aussi être enregistrée depuis l'interface;
elle est alors chiffrée en base.

## Valorisation

La chaîne de fournisseurs dépend du type d'actif:

| Type | Ordre de résolution |
|---|---|
| Cryptomonnaie | CoinGecko → valeur manuelle → dernier prix connu |
| Or | GoldAPI → valeur manuelle → dernier prix connu |
| Action, ETF, ETN | Twelve Data ou Finnhub → Yahoo Finance → valeur manuelle → dernier prix connu |
| Immobilier, cash, livrets, autre | Valeur manuelle → dernier prix connu |

Pour les instruments de marché, renseigner un ticker ou un ISIN. Les notes
personnelles et les clés OpenCode enregistrées par un utilisateur sont
chiffrées au repos.

## Commandes

```bash
make help            # liste des commandes
make up              # construit et démarre les services
make down            # arrête les services
make logs            # suit les logs
make ps              # affiche l'état des conteneurs
make migrate         # exécute les migrations
make migrate-seed    # recrée la base et charge les référentiels
make test            # exécute tous les tests backend
make test-unit       # exécute les tests unitaires
make test-feature    # exécute les tests fonctionnels
make shell           # ouvre un shell dans le backend
make shell-frontend  # ouvre un shell dans le frontend
```

Commandes métier:

```bash
docker compose exec app php artisan patrimoine:sync-prices
docker compose exec app php artisan patrimoine:snapshot
docker compose exec app php artisan patrimoine:snapshot --date=2026-06-21
```

Le scheduler synchronise les prix à `09:00` et `18:00` UTC, puis crée un
snapshot quotidien à `23:00` UTC.

## Tests et qualité

```bash
make test
docker compose exec frontend npm run build
```

Les tests Pest couvrent notamment l'authentification, le CRUD des
investissements, le dashboard, les exports, les snapshots, le chat et les
différents fournisseurs de prix.

## API

L'API JSON est versionnée sous `/api/v1`. Après authentification, elle expose
principalement:

- `/investments` pour le CRUD et la valorisation
- `/dashboard/*` pour les synthèses et graphiques
- `/exports/portfolio.json` et `/exports/portfolio.csv`
- `/chat` et `/chat/models` pour l'assistant IA
- `/asset-types`, `/price-providers` et `/currencies` pour les référentiels

## Architecture

```text
.
├── backend/            API Laravel, domaine, migrations, jobs et tests
├── frontend/           Application Next.js
├── docker/             Configuration Nginx et PHP
├── ops/backup.sh       Sauvegarde PostgreSQL
├── docker-compose.yml
├── Makefile
└── .env.example
```

Le backend sépare les couches `Domain`, `Application` et `Infrastructure`.
Nginx sert le frontend et route `/api` vers Laravel afin de conserver une
origine unique pour les cookies Sanctum.

## Sauvegarde

Le script `ops/backup.sh` crée une archive PostgreSQL compressée et supprime par
défaut les sauvegardes de plus de 14 jours:

```bash
PGPASSWORD=changeme DB_HOST=localhost ./ops/backup.sh
```

Le client `pg_dump` doit être installé sur la machine qui exécute le script.
La durée de rétention et le dossier de destination sont configurables avec
`RETENTION_DAYS` et `BACKUP_DIR`.

## Sécurité

- Ne jamais versionner les fichiers `.env` ni des clés réelles.
- Remplacer les identifiants administrateur et PostgreSQL par défaut.
- Désactiver les flags `AUTH_BYPASS` hors développement local.
- Servir l'application en HTTPS derrière un reverse proxy en production.
- Sauvegarder régulièrement PostgreSQL et tester la restauration.
