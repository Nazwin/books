1. `docker-compose up -d --build` або `make build`
2. `docker exec -it books_php bash`
3. `composer install`
4. `php bin/console doctrine:migrations:migrate`

## Що можна покращити?
1. Використати VichUploaderBundle для завантаження файлів замість власного рішення
2. Створити окремий сервіс для роботи з файлами
3. Створити окремий validator для створення, оновлення книги