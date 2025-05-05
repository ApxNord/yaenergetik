<?php

use App\Services\UI\LeftMenuBuilder;

/**
 * Controller is the customized base controller class.
 * All controller classes for this application should extend from this base class.
 */
class ManagerController extends AuthorizedController
{
    /**
     * Куки с идентификатором последней сети.
     */
    const LAST_NETWORK_ID_COOKIE = 'LastNetworkId';

    const SUCCESS_FLASH_MESSAGE_KEY = 'ManagerSuccessFlash';

    const WARNING_FLASH_MESSAGE_KEY = 'ManagerWarningFlash';

    const ERROR_FLASH_MESSAGE_KEY = 'ManagerErrorFlash';

    const INFO_FLASH_MESSAGE_KEY = 'ManagerInfoFlash';

    /**
     * @var string the default layout for the controller view. Defaults to '//layouts/column1',
     *             meaning using a single column layout. See 'protected/views/layouts/column1.php'
     */
    public $layout = 'manager';

    public $userRole = ['company', 'demo', 'worker', 'contractor', 'partner'];

    /**
     * Сеть, в которой происходит действие.
     *
     * @var Network|null
     */
    private $network = null;

    /**
     * Показывает заголовок страницы.
     *
     * Если значением поля установить false, то вывод заголовка страницы
     * ложится на программиста вьюхи. Требуется редко, но иногда удобно.
     *
     * @var bool
     */
    public $showPageTitle = true;

    /**
     * id открытой в данный момент вкладки меню для случаев, когда автоопределение
     * вкладки работает не так, как хотелось бы.
     * При указании false ни одна вкладка меню не будет открыта.
     *
     * @var string|false|null
     */
    public $openMenuItem = null;

    /**
     * Сбросить у админов часовой пояс на пояс по-умолчанию перед выполнением действий контроллера.
     *
     * @var bool
     */
    public $resetAdminTimezone = false;

    protected $leftMenuBuilder;

    /**
     * Запрещает доступ сотрудникам сетевых компаний.
     * Фильтр 'userWorker'.
     *
     * @param CFilterChain $filterChain
     *
     * @throws CHttpException
     */
    public function filterUserWorker($filterChain)
    {
        $user = $this->getWebUser();
        if ($user->getIsWorker() && $user->getModel()->worker->getIsSupply()) {
            throw new CHttpException(403, Yii::t('yii', 'You are not authorized to perform this action.'));
        }
        $filterChain->run();
    }

    /**
     * Создать отчет и выполнить его инициализацию.
     * Отчеты создаются в виде классов, хранятся в папке report/.
     *
     * @param string $className  Имя класса отчета
     * @param array  $properties Опции отчета
     *
     * @return BaseReport Созданный и инициированный отчет
     */
    public function createReport($className, $properties)
    {
        $report = Yii::app()->reportFactory->createReport($this, $className, $properties);
        $report->init();

        return $report;
    }

    /**
     * Проверяет, может ли пользователь добавить новую точку учета.
     *
     * @param Network $network Сеть
     *
     * @throws CHttpException HTTP 400, если достигнут лимит точек учета
     */
    public function ensureCanCreateControlPoint(Network $network)
    {
        if (!$network->getCanCreateControlPoint()) {
            throw new CHttpException(400, Yii::t('controllers.ManagerController', 'tariff_limitation_error'));
        }
    }

    /**
     * Проверяет, может ли пользователь выполнить действие или услугу в сети.
     * Администратор может выполнить действие или услугу независимо от баланса пользователя.
     *
     * @param Network  $network   Сеть
     * @param int|null $serviceId Идентификатор услуги
     *
     * @throws CHttpException HTTP 400, если выполнение действия или услуги невозможно
     */
    public function ensureCanExecute(Network $network, $serviceId = null)
    {
        if ($this->getWebUser()->getIsAdmin()) {
            $state = $serviceId
                ? $network->getService($serviceId)->getState()
                : DomainState::success();
        } else {
            $state = $serviceId
                ? $network->getService($serviceId)->getExecutionState()
                : $network->getExecutionState();
        }

        $this->ensureStateIsSuccess($state);
    }

    /**
     * Проверяет, имеет ли пользователь доступ к указанной услуге.
     *
     * @param Network  $network   Сеть
     * @param int|null $serviceId Идентификатор услуги
     *
     * @throws CHttpException HTTP 400, если доступа к услуге нет
     */
    public function ensureHasService(Network $network, $serviceId)
    {
        $this->ensureStateIsSuccess($network->getService($serviceId)->getState());
    }

    /**
     * Проверяет, является ли состояние успешным.
     *
     * @param DomainState $state Состояние
     *
     * @throws CHttpException HTTP 400, если состояние не успешно
     */
    public function ensureStateIsSuccess(DomainState $state)
    {
        if (!$state->isSuccess()) {
            throw new CHttpException(400, Yii::t('controllers.ManagerController', 'action_not_available_error').$state->getLastReason());
        }
    }

    /**
     * Возвращает уведомления, которые нужно показать сейчас
     *
     * @return array
     */
    public function getSiteNotifications()
    {
        $user = $this->getWebUserModel();
        $fn = function ($notification, $collectionKey, $collection) {
            return $notification->show();
        };

        return Functional\select($user->siteNotifications, $fn);
    }

