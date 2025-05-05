<?php

/**
 * Controller is the customized base controller class.
 * All controller classes for this application should extend from this base class.
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
    public $csrfRules = self::CSRF_ALLOW_SAME;

    /**
     * Разрешить принимать запросы только от того же домена.
     */
    const CSRF_ALLOW_SAME = 0;
    /**
     * Разрешить принимать запросы с домена на уровень выше (если приложение на поддомене).
     */
    const CSRF_ALLOW_HOST = 1;
    /**
     * Разрешить принимать любые запросы.
     */
    const CSRF_ALLOW_ANY = 10;

    // Статусы для AJAX-запросов
    const FAIL = 0;
    const SUCCESS = 1;

    protected BrandingProviderInterface $branding;
    protected AssetManager $assetManager;
    protected ModelService $modelService;
    protected CClientScript $clientScript;
    public function init()
    {
        parent::init();

        $this->branding = Yii::app()->branding;
        $this->assetManager = Yii::app()->assetManager;
        $this->clientScript = Yii::app()->clientScript;

        $this->modelService = new ModelService();

        $this->initTags();
        $this->initLanguage();
    }

    private function initLanguage(): void
    {
        Yii::app()->language = $this->branding->getLanguage();
        Ascue\Lang\Lang::set($this->branding->getLanguage());
    }

    private function initTags(): void
    {
        $this->clientScript->registerMetaTag('text/html; charset=utf-8', null, 'Content-Type');
        $this->clientScript->registerMetaTag($this->branding->getLanguage(), 'language');
        $this->clientScript->registerMetaTag('width=device-width, initial-scale=1.0', 'viewport');
    }

    private function initLinks(): void
    {
        $this->clientScript->registerLinkTag('icon', null, '/favicon.ico');
        $this->clientScript->registerLinkTag('shortcut icon', null, '/favicon.ico');      
    }

    private function initPackage()
    {
        $this->clientScript->registerPackage('font-open-sans');
    }

    /**
     * Возвращает полное название страницы, которое можно использовать в секции <head></head>.
     *
     * Включает в себя текущее название страницы и название партнера.
     *
     * @return string
     */
    public function getHeadPageTitle(): string
    {
        $pageTitle = $this->resolvePageTitle();
        return $pageTitle 
        ? sprintf('%s - %s', $pageTitle, $this->branding->getApplicationName())
        : $this->branding->getApplicationName();
    }

    private function resolvePageTitle(): string 
    {
        if ($this->pageTitle instanceof PageTitle) {
            return $this->pageTitle->getLastLabel();
        }

        return (string) $this->pageTitle;
    }

    protected function testCsrf(CHttpRequest $request, int $rule): void
    {
        try {
            $strategy = CsrfStrategyFactory::create($rule);
            $strategy->validate($request);
        } catch (CsrfValidationException $e) {
            $this->logCsrfError($e, $request);
            throw $e;
        }
    }

    private function logCsrfError(CsrfValidationException $e, CHttpRequest $request): void {
        $context = [
            'url' => $request->url,
            'userAgent' => $request->userAgent,
            'referer' => $request->getUrlReferrer(),
        ];

        Log::warning("CSRF Validation Failed: {$e->getMessage()}",
            CLogger::LEVEL_WARNING,
            'security',
            [
                'ip' => $request->userHostAddress,
                'url' => $request->url,
                'referer' => $request->getUrlReferrer(),
                'userAgent' => $request->userAgent
            ]
        );
        throw new CHttpException(403, Yii::t('app', 'Access denied'), 403, $e);
    }

    /**
     * {@inheritdoc}
     */
    protected function beforeAction($action)
    {
        $this->resolveDatabases($action);
        $rule = $this->resolveCsrfRule($action);
        $this->testCsrf(Yii::app()->request, $rule);

        $this->clientScript->registerPackage('lang');
        $this->clientScript->registerScript(
            'lang-init',
            'Lang.init('.CJavaScript::encode([
                'language' => Yii::app()->language,
            ]).');'
        );

        return true;
    }

    private function resolveCsrfRule(CAction $action): int
    {
        if (is_array($this->csrfRules)) {
            return $this->csrfRules[$action->id] 
                ?? $this->csrfRules['*'] 
                ?? self::CSRF_ALLOW_SAME;
        }

        return $this->csrfRules;
    }

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

    //------------------------------------------------------------------------
    //  Отправка данных в виде JSON-объектов
    //------------------------------------------------------------------------

    /**
     * Отправить данные в виде JSON.
     *
     * @param mixed $success
     * @param array $data
     *
     * @fixme $data не может иметь ключ 'success'
     */
    public function renderJson($success, $data = [])
    {
        $data['success'] = $success;

        Yii::app()->json->send($data);
    }

    /**
     * Отправить страницу в виде JSON.
     *
     * @param string $view
     * @param array  $data
     * @param bool   $processOutput
     */
    public function renderJsonView($view, $data = [], $processOutput = false)
    {
        Yii::app()->json->send(
            [
                'success' => CHtml::value($data, 'success', self::SUCCESS),
                'html' => $this->renderPartial($view, $data, true, $processOutput),
            ]
        );
    }

    //------------------------------------------------------------------------
    //  Проверка входящих запросов
    //------------------------------------------------------------------------

    /**
     * Проверить, что запрос является POST-запросом.
     * Если это не так, будет вызвано исключение.
     */
    public function assertPostRequest()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(400, 'Invalid request. Please do not repeat this request again.');
        }
    }

    //------------------------------------------------------------------------
    //  Перенаправления
    //------------------------------------------------------------------------

    /**
     * Возвращает домашнюю страницу для текущего пользователя.
     *
     * @return string
     */
    public function getDashboardUrl()
    {
        return Yii::app()->user->getDashboardUrl();
    }

    /**
     * Перенаправляет пользователя по данному url. В отличие от CController::redirect(),
     * этот метод обрабатывает ajax запросы особым образом, чтобы можно было обработать
     * перенаправление на стороне js (обычно во время ajax запроса перенаправление
     * происходит автоматически без возможности его контролировать).
     *
     * @param string|array $url
     * @param bool         $terminate
     */
    public function redirectEx($url, $terminate = true)
    {
        if (Yii::app()->request->getIsAjaxRequest()) {
            $this->redirect($url, $terminate, 400);
        } else {
            $this->redirect($url, $terminate);
        }
    }
}
