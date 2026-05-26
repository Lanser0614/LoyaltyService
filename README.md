# Executive Summary

Система больше не является только Coupon Service. После добавления BellCoin, customer wallet, segmentation, automatic rewards и stacking rules правильнее называть её:

```
Loyalty & Promotion Service
```

Loyalty & Promotion Service — это модульный монолит для управления промокодами, гарантированными купонами, бесплатными продуктами/combo, BellCoin, сегментацией клиентов, динамическими условиями и автоматической выдачей rewards на основе событий Delivery IO.

Ключевая идея: **IIKO и Delivery IO остаются source of truth**, но Loyalty & Promotion Service хранит локальные projections, чтобы быстро и безопасно валидировать rewards без runtime-зависимости от внешних сервисов.

## Что решаем

- Единая логика скидок для mobile app, checkout, call center и marketing campaigns.
- Поддержка обычных промокодов и гарантированных купонов клиента.
- Поддержка BellCoin: начисление, баланс, резервирование, списание и возврат.
- Поддержка IIKO products, groups, menus, availability и combo.
- Автоматическая выдача купонов/rewards через Kafka events.
- UI builder условий и действий для маркетинга без ручного JSON.
- Сегментация клиентов и customer behavior stats.
- StackingRules / IncentivePolicy: контроль совместимости купонов, BellCoin и других rewards.
- Аудит: почему reward применился, не применился, был выдан или был отклонён.

---

# 1. Общая архитектура

```
IIKO
  ↓ sync
Local IIKO Catalog Snapshot
  ↓
Promotion Engine ← UI Rule Builder (conditions + actions)
  ↓
Validate / Apply Incentives

Delivery IO
  ↓ order events
Kafka
  ↓
Event Inbox
  ↓
Order Projection + Customer Stats
  ↓
Issue Rule Engine
  ↓
Customer Wallet / Loyalty
  ↓
coupon.issued / loyalty.bellcoin_accrued events
```

## Sources of truth

| Домен | Источник истины | Роль Loyalty & Promotion Service |
| --- | --- | --- |
| Продукты / меню / combo | IIKO | Локальный snapshot каталога |
| Заказы | Delivery IO | Локальная projection заказов |
| Promotions / rules / usages | Loyalty & Promotion Service | Основной владелец данных |
| Customer rewards / coupons | Loyalty & Promotion Service | Основной владелец данных |
| BellCoin balance / ledger | Loyalty & Promotion Service | Основной владелец данных |

## Architecture Decision: Delivery IO vs Loyalty ownership

```
Delivery IO = source of truth для order lifecycle и финального состояния заказа.
Delivery IO ничего не знает о скидках, купонах и BellCoin.

Loyalty & Promotion Service = source of truth для incentive rules, coupon usages,
BellCoin ledger и audit применения incentives.
```

Mobile App вызывает Loyalty до отправки в Delivery IO, получает готовый результат
и корректирует request body. Delivery IO получает уже финальный request — с правильными
items и payment_amount — и просто отправляет в IIKO.

### Recommended flow

```
Step 1 — Preview (показать клиенту результат до подтверждения)

Mobile App
  ↓ POST /api/incentives/checkout/preview
    { customer_id, order_total, items, coupon_code, use_bellcoin, bellcoin_amount }
Loyalty & Promotion Service
  - валидирует coupon / customer_coupon
  - проверяет BellCoin balance и redemption rules
  - проверяет StackingRules / IncentivePolicy
  - считает discount_amount, free_items, итоговую сумму
  - ничего не резервирует, только считает
  ↓ возвращает:
    { discount_amount, free_items, final_amount, payment_amount }
Mobile App показывает клиенту финальную цену и состав заказа
```

```
Step 2 — Apply (клиент подтверждает заказ)

Mobile App
  ↓ POST /api/incentives/checkout/apply
    { customer_id, order_total, items, coupon_code, use_bellcoin, bellcoin_amount }
Loyalty & Promotion Service
  - повторяет все проверки (validate + stacking)
  - BEGIN TRANSACTION
      coupon_usage → reserved
      customer_coupon → reserved
      BellCoin → reserved
      order_incentive_application → reserved
  - COMMIT
  ↓ возвращает:
    {
      incentive_application_id,
      final_items,       ← original items + free_items
      final_amount,
      payment_amount
    }
```

```
Step 3 — Create Order

Mobile App берёт ответ из Step 2 и корректирует request body
  ↓ POST /delivery-io/orders/create
    {
      items: final_items,
      payment_amount: 130000,
      incentive_application_id: "abc-123",  ← Delivery IO просто сохраняет
      ...остальные поля заказа
    }
Delivery IO
  - принимает готовый request
  - сохраняет incentive_application_id как поле (не использует)
  - отправляет заказ в IIKO as-is
  ↓ возвращает: { order_id }
```

```
Step 4a — Успех (через Kafka)

Delivery IO публикует:
  order.status_changed { status: WaitCooking, order_id, incentive_application_id }

Loyalty Kafka consumer:
  - находит order_incentive_application по incentive_application_id
  - привязывает order_id
  - coupon_usage → applied
  - customer_coupon → used
  - BellCoin reservation → committed
  - order_incentive_application → applied
```

```
Step 4b — Неуспех (TTL job)

Если за 5 минут WaitCooking event не пришёл:
Scheduled job каждую минуту находит reserved applications старше 5 минут:
  - coupon_usage → cancelled
  - customer_coupon → available
  - BellCoin → released
  - order_incentive_application → cancelled
```

### Why this boundary matters

Delivery IO получает чистый заказ. Если нужно понять почему payment_amount = 130 000,
а не 150 000 — аудит живёт полностью внутри Loyalty по `incentive_application_id`.

```
Loyalty хранит по incentive_application_id:
  order_id = 999
  coupon_code = PIZZA20
  rule = 20% discount
  calculated_discount = 20 000
  customer_id = 555
  free_items = [cinnamon_roll x1]
  bellcoin_reserved = 0
  applied_at = ...
```

---

## Technology Decision: Laravel / PHP

Для Loyalty & Promotion Service используется Laravel как основной backend stack.

```
Primary language: PHP
Framework: Laravel
Architecture style: modular monolith (нативные namespaces, без пакетов)
Database: PostgreSQL
Messaging: Kafka (junges/laravel-kafka)
Migrations: Laravel Migrations
API: REST для checkout/admin flows
Internal events: Kafka consumers/producers
Testing: PHPUnit, RefreshDatabase, Testcontainers (опционально)
```

### Почему Laravel подходит

```
1. Команда знает стек — быстрый старт без onboarding.
2. Eloquent + DB::transaction() покрывают все ledger и reservation flows.
3. lockForUpdate() решает BellCoin concurrency без дополнительных инструментов.
4. Laravel Horizon уже используется в других сервисах Bellissimo.
5. Kafka интеграция через junges/laravel-kafka с inbox/idempotency pattern.
6. Модульная структура через нативные namespaces — без лишних пакетов.
```

