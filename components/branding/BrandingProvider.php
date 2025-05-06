<?php

/**
 * Класс для получения брендинговых данных приложения.
 * 
 * ### Основные функции:
 * - Определение языка интерфейса
 * - Получение названия приложения
 * - Управление стилями меню
 * - Работа с логотипом
 * 
 * @package components.branding
 */
class BrandingProvider implements BrandingProviderInterface {

    /**
     * @var User|null Текущий пользователь (null для гостей)
     */
    private ?User $user;

    /**
     * @var PartnerProgramManager Менеджер партнерских программ
     */
    private PartnerProgrammManager $partnerManager;

    /**
     * @var string Название приложения по умолчанию
     */
    private string $defaultAppName;

    /**
     * @var string Исходный язык (используется если у пользователя не задан)
     */
    private string $sourceLanguage;

    /**
     * @param User|null $user Текущий пользователь
     * @param PartnerProgramManager $partnerManager Менеджер партнерок
     * @param string $defaultAppName Дефолтное название
     * @param string $sourceLanguage Язык по умолчанию
     */
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
    
    public function getApplicationLanguage(): string
    {
        return $this->user?->language ?? $this->sourceLanguage;
    }

    public function getApplicationName(): string
    {
        $brand = $this->getUserBrand() ?? $this->getDefaultBrand();
        return $brand->name ??  $this->defaultAppName;
    }

    public function getApplicationMenuStyles(): array
    {
        $brand = $this->getUserBrand();
        return $brand ? json_decode($brand->menuStyle, true) : [];
    }

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