    /**
     * Возвращает набор объектов для построения левого меню.
     *
     * @return array
     */
    public function getMenuItems()
    {
        $result = $this->leftMenuBuilder->getMenu();

        return $result;
    }

    /**
     * Удаление закэшированых пунктов меню.
     *
     * @return array
     */
    public function flushMenuItemsCache()
    {
        $this->leftMenuBuilder->flushCache();
    }

    /**
     * Возвращает элементы для навигационной панели.
     *
     * @return array
     */
    public function getNavbarItems()
    {
        $items = [];

        $network = $this->getNetwork();
        $webUser = $this->getWebUser();
        $currentUser = $this->getWebUserModel();
        $currentDb = Yii::app()->db->getCurrentDatabase();
        $roam = $webUser->getIsRoaming();

        $hasBalance = !$roam && $network && $network->userId == $webUser->getModelId() && (
            $webUser->getIsContractor() ||
            $webUser->getIsAdmin() || $webUser->getIsManager()
        ) && !$webUser->getIsDemo() && ($contract = $network->activeUsageSystemContract);

        $useCsdMinutes = ($network && !$webUser->getIsDemo() && ($contract = $network->getActiveUsageSystemContract()) && $contract->getUseCsdMinutes());
        $useStorage = $network && !$webUser->getIsDemo() && $contract && $webUser->getIsAdmin(); // Только админский доступ - временно

        $newsUnreadCount = UserInfoBlock::model()->forUser($currentUser)->unread()->count([
            'with' => [
                'infoBlock' => [
                    'scopes' => 'active',
                    'condition' => 'infoBlock.userId IS NULL',
                ],
            ],
        ]);
        $messagesUnreadCount = UserInfoBlock::model()->forUser($currentUser)->unread()->count([
            'with' => [
                'infoBlock' => [
                    'scopes' => 'active',
                    'condition' => 'infoBlock.userId IS NOT NULL',
                ],
            ],
        ]);

        if ($network && !$network->getIsNewRecord() && !$webUser->getIsWorker() && !$webUser->getIsContractor()) {
            $items[] = [
                'class' => 'NavbarWidgetItem',
                'label' => CHtml::encode($network->name),
                'widgetClass' => 'NetworkInfoPanel',
                'widgetOptions' => [
                    'network' => $network,
                ],
            ];
        }

        if ($webUser->getIsAdmin() && $currentDb->title && Database::model()->count() > 1) {
            $items[] = [
                'class' => 'NavbarWidgetItem',
                'label' => $currentDb->title,
                'htmlOptions' => ['title' => 'Сервер', 'class' => 'navbar-widget-item-bordered'],
                'widgetClass' => 'DatabaseSwitchWidget',
                'widgetOptions' => [],
            ];
        }

        if (!$webUser->getIsRegionOperator() && !$webUser->getIsAgent()) {
            $items[] = [
                'class' => 'NavbarInputItem',
                'id' => 'Navbar_search',
                'htmlOptions' => [
                    'title' => Yii::t('controllers.ManagerController', 'search_f'),
                    'placeholder' => Yii::t('controllers.ManagerController', 'search'),
                ],
            ];
        }

        // Элементы справа

        if ($currentUser->isCanViewCustomerSegment()) {
            $items[] = [
                'label' => $label = implode('/', array_filter([
                        Utils::optional(User::MARKETING['marketingDirection'])[$this->getRelatedUser()->marketingDirection],
                        Utils::optional($this->getRelatedUser()->customerSegment)->name,
                ])),
                'url' => ['user/systemSettings', 'id' => $currentUser->id],
                'visible' => $label,
                'position' => 'right',
            ];
        }

        if ($newsUnreadCount && !$roam) {
            $items[] = [
                'label' => Yii::t('controllers.ManagerController', 'news_2').Html::tag('span', ['id' => 'info-blocks-unread-count'], $newsUnreadCount),
                'url' => ['user/showInfoBlocks', 'id' => $currentUser->id, 'type' => 'news'],
                'id' => 'show-news',
                'htmlOptions' => [
                    'title' => Yii::t('controllers.ManagerController', 'news'),
                    'class' => 'itemShowInfoBlocks',
                ],
                'position' => 'right',
            ];
        }
        if ($messagesUnreadCount && !$roam) {
            $items[] = [
                'label' => Yii::t('controllers.ManagerController', 'messages_2').Html::tag('span', ['id' => 'info-blocks-unread-count'], $messagesUnreadCount),
                'url' => ['user/showInfoBlocks', 'id' => $currentUser->id, 'type' => 'messages'],
                'id' => 'show-messages',
                'htmlOptions' => [
                    'title' => Yii::t('controllers.ManagerController', 'messages'),
                    'class' => 'itemShowInfoBlocks',
                ],
                'position' => 'right',
            ];
        }

        if ($useCsdMinutes) {
            $minutes = 'CSD '.$contract->getMinutesBalance().Yii::t('controllers.ManagerController', 'minutes');
            $items[] = [
                'label' => $minutes,
                'url' => ['transaction/index', 'id' => $contract->id],
                'position' => 'right',
                'htmlOptions' => [
                    'title' => Yii::t('controllers.ManagerController', 'csd_minutes_balance'),
                ],
            ];
        }

        if ($useStorage && !$webUser->getIsContractor()) {
            $totalStorageSpace = $contract->getTotalStorageAmount() / 1024 / 1024;
            $usageStorageSpace = $contract->storageBalanceMegabytes;

            $items[] = [
                'label' => ceil($usageStorageSpace).' / '.ceil($totalStorageSpace).Yii::t('controllers.ManagerController', 'megabytes'),
                'url' => ['transaction/index', 'id' => $contract->id],
                'position' => 'right',
                'htmlOptions' => [
                    'title' => Yii::t('controllers.ManagerController', 'storage_space'),
                ],
            ];
        }

        if ($hasBalance) {
            $balanceLabel = Yii::app()->format->currencyForUser($contract->balance, $currentUser);
            /* @var $balanceState DomainState */
            $balanceState = $contract->getBalanceState();
            $title = Yii::t('controllers.ManagerController', 'account_balance');

            $items[] = [
                'label' => $balanceLabel,
                'url' => ['transaction/index', 'id' => $contract->id],
                'position' => 'right',
                'state' => $balanceState,
                'htmlOptions' => [
                    'title' => $title,
                ],
            ];
        }

        $items[] = [
            'class' => 'NavbarUserTasksItem',
            'position' => 'right',
            'user' => $currentUser,
        ];

        $items[] = [
            'label' => Yii::t('controllers.ManagerController', 'register'),
            'url' => ['site/register', 'from' => 'demo'],
            'icon' => 'star',
            'position' => 'right',
            'visible' => $webUser->getIsDemo(),
            'htmlOptions' => [
                'title' => Yii::t('controllers.ManagerController', 'register_service'),
            ],
        ];

        $items[] = [
            'class' => 'NavbarWidgetItem',
            'label' => CHtml::encode($currentUser->name),
            'position' => 'right',
            'icon' => 'user',
            'widgetClass' => UserPanel::class,
            'widgetOptions' => [
                'user' => $currentUser->getOriginal(),
                'webUser' => $webUser,
            ],
        ];

        return $items;
    }