### Структура модулей (нативные namespaces)

```
app/
├── Promotion/
│   ├── Models/
│   ├── Services/
│   ├── Handlers/
│   │   ├── Conditions/
│   │   └── Actions/
│   ├── Registries/
│   ├── DTOs/
│   ├── Exceptions/
│   └── Http/Controllers/
├── CustomerWallet/
│   ├── Models/
│   ├── Services/
│   ├── DTOs/
│   └── Http/Controllers/
├── Loyalty/
│   ├── Models/
│   ├── Services/
│   ├── DTOs/
│   ├── Exceptions/
│   └── Http/Controllers/
├── IncentivePolicy/
│   ├── Models/
│   ├── Services/
│   ├── DTOs/
│   └── Exceptions/
├── Catalog/
│   ├── Models/
│   ├── Services/
│   └── Http/
│       └── IikoApiClient.php
├── CustomerInsights/
│   ├── Models/
│   ├── Services/
│   └── Contracts/
│       └── CustomerSegmentProviderInterface.php
├── Integration/
│   ├── Kafka/
│   │   ├── Consumers/
│   │   └── Producers/
│   ├── Inbox/
│   │   ├── Models/
│   │   └── Services/
│   └── Contracts/
└── Shared/
    ├── DTOs/
    ├── Exceptions/
    ├── Money/
    └── Enums/
        ├── IncentiveType.php
        ├── CouponStatus.php
        ├── LedgerTransactionType.php
        └── FailureReason.php
```

`composer.json` — autoload через стандартный PSR-4:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/"
    }
}
```

### Межмодульное взаимодействие

Модули не импортируют Models друг друга напрямую. Общение только через Service или Contract:

```php
// НЕПРАВИЛЬНО
use App\Loyalty\Models\LoyaltyAccount;

// ПРАВИЛЬНО
use App\Loyalty\Services\BellCoinReserveService;
use App\CustomerInsights\Contracts\CustomerSegmentProviderInterface;
```

Исключение — `Shared/`. Его могут импортировать все модули.

### Transaction boundaries

```php
public function apply(ApplyIncentiveDTO $dto): IncentiveApplicationResult
{
    // Валидация ВНЕ транзакции
    $this->couponValidator->validate($dto);
    $this->bellcoinValidator->validate($dto);
    $this->stackingPolicy->check($dto);

    return DB::transaction(function () use ($dto) {
        $bellcoinReservation = $this->bellcoinReserveService->reserve($dto);
        $couponUsage = $this->couponUsageService->create($dto);

        return OrderIncentiveApplication::create([
            'order_id'                => $dto->orderId,
            'customer_id'             => $dto->customerId,
            'coupon_usage_id'         => $couponUsage?->id,
            'bellcoin_transaction_id' => $bellcoinReservation?->id,
            'total_discount_amount'   => $dto->totalDiscount,
            'incentives_snapshot'     => $dto->toSnapshot(),
            'status'                  => 'reserved',
        ]);
    });
}
```

### BellCoin pessimistic lock

```php
DB::transaction(function () use ($customerId, $amount) {
    $account = LoyaltyAccount::where('customer_id', $customerId)
        ->lockForUpdate()   // SELECT ... FOR UPDATE
        ->firstOrFail();

    if ($account->available_balance < $amount) {
        throw new InsufficientBellCoinException();
    }

    $account->increment('reserved_balance', $amount);

    LoyaltyLedgerTransaction::create([...]);
});
```

### Kafka processing pattern

```
Kafka message received
  ↓
insert into event_inbox by event_id
  ↓
if duplicate event_id → ignore
  ↓
process business logic in transaction
  ↓
mark event as processed
  ↓
publish output event if needed
```

### ServiceProvider

```php
// app/Providers/LoyaltyPromotionServiceProvider.php
class LoyaltyPromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConditionHandlers();
        $this->registerActionHandlers();
        $this->registerCustomerInsights();
    }

    private function registerConditionHandlers(): void
    {
        $this->app->singleton(ConditionHandlerRegistry::class, function ($app) {
            $registry = new ConditionHandlerRegistry();
            $registry->register('cart.min_amount',  $app->make(MinAmountConditionHandler::class));
            $registry->register('customer.segment', $app->make(CustomerSegmentConditionHandler::class));
            $registry->register('cart.has_combo',   $app->make(CartHasComboConditionHandler::class));
            return $registry;
        });
    }

    private function registerActionHandlers(): void
    {
        $this->app->singleton(ActionHandlerRegistry::class, function ($app) {
            $registry = new ActionHandlerRegistry();
            $registry->register('percent',       $app->make(PercentDiscountHandler::class));
            $registry->register('fixed',         $app->make(FixedDiscountHandler::class));
            $registry->register('free_delivery', $app->make(FreeDeliveryHandler::class));
            $registry->register('free_product',  $app->make(FreeProductHandler::class));
            $registry->register('free_combo',    $app->make(FreeComboHandler::class));
            return $registry;
        });
    }

    private function registerCustomerInsights(): void
    {
        $this->app->bind(
            CustomerSegmentProviderInterface::class,
            CustomerSegmentResolverService::class
        );
    }
}
```

---

## Development Environment: Docker & Docker Compose

Docker Compose используется только для local development и integration testing.
Production deployment имеет отдельную инфраструктурную конфигурацию.

### Local development services

```
postgres   - основная база данных сервиса
kafka      - локальный Kafka broker
kafka-ui   - web UI для просмотра topics/messages
redis      - optional cache/rate limit, не для core balance
```

### Recommended local ports

```
Application API: 8080
PostgreSQL:      5432
Kafka:           9092
Kafka UI:        8085
Redis:           6379
```

### docker-compose.yml

```yaml
version: "3.9"

services:
  postgres:
    image: postgres:16-alpine
    container_name: loyalty-promotion-postgres
    environment:
      POSTGRES_DB: loyalty_promotion
      POSTGRES_USER: loyalty_user
      POSTGRES_PASSWORD: loyalty_password
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U loyalty_user -d loyalty_promotion"]
      interval: 5s
      timeout: 5s
      retries: 10

  kafka:
    image: bitnami/kafka:3.7
    container_name: loyalty-promotion-kafka
    ports:
      - "9092:9092"
    environment:
      KAFKA_CFG_NODE_ID: 1
      KAFKA_CFG_PROCESS_ROLES: broker,controller
      KAFKA_CFG_CONTROLLER_QUORUM_VOTERS: 1@kafka:9093
      KAFKA_CFG_LISTENERS: PLAINTEXT://:9092,CONTROLLER://:9093
      KAFKA_CFG_ADVERTISED_LISTENERS: PLAINTEXT://localhost:9092
      KAFKA_CFG_LISTENER_SECURITY_PROTOCOL_MAP: CONTROLLER:PLAINTEXT,PLAINTEXT:PLAINTEXT
      KAFKA_CFG_CONTROLLER_LISTENER_NAMES: CONTROLLER
      KAFKA_CFG_AUTO_CREATE_TOPICS_ENABLE: "true"
      ALLOW_PLAINTEXT_LISTENER: "yes"
    volumes:
      - kafka_data:/bitnami/kafka

  kafka-ui:
    image: provectuslabs/kafka-ui:latest
    container_name: loyalty-promotion-kafka-ui
    ports:
      - "8085:8080"
    environment:
      KAFKA_CLUSTERS_0_NAME: local
      KAFKA_CLUSTERS_0_BOOTSTRAPSERVERS: kafka:9092
    depends_on:
      - kafka

  redis:
    image: redis:7-alpine
    container_name: loyalty-promotion-redis
    ports:
      - "6379:6379"
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data

