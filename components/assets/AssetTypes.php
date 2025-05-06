<?php

/**
 * Класс для работы с типами ассетов приложения.
 * 
 * Определяет структуру и допустимые типы ресурсов, их псевдонимы путей 
 * и предоставляет методы валидации.
 * 
 * ### Основные типы ассетов:
 * - **JS**: Клиентские скрипты (например, `main.js`)
 * - **CSS**: Стили (например, `styles.css`)
 * - **KOJS**: Knockout.js компоненты
 * - **RES**: Статические ресурсы (изображения, шрифты)
 * - **LANG**: Локализованные языковые файлы
 * 
 * @package components.assets
 */
class AssetTypes
{
    const TYPE_JS = 'js';
    const TYPE_CSS = 'css';
    const TYPE_KOJS = 'kojs';
    const TYPE_RES = 'resources';
    const TYPE_LANG = 'lang';

    private static array $aliases = [
        self::TYPE_JS => 'application.assets.js.project',
        self::TYPE_CSS => 'application.assets.css.project',
        self::TYPE_KOJS => 'application.assets.js.kojs-components',
        self::TYPE_RES => 'application.assets.resources',
        self::TYPE_LANG => 'application.lang.js.project',
    ];

    /**
     * Возвращает псевдоним пути для указанного типа ассета.
     * 
     * @param string $type Одна из констант TYPE_*
     * @return string Псевдоним пути (например, `application.assets.js.project`)
     * 
     * @throws InvalidArgumentException Если тип не существует
     * 
     * @example
     * AssetTypes::getAlias(AssetTypes::TYPE_JS); // 'application.assets.js.project'
     */
    public static function getAlias(string $type): string
    {
        if (!isset(self::$aliases[$type])) {
            throw new InvalidArgumentException("Invalid asset type: $type");
        }
        return self::$aliases[$type];
    }

    /**
     * Проверяет валидность типа ассета.
     * 
     * @param string $type Проверяемый тип
     * @throws InvalidAssetTypeException Если тип не поддерживается
     */
    public static function validateType(string $type): void
    {
        if (!array_key_exists($type, self::$aliases)) {
            throw new InvalidAssetTypeException("Unsupported asset type: $type");
        }
    }

    /**
     * Возвращает список всех доступных типов ассетов.
     * 
     * @return array<string> Массив констант TYPE_*
     * 
     * @example
     * AssetTypes::getAllTypes(); // ['js', 'css', 'kojs', ...]
     */
    public static function getAllTypes(): array
    {
        return array_keys(self::$aliases);
    }

    /**
     * Проверяет, является ли тип ассета скриптом (JS или KOJS).
     * 
     * @param string $type Проверяемый тип
     * @return bool 
     * 
     * @example
     * AssetTypes::isScriptType(AssetTypes::TYPE_JS); // true
     */
    public static function isScriptType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_JS,
            self::TYPE_KOJS,
        ]);
    }
}