    /**
     * Возвращает сеть, с которой в данный момент происходит работа.
     *
     * @return Network|null
     */
    public function getNetwork()
    {
        $network = $this->network;
        $user = $this->getWebUserModel();

        if (!$network) {
            $network = $this->restoreLastNetwork();
        }

        if (!$network && !$user->showNetworks) {
            $network = Network::model()->undeleted()->findByAttributes(['userId' => $user->id]);
        }

        $this->setNetwork($network);
        $this->saveLastNetwork($network);

        return $network;
    }

    /**
     * Возвращает пользователя, относительно которого выполняется текущий запрос.
     *
     * @return User
     */
    public function getRelatedUser()
    {
        $network = $this->getNetwork();
        $user = $this->getWebUserModel();
        if ($network && !$user->getIsWorker() && !$user->getIsContractor()) {
            $user = $network->user;
        }
        $this->ensureModelExists($user);

        return $user;
    }

    /**
     * Возвращает текущего пользователя, от которого пришел запрос.
     *
     * @return WebUser
     */
    public function getWebUser()
    {
        return Yii::app()->getUser();
    }

    /**
     * Возвращает модель пользователя, от которого пришел запрос.
     *
     * @return User
     *
     * @throws CHttpException 404, если модель не найдена
     */
    public function getWebUserModel()
    {
        $webUser = Yii::app()->getUser();

        if ($webUser->getIsGuest()) {
            Yii::app()->getUser()->loginRequired();
        }

        $model = $webUser->getModel();

        if ($model === null) {
            throw new CHttpException(404);
        }

        return $model;
    }

    /**
     * @see CController::init()
     */
    public function init()
    {
        if (!$this->getWebUser()->getIsGuest() && ($network = $this->getNetwork()) !== null) {
            DateUtils::setTimezone($network->getLocalTimezone());
            $this->timezoneSet = true;
        }
        parent::init();

        $this->pageTitle = new PageTitle();

        Yii::app()->widgetFactory->widgets['GridView'] = [
            'type' => [],
            'template' => '{items}{pager}{summary}',
            'pagerCssClass' => 'pull-right',
            'summaryCssClass' => 'summary pull-left text-muted',
            'htmlOptions' => ['class' => 'grid-view clearfix'],
        ];

        Yii::app()->widgetFactory->widgets['TbGridView'] = [
            'type' => [],
            'template' => '{items}{pager}{summary}',
            'pagerCssClass' => 'pull-right',
            'summaryCssClass' => 'summary pull-left text-muted',
            'htmlOptions' => ['class' => 'grid-view clearfix'],
        ];

        Yii::app()->widgetFactory->widgets['TbListView'] = [
            'template' => '{items}{pager}{summary}',
            'pagerCssClass' => 'pull-right',
            'summaryCssClass' => 'summary pull-left text-muted',
            'htmlOptions' => ['class' => 'list-view clearfix'],
        ];

        Yii::app()->widgetFactory->widgets['TbAlert'] = [
            'alerts' => [
                self::SUCCESS_FLASH_MESSAGE_KEY => ['color' => TbHtml::ALERT_COLOR_SUCCESS],
                self::WARNING_FLASH_MESSAGE_KEY => ['color' => TbHtml::ALERT_COLOR_WARNING],
                self::ERROR_FLASH_MESSAGE_KEY => ['color' => TbHtml::ALERT_COLOR_DANGER],
                self::INFO_FLASH_MESSAGE_KEY => ['color' => TbHtml::ALERT_COLOR_INFO],
            ],
        ];
        $this->leftMenuBuilder = new LeftMenuBuilder($this);
    }

