<?php

/**
 * Класс для инициализации языковых настроек приложения.
 * 
 * ### Основные функции:
 * - Установка текущего языка из брендинга
 * - Регистрация языковых скриптов
 * - Интеграция с системой локализации Ascue
 * 
 * @package components.i18n
 */
class LanguageInitializer
{
    /**
     * Инициализирует языковые настройки приложения.
     * 
     * @param BrandingProviderInterface $branding Поставщик брендинг-данных
     * @throws InvalidArgumentException Если язык не поддерживается
     */
    public function init(BrandingProviderInterface $branding): void
    {
        Yii::app()->language = $branding->getApplicationLanguage();
        Ascue\Lang\Lang::set(Yii::app()->language);
        $this->registerLangScripts();
    }

    /**
     * Регистрирует клиентские скрипты для локализации.
     */
    private function registerLangScripts(): void
    {
        Yii::app()->clientScript->registerPackage('lang');
        Yii::app()->clientScript->registerScript(
            'lang-init',
            'Lang.init(' . CJavaScript::encode(['language' => Yii::app()->language]) . ');'
        );
    }
}
