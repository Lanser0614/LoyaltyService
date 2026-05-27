# Алгоритм купонов и incentive checkout

Документ для разработчика: как backend применяет купоны, BellCoin и другие incentive-механики в checkout.

Основной код:

- `modules/Checkout/Http/Controllers/CheckoutIncentiveController.php`
- `modules/Checkout/Services/CheckoutIncentiveService.php`
- `modules/Promotion/Services/CouponResolverService.php`
- `modules/Promotion/Services/ConditionEvaluator.php`
- `modules/Promotion/Services/CouponActionResolverService.php`
- `modules/Promotion/Services/CouponUsageLimitService.php`
- `modules/IncentivePolicy/Services/StackingPolicyService.php`

## Главная идея

Checkout сначала делает расчет, а потом резервирует результат.

Есть три основных сценария:

1. `preview` - проверить, можно ли применить купон/BellCoin, и вернуть расчет без записи в БД.
2. `apply` - повторить расчет и создать reservation в БД.
3. `cancel` / `commitByWaitCooking` - отменить reservation или финализировать ее после подтверждения заказа.

Это важно: `preview` не гарантирует, что `apply` пройдет позже. Между ними могут измениться лимиты, статус купона, баланс BellCoin или срок действия.

## HTTP entry points

### `POST /api/incentives/checkout/preview`

Контроллер:

```text
CheckoutIncentiveController::preview()
```

Flow:

1. Валидирует payload.
2. Создает `CheckoutIncentiveRequest`.
3. Вызывает `CheckoutIncentiveService::preview()`.
4. Возвращает:
   - `allowed = true` и расчет, если все прошло;
   - `allowed = false` и `failure_reason`, если есть бизнес-ошибка.

`preview` не создает:

- `coupon_usages`
- `order_incentive_applications`
- BellCoin reservation

### `POST /api/incentives/checkout/apply`

Контроллер:

```text
CheckoutIncentiveController::apply()
```

Flow:

1. Валидирует payload.
2. Создает `CheckoutIncentiveRequest`.
3. Вызывает `CheckoutIncentiveService::apply()`.
4. Внутри transaction создает reservation.
5. Возвращает `incentive_application_id`, финальные items и суммы.

Если купон отклонен, возвращается HTTP `422`:

```json
{
  "allowed": false,
  "failure_reason": "COUPON_NOT_ACTIVE"
}
```

### `POST /api/incentives/checkout/cancel`

Контроллер:

```text
CheckoutIncentiveController::cancel()
```

Принимает:

```json
{
  "incentive_application_id": "uuid"
}
```

Отменяет reservation, если она еще в статусе `reserved`.

## Входной DTO

Payload превращается в:

```text
Modules\Checkout\DTOs\CheckoutIncentiveRequest
```

Основные поля:

- `customerId` - клиент.
- `orderTotal` - сумма заказа.
- `items` - товары в корзине.
- `couponCode` - уникальный код купона или промокода.
- `useBellCoin` - хочет ли клиент списать BellCoin.
- `bellCoinAmount` - сумма списания BellCoin.
- `deliveryFee` - стоимость доставки.

Купон считается переданным только если есть `couponCode`.

В checkout нет отдельного id купона клиента: пользователь передает только код купона.

## Центральный метод `calculate()`

Основной алгоритм находится в:

```text
CheckoutIncentiveService::calculate()
```

Метод вызывается и из `preview`, и из `apply`.

Порядок работы:

1. Найти купон.
2. Проверить активность купона.
3. Проверить лимиты использования.
4. Проверить условия применения.
5. Посчитать действия купона.
6. Если используется BellCoin, проверить BellCoin redemption.
7. Проверить stacking policy.
8. Посчитать итоговую сумму.
9. Вернуть расчет и snapshot.

Псевдокод:

```text
coupon = resolve(request)

if coupon exists:
    load conditions and actions
    assert coupon is active and in date range
    assert usage limits are not exceeded
    assert all usage conditions match
    actionResult = resolve coupon actions

if BellCoin is used:
    validate BellCoin redemption

couponDiscount = actionResult.discountAmount or 0
bellCoinDiscount = request.bellCoinAmount if BellCoin is used
totalDiscount = couponDiscount + bellCoinDiscount

assert stacking policy allows this combination

finalAmount = max(0, orderTotal - totalDiscount)

return calculation snapshot
```

## Поиск купона

Класс:

```text
CouponResolverService
```

### Купон по коду

Если передан `coupon_code`, система ищет:

```text
coupons.code = coupon_code
```

Если запись не найдена, выбрасывается:

```text
COUPON_NOT_FOUND
```

