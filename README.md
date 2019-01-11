# HTTP клинте для общения между сервисами
HTTP client на основе [Guzzle](https://github.com/guzzle/guzzle) с предустановленными настройками. Можно выполнять 
настроенные запросы - класс **Request**, так и самому тонко настраивать запросы - класс **Client**

### Возможности
- Кэширование GET запросов
- Автовыключение неработоспособных сервисов ([Pattern: Circuit Breaker](https://microservices
.io/patterns/reliability/circuit-breaker.html))
- Повтор запроса, если сервис не отвечает за тайм аут или отвечает не успешным статусом
- Выполнение нескольких параллельных асинхронных запросов ([Pattern: API Composition](https://microservices.io/patterns/data/api-composition.html))

### Требования
- Phalcon 3.x+
- PHP 7.0+
- Guzzle 6.0+


