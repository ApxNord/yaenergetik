<?php

class AssetManager implements AssetManagerInterface
{
    private CClientScript $clientScript;
    private array $config = [];
    private array $publishedPaths = [];

    public function __construct(CClientScript $clientScript, array $config = [])
    {
        $this->clientScript = $clientScript;
        $this->config = $config;
    }

    public function registerCoreAsset(): void
    {
        $this->clientScript->registerPackage('font-open-sans');
        $this->registerFavicon();
        $this->registerViewportMeta();
    }

    public function registerJs(string $name, array $data = [], $exports = null): void
    {
        $this->registerScript(AssetTypes::TYPE_JS, $name, $data, $exports);
    }

    public function registerJsFunction(string $name, string $func, array $data = []): void
    {
        $alias = AssetTypes::getAlias(AssetTypes::TYPE_JS);
        $path = Yii::getPathOfAlias($alias);
        $url = Utils::getAssetManager()->publish($path);

        $this->clientScript->registerScriptFile("$url/$name.js");
        
        $exports = $this->generateExportName($name);
        $this->clientScript->registerScript(
            md5("$exports.$func"),
            "$exports.$func(".CJSON::encode($data).");"
        );
    }

    public function registerCss(string $name): void
    {
        $this->registerStyle(AssetTypes::TYPE_CSS, $name);
    }

    public function registerCssPiece($css, $id = null, $media = ''): void
    {
        static $cssCounter = 0;
        $id ??= 'generic-css-' . ++$cssCounter;
        $this->clientScript->registerCss($id, $css, $media);
    }

    private function generateExportName(string $name): string {
        $nameParts = explode('-', $name);
        $exports = '';

        foreach ($nameParts as $part) {
            $exports .= ucfirst($part);
        }

        return $exports;
    }

    public function registerPackage(string $name): void
    {
        Utils::registerPackage($name);
    }

    public function registerResource(string $name): string
    {
        $path = Yii::getPathOfAlias(AssetTypes::TYPE_RES);
        $url = Utils::getAssetManager()->publish($path);

        return "$url/$name";
    }

    private function registerFavicon(): void
    {
        $this->clientScript->registerLinkTag('icon', null, '/favicon.ico');
        $this->clientScript->registerLinkTag('shortcut icon', null, '/favicon.ico');
    }

    private function registerViewportMeta():void
    {
        $this->clientScript->registerMetaTag('width=device-width, initial-scale=1.0', 'viewport');
    }

    private function registerStyle(string $type, string $name): string
    {
        AssetTypes::validateType($type);
        $alias = AssetTypes::getAlias($type);

        $path = Yii::getPathOfAlias($alias);
        $url = Utils::getAssetManager()->publish($path);

        $fileUrl = "$url/$name.css";
        $this->clientScript->registerCssFile($fileUrl);

        return $fileUrl;
    }

    private function registerScript(string $type, string $name, array $data = [], $exports = null)
    {
        AssetTypes::validateType($type);
        $alias = AssetTypes::getAlias($type);

        LangScriptHelper::registerLangPackage(AssetTypes::getAlias(AssetTypes::TYPE_LANG),'lang-initAsset-', $name, $name);
        //LangScriptHelper::registerLangPackage(AssetTypes::getAlias(AssetTypes::TYPE_LANG),'Asset-', $name, $name);

        $path = Yii::getPathOfAlias($alias);
        $url = Utils::getAssetManager()->publish($path);

        $this->clientScript->registerScriptFile("$url/$name.js");

        if ($exports !== false) {
            $exportsName = $exports ?? $this->generateExportName($name);
            $this->clientScript->registerScript(
                "$exportsName.init", 
                "$exportsName.init(" . CJavaScript::encode($data) . ");"
            );
        }
    }
}
