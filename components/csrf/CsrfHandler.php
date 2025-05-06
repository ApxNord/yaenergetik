<?php

/**
 * Класс для управления CSRF-валидацией запросов.
 * 
 * ### Основные функции:
 * - Определение стратегии проверки на основе правил контроллера
 * - Выполнение проверки CSRF через стратегии
 * - Преобразование ошибок в HTTP-исключения
 * 
 * @package components.security
 */
class CsrfHandler
{
    /**
     * Выполняет CSRF-валидацию запроса.
     * 
     * @param int $rule Идентификатор правила (константа из CsrfStrategyFactory)
     * @param CHttpRequest $request Объект запроса
     * 
     * @throws CHttpException 403 при ошибке валидации
     * 
     * @example
     * $handler->validate(CsrfStrategyFactory::ALLOW_SAME, Yii::app()->request);
     */
    public function validate(int $rule, CHttpRequest $request): void
    {
        $strategy = CsrfStrategyFactory::create($rule);
        
        try {
            $strategy->validate($request);
        } catch (CsrfValidationException $e) {
            Log::warning('CSRF validation failed: ' . $e->getMessage());
            throw new CHttpException(403, $e->getMessage());
        }
    }

    /**
     * Определяет применяемое правило CSRF для экшена.
     * 
     * @param mixed $controllerRules Правила из контроллера (int|array)
     * @param CAction $action Текущий экшен
     * @return int Идентификатор правила
     * 
     * @example
     * // Для правил ['create' => 1, '*' => 0] и экшена 'update':
     * $rule = resolveRule($rules, $action); // 0
     */
    public function resolveRule($controllerRules, CAction $action): int
    {
        if (is_int($controllerRules)) {
            return $controllerRules;
        }
        
        return $controllerRules[$action->getId()] 
            ?? ($controllerRules['*'] ?? CsrfStrategyFactory::DEFAULT_RULE);
    }
}