volumes:
  postgres_data:
  kafka_data:
  redis_data:
```

### Запуск для разработчика

```bash
# Поднять инфраструктуру
docker compose up -d postgres kafka kafka-ui redis

# Запустить Laravel из IDE / терминала
php artisan serve

# Логи
docker compose logs -f kafka
docker compose logs -f postgres

# Остановить
docker compose down

# Остановить и удалить volumes
docker compose down -v
```

### application-local.yml (Laravel .env аналог)

```env
APP_ENV=local
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=loyalty_promotion
DB_USERNAME=loyalty_user
DB_PASSWORD=loyalty_password

KAFKA_BROKERS=localhost:9092

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

IIKO_CLOUD_BASE_URL=https://api-ru.iiko.services/api
IIKO_API_LOGIN=xxxxxxxx
```

### Development rules

```
1. PostgreSQL is mandatory for core data.
2. Redis must not be used for BellCoin balance, coupon usages or ledger.
3. Kafka events must be idempotent through event_inbox.
4. Local Docker Compose must be close to production infrastructure, but not treated as production config.
5. Integration tests should use RefreshDatabase or Testcontainers where possible.
```

---

# 2. Модули модульного монолита

```
Loyalty & Promotion Service
├── Promotion
├── CustomerWallet
├── Loyalty
├── CustomerInsights
├── Catalog
├── Integration
└── IncentivePolicy / StackingRules
```

## 2.1 Promotion

Отвечает за promo rules, coupons, conditions, actions, builder и simulation.

```
coupons
coupon_conditions
coupon_issue_conditions
coupon_actions
coupon_condition_definitions
coupon_action_definitions
ConditionHandlerRegistry
ActionHandlerRegistry
```

## 2.2 CustomerWallet

Отвечает за то, какие rewards/купоны есть у конкретного клиента и как они используются.

```
customer_coupons
coupon_usages
available rewards
reward history
```

## 2.3 Loyalty

Отвечает за BellCoin.

```
loyalty_accounts
loyalty_ledger_transactions
loyalty_accrual_rules
loyalty_redemption_rules
reserve / commit / release
```

## 2.4 CustomerInsights

Отвечает за статистику и сегментацию.

```
customer_behavior_stats
customer_segments
customer_segment_memberships
computed segments
```

## 2.5 Catalog

Локальная копия IIKO. Синхронизируется через scheduled jobs, не используется в runtime.

```
iiko_organizations
iiko_cities
iiko_products
iiko_product_groups
iiko_menus
iiko_menu_items
iiko_product_availability
iiko_combos
iiko_combo_groups
iiko_combo_group_items
```

## 2.6 Integration

Отвечает за Kafka, inbox, external events и event publishing.

```
event_inbox
Kafka consumers
Kafka producers
Delivery IO event contracts
```

## 2.7 IncentivePolicy / StackingRules

Отвечает за совместимость incentives в одном заказе.

```
coupon + BellCoin = not allowed
multiple coupons = not allowed
free_delivery + BellCoin = not allowed
free_product + BellCoin = not allowed
```

---

# 3. Типы rewards / incentives

## 3.1 Public promo-code coupon

Обычный промокод, который пользователь вводит вручную.

```
PIZZA20
COMBO20
FREEDELIVERY
```

Flow:

```
Пользователь вводит code
  ↓
Promotion валидирует code + usage conditions
  ↓
Promotion определяет action
  ↓
IncentivePolicy проверяет совместимость с BellCoin / другими rewards
  ↓
Promotion применяет скидку или free item
```

## 3.2 Issued customer coupon

Гарантированный купон, который заранее выдан конкретному клиенту.

```
Клиент сделал 5 закрытых заказов → получил бесплатную доставку
Клиент получил компенсационный купон за позднюю доставку
Call Center вручную выдал купон клиенту
Клиент не заказывал 30+ дней → получил купон реактивации
```

Flow:

```
Issue condition matched
  ↓
CustomerWallet создаёт customer_coupon
  ↓
Клиент видит купон в мобильном приложении
  ↓
Клиент выбирает купон на checkout
  ↓
Promotion проверяет usage conditions
  ↓
IncentivePolicy проверяет compatibility
  ↓
CustomerWallet фиксирует usage
```

## 3.3 BellCoin

BellCoin — loyalty balance клиента.

```
1 BellCoin = 1 сум
```

BellCoin не является купоном. Это wallet/ledger.

```
Closed order → начислить BellCoin
Checkout → клиент может списать BellCoin как скидку
Cancelled order → release / refund BellCoin
```

---

# 4. Core Data Model: Promotion

## 4.1 coupons

```
coupons
- id
- code
- name
- coupon_kind: public_code_coupon / issued_customer_coupon
- starts_at
- ends_at
- status: draft / active / paused / expired / archived
- usage_limit_total
- usage_limit_per_customer
- issue_limit_total
- issue_limit_per_customer
- issue_policy
- auto_apply
- visible_to_customer
- requires_code_input
- priority
- stackable
- is_stackable_with_bellcoin boolean default false
- is_stackable_with_other_coupons boolean default false
```

Примечание: `discount_type` и `discount_value` должны жить в `coupon_actions`.

## 4.2 coupon_actions

Действие купона — что именно получает клиент при применении.

```
coupon_actions
- id
- coupon_id
- action_type: percent / fixed / free_delivery / free_product / free_combo
- value
- max_discount_amount
- product_iiko_id
- combo_iiko_id
- quantity
- price_override
```

### Action types

| action_type | Описание | Ключевые поля |
| --- | --- | --- |
| percent | Скидка X% на заказ или target | value, max_discount_amount |
| fixed | Фиксированная сумма скидки | value |
| free_delivery | Стоимость доставки = 0 | — |
| free_product | Добавить продукт бесплатно | product_iiko_id, quantity |
| free_combo | Добавить combo бесплатно | combo_iiko_id, quantity |

---

# 5. CustomerWallet

## 5.1 customer_coupons

Конкретный купон, выданный конкретному клиенту.

```
customer_coupons
- id
- coupon_id
- customer_id
- code
- campaign_key
- status: issued / available / reserved / used / expired / cancelled
- issued_reason
- issued_by_type
- issued_by_id
- source_event_id
- starts_at
- expires_at
- used_at
- cancelled_at
- metadata
```

Unique constraint:

```
unique(customer_id, coupon_id, campaign_key)
```

## 5.2 coupon_usages

Факт применения или резервирования купона.

```
coupon_usages
- id
- coupon_id
- customer_coupon_id nullable
- customer_id
- order_id
- status: reserved / applied / cancelled / expired
- discount_amount
- free_items_snapshot
- reservation_key
- validation_snapshot_id
- applied_at
- cancelled_at
```

---

# 6. Loyalty / BellCoin

## 6.1 Основной принцип

BellCoin — это не coupon action, а отдельная loyalty wallet система.

```
Coupon = право на скидку / подарок
BellCoin = баланс клиента
Segment = характеристика клиента
Reward = то, что клиент может получить
```

## 6.2 Начисление BellCoin

```
order.status_changed.to_closed
  ↓
