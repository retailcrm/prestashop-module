# Connection

## Order number

Данные опции позволяют передавать номер заказа при синхронизации заказов между PrestaShop и Simla.com.

### Опция "Send order number to Simla.com"

|  Статус     |  Номер заказа в PrestaShop  |  Номер заказа в Simla.com                                      |
|-------------|-----------------------------|----------------------------------------------------------------|
|  Включена   |  `reference`                |  `reference`                                                   |
|  Выключена  |  `reference`                |  Cоответствует шаблону в CRM для заказа, созданного через API  |

### Опция "Receive order number from Simla.com"

|  Статус     |  Номер заказа в Simla.com     |  Номер заказа в PrestaShop                                   |
|-------------|-------------------------------|--------------------------------------------------------------|
|  Включена   |  Cоответствует шаблону в CRM  |  Cоответствует шаблону в CRM                                 |
|  Выключена  |  Cоответствует шаблону в CRM  |  `reference`                                                 |

Вернуть логику передачи внешнего ID заказа в Simla.com можно путём указания в настройках CRM в поле "Шаблон генерации номера заказа из API" значения `{external_id}`.
