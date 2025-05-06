<?php

/**
 * Контроллер, который является базовым для контроллеров, с которыми работают
 * авторизованные пользователи.
 *
 * Включает в себя базовую проверку доступа, проверку на исполнение действий,
 * получение пользователя по идентификатору, установку пареметров по умолчанию
 * для обработки запроса пользователя.
 */
class AuthorizedController extends BaseController
{
    /**
     * Роль пользователя. Только пользователи, которые имеет указанную роль,
     * могут выполнять действия контроллера и всех его наследников.
     *
     * @var null|string|array[string]
     */
    public $userRole;

    /**
     * @var bool установлен ли часовой пояс.
     */
    protected $timezoneSet = false;

    protected UserLoader $userLoader;
    protected AccessManager $accessManager;

    /**
     * {@inheritdoc}
     */
    public function accessRules()
    {
        return $this->accessManager->createRules(
            $this->userRole,
            ['admin'],
            ['@']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function filters()
    {
        return [
            'accessControl',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        $this->userLoader = new UserLoader();
        $this->accessManager = new AccessManager();

        // Если часовой пояс еще не установлен дочерним контроллером
        if (!$this->timezoneSet) {
            // Устанавливаем часовой пояс для пользователя
            $webUser = Yii::app()->getUser();

            if (!$webUser->getIsGuest()) {
                DateUtils::setUserTimezone($webUser->getModel());
            }
        }
    }

    /**
     * Возвращает пользователя, от имени которого выполняется запрос.
     *
     * Для обычных пользователей будет возвращен сам пользователь,
     * а если запрос сделан администратором, то будет возвращен
     * пользователь по указанному идентификатору.
     *
     * @param int|null $id Идентификатор пользователя
     *
     * @return User
     */
    public function loadUser($id)
    {
        return $this->userLoader->load($id);
    }

    protected function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::app()->getUser();
        if ($user->getIsApiSession()) {
            throw new CHttpException(403);
        }
        $user->updateLastVisitTime();

        if (!$user->getIsGuest() && $user->getAuthUser()->locked) {
            $user->logout();
            $this->redirect('/');

            return false;
        }

        return true;
    }

    public function checkAccess($opName, ?ActiveRecord $model = null)
    {
        $this->accessManager->verify($opName, $model);
    }

    public function blockDemo()
    {
        if ($this->getWebUserModel()->getIsDemo()) {
            throw new CHttpException(403, Yii::t('controllers.AuthorizedController', 'access_error'));
        }
    }
}