Loyalty проверяет accrual rules
  ↓
создаёт ledger transaction
  ↓
увеличивает balance клиента
```

## 6.3 Использование BellCoin

```
Order total: 150 000 сум
BellCoin balance: 20 000
Клиент хочет списать: 15 000 BellCoin
Discount: 15 000 сум
Remaining balance: 5 000 BellCoin
```

## 6.4 loyalty_accounts

```
loyalty_accounts
- id
- customer_id
- balance
- reserved_balance
- status: active / blocked
- created_at
- updated_at
```

## 6.5 loyalty_ledger_transactions

Все операции BellCoin должны идти через ledger.

```
loyalty_ledger_transactions
- id
- customer_id
- account_id
- order_id nullable
- event_id nullable
- type: accrual / redemption / reservation / release / reversal / adjustment / expire
- amount
- balance_after
- status: pending / completed / cancelled
- reason
- related_transaction_id nullable
- metadata json
- created_at
```

Примеры:

```
+3600  accrual     за закрытый заказ
-15000 redemption  использовано в заказе
+15000 release     заказ отменён, BellCoin возвращены
-3600  reversal    BellCoin отозваны при отмене закрытого заказа
-5000  expire      истёк срок действия
```

## 6.6 loyalty_accrual_rules

```
loyalty_accrual_rules
- id
- name
- trigger_event
- accrual_type: fixed / percent
- value
- min_order_amount
- max_accrual_amount nullable
- starts_at
- ends_at
- status
```

## 6.7 loyalty_redemption_rules

```
loyalty_redemption_rules
- id
- name
- min_order_amount
- max_redeem_amount
- max_redeem_percent
- allowed_delivery_types
- allow_with_coupon boolean default false
- starts_at
- ends_at
- status
```

## 6.8 Reserve / Commit / Release

BellCoin нельзя просто списывать на preview. Нужен безопасный lifecycle.

### Preview

```
Клиент видит available balance и maximum redeem amount.
Баланс ещё не меняется.
```

### Reserve

```
BEGIN;
SELECT loyalty_account FOR UPDATE;   ← lockForUpdate() в Laravel
Проверить available balance;
Увеличить reserved_balance;
Создать ledger transaction: reservation;
COMMIT;
```

### Commit

```
reservation → redemption
balance уменьшается окончательно
reserved_balance уменьшается
```

### Release

```
reservation → release
reserved_balance уменьшается
BellCoin возвращаются клиенту
```

---

# 7. IncentivePolicy / StackingRules

## 7.1 Назначение

StackingRules отвечают на вопрос:

```
Можно ли одновременно применить несколько incentives в одном заказе?
```

## 7.2 Типы incentives

```
coupon
bellcoin
free_delivery
free_product
free_combo
manual_discount
promotion_discount
```

## 7.3 MVP rules

```
coupon + BellCoin = not allowed
multiple coupons = not allowed
free_delivery + BellCoin = not allowed
free_product + BellCoin = not allowed
free_combo + BellCoin = not allowed
max_total_discount_amount = 80 000 сум
```

## 7.4 incentive_stacking_rules

```
incentive_stacking_rules
- id
- primary_incentive_type
- secondary_incentive_type
- is_allowed
- priority
- failure_reason
- starts_at
- ends_at
- status
```

Пример:

```
primary_incentive_type = coupon
secondary_incentive_type = bellcoin
is_allowed = false
failure_reason = COUPON_AND_BELLCOIN_NOT_STACKABLE
```

## 7.5 incentive_limit_rules

```
incentive_limit_rules
- id
- rule_type
- value
- status
```

Примеры:

```
max_total_discount_amount = 80000
max_bellcoin_redeem_percent = 100
max_bellcoin_redeem_amount = 80000
```

## 7.6 Приоритет правил

```
1. Global stacking rules
2. Coupon-specific stacking settings
3. Loyalty redemption rules
```

---

# 8. Checkout Incentive Flow

## 8.1 Preview endpoint

Только считает — ничего не резервирует и не пишет в БД.

```
POST /api/incentives/checkout/preview
```

Request:

```json
{
  "customer_id": 555,
  "order_total": 150000,
  "coupon_code": "PIZZA20",
  "use_bellcoin": false,
  "bellcoin_amount": 0,
  "items": []
}
```

Response:

```json
{
  "allowed": true,
  "discount_amount": 20000,
  "free_items": [
    { "iiko_product_id": "cinnamon_roll_001", "quantity": 1, "price": 0 }
  ],
  "final_amount": 130000,
  "payment_amount": 130000
}
```

Если rejected:

```json
{
  "allowed": false,
  "failure_reason": "COUPON_AND_BELLCOIN_NOT_STACKABLE"
}
```

## 8.2 Apply endpoint

Повторяет все проверки и резервирует.

```
POST /api/incentives/checkout/apply
```

Request — идентичен preview.

Внутри:

```
Validate coupon / customer_coupon
Validate BellCoin balance + redemption rules
Check StackingRules
Check total discount limits

BEGIN TRANSACTION
  coupon_usage → reserved
  customer_coupon → reserved
  BellCoin → reserved (lockForUpdate на loyalty_account)
  order_incentive_application → reserved (без order_id пока)
COMMIT
```

Response:

```json
{
  "incentive_application_id": "abc-123",
  "final_items": [...],
  "final_amount": 130000,
  "payment_amount": 130000,
  "discount_amount": 20000
}
```

## 8.3 Commit через Kafka (WaitCooking)

После успешного создания заказа Delivery IO публикует:

```
order.status_changed { status: WaitCooking, order_id: 999, incentive_application_id: "abc-123" }
```

Loyalty consumer:

```
Найти order_incentive_application по incentive_application_id
  ↓