`coupon_kind` не участвует в поиске при checkout. Если код уникальный, backend может применить как обычный промокод, так и купон, созданный под отдельную кампанию.

## Проверка активности купона

Метод:

```text
CouponResolverService::assertCouponActive()
```

Проверяется:

- `coupon.status === active`
- `starts_at` пустой или уже наступил
- `ends_at` пустой или еще не наступил

Ошибки:

- `COUPON_NOT_ACTIVE`
- `COUPON_EXPIRED`

## Лимиты использования

Класс:

```text
CouponUsageLimitService
```

Лимиты проверяются по таблице `coupon_usages`.

Учитываются только статусы:

- `reserved`
- `applied`

`cancelled` и `expired` не занимают лимит.

### `usage_limit_total`

Общий лимит на купон.

Если количество `reserved + applied` записей по `coupon_id` больше или равно лимиту, выбрасывается:

```text
COUPON_USAGE_LIMIT_REACHED
```

### `usage_limit_per_customer`

Лимит на одного клиента.

Если количество `reserved + applied` записей по `coupon_id + customer_id` больше или равно лимиту, выбрасывается:

```text
COUPON_CUSTOMER_LIMIT_REACHED
```

## Conditions

Класс:

```text
ConditionEvaluator
```

У купона есть `conditions`.

Алгоритм:

1. Перебрать все `coupon.conditions`.
2. Для каждого условия найти handler по `condition_type`.
3. Выполнить `handler->evaluate()`.
4. Если хотя бы одно условие не прошло, остановить расчет и выбросить ошибку.

Логика условий работает как `AND`: должны пройти все условия.

### Зарегистрированные condition handlers

Регистрация находится в:

```text
App\Providers\LoyaltyPromotionServiceProvider
```

Сейчас есть:

```text
cart.min_amount
customer.segment
cart.has_combo
```

### `cart.min_amount`

Handler:

```text
MinAmountConditionHandler
```

Берет:

```text
condition.value
```

Проверяет:

```text
request.orderTotal >= condition.value
```

Если сумма заказа меньше, ошибка:

```text
COUPON_CONDITION_NOT_MATCHED
```

### `customer.segment`

Handler:

```text
CustomerSegmentConditionHandler
```

Берет segment code из:

```text
condition.payload.segment_code
```

Если его нет, берет:

```text
condition.value
```

Дальше вызывает:

```text
CustomerSegmentProviderInterface::hasSegment(customerId, segmentCode)
```

Если клиент не в сегменте, ошибка:

```text
COUPON_CONDITION_NOT_MATCHED
```

### `cart.has_combo`

Handler:

```text
CartHasComboConditionHandler
```

Проверяет, есть ли среди `request.items` хотя бы один item с заполненным `comboIikoId`.

Если combo нет, ошибка:

```text
COUPON_CONDITION_NOT_MATCHED
```

## Issue Conditions

`issueConditions` хранятся отдельно от `conditions`.

Текущий checkout algorithm не проверяет `issueConditions` при применении купона.

Их назначение - использовать при автоматической выдаче купона. То есть это слой issuing/campaign logic, а не checkout validation.

Сейчас реализован issuing flow для гарантии доставки:

```text
DeliveryGuaranteeCouponIssueService::issueFromDeliveredEvent()
```

Он обрабатывает Kafka payload с:

```text
event_type = Delivered
payload.timestamps.cooking_start_at
payload.timestamps.delivered_at
```

Условие template coupon:

```text
condition_type = order.delivery_time_over
operator = >
value = 35
```

Алгоритм:

1. Посчитать `duration = delivered_at - cooking_start_at`.
2. Найти active template coupon с `coupon_kind = issued_customer_coupon` и issue condition `order.delivery_time_over`.
3. Проверить `issue_limit_total` и `issue_limit_per_customer`.
4. Проверить, что по этому `source_order_id` купон еще не выдавался.
5. Создать новый активный coupon с уникальным `code`.
6. Скопировать из template coupon usage conditions и actions.
7. Записать source поля:
   - `issued_from_coupon_id`
   - `issued_customer_id`
   - `issued_customer_phone`
   - `source_order_id`
   - `source_event_id`

Template coupon не применяется в checkout напрямую: resolver не принимает `issued_customer_coupon` без `issued_from_coupon_id`.

## Actions

Класс:

```text
CouponActionResolverService
```

У купона есть `actions`.

Алгоритм:

1. Если actions пустые, ошибка `COUPON_ACTION_NOT_FOUND`.
2. Создать пустой `ActionResult`.
3. Пройти по каждому action.
4. Найти handler по `action_type`.
5. Выполнить action.
6. Смерджить результат в общий `ActionResult`.

