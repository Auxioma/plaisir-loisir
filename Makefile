.PHONY: help up down restart build rebuild logs ps clean

help: ## Affiche cette aide
	@echo "Usage: make [cible]"
	@echo ""
	@echo "Cibles disponibles :"
	@echo "  make up        - Demarre les conteneurs Docker en arriere-plan"
	@echo "  make down      - Arrete les conteneurs Docker"
	@echo "  make restart   - Redemarre les conteneurs Docker"
	@echo "  make build     - Construit/reconstruit les images Docker"
	@echo "  make rebuild   - Arrete, reconstruit et relance les conteneurs"
	@echo "  make logs      - Affiche les logs des conteneurs en temps reel"
	@echo "  make ps        - Affiche le statut des conteneurs"
	@echo "  make clean     - Arrete les conteneurs et supprime les volumes"

up: ## Demarre les conteneurs Docker en arriere-plan
	docker compose up -d

down: ## Arrete les conteneurs Docker
	docker compose down

restart: ## Redemarre les conteneurs Docker
	docker compose restart

build: ## Reconstruit les images Docker
	docker compose build

rebuild: ## Arrete, reconstruit et relance les conteneurs
	docker compose down
	docker compose up -d --build

logs: ## Affiche les logs des conteneurs
	docker compose logs -f

ps: ## Affiche l'etat des conteneurs
	docker compose ps

clean: ## Arrete les conteneurs et supprime les volumes
	docker compose down -v
