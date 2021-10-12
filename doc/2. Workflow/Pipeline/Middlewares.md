# Middlewares

Pipeline содержит стек middleware. В глубине стека выполняется callback-функция, переданная в метод setAction класса *RetailcrmPipeline*.

Для добавления дополнительной логики при обработке запроса вы можете добавить новую middleware в pipeline.
Для этого требуется создать класс, реализующий интерфейс *RetailcrmMiddlewareInterface* и добавить имя класса в массив, который передаётся в метод setMiddlewares класса RetailcrmPipeline.

Порядок в массиве, передаваемом в *setMiddlewares()* определяет позицию middleware в стеке. Напрмер, массив `[MW1, MW2, MW3]` приведет к вызову `MW1 -> MW2 -> MW3 -> ACTION -> MW3 -> MW2 -> MW1`.
К middleware применяются фильтры из папки `custom/hooks`

В методе *__invoke()* middleware-класса должна быть описана логика, которую вы хотите выполнить при обработке запроса, а так же вызов следующей middleware в стеке.