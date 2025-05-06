<?php

interface BrandingProviderInterface
{
    public function getApplicationName(): string;
    public function getLogoPath(): ?string;
    public function getApplicationMenuStyles(): array;
    public function getApplicationLanguage(): string;
}
