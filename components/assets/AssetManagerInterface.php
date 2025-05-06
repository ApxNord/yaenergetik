<?php

/**
 * Интерфейс для управления ресурсами (CSS, JS) приложения.
 * 
 * Обеспечивает единый способ регистрации скриптов, стилей и их компонентов,
 * интегрируясь с системой ассетов Yii.
 */
interface AssetManagerInterface
{
    /**
     * Регистрирует основные ресурсы приложения.
     * 
     * Включает:
     * - Базовые скрипты (например, jQuery, Bootstrap)
     * - Шрифты (например, Open Sans)
     * - Иконки (favicon)
     * - Метатег viewport
     * 
     * @example
     * $assetManager->registerCoreAsset();
     */
    public function registerCoreAsset(): void;
    
    /**
     * Регистрирует JS-файл и инициализирует его экспорт.
     * 
     * @param string $name Имя файла (без расширения) из папки assets/js
     * @param array $data Данные для передачи в JS-модуль
     * @param string|null $exports Имя глобальной переменной для экспорта (автогенерация, если null)
     * 
     * @example
     * $assetManager->registerJs('chart', ['color' => '#ff0000'], 'ChartModule');
     * // Создаст переменную ChartModule с переданными данными
     */
    public function registerJs(string $name, array $data = [], $exports = null): void;

    /**
     * Регистрирует CSS-файл.
     * 
     * @param string $name Имя файла (без расширения) из папки assets/css
     * 
     * @example
     * $assetManager->registerCss('print-styles');
     */
    public function registerCss(string $name): void;

    /**
     * Регистрирует JS-функцию как отдельный файл.
     * 
     * @param string $name Базовое имя файла (например, 'utils')
     * @param string $func Тело функции (без обертки в function(){...})
     * @param array $data Аргументы для вызова функции
     * 
     * @example
     * $assetManager->registerJsFunction('geo', 'alert("Location: " + data.lat)', ['lat' => 55.76]);
     * // Создаст функцию geo.init({lat: 55.76})
     */
    public function registerJsFunction(string $name, string $func, array $data = []): void;

    /**
     * Добавляет произвольный CSS-код на страницу.
     * 
     * @param string $css CSS-правила (без тегов <style>)
     * @param string|null $id Уникальный идентификатор стиля (автогенерация, если null)
     * @param string $media Медиа-запрос (например, 'screen and (max-width: 600px)')
     * 
     * @example
     * $assetManager->registerCssPiece('.error {color: red}', 'error-styles');
     */
    public function registerCssPiece($css, $id = null, $media = ''): void;

    /**
     * Регистрирует CSS/JS пакет через Utils.
     * 
     * @param string $name Имя пакета из config/packages.php
     */
    public function registerPackage(string $name): void;

    /**
     * Публикует и возвращает URL ресурса.
     * 
     * @param string $name Имя файла из папки resources
     * @return string Абсолютный URL
     */
    public function registerResource(string $name): string;
}