BEGIN TRANSACTION
  order_incentive_application.order_id = 999
  order_incentive_application → applied
  coupon_usage → applied
  customer_coupon → used
  BellCoin reservation → committed
COMMIT
```

## 8.4 TTL Release job

Если WaitCooking event не пришёл за 5 минут — автоматический release.

```
Scheduled job каждую минуту:
  Найти order_incentive_applications
    WHERE status = reserved
    AND created_at < NOW() - 5 минут

  Для каждого:
    coupon_usage → cancelled
    customer_coupon → available
    BellCoin → released
    order_incentive_application → cancelled
```

Idempotent: если WaitCooking всё же пришёл после TTL release — consumer проверяет статус
application и игнорирует если уже cancelled.

## 8.5 Cancel endpoint

Если Delivery IO вернул ошибку — Mobile App явно отменяет:

```
POST /api/incentives/checkout/cancel
{ "incentive_application_id": "abc-123" }
```

```
coupon_usage → cancelled
customer_coupon → available
BellCoin → released
order_incentive_application → cancelled
```

## 8.6 order_incentive_applications

```
order_incentive_applications
- id
- incentive_application_id  ← UUID, генерируется при apply
- order_id nullable          ← заполняется после WaitCooking
- customer_id
- coupon_usage_id nullable
- bellcoin_transaction_id nullable
- total_discount_amount
- incentives_snapshot json
- status: reserved / applied / cancelled
- reserved_at
- applied_at nullable
- cancelled_at nullable
- created_at
```

Пример snapshot:

```json
{
  "coupon": {
    "coupon_code": "PIZZA20",
    "discount_amount": 20000
  },
  "free_items": [
    { "iiko_product_id": "cinnamon_roll_001", "quantity": 1, "price": 0, "source": "loyalty_promotion" }
  ],
  "bellcoin": null,
  "stacking_policy": {
    "coupon_with_bellcoin": false
  }
}
```

---

# 9. IIKO Catalog Snapshot

Loyalty & Promotion Service не должен обращаться к IIKO при validation/apply. Все проверки выполняются по локально синхронизированным данным.

```
iiko_organizations
iiko_cities
iiko_products
iiko_product_groups
iiko_menus
iiko_menu_items
iiko_product_availability
iiko_combos
iiko_combo_groups
iiko_combo_group_items
```

Важные правила:

- `raw_payload` хранится для всех IIKO сущностей.
- Если сущность исчезла из IIKO, физически не удаляем — ставим inactive.
- Runtime checkout не зависит от доступности IIKO.
- Combo хранится отдельно, не как обычный product.
- `product_iiko_id` в `coupon_actions` ссылается на `iiko_products.external_id`.

---

# 10. Поддержка combo

## MVP поддерживает

```
cart.has_combo
cart.combo_contains_product
cart.combo_contains_group
скидка на весь combo
скидка на весь order при наличии combo
бесплатное combo (action_type: free_combo)
```

## Не входит в MVP

```
скидка на отдельный item внутри combo
```

Причина: если IIKO отдаёт цену только на уровне combo, для item-level discount нужен отдельный mechanism discount allocation.

---

# 11. UI Condition & Action Builder

## Логика MVP

```
Condition 1 AND Condition 2 AND Condition 3
→ Action
→ Stacking settings / compatibility
```

## UI tabs

```
1. Основная информация
2. Условия выдачи
3. Условия использования
4. Действие
5. Compatibility / Stacking
6. Лимиты и сроки
7. Simulation
8. Logs
```

## Action tab

```
% скидка             → input: значение %, optional: max сумма
Фикс. скидка         → input: сумма в сумах
Бесплатная доставка  → без дополнительных параметров
Бесплатный продукт   → search по IIKO каталогу + quantity
Бесплатное combo     → search по IIKO combo + quantity
```

## Compatibility / Stacking tab

```
[ ] Можно использовать с BellCoin
[ ] Можно использовать с другими coupons
[ ] Можно использовать с free delivery
[ ] Можно использовать с free product
```

Глобальные правила всё равно имеют приоритет над настройками конкретного coupon.

---

# 12. Backend Architecture — Handler Registry

## Condition Handler Registry

```php
interface ConditionHandlerInterface {
    public function conditionType(): string;
    public function evaluate(CouponCondition $condition, ConditionContext $ctx): ConditionCheckResult;
}

class ConditionHandlerRegistry {
    public function register(string $type, ConditionHandlerInterface $handler): void;
    public function resolve(string $type): ConditionHandlerInterface;
}

class ConditionEvaluator {
    public function evaluate(array $conditions, ConditionContext $ctx): EvaluationResult;
}
```

## Action Handler Registry

```php
interface CouponActionHandlerInterface {
    public function actionType(): string;
    public function resolve(CouponAction $action, ApplyContext $ctx): ActionResult;
}

// Registered action handlers:
PercentDiscountHandler   → percent
FixedDiscountHandler     → fixed
FreeDeliveryHandler      → free_delivery
FreeProductHandler       → free_product
FreeComboHandler         → free_combo
```

## Loyalty handlers

```
AccrualRuleHandler
RedemptionRuleHandler
BellCoinReserveHandler
BellCoinCommitHandler
BellCoinReleaseHandler
```

---

# 13. Kafka Automatic Issuing & Loyalty Accrual

## Required events for MVP

```
order.status_changed
order.closed
order.cancelled
order.delayed
```

## Event processing flow

```
1. Получить Kafka event.
2. Вставить event в event_inbox по event_id.
3. Если event_id уже существует — ignore.
4. Обновить order projection.
5. Обновить customer_behavior_stats.
6. Проверить issue rules для coupons/rewards.
7. Проверить loyalty accrual rules.
8. Выдать customer_coupon или начислить BellCoin.
9. Опубликовать coupon.issued / loyalty.bellcoin_accrued.
10. Пометить event как processed.
```

## Idempotency

```
Kafka at-least-once delivery + idempotent business processing
unique(event_id) в event_inbox гарантирует однократную обработку
```

---

# 14. Local Order Projection

## Поддерживаемые статусы

```
Unconfirmed
WaitCooking
ReadyForCooking
CookingStarted
CookingCompleted
Waiting
OnWay
Checked
OnWayNow
Arrived
Delayed
Delivered
Closed
Cancelled
```

## Рекомендованная интерпретация статусов

| Status | Роль |
| --- | --- |
| Closed | Основной статус для completed order stats, milestone rewards и BellCoin accrual |
| Cancelled | Cancel usage / restore customer_coupon / release BellCoin |
| Delayed | Potential compensation trigger только с threshold |
| Delivered | Факт доставки; stats лучше считать по Closed |
| Early statuses | Хранить в projection/history, но не выдавать rewards автоматически |

---

# 15. CustomerInsights / Segmentation

```
CustomerInsights
  → говорит, к какому сегменту относится клиент

