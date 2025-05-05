<?php

class AssetManager extends CApplicationComponent
{
    private CClientScript $clientScript;
    private array $config = [];
    private array $publishedPaths = [];

    public function __construct(CClientScript $clientScript, array $config = [])
    {
        $this->clientScript = $clientScript;
        $this->config = array_merge(
            [
                'js' => AssetTypes::getAlias(AssetTypes::TYPE_JS),
                'css' => AssetTypes::getAlias(AssetTypes::TYPE_CSS),
                'kojs' => AssetTypes::getAlias(AssetTypes::TYPE_KOJS),
                'res' => AssetTypes::getAlias(AssetTypes::TYPE_RES)
                ], $config);
    }

    public function registerAsset(string $type, $name, $data = [], $exports = null, $func = '', $langAsset = '')
    {
        AssetTypes::validateType($type);

        $alias = AssetTypes::getAlias($type);

        $assetPath = $this->registerResource($name, $alias);

        if (AssetTypes::isScriptType($type)) {
            LangScriptHelper::registerLangPackage($this->langPackage, $langAsset, $name, $name);
            $this->registerJsAsset($assetPath, $name, $data, $exports, $func);
        }
        else {
            $this->registerCssAsset($assetPath);
        }
    }

    private function registerJsAsset($assetPath, $name, $data, $exports, $func) 
    {
        $this->clientScript->registerScriptFile($assetPath.'.js');

        if ($exports !== false) {
            if ($exports === null) {
                $nameParts = explode('-', $name);
                $exports = '';
                foreach ($nameParts as $part) {
                    $exports .= ucfirst($part);
                }
            }
            $jsData = CJavaScript::encode($data);
            $jsCallable = "{$exports}.{$func}({$jsData})";
            $jsId = md5($jsCallable);
        
            $this->clientScript->registerScript($jsId, $jsCallable);
        }
    }
    
    private function registerCssAsset($assetPath)
    {
        $fileUrl = $assetPath.'.css';
        $this->clientScript->registerCssFile($fileUrl);

        return $fileUrl;
    }

    public function registerCssPiece($css, $id = null, $media = '')
    {
        static $cssCounter = 0;
        if ($id === null) {
            $id = 'generic-css-'.++$cssCounter;
        }
        $this->clientScript->registerCss($id, $css, $media);
    }

    public function registerResource($name, $alias)
    {
        $path = Yii::getPathOfAlias($alias);
        $url = Utils::getAssetManager()->publish($path);

        return $url.DIRECTORY_SEPARATOR.$name;
    }

    public function registerPackage($name)
    {
        Utils::registerPackage($name);
    }
}