<?php

/**
 * Интерфейс для стратегий проверки CSRF-токена.
 * 
 * Определяет контракт для реализации различных методов валидации запросов 
 * на соответствие политикам защиты от межсайтовой подделки запросов (CSRF).
 * 
 * @package components.security
 */
interface CsrfValidationStrategyInterface
{
    /**
     * Выполняет проверку запроса на соответствие CSRF-политикам.
     * 
     * @param CHttpRequest $request Объект HTTP-запроса
     * @throws CsrfValidationException При нарушении политик безопасности
     * 
     * @example
     * $strategy = new SameHostStrategy();
     * $strategy->validate(Yii::app()->request);
     */
    public function validate(CHttpRequest $request): void;
}
