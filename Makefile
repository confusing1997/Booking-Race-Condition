# Biến số
DC = docker compose
APP = $(DC) exec -it app

# Lệnh mặc định
.DEFAULT_GOAL := help

help: ## Hiển thị danh sách các lệnh
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: ## Cài đặt dự án từ đầu (A-Z)
	$(DC) up -d --build
	$(APP) composer install
	$(APP) cp .env.example .env
	$(APP) php artisan key:generate
	$(APP) chmod -R 775 storage bootstrap/cache
	$(APP) chown -R www-data:www-data storage bootstrap/cache
	$(APP) php artisan migrate --seed
	@echo "------------------------------------------------"
	@echo "✅ ĐÃ CÀI ĐẶT XONG! Truy cập: http://localhost:8080"
	@echo "------------------------------------------------"

up: ## Khởi động các container
	$(DC) up -d

down: ## Dừng các container
	$(DC) down

test: ## Chạy Automated Tests
	$(APP) php artisan test

tinker: ## Chui vào môi trường Tinker của Laravel
	$(APP) php artisan tinker

migrate: ## Chạy lại migration và seed
	$(APP) php artisan migrate:fresh --seed

log: ## Xem log của Laravel theo thời gian thực
	$(APP) tail -f storage/logs/laravel.log