Несколько actions в одном купоне разрешены на уровне алгоритма. Их результаты объединяются.

### Зарегистрированные action handlers

Сейчас есть:

```text
percent
fixed
free_delivery
free_product
free_combo
```

### `percent`

Handler:

```text
PercentDiscountHandler
```

Расчет:

```text
rawDiscount = floor(orderTotal * value / 100)
discount = min(rawDiscount, max_discount_amount) если max_discount_amount заполнен
```

Возвращает money discount.

### `fixed`

Handler:

```text
FixedDiscountHandler
```

Расчет:

```text
discount = min(value, orderTotal)
```

Скидка не может быть больше суммы заказа.

### `free_delivery`

Handler:

```text
FreeDeliveryHandler
```

Расчет:

```text
discount = deliveryFee
```

Возвращает тип incentive `FreeDelivery`.

### `free_product`

Handler:

```text
FreeProductHandler
```

Создает `FreeItemDTO`:

```text
iikoProductId = action.product_iiko_id
quantity = max(1, action.quantity)
price = action.price_override или 0
```

Денежную скидку не добавляет. Бесплатный товар возвращается отдельно в `free_items`.

### `free_combo`

Handler:

```text
FreeComboHandler
```

Возвращает snapshot:

```text
combo_iiko_id
quantity
price
source = loyalty_promotion
```

Денежную скидку не добавляет. Это сигнал для следующего слоя, который должен правильно добавить combo в заказ.

## BellCoin

BellCoin применяется независимо от купона, но проходит общую stacking policy.

Если:

```text
request.useBellCoin = true
request.bellCoinAmount > 0
```

то система:

1. В `calculate()` вызывает `BellCoinReserveService::validateRedemption()`.
2. В `apply()` внутри transaction вызывает `BellCoinReserveService::reserve()`.
3. В `cancel()` освобождает reservation.
4. В `commitByWaitCooking()` финализирует reservation.

BellCoin discount добавляется к coupon discount:

```text
totalDiscount = couponDiscount + bellCoinAmount
```

## Stacking policy

Класс:

```text
StackingPolicyService
```

Проверяет, можно ли совмещать выбранные incentives.

Правила сейчас:

1. Купон + BellCoin запрещены, если у купона `is_stackable_with_bellcoin = false`.
2. `free_delivery` + BellCoin запрещены.
3. `free_product` + BellCoin запрещены.
4. `free_combo` + BellCoin запрещены.
5. Общая скидка не должна превышать `config('loyalty.max_total_discount_amount', 80000)`.

Возможные ошибки:

- `COUPON_AND_BELLCOIN_NOT_STACKABLE`
- `FREE_DELIVERY_AND_BELLCOIN_NOT_STACKABLE`
- `FREE_PRODUCT_AND_BELLCOIN_NOT_STACKABLE`
- `FREE_COMBO_AND_BELLCOIN_NOT_STACKABLE`
- `MAX_TOTAL_DISCOUNT_EXCEEDED`

Важно: поле `is_stackable_with_other_coupons` сейчас есть в модели и админке, но текущий checkout payload не поддерживает несколько купонов одновременно. Поэтому это поле пока не участвует в основном checkout flow.

## Расчет сумм

После actions и BellCoin:

```text
couponDiscount = actionResult.discountAmount или 0
bellCoinDiscount = bellCoinAmount, если BellCoin используется
totalDiscount = couponDiscount + bellCoinDiscount
finalAmount = max(0, orderTotal - totalDiscount)
paymentAmount = finalAmount
```

`free_items` не уменьшают `finalAmount` напрямую. Они возвращаются как отдельные позиции, которые нужно добавить к заказу.

## Snapshot

`calculate()` возвращает snapshot:

```text
coupon:
  coupon_id
  coupon_code
  discount_amount
  actions
bellcoin:
  amount
free_items:
  list of free item DTOs
stacking_policy:
  coupon_with_bellcoin
```

При `apply()` snapshot сохраняется в:

```text
order_incentive_applications.incentives_snapshot
```

Цель snapshot:

- видеть, почему была рассчитана скидка;
- иметь audit trail;
- не зависеть полностью от будущих изменений купона.

## Apply reservation

Метод:

```text
CheckoutIncentiveService::apply()
```

После успешного `calculate()` выполняется DB transaction.

Внутри transaction:

1. Если был купон, создается `coupon_usages` со статусом `reserved`.
2. Если был BellCoin, создается BellCoin reservation.
3. Создается `order_incentive_applications` со статусом `reserved`.
4. Возвращается `incentive_application_id`.

### `coupon_usages`

Создается запись:

```text
coupon_id
customer_id
status = reserved
discount_amount
free_items_snapshot
reservation_key = uuid
```

