.PHONY: up down logs logs-error bash

# Define the services
SERVICES = prestashop prestashop_db

# Start the containers
up:
	docker compose up -d

# Stop the containers
down:
	docker compose down

down-v:
	docker compose down -v	

# View logs for all services
logs:
	docker compose logs -f

logs-error:
	docker compose logs -f | grep -i error

# Access bash in the prestashop container
bash:
	docker compose exec prestashop bash

# Access bash in the prestashop_db container
db_bash:
	docker compose exec prestashop_db bash

# Clean up unused images and containers
clean:
	docker system prune -f

# Restart the containers
restart: down up

# Build the containers
build:
	docker compose build
