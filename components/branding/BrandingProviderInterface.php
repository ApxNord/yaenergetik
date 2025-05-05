<?php

interface BrandingProviderInterface
{
    public function getApplicationName(): string;
    public function getLogoPath(): ?string;
    public function getMenuStyles(): array;
    public function getLanguage(): string;
}