Promotion / Loyalty
  → решает, какой reward можно выдать или применить
```

## customer_behavior_stats

```
customer_behavior_stats
- customer_id primary key
- closed_orders_count
- delivered_orders_count
- cancelled_orders_count
- total_closed_amount
- average_order_value
- first_closed_order_at
- last_closed_order_at
- days_since_last_order
- avg_days_between_orders
- combo_orders_count
- favorite_iiko_product_id
- favorite_iiko_group_id
- coupon_usage_rate
- bellcoin_balance_snapshot
- late_deliveries_count
- updated_at
```

## Примеры сегментов

```
new_customer: closed_orders_count = 0
loyal_customer: closed_orders_count >= 5
vip_customer: closed_orders_count >= 30 OR total_closed_amount >= X
inactive_30_days: days_since_last_order >= 30
combo_lover: combo_orders_count >= 3
coupon_sensitive: coupon_usage_rate >= 60%
loyalty_active_user: bellcoin_used_count >= 1
high_bellcoin_balance: bellcoin_balance >= X
```

Контракт для Promotion/Loyalty:

```php
interface CustomerSegmentProviderInterface {
    public function hasSegment(int $customerId, string $segmentCode): bool;
    public function getSegments(int $customerId): array;
}
```

---

# 16. API endpoints

## Promotion / coupons

```
POST /api/coupons/validate
POST /api/coupons/apply
POST /api/coupons/{coupon_id}/simulate
GET  /api/coupon-builder/condition-definitions
GET  /api/coupon-builder/action-definitions
```

## CustomerWallet

```
GET  /api/customers/{customer_id}/coupons/available
POST /api/customers/{customer_id}/coupons/issue
POST /api/customer-coupons/{customer_coupon_id}/validate
POST /api/customer-coupons/{customer_coupon_id}/apply
```

## Loyalty / BellCoin

```
GET  /api/customers/{customer_id}/loyalty/balance
POST /api/loyalty/redemption/preview
POST /api/loyalty/redemption/reserve
POST /api/loyalty/redemption/commit
POST /api/loyalty/redemption/release
```

## Unified checkout incentives

```
POST /api/incentives/checkout/preview   ← только считает, ничего не пишет
POST /api/incentives/checkout/apply     ← резервирует, возвращает incentive_application_id
POST /api/incentives/checkout/cancel    ← явная отмена если Delivery IO вернул ошибку
```

---

# 17. Failure Reasons

```
COUPON_NOT_FOUND
CUSTOMER_COUPON_NOT_FOUND
CUSTOMER_COUPON_NOT_OWNED_BY_CUSTOMER
CUSTOMER_COUPON_NOT_AVAILABLE
COUPON_NOT_ACTIVE
COUPON_EXPIRED
COUPON_USAGE_LIMIT_REACHED
COUPON_CUSTOMER_LIMIT_REACHED
COUPON_CONDITION_NOT_MATCHED
COUPON_ACTION_NOT_FOUND
COUPON_ACTION_PRODUCT_INACTIVE
BELLCOIN_ACCOUNT_NOT_FOUND
BELLCOIN_INSUFFICIENT_BALANCE
BELLCOIN_REDEMPTION_LIMIT_EXCEEDED
BELLCOIN_RESERVATION_NOT_FOUND
COUPON_AND_BELLCOIN_NOT_STACKABLE
FREE_DELIVERY_AND_BELLCOIN_NOT_STACKABLE
FREE_PRODUCT_AND_BELLCOIN_NOT_STACKABLE
FREE_COMBO_AND_BELLCOIN_NOT_STACKABLE
MULTIPLE_COUPONS_NOT_ALLOWED
MAX_TOTAL_DISCOUNT_EXCEEDED
INVALID_CART_SNAPSHOT
INVALID_COMBO_STRUCTURE
INVALID_CONDITION_DEFINITION
INVALID_ACTION_DEFINITION
EVENT_ALREADY_PROCESSED
EVENT_OUT_OF_ORDER
INTERNAL_ERROR
```

---

# 18. План реализации по этапам

## Phase 1 — Promotion Core

- coupons
- coupon_conditions
- coupon_actions
- coupon_usages
- validate/apply MVP
- Handler Registry для conditions/actions

## Phase 2 — Catalog / IIKO Snapshot

- iiko_organizations, iiko_cities
- iiko_products, iiko_product_groups
- iiko_menus, iiko_menu_items
- iiko_combos, iiko_combo_groups, iiko_combo_group_items
- iiko_product_availability (stop list)
- CatalogSyncService + IikoApiClient
- combo conditions
- free_product / free_combo action

## Phase 3 — CustomerWallet

- customer_coupons
- manual issue
- available coupons endpoint
- validate/apply customer_coupon

## Phase 4 — Integration + Projection

- event_inbox
- Kafka consumer
- order projection
- customer_behavior_stats

## Phase 5 — Automatic Rewards

- coupon_issue_rules
- coupon_issue_conditions
- automatic issue engine
- reactivation condition
- coupon.issued event

## Phase 6 — Loyalty / BellCoin

- loyalty_accounts
- loyalty_ledger_transactions
- accrual rules
- redemption rules
- balance endpoint
- preview / reserve / commit / release

## Phase 7 — IncentivePolicy / StackingRules

- coupon + BellCoin compatibility
- multiple coupons rule
- total discount limits
- unified checkout preview/apply/cancel
- order_incentive_applications
- Kafka consumer: WaitCooking → commit (coupon_usage → applied, BellCoin → committed)
- TTL release job: reserved applications старше 5 минут → автоматический release

## Phase 8 — UI Builder & Admin UX

- condition definitions
- action definitions
- stacking settings
- simulation endpoint
- logs

## Phase 9 — Hardening

- metrics
- concurrency tests
- out-of-order event tests
- idempotency tests
- rebuild jobs
- documentation

---

# 19. Оценка сроков

## MVP без BellCoin

```
Promotion + CustomerWallet + Catalog + Kafka projection: 5–7 недель
```

## MVP с BellCoin и StackingRules

```
7–10 недель
```

## Полная production-версия

```
10–14 недель
```

Зависит от глубины UI builder, правил сегментации, отчётности и edge cases по refund/cancel.

---

# 20. Business Decisions

| # | Вопрос | Решение |
| --- | --- | --- |
| 1 | Можно ли использовать несколько coupons в одном заказе? | **Нет**. Только один coupon на заказ. |
| 2 | Можно ли использовать coupon и BellCoin вместе? | **Нет**. `coupon + BellCoin = not allowed`. |
| 3 | Можно ли использовать free_delivery и BellCoin вместе? | **Нет**. |
| 4 | Можно ли использовать free_product и BellCoin вместе? | **Нет**. |
| 5 | Какой maximum total discount для заказа? | **80 000 сум**. |
| 6 | Сколько BellCoin начислять за Closed order? | **2% от суммы заказа**. |
| 7 | От какой суммы начислять BellCoin? | От **products_final_amount** (без delivery fee). |
| 8 | Начислять BellCoin за delivery fee? | **Нет**. Только за products. |
| 9 | Можно ли использовать BellCoin для оплаты delivery fee? | **Нет**. Только к products amount. |
| 10 | Есть ли срок жизни BellCoin? | **Да, 1 год** с момента начисления. |
| 11 | Что делать с BellCoin при Cancelled/refund? | **Reversal transaction**. |
| 12 | Должен ли cancelled order возвращать customer_coupon в available? | **Да**. Idempotent. |
| 13 | Кто добавляет free_product в заказ? | **Loyalty & Promotion Service**. |
| 14 | Когда free_product виден клиенту? | **Только на checkout**. |
| 15 | Нужно ли показывать BellCoin в fiscal/receipt? | **Нет**. |
| 16 | Какие сегменты нужны в MVP? | Решим позже. Архитектура расширяемая. |

## 20.1. Итоговые правила StackingRules для MVP

```
multiple coupons = not allowed
coupon + BellCoin = not allowed
free_delivery + BellCoin = not allowed
free_product + BellCoin = not allowed
free_combo + BellCoin = not allowed
max_total_discount_amount = 80 000 сум
```

Failure reasons:

```
MULTIPLE_COUPONS_NOT_ALLOWED
COUPON_AND_BELLCOIN_NOT_STACKABLE
FREE_DELIVERY_AND_BELLCOIN_NOT_STACKABLE
FREE_PRODUCT_AND_BELLCOIN_NOT_STACKABLE
FREE_COMBO_AND_BELLCOIN_NOT_STACKABLE
MAX_TOTAL_DISCOUNT_EXCEEDED
```

## 20.2. Итоговые правила BellCoin

```
1 BellCoin = 1 сум
BellCoin accrual = 2% от products_final_amount
BellCoin не начисляется за delivery fee
BellCoin нельзя использовать для delivery fee
BellCoin expires after 1 year
Cancelled/refunded order → BellCoin reversal
```

Формула начисления:

```
bellcoin_accrual_amount = products_final_amount * 0.02