    /**
     * Создать отчет и запустить его автоматическое выполнение.
     * После создания отчет запускается. Поведение отчета зависит от текущего запроса.
     * Если запрос был передан методом POST и содержит GET-параметр output=xls,
     * то отчет будет выгружен в файл Excel и отправлен пользователю.
     * В других случаях отчет будет выгружен в виде текста, который представляет собой
     * html-таблицу для вывода пользователю.
     *
     * @param string $className  Имя класса отчета
     * @param array  $properties Опции отчета
     *
     * @return mixed Результат выполнения отчета
     */
    public function report($className, $properties)
    {
        $report = $this->createReport($className, $properties);

        return $report->run();
    }

    /**
     * Устанавливает модель сети, по которой будет выведено главное меню.
     *
     * @deprecated
     */
    public function setModel(ActiveRecord $model = null)
    {
        @trigger_error('setModel() deprecated, use setNetwork() instead.', E_USER_DEPRECATED);

        if ($model instanceof INetworkModel) {
            $this->setNetwork($model->getNetwork());
        }
    }

    /**
     * Устанавливает сеть запроса.
     */
    public function setNetwork(Network $network = null)
    {
        $oldId = $this->network->id ?? null;
        $this->network = $network;
        if ($oldId != ($network->id ?? null)) {
            if ($network === null) {
                DateUtils::setUserTimezone($this->getWebUserModel());
            } else {
                DateUtils::setTimezone($network->getLocalTimezone());
            }
        }
    }

    /**
     * Сбрасывает текущий часовой пояс на стандартный. Необходимо для некоторых контроллеров,
     * которые используют системные сущности с датами в системном часовом поясе.
     */
    public function resetTimezone()
    {
        DateUtils::setTimezone(Timezone::DEFAULT_TIMEZONE);
    }

    /**
     * Устанавливает заголовок.
     *
     * @param string $title Текст заголовка
     */
    public function setPageTitle($title = '')
    {
        $this->pageTitle = new PageTitle();
        $this->pageTitle->addLabel($title);
    }

    /**
     * Устанавливает пользовательское сообщение (успех).
     *
     * В сообщении можно использовать вставки вида {поле:формат}
     * для того, чтобы подставлять переменные в вывод.
     *
     * @param string|null $message Сообщение
     * @param mixed       $data    Данные для сообщения
     */
    public function setSuccessFlash($message, $data = null)
    {
        $this->setFlash(self::SUCCESS_FLASH_MESSAGE_KEY, $message, $data);
    }

    /**
     * Устанавливает пользовательское сообщение (предупреждение).
     *
     * В сообщении можно использовать вставки вида {поле:формат}
     * для того, чтобы подставлять переменные в вывод.
     *
     * @param string|null $message Сообщение
     * @param mixed       $data    Данные для сообщения
     */
    public function setWarningFlash($message, $data = null)
    {
        $this->setFlash(self::WARNING_FLASH_MESSAGE_KEY, $message, $data);
    }

    /**
     * Устанавливает пользовательское сообщение (ошибка).
     *
     * В сообщении можно использовать вставки вида {поле:формат}
     * для того, чтобы подставлять переменные в вывод.
     *
     * @param string|null $message Сообщение
     * @param mixed       $data    Данные для сообщения
     */
    public function setErrorFlash($message, $data = null)
    {
        $this->setFlash(self::ERROR_FLASH_MESSAGE_KEY, $message, $data);
    }

    /**
     * Устанавливает пользовательское сообщение (информация).
     *
     * В сообщении можно использовать вставки вида {поле:формат}
     * для того, чтобы подставлять переменные в вывод.
     *
     * @param string|null $message Сообщение
     * @param mixed       $data    Данные для сообщения
     */
    public function setInfoFlash($message, $data = null)
    {
        $this->setFlash(self::INFO_FLASH_MESSAGE_KEY, $message, $data);
    }

    /**
     * Устанавливает пользовательское предупреждение о состоянии баланса текущей сети.
     * Предупреждение выводится только администраторам при просмотре отключенных сетей.
     */
    public function setUserBalanceFlash()
    {
        if ($this->getWebUser()->getIsAdmin()) {
            $network = $this->getNetwork();
            if ($network && !$network->getIsBalanceAboveThreshold()) {
                $this->setWarningFlash(Yii::t('controllers.ManagerController', 'network_disabled'));
            }
        }
    }

    /**
     * Устанавливает пользовательское сообщение.
     *
     * В сообщении можно использовать вставки вида {поле:формат}
     * для того, чтобы подставлять переменные в вывод.
     *
     * @param string      $key     Код сообщения
     * @param string|null $message Сообщение
     * @param mixed       $data    Данные для сообщения
     */
    private function setFlash($key, $message, $data)
    {
        if ($message !== null) {
            $message = Yii::app()->format->template($message, $data);
        }

        Yii::app()->user->setFlash($key, $message);
    }

    public function getHasFlashes()
    {
        return !empty(Yii::app()->user->getFlashes(false));
    }

    /**
     * {@inheritdoc}
     */
    protected function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->addSystemContractNotifications();

