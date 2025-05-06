<?php

interface AssetManagerInterface
{
    public function registerCoreAsset(): void;
    public function registerJs(string $name, array $data = [], $exports = null): void;
    public function registerCss(string $name): void;
    public function registerJsFunction(string $name, string $func, array $data = []): void;
    public function registerCssPiece($css, $id = null, $media = ''): void;
}