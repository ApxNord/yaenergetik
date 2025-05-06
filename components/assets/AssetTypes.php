<?php
// protected/components/assets/AssetTypes.php

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

    public static function getAlias(string $type): string
    {
        if (!isset(self::$aliases[$type])) {
            throw new InvalidArgumentException("Invalid asset type: $type");
        }
        return self::$aliases[$type];
    }

    public static function validateType(string $type): void
    {
        if (!array_key_exists($type, self::$aliases)) {
            throw new InvalidAssetTypeException("Unsupported asset type: $type");
        }
    }

    public static function getAllTypes(): array
    {
        return array_keys(self::$aliases);
    }

    public static function isScriptType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_JS,
            self::TYPE_KOJS,
        ]);
    }
}