        $this->registerPackage('yiistrap');
        $this->registerPackage('fontawesome');
        $this->registerPackage('awesomebc');
        $this->registerPackage('growl');
        $this->registerPackage('nprogress');
        $this->registerPackage('typeahead');
        $this->registerPackage('hotkeys');
        $this->registerPackage('timezonejs');
        $this->registerPackage('helpers');

        // Переопределение стилей бутстрапа
        $this->registerCss('overstrap');

        $this->registerJs('web-app', [
            'databaseId' => Yii::app()->db->getCurrentDatabase()->id,
            'database' => Yii::app()->db->getCurrentDatabase()->title,
            'userTypeId' => Yii::app()->user->getUserType(),
            'isReadonly' => Yii::app()->user->getIsReadOnly(),
            'isDemo' => Yii::app()->user->getIsDemo(),
        ]);

        $this->registerJs('manager-layout');
        $this->registerCss('manager-layout');

        if ($this->resetAdminTimezone && $this->getWebUser()->getIsAdmin()) {
            $this->resetTimezone();
        }

        return true;
    }

    /**
     * Проверяет, есть ли услуга у текущего пользователя.
     *
     * @param int $serviceId Идентификатор услуги
     *
     * @return bool
     */
    protected function hasService($serviceId)
    {
        return Yii::app()->user->hasService($serviceId);
    }

    /**
     * Возвращает часть левого меню, специфичную для сети.
     *
     * @param Network $network Сеть
     *
     * @return array Набор пунктов меню
     */
    private function getNetworkMenuItems(Network $network)
    {
        $webUser = $this->getWebUser();
        $isAdmin = $webUser->getIsAdmin();
        $user = $this->getRelatedUser();
        $partnerProgram = $user->getAssociatedPartnerProgram();
        $hourlyProfile = $network->hasService(Service::COUNTER_HOURLY_PROFILE) && (!$webUser->getIsWorker() || !$webUser->model->worker->isSupply) && !$webUser->getIsContractor();
        $supplyMailing = $hourlyProfile && $network->hasService(Service::SUPPLY_MAILING) && ObjectAccess::check($network, 'viewReports');
        $supplyMailingSelf = $network->hasService(Service::SUPPLY_MAILING_SELF);
        $isCanViewReports = ObjectAccess::check($network, 'viewReports');
        $isCanCreateReports = ObjectAccess::check($network, 'createReports');
        $powerProfile = $network->hasService(Service::COUNTER_HOURLY_PROFILE) && ObjectAccess::check($network, 'powerProfile');
        $calculations = ObjectAccess::check($network, 'calculations'); // && $this->getHasPricecats(false, false);
        $interrogation = ObjectAccess::check($network, 'interrogation');
        $sbis = $user->hasService(Service::SBIS);
        $ascue = $network->hasService(Service::ASCUE);
        $powerQuality = $ascue && $network->hasService(Service::POWER_QUALITY);
        $notifications = !$webUser->getIsWorker() && !$webUser->getIsContractor();
        $contract = $network->getActiveUsageSystemContract();
        $workerBalanceVisible = ObjectAccess::check($network, 'editFeedersSchema');
        $balance = $workerBalanceVisible && $network->user->hasService(Service::BALANCE);
        $isTsoDirectionUser = $user->marketingDirection == User::MARKETING_DIRECTION_TSO;
        $economyReports = $network->hasService(Service::PRICE_CATEGORIES_REPORTS) && !Yii::app()->params['boxEnvironment'] &&
            !$webUser->getIsWorker() && $partnerProgram && $partnerProgram->id === Utils::optional(PartnerProgram::findDefault())->id;

        $isContractor = $webUser->getIsContractor();

        $locale = Locales::model()->findByPk(Locales::getLocale($network));

        $feedersSchemaItems = [];
        if ($balance) {
            $feedersSchemaItems[] = [
                'label' => Yii::t('controllers.ManagerController', 'scheme'),
                'url' => ['network/feedersSchema', 'id' => $network->id],
                'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::BALANCES_SCHEME])),
            ];
            if ($network->feedersSchema) {
                $feedersSchemaItems = array_merge($feedersSchemaItems, [
                    [
                        'label' => Yii::t('controllers.ManagerController', 'energy_balance'),
                        'url' => ['feedersSchema/energyBalance', 'id' => $network->feedersSchema->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::BALANCES_ENERGY])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'power_balance'),
                        'url' => ['feedersSchema/powerBalance', 'id' => $network->feedersSchema->id],
                        'visible' => false, // временно
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'losses'),
                        'url' => ['feedersSchema/losses', 'id' => $network->feedersSchema->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::BALANCES_LOSSES])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'switching_history'),
                        'url' => ['network/feedersSchemaEventJournal', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::BALANCES_HISTORY])),
                    ],
                ]);
            }
        }

        return [
            [
                'label' => Yii::t('controllers.ManagerController', 'objects'),
                'url' => ['network/items', 'id' => $network->id],
                'visible' => $network->showObjects,
                'button' => [
                    'label' => '<i class="fa fa-plus"></i>',
                    'url' => ['object/create', 'id' => $network->id],
                    'visible' => $network->getCanCreateControlPoint() && !$webUser->isDemo && ObjectAccess::checkCreate($network, Obj::class),
                    'htmlOptions' => ['title' => Yii::t('controllers.ManagerController', 'create_object')],
                ],
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'meters'),
                'url' => ['network/items', 'id' => $network->id],
                'visible' => !$network->showObjects && !$webUser->isDemo,
                'button' => [
                    'label' => '<i class="fa fa-plus"></i>',
                    'url' => ['counter/simplyCreate', 'id' => $network->id],
                    'visible' => $network->getCanCreateControlPoint() && ObjectAccess::checkCreate($network, Obj::class),
                    'htmlOptions' => ['title' => Yii::t('controllers.ManagerController', 'create_meter')],
                ],
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'meters'),
                'url' => ['network/items', 'id' => $network->id],
                'visible' => !$network->showObjects && $webUser->isDemo,
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'meters'),
                'url' => ['network/items', 'id' => $network->id, 'showCounters' => true],
                'visible' => $network->showObjects,
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'data_collection'),
                'id' => 'MainMenu_meters',
                'visible' => !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION])),
                'items' => [
                    [
                        'label' => Yii::t('controllers.ManagerController', 'overview'),
                        'url' => ['network/meters', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_OVERVIEW])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'power_quality'),
                        'url' => ['powerQuality/network', 'id' => $network->id],
                        'visible' => $powerQuality && $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_POWER_QUALITY])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'journals'),
                        'url' => ['network/countersJournals', 'id' => $network->id],
                        'visible' => $network->hasService(Service::ASCUE) && $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_JOURNALS])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'upload_readings'),
                        'url' => ['networkImport/meters', 'id' => $network->id],
                        'visible' => $interrogation &&
                                        !$webUser->isDemo &&
                                        $network->hasService(Service::METER_IMPORT) &&
                                        $this->getHasModelAccess('importMeter', $network) &&
                                        $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_UPLOAD_READINGS])),
                        'adminVisible' => $interrogation,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'upload_power_profile'),
                        'url' => ['networkImport/powerProfileIndex', 'id' => $network->id],
                        'visible' => $hourlyProfile &&
                                        $network->hasService(Service::POWER_PROFILE_IMPORT) &&
                                        $this->getHasModelAccess('importPowerProfile', $network) &&
                                        $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_UPLOAD_POWER_PROFILE])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'schedules'),
                        'url' => ['ascueSchedule/index', 'id' => $network->id],
                        'visible' => $network->hasService(Service::ASCUE_SCHEDULE) && $this->getHasModelAccess('updateAscue', $network) && $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_SCHEDULES])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'notifications'),
                        'url' => ['notification/index', 'id' => $network->id],
                        'visible' => $notifications && $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_NOTIFICATIONS])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'uspds'),
                        'url' => ['uspd/index', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::DATA_COLLECTION_USPD])),
                    ],
                ],
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'calculations'),
                'visible' => $calculations && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS])),
                'items' => [
                    [
                        'label' => Yii::t('controllers.ManagerController', 'overview'),
                        'url' => ['network/economy', 'id' => $network->id],
                        'visible' => $calculations && !$isContractor && !$isTsoDirectionUser && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_OVERVIEW])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'contracts'),
                        'url' => ['networkContract/index', 'id' => $network->id],
                        'visible' => !$webUser->getIsWorker() && !$webUser->getIsContractor() && ($calculations || $supplyMailing) && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_CONTRACTS])),
                        'adminVisible' => true,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'exchange_contracts'),
                        'url' => ['exchangeContract/index', 'id' => $network->id],
                        'visible' => $webUser->getModel()->exchangeContractsAvailable && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_EXCHANGE])),
                        'adminVisible' => true,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'savings_by_objects'),
                        'url' => ['network/economyObjects', 'id' => $network->id],
                        'visible' => $calculations && !$isContractor && !$isTsoDirectionUser && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_SAVINGS])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'cost_calculation'),
                        'url' => ['network/prices', 'id' => $network->id],
                        'visible' => $calculations && !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_COST])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'interval_accounting'),
                        'url' => ['network/powerProfile', 'id' => $network->id],
                        'visible' => $hourlyProfile && !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_INTERVAL])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'consumption'),
                        'url' => ['network/fparams', 'id' => $network->id],
                        'visible' => $calculations && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_CONSUMPTION])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'network_calculation'),
                        'url' => ['network/priceReport', 'id' => $network->id],
                        'visible' => $calculations && !$supplyMailingSelf && !$isContractor && !$isTsoDirectionUser && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_NETWORK])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'power_calculation'),
                        'url' => ['network/threeRate', 'id' => $network->id],
                        'visible' => false && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_POWER])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'savings_report'),
                        'url' => ['report/networkEconomy', 'id' => $network->id],
                        'visible' => $calculations && !$supplyMailingSelf && !$isContractor && !$isTsoDirectionUser && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_REPORT])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'payment_terms'),
                        'url' => ['report/networkPrepayment', 'id' => $network->id],
                        'visible' => $calculations && !$isContractor && !$isTsoDirectionUser && $locale->isHasModule(Module::model()->findByName(['name' => Module::CALCULATIONS_PAYMENT])),
                        'adminVisible' => $hourlyProfile,
                    ],
                ],
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'balances'),
                'visible' => $balance && $locale->isHasModule(Module::model()->findByName(['name' => Module::BALANCES])),
                'url' => empty($feedersSchemaItems) ? ['network/feedersSchemaAdvertisment', 'id' => $network->id] : null,
                'items' => $feedersSchemaItems,
            ],
            [
                'id' => 'MainMenu_reports',
                'label' => Yii::t('controllers.ManagerController', 'reports'),
                'visible' => $this->getHasModelAccess('viewReports', $network) && $locale->isHasModule(Module::model()->findByName(['name' => Module::REPORTS])),
                'items' => [
                    [
                        'label' => Yii::t('controllers.ManagerController', 'consumption'),
                        'url' => ['templatedReport/index', 'id' => $network->id],
                        'visible' => $network->hasService(Service::ASCUE) && $isCanViewReports && $locale->isHasModule(Module::model()->findByName(['name' => Module::REPORTS_CONSUMPTION])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'send_to_GP_and_TSO'),
                        'url' => ['mailing/network', 'id' => $network->id],
                        'visible' => $supplyMailing && !$isContractor || $user->showSentMails && $locale->isHasModule(Module::model()->findByName(['name' => Module::REPORTS_SEND])),
                        'adminVisible' => $hourlyProfile,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'meter_information'),
                        'url' => ['network/reportMeters', 'id' => $network->id],
                        'visible' => !$isContractor || $isCanCreateReports && $locale->isHasModule(Module::model()->findByName(['name' => Module::REPORTS_METER])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'reference_information'),
                        'url' => ['network/stateChecks', 'id' => $network->id],
                        'visible' => !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::REPORTS_REFERENCE])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'pdf_reports'),
                        'url' => ['reportSchedule/index', 'id' => $network->id],
                        'visible' => $network->hasService(Service::ASCUE) && $isCanViewReports && $locale->isHasModule(Module::model()->findByName(['name' => Module::REPORTS_PDF])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'sbis'),
                        'url' => ['sbis/reports', 'networkId' => $network->id],
                        'visible' => $sbis && !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::REPORTS_SBIS])),
                    ],
                ],
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'tariff_analysis'),
                'visible' => $economyReports && !$user->getIsContractor() && $locale->isHasModule(Module::model()->findByName(['name' => Module::TARIFF_ANALYSIS])),
                'items' => [
                    [
                        'label' => Yii::t('controllers.ManagerController', 'calculate'),
                        'url' => ['economyReport/promo', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::TARIFF_ANALYSIS_CALCULATE])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'reports'),
                        'url' => ['economyReport/index', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::TARIFF_ANALYSIS_REPORTS])),
                    ],
                ],
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'statements'),
                'url' => ['payroll/index', 'id' => $network->id],
                'visible' => $network->hasService(Service::CHARGING) && !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::STATEMENTS])),
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'contracts'),
                'url' => ['contract/index', 'id' => $network->id],
                'visible' => $network->hasService(Service::CHARGING) && !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::CONTRACTS])),
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'map'),
                'url' => ['network/map', 'id' => $network->id],
                'visible' => !$isContractor && $locale->isHasModule(Module::model()->findByName(['name' => Module::MAP])),
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'settings'),
                'items' => [
                    [
                        'label' => Yii::t('controllers.ManagerController', 'basic_settings'),
                        'url' => ['network/update', 'id' => $network->id],
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'documents'),
                        'url' => ['s3/network', 'id' => $network->id],
                        'visible' => $contract && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_DOCUMENTS])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'admin_reports'),
                        'url' => ['network/adminReport', 'id' => $network->id],
                        'adminVisible' => true,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'meter_import'),
                        'url' => ['networkImport/counters', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_METER])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'import'),
                        'url' => ['networkImport/ascueSettings', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_IMPORT])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'deleted_counters'),
                        'url' => ['network/deletedItems', 'id' => $network->id],
                        'adminVisible' => true,
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'billing_order'),
                        'url' => ['network/engines', 'id' => $network->id],
                        'visible' => $network->hasService(Service::CHARGING) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_BILLING])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'tariff_plan'),
                        'url' => ['network/tariff', 'id' => $network->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_TARIFF_PLAN])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'account_and_payment'),
                        'url' => ['transaction/index', 'id' => $contract->id ?? 0],
                        'visible' => isset($contract) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_ACCOUNT])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'events'),
                        'url' => ['userEvent/user', 'id' => $user->id],
                        'visible' => $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_EVENTS])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'api_settings'),
                        'url' => ['network/api', 'id' => $network->id],
                        'visible' => $network->hasService(Service::API) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_API])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'access'),
                        'url' => ['objectAccess/network', 'id' => $network->id],
                        'visible' => $webUser->getIsManager() || $webUser->getIsAdmin() && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_ACCESS])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'integration'),
                        'url' => ['integration/index', 'id' => $network->id],
                        'visible' => $this->getHasModelAccess('createObject', $network) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_INTEGRATION])),
                    ],
                ],
                'visible' => !$webUser->getIsWorker() && !$webUser->getIsContractor() && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS])),
            ],
            [
                'label' => Yii::t('controllers.ManagerController', 'settings'),
                'items' => [
                    [
                        'label' => Yii::t('controllers.ManagerController', 'account_and_payment'),
                        'url' => ['transaction/index', 'id' => $contract->id ?? 0],
                        'visible' => isset($contract) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_ACCOUNT])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'meter_import'),
                        'url' => ['networkImport/counters', 'id' => $network->id],
                        'visible' => $this->getHasModelAccess('importCounter', $network) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_METER])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'import'),
                        'url' => ['networkImport/ascueSettings', 'id' => $network->id],
                        'visible' => $this->getHasModelAccess('importAscueSettings', $network) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_IMPORT])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'access'),
                        'url' => ['objectAccess/network', 'id' => $network->id],
                        'visible' => $this->getHasModelAccess('grantAccessContractor', $network->user) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_ACCESS])),
                    ],
                    [
                        'label' => Yii::t('controllers.ManagerController', 'integration'),
                        'url' => ['integration/index', 'id' => $network->id],
                        'visible' => $this->getHasModelAccess('createObject', $network) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS_INTEGRATION])),
                    ],
                ],
                'visible' => $webUser->getIsWorker() && (
                    $this->getWebUserModel()->worker->canViewAccountingDocuments ||
                    $this->getHasModelAccess('importAscueSettings', $network) ||
                    $this->getHasModelAccess('importCounter', $network) ||
                    $this->getHasModelAccess('createObject', $network)

                ) && $locale->isHasModule(Module::model()->findByName(['name' => Module::SETTINGS])),
            ],
        ];
    }

    /**
     * Возвращает последнюю запомненную сеть.
     *
     * Идентификатор сети извлекается из куки, а так же проверяется, имеет ли
     * пользователь доступ к этой сети.
     *
     * @return Network|null
     */
    private function restoreLastNetwork()
    {
        $cookies = Yii::app()->getRequest()->getCookies();

        if (!$cookies->contains(self::LAST_NETWORK_ID_COOKIE)) {
            return;
        }

        $value = explode('-', $cookies[self::LAST_NETWORK_ID_COOKIE]->value);
        if (!$value || count($value) < 2 || !Yii::app()->user->getIsGuest() && Yii::app()->db->getCurrentDatabase()->id != $value[1]) {
            $cookies->remove(self::LAST_NETWORK_ID_COOKIE);

            return;
        }
        $lastNetworkId = $value[0];

        $network = Network::model()->undeleted()->findByPk($lastNetworkId);
        $webUser = $this->getWebUser();

        if ($network && ($webUser->getIsAdmin() || $webUser->getIsAdminVisit() || $network->userId === $webUser->getModelId())) {
            return $network;
        }
    }

    /**
     * Сохраняет идентификатор сети в куки.
     */
    private function saveLastNetwork(Network $network = null)
    {
        $cookies = Yii::app()->getRequest()->getCookies();

        if ($network) {
            $cookies[self::LAST_NETWORK_ID_COOKIE] = new CHttpCookie(
                self::LAST_NETWORK_ID_COOKIE,
                $network->id.'-'.Yii::app()->db->getCurrentDatabase()->id,
                [
                    'secure' => ConfigHelper::getEnvAsBoolean('CONNECTION_SECURE', false),
                ]
            );
        } else {
            $cookies->remove(self::LAST_NETWORK_ID_COOKIE);
        }
    }

    /**
     * Доступны ли пользователю ценовые категории.
     *
     * @param bool $edit       если true, проверяет так же доступно ли пользователю редактирование параметров ЦК
     * @param bool $checkAdmin не проверять для админов доступность услуги
     *
     * @return bool
     */
    public function getHasPricecats($edit = false, $checkAdmin = true)
    {
        $admin = $checkAdmin && $this->getWebUser()->getIsAdmin();
        $network = $this->getNetwork();
        $result = $network && $network->hasService(Service::COUNTER_HOURLY_PROFILE) &&
            ($admin || $network->hasService(Service::PRICE_CATEGORIES));
        if ($result && $edit && !$admin && !$this->getWebUserModel()->getCanEditPricecats()) {
            return false;
        }

        return $result;
    }

    /**
     * Создает фоновую задачу, отслеживаемую пользователем и отправляет json ответ.
     *
     * @param string $handler
     * @param array  $params
     * @param string $name
     * @param string $queueName
     * @param bool   $performAjax Выполняется ли запрос асинхронно
     *
     * @see TaskQueue::push()
     */
    public function pushUserTask($handler, $params, $name, $queueName = null, bool $performAjax = true)
    {
        if (!Yii::app()->request->getIsAjaxRequest() && $performAjax) {
            throw new CHttpException(400, Yii::t('controllers.ManagerController', 'ajax_not_supported'));
        }
        $id = Yii::app()->taskQueue->push($handler, $params, null, false, $queueName);
        if ($id === null) {
            if ($performAjax) {
                $this->renderJson(self::FAIL, ['message' => Yii::t('controllers.ManagerController', 'task_already_created')]);
            } else {
                throw new CHttpException(400, Yii::t('controllers.ManagerController', 'task_already_created'));
            }
        }
        $task = UserTask::createForBackground($id, $name);
        if ($performAjax) {
            $this->renderJson(self::SUCCESS, ['task' => $task->getAjaxData()]);
        }

        return $task;
    }

    /**
     * Создает фоновую задачу генерации отчета, отслеживаемую пользователем и отправляет json ответ.
     *
     * @param string $class  класс отчета
     * @param array  $params параметры
     * @param string $name   название задачи
     *
     * @see self::pushUserTask()
     */
    public function pushReportTask($class, $params, $name)
    {
        $this->pushUserTask('excelReport', [
            'class' => $class,
            'params' => $params,
        ], $name, TaskQueue::USER_QUEUE);
    }
}