Связь с отдельным customer coupon в текущем checkout flow не заполняется.

### `order_incentive_applications`

Создается запись:

```text
incentive_application_id = uuid
customer_id
coupon_usage_id
bellcoin_transaction_id
total_discount_amount
incentives_snapshot
status = reserved
reserved_at = now()
```

Эта запись связывает checkout/order lifecycle с coupon usage и BellCoin reservation.

## Cancel

Метод:

```text
CheckoutIncentiveService::cancel()
```

Работает только если application в статусе `reserved`.

Flow:

1. Находит `order_incentive_applications` по `incentive_application_id`.
2. Берет lock через `lockForUpdate()`.
3. Если статус уже не `reserved`, ничего не делает.
4. Если есть `coupon_usage_id`, переводит usage в `cancelled`.
5. Если есть BellCoin reservation, освобождает ее.
6. Переводит application в `cancelled`.

Cancel можно вызвать явно через API или автоматически через release expired reservations.

## Commit by WaitCooking

Метод:

```text
CheckoutIncentiveService::commitByWaitCooking()
```

Вызывается из Kafka consumer:

```text
OrderStatusChangedConsumer
```

Условие:

```text
payload.status === WaitCooking
payload.incentive_application_id is not empty
```

Flow:

1. Находит `order_incentive_applications`.
2. Берет lock через `lockForUpdate()`.
3. Если статус уже не `reserved`, ничего не делает.
4. Если есть coupon usage:
   - записывает `order_id`;
   - переводит usage в `applied`;
   - ставит `applied_at`.
5. Если есть BellCoin reservation:
   - вызывает `commitReservation()`.
6. Переводит application в `applied`.

То есть фактическое списание/использование закрепляется не на `apply`, а когда заказ дошел до статуса `WaitCooking`.

## Expired reservations

Класс:

```text
ExpiredReservationReleaseService
```

Команда:

```text
incentives:release-expired-reservations
```

Scheduler запускает ее каждую минуту.

Логика:

1. Берет `order_incentive_applications` в статусе `reserved`.
2. Ищет записи старше `config('loyalty.reservation_ttl_minutes', 5)`.
3. Для каждой вызывает `CheckoutIncentiveService::cancel()`.

Это освобождает лимиты купона и BellCoin reservation, если checkout не был завершен.

## Lifecycle статусов

### `order_incentive_applications`

```text
reserved -> applied
reserved -> cancelled
```

`applied` ставится при `WaitCooking`.

`cancelled` ставится при ручной отмене или истечении reservation.

### `coupon_usages`

```text
reserved -> applied
reserved -> cancelled
```

В enum также есть `expired`, но текущий cancel flow переводит expired reservations в `cancelled`.

## Где расширять алгоритм

### Добавить новый condition type

1. Создать handler, реализующий `ConditionHandlerInterface`.
2. Вернуть уникальный `conditionType()`.
3. Зарегистрировать handler в `LoyaltyPromotionServiceProvider`.
4. Добавить option в `CouponResource::conditionSchema()`.
5. Добавить тесты для positive/negative cases.

### Добавить новый action type

1. Создать handler, реализующий `CouponActionHandlerInterface`.
2. Вернуть уникальный `actionType()`.
3. Зарегистрировать handler в `LoyaltyPromotionServiceProvider`.
4. Добавить поля в `coupon_actions`, если нужны новые данные.
5. Добавить option и conditional fields в `CouponResource`.
6. Обновить snapshot/result DTO, если action возвращает новый тип результата.
7. Добавить stacking rules, если action нельзя совмещать с BellCoin или другими incentives.

### Добавить несколько купонов в один checkout

Сейчас алгоритм рассчитан на один купон:

- `coupon_code` один;
- `CouponResolverService::resolve()` возвращает `?Coupon`;
- `order_incentive_applications` хранит один `coupon_usage_id`.

Для multi-coupon нужно менять контракт payload, resolver, usage reservation, stacking policy и snapshot.

## Что важно помнить

- `preview` не пишет в БД.
- `apply` резервирует, но еще не финализирует использование.
- Финализация происходит на `WaitCooking`.
- Лимиты считают `reserved` и `applied`, поэтому незавершенные reservations временно занимают лимит.
- Expired reservation cleanup нужен, чтобы лимиты не зависали.
- `Usage Conditions` проверяются в checkout.
- `Issue Conditions` сейчас не участвуют в checkout flow.
- `free_product` и `free_combo` возвращают free items/snapshot, но не уменьшают `finalAmount` напрямую.
- `is_stackable_with_other_coupons` пока не используется, потому что checkout принимает только один купон.
