<?php

class BrandingProvider implements BrandingProviderInterface {

    private ?User $user;
    private PartnerProgrammManager $partnerManager;
    private string $defaultAppName;
    private string $sourceLanguage;

    public function __construct(
        ?User $user, 
        PartnerProgrammManager $partnerManager,
        string $defaultAppName,
        string $sourceLanguage)
    {
        $this->user = $user;
        $this->partnerManager = $partnerManager;
        $this->defaultAppName = $defaultAppName;
        $this->sourceLanguage = $sourceLanguage;
    }
    
    public function getLanguage(): string
    {
        return $this->user?->language ?? $this->sourceLanguage;
    }

    /**
     * Возвращает имя приложения с учетом бренда.
     *
     * @return string
     */
    public function getApplicationName(): string
    {
        $brand = $this->getUserBrand() ?? $this->getDefaultBrand();
        return $brand->name ??  $this->defaultAppName;
    }

    /**
     * Возвращает CSS стили меню (в формате JSON) с учетом бренда.
     *
     * @return string
     */
    public function getMenuStyles(): array
    {
        $brand = $this->getUserBrand();
        return $brand ? json_decode($brand->menuStyle, true) : [];
    }

    /**
     * Возвращает path изображения логотипа с учетом бренда.
     *
     * @return string
     */
    public function getLogoPath(): ?string
    {
        $brand = $this->getUserBrand();
        $brandLogoHash = '';

        if ($brand && $brand->logo) {
            $brandLogoHash = $brand->logoHash;
        } else {
            $defPartnerProgramm = $this->partnerManager->getProgram();
            $defBrand = $defPartnerProgramm ? $defPartnerProgramm->brand : false;
            if ($defBrand && $defBrand->logo) {
                $brandLogoHash = $defBrand->logoHash;
            } else {
                return false;
            }
        }

        $baseUrl = Yii::getPathOfAlias('application.runtime.logos');
        $url = Utils::getAssetManager()->publish($baseUrl);
        return $url.DIRECTORY_SEPARATOR.$brandLogoHash;
    }

    private function getUserBrand(): ?Brand
    {
        return $this->user?->getUsageBrand();
    }

    private function getDefaultBrand(): ?Brand
    {
        $program = $this->partnerManager->getProgram();
        return Utils::optional($program, 1)->getBrand();
    }
}
