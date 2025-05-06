<?php

class LanguageInitializer
{
    public function init(BrandingProviderInterface $branding): void
    {
        Yii::app()->language = $branding->getApplicationLanguage();
        Ascue\Lang\Lang::set(Yii::app()->language);
        $this->registerLangScripts();
    }

    private function registerLangScripts(): void
    {
        Yii::app()->clientScript->registerPackage('lang');
        Yii::app()->clientScript->registerScript(
            'lang-init',
            'Lang.init(' . CJavaScript::encode(['language' => Yii::app()->language]) . ');'
        );
    }
}