где:
products_final_amount = final_amount - delivery_fee
```

Начисление только после статуса `Closed`.

## 20.3. Reversal при Cancelled / Refund

```
type = reversal
amount = -original_accrual_amount
reason = ORDER_CANCELLED | ORDER_REFUNDED
related_transaction_id = original_accrual_transaction_id
```

Все изменения BellCoin только через `loyalty_ledger_transactions`. Нельзя уменьшать balance без ledger entry.

## 20.4. Customer coupon revert при Cancelled

```
coupon_usage → cancelled
customer_coupon → available
```

Idempotent: повторный `order.cancelled` event не должен вернуть coupon дважды.

## 20.5. Free product ownership

Delivery IO получает готовый результат от Loyalty & Promotion Service:

```json
{
  "free_items": [
    {
      "iiko_product_id": "cinnamon_roll_001",
      "quantity": 1,
      "price": 0,
      "source": "loyalty_promotion"
    }
  ]
}
```

Delivery IO не знает, почему продукт стал бесплатным.

## 20.6. Расширяемая архитектура сегментации

```php
interface CustomerSegmentProviderInterface {
    public function hasSegment(int $customerId, string $segmentCode): bool;
    public function getSegments(int $customerId): array;
}
```

MVP: computed segments из `customer_behavior_stats`.
V2: persisted `customer_segments` + `customer_segment_memberships`.

---

# 21. Финальная рекомендация

```
1.  Называть систему Loyalty & Promotion Service, а не Coupon Service.
2.  Модульный монолит на Laravel с нативными namespaces.
3.  Promotion отвечает за coupons, actions, conditions и builder.
4.  CustomerWallet отвечает за customer_coupons и usages.
5.  Loyalty отвечает за BellCoin, ledger и reserve/commit/release.
6.  CustomerInsights отвечает за stats и segmentation.
7.  Catalog отвечает за локальный IIKO snapshot.
8.  Integration отвечает за Kafka и внешние события.
9.  IncentivePolicy отвечает за совместимость rewards.
10. По умолчанию запретить coupon + BellCoin в одном заказе.
11. Все финансовые операции BellCoin только через ledger.
12. Проверять StackingRules до reserve BellCoin и до create coupon_usage.
13. BellCoin concurrency решается через lockForUpdate() + DB::transaction().
14. Kafka idempotency через event_inbox с unique(event_id).
```

---

# 22. iiko API Integration

Loyalty & Promotion Service использует iiko Cloud API как единственный источник
для синхронизации Catalog данных.

**Принцип:** Loyalty & Promotion Service никогда не обращается к iiko в runtime.
Все данные синхронизируются заранее через scheduled jobs и хранятся локально в Catalog модуле.

Base URL: `${IIKO_CLOUD_BASE_URL}` (например `https://api-ru.iiko.services/api`)

---

## 22.1 Используемые endpoints

| Endpoint | Метод | Версия | Назначение |
| --- | --- | --- | --- |
| `/1/access_token` | POST | v1 | Получить Bearer token |
| `/1/organizations` | POST | v1 | Список организаций с деталями |
| `/1/organizations/settings` | POST | v1 | Настройки организации |
| `/1/cities` | POST | v1 | Список городов по организациям |
| `/2/menu` | POST | **v2** | Список внешних меню |
| `/2/menu/by_id` | POST | **v2** | Полное дерево меню с ценами |
| `/1/stop_lists` | POST | v1 | Стоп-лист по организациям |
| `/1/nomenclature` | POST | v1 | Продукты, группы, ревизия |
| `/1/combo` | POST | v1 | Combo структуры |

> **Версии:** Menu использует `/2/` prefix. Все остальные endpoints — `/1/`.
> Реальный base URL уже содержит `/api`, поэтому пути начинаются с `/1/` и `/2/`.

---

## 22.2 Аутентификация

```
POST /1/access_token
Body: { "apiLogin": "xxxxxxxx" }
Response: { "token": "xxxxx..." }
```

Токен живёт ~60 минут. Кешируется в Laravel Cache на 3500 секунд.
Передаётся как `Authorization: Bearer {token}` во все последующие запросы.

---

## 22.3 Детали каждого endpoint

### /1/organizations

```json
POST /1/organizations
{
  "organizationIds": null,
  "returnAdditionalInfo": true,
  "includeDisabled": false
}
```

Возвращает список всех организаций доступных для API login.
`organizationId` используется как обязательный параметр во всех остальных запросах.
`organizationIds` получаются динамически — не хардкодятся в конфиге.
Это важно для поддержки нескольких стран (UZ, KZ, KG).

Хранится в: `iiko_organizations`

---

### /1/organizations/settings

```json
POST /1/organizations/settings
{
  "organizationIds": ["uuid"]
}
```

