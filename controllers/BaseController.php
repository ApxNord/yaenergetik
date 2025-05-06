<?php

/**
 * Базовый контроллер приложения, от которого должны наследоваться все контроллеры.
 */
class BaseController extends CController
{
    /**
     * @var string the default layout for the controller view. Defaults to '//layouts/column1',
     *             meaning using a single column layout. See 'protected/views/layouts/column1.php'.
     */
    public $layout = 'main';

    /**
     * @var array context menu items. This property will be assigned to {@link CMenu::items}.
     */
    public $menu = [];

    /**
     * Название страницы.
     *
     * @var PageTitle|string|null
     */
    public $pageTitle = null;

    /**
     * @var int|array<string, int> настройки защиты POST запросов от CSRF путем проверки Referer
     */
    public $csrfRules = CsrfStrategyFactory::CSRF_ALLOW_SAME;

    /**
     * @var AssetManagerInterface Менеджер ассетов (CSS/JS).
     */
    protected AssetManagerInterface $assetManager;

    /**
     * @var BrandingProviderInterface Поставщик данных брендинга (название приложения, логотип, язык).
     */
    protected BrandingProviderInterface $brandingProvider;

    public function __construct(
        $id,
        $module = null,
        AssetManagerInterface $assetManager = null,
        BrandingProviderInterface $brandingProvider = null)
    {
        parent::__construct($id, $module);

        $this->assetManager = $assetManager ?? Yii::app()->assetManager;
        $this->brandingProvider = $brandingProvider ?? Yii::app()->brandingProvider;
    }

    public function init()
    {
        parent::init();

        $this->assetManager->registerCoreAsset();
        Yii::app()->languageInitializer->init($this->brandingProvider);
        $this->initMetaTags();
    }

    /**
     * Генерирует полный заголовок страницы для <title>.
     * @return string
     */
    public function getHeadPageTitle(): string
    {
        // текстовое название страницы
        $pageTitle = $this->resolvePageTitle();

        return ($pageTitle ? $pageTitle.' - ' : '').$this->brandingProvider->getApplicationName();
    }

    private function resolvePageTitle(): ?string
    {
        if ($this->pageTitle instanceof PageTitle) {
            return $this->pageTitle->getLastLabel();
        }

        return is_string($this->pageTitle) ? $this->pageTitle: null;
    }

    /**
     * Проверяет наличие лицензионного контракта у пользователя и запускает прогнозирование.
     * @return void
     */
    public function addSystemContractNotifications()
    {
        $user = Yii::app()->user->getModel();
        $contract = $user
            ? SystemContract::model()->find('userId = :id', [':id' => $user->id])
            : null;
        if ($contract && $contract->getIsLicenseType()) {
            (new UserSystemLicensePredictor($contract))->run();
        }
    }

    protected function beforeAction($action)
    {
        $this->resolveDatabases($action);

        if ($action->getId() !== 'error') {
            $rule = $this->resolveCsrfRule($action);
            $this->applyCsrfStrategy($rule);
        }

        return true;
    }

    public function renderJson($success, $data = [])
    {
        Yii::app()->jsonResponseHandler->send($success, $data);
    }

    public function renderJsonView($view, $data = [], $processOutput = false)
    {
        Yii::app()->jsonResponseHandler->sendView($view, $data, $processOutput);
    }

    /**
     * Проверяет, что запрос является POST. В противном случае возвращает HTTP 400.
     * @throws \CHttpException
     * @return void
     */
    public function assertPostRequest()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
        }
    }

    /**
     * Возвращает URL личного кабинета пользователя.
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return Yii::app()->redirectHandler->getDashboardUrl();
    }

    /**
     * Перенаправляет на URL. Для AJAX-запросов возвращает HTTP 400 с заголовком перенаправления.
     * @param mixed $url
     * @param mixed $terminate
     * @return void
     */
    public function redirectEx($url, $terminate = true): void
    {
        Yii::app()->redirectHandler->redirectEx($url, $terminate);
    }

    protected function resolveDatabases(CAction $action): void
    {
        Yii::app()->databaseResolver->resolve($action, $this);
    }
    
    protected function getDefaultDbActions(): ?array
    {
        return null;
    }

    /**
     * Регистрирует метатеги Content-Type и language.
     * @return void
     */
    private function initMetaTags()
    {
        Yii::app()->clientScript->registerMetaTag('text/html; charset=utf-8', null, 'Content-Type');
        Yii::app()->clientScript->registerMetaTag($this->brandingProvider->getApplicationLanguage(), 'language');
    }

    /**
     * Определяет правило CSRF для текущего экшена.
     * @param CAction $action
     * @return int
     */
    private function resolveCsrfRule(CAction $action): int
    {
        return Yii::app()->csrfHandler->resolveRule($this->csrfRules, $action);
    }

    /**
     * Применяет выбранную стратегию CSRF через CsrfHandler.
     * @param int $rule
     * @return void
     */
    private function applyCsrfStrategy(int $rule): void
    {
        Yii::app()->csrfHandler->validate($rule, Yii::app()->request);
    }
}
