<?php

interface BrandingProviderInterface
{
    /**
     * Возвращает название приложения.
     * 
     * Приоритеты:
     * 1. Бренд пользователя
     * 2. Партнерский бренд
     * 3. Название по умолчанию
     * 
     * @return string
     */
    public function getApplicationName(): string;

    /**
     * Возвращает абсолютный путь к логотипу.
     * 
     * @return string|null URL логотипа или null
     */
    public function getLogoPath(): ?string;

    /**
     * Возвращает стили меню в виде массива.
     * 
     * @return array<string, mixed> Пример: ['color' => '#fff', 'fontSize' => '14px']
     */
    public function getApplicationMenuStyles(): array;

    /**
     * Возвращает язык интерфейса.
     * 
     * Приоритеты:
     * 1. Язык текущего пользователя
     * 2. Системный язык
     * 
     * @return string Код языка (например, 'ru_RU')
     */
    public function getApplicationLanguage(): string;
}