Возвращает настройки организации: валюта, временная зона, параметры доставки.
Нужно для корректного отображения сумм и времени в audit логах.

Хранится в: `iiko_organizations` (дополнительные поля)

---

### /1/cities

```json
POST /1/cities
{
  "organizationIds": ["uuid"]
}
```

Возвращает список городов с `id` и `name` по каждой организации.
Нужно для conditions типа `cart.delivery_city`.

Хранится в: `iiko_cities`

---

### /2/menu

```
POST /2/menu
(без body, только Authorization header)
```

Возвращает список внешних меню: `id`, `name`, `description`.
Нужно для получения `externalMenuId` перед вызовом `/2/menu/by_id`.

Хранится в: `iiko_menus`

---

### /2/menu/by_id

```json
POST /2/menu/by_id
{
  "externalMenuId": "menu_id",
  "organizationIds": ["uuid"],
  "priceCategoryId": null,
  "startRevision": 0
}
```

Возвращает полное дерево меню: категории → продукты → модификаторы → цены.
Основной источник актуальных цен для расчёта стоимости `free_product`.
Поддерживает инкрементальный sync через `startRevision`.

Хранится в: `iiko_menu_items`, `iiko_menu_item_modifiers`

---

### /1/stop_lists

```json
POST /1/stop_lists
{
  "organizationIds": ["uuid"]
}
```

Возвращает `terminalGroupStopLists` — недоступные продукты по каждому терминалу.

Используется при применении coupon action: если `free_product` из `coupon_action`
находится в стоп-листе — action отклоняется с `COUPON_ACTION_PRODUCT_INACTIVE`.

Хранится в: `iiko_product_availability`
Обновляется: каждые 5 минут или по iiko webhook `StopListUpdate`

---

### /1/nomenclature

```json
POST /1/nomenclature
{
  "organizationId": "uuid",
  "startRevision": 0
}
```

Возвращает master каталог:
- `products` — все продукты (id, name, type, groupId, sizePrices, isDeleted)
- `groups` — группы продуктов
- `productCategories` — категории
- `revision` — номер для инкрементального sync

Инкрементальный sync:
```json
{ "organizationId": "uuid", "startRevision": 123456 }
```
Возвращает только изменения с момента последней синхронизации.

`product_iiko_id` в `coupon_actions` ссылается на `products.id` из этого endpoint.

Хранится в: `iiko_products`, `iiko_product_groups`

---

### /1/combo

```json
POST /1/combo
{
  "organizationId": "uuid",
  "extraData": ["comboPriceGroups"]
}
```

Возвращает все combo с:
- `id`, `name`, `status`
- `comboSpecifications` — группы и допустимые продукты внутри каждой группы
- `priceGroups` — ценовые группы combo

`combo_iiko_id` в `coupon_actions` ссылается на `id` из этого endpoint.

Хранится в: `iiko_combos`, `iiko_combo_groups`, `iiko_combo_group_items`

---

## 22.4 Что НЕ используется

| Endpoint | Причина |
| --- | --- |
| `/1/deliveries/create` | Заказы создаёт Delivery IO |
| `/1/loyalty/iiko/*` | iiko loyalty не используется, есть своя система |
| `/1/addresses/*` | Адреса — ответственность Delivery IO |
| `/1/notifications/send` | Push — ответственность Notify Service |
| `/1/terminal_groups` | Уже интегрировано в другом сервисе Bellissimo |

---

## 22.5 Sync Strategy

### Full Sync (первый деплой, ручной запуск)

```bash
php artisan catalog:sync --full
```

Порядок выполнения:
1. `/1/access_token` → получить токен
2. `/1/organizations` → получить organizationIds динамически
3. `/1/organizations/settings` → настройки
4. `/1/cities` → список городов
5. `/1/nomenclature` (startRevision=0) → все продукты
6. `/2/menu` → список меню
7. `/2/menu/by_id` → детали каждого меню
8. `/1/combo` → все combo
9. `/1/stop_lists` → текущий стоп-лист

### Incremental Sync (каждые 30 минут)

```bash
php artisan catalog:sync --incremental
```

Использует сохранённый `revision` для nomenclature и menu.
Остальные данные синхронизируются полностью (меняются редко).

### StopList Sync (каждые 5 минут)

```bash
php artisan catalog:sync --stoplists
```

Только `/1/stop_lists`. Продукты заканчиваются в течение дня — нужна частая синхронизация.

### Webhook (опционально)

iiko поддерживает webhook на `StopListUpdate`.
Если настроен — StopList Sync по расписанию можно отключить.

---

## 22.6 CatalogSyncService — структура

```
app/Catalog/
├── Http/
│   └── IikoApiClient.php               ← HTTP клиент с auth + retry
└── Services/
    ├── CatalogSyncService.php          ← оркестратор
    ├── OrganizationSyncService.php     ← /1/organizations + /1/organizations/settings
    ├── CitySyncService.php             ← /1/cities
    ├── NomenclatureSyncService.php     ← /1/nomenclature
    ├── MenuSyncService.php             ← /2/menu + /2/menu/by_id
    ├── ComboSyncService.php            ← /1/combo
    └── StopListSyncService.php         ← /1/stop_lists
```

---

## 22.7 IikoApiClient (Laravel)

```php
// app/Catalog/Http/IikoApiClient.php
namespace App\Catalog\Http;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Response;

class IikoApiClient
{
    private string $baseUrl;
    private string $apiLogin;

    public function __construct()
    {
        $this->baseUrl  = config('iiko.cloud_base_url');
        $this->apiLogin = config('iiko.api_login');
    }

    public function getToken(): string
    {
        return Cache::remember('iiko_access_token', 3500, function () {
            return $this->post('/1/access_token', [
                'apiLogin' => $this->apiLogin,
            ], withAuth: false)->json('token');
        });
    }

    public function post(string $endpoint, array $body = [], bool $withAuth = true): Response
    {
        $request = Http::retry(3, 1000)->baseUrl($this->baseUrl);

        if ($withAuth) {
            $request = $request->withToken($this->getToken());
        }

        return $request->post($endpoint, $body)->throw();
    }
}
```

---

## 22.8 Конфигурация

```php
// config/iiko.php
return [
    'cloud_base_url' => env('IIKO_CLOUD_BASE_URL', 'https://api-ru.iiko.services/api'),
    'api_login'      => env('IIKO_API_LOGIN'),
    'sync' => [
        'incremental_interval_minutes' => 30,
        'stoplist_interval_minutes'    => 5,
    ],
];
```

```env
IIKO_CLOUD_BASE_URL=https://api-ru.iiko.services/api
IIKO_API_LOGIN=xxxxxxxx
```

`organizationIds` получаются динамически через `/1/organizations` при каждом sync,
не хардкодятся в конфиге — это необходимо для поддержки нескольких стран (UZ, KZ, KG).
