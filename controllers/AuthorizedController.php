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

    /**
     * {@inheritdoc}
     */
    public function accessRules()
    {
        $allow = ['allow'];
        if ($this->userRole !== null) {
            $allow['roles'] = array_merge(['admin'], (array) $this->userRole);
        } else {
            $allow['users'] = ['@'];
        }

        return [$allow, ['deny']];
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
        $model = Yii::app()->user->getIsAdmin() && !empty($id)
            ? $model = User::model()->findByPk($id)
            : $model = Yii::app()->user->getModel();

        $this->ensureModelExists($model, Yii::t('controllers.AuthorizedController', 'user_not_found'));

        return $model;
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

    public function getHasAccess($opName, array $params = [])
    {
        if (!isset($params['model'])) {
            $params['model'] = null;
        }

        return Yii::app()->user->checkAccess($opName, $params);
    }

    public function getHasModelAccess($opName, ActiveRecord $model = null)
    {
        $this->ensureModelExists($model);
        $params = ['model' => $model];

        return $this->getHasAccess($opName, $params);
    }

    public function checkAccess($opName, array $params = [])
    {
        if (!isset($params['model'])) {
            $params['model'] = null;
        }

        if (!Yii::app()->user->checkAccess($opName, $params)) {
            if (Yii::app()->request->getIsAjaxRequest()) {
                $this->renderJson(self::FAIL, ['message' => Yii::t('controllers.AuthorizedController', 'access_error')]);
            } else {
                throw new CHttpException(403, Yii::t('controllers.AuthorizedController', 'access_error'));
            }
        }
    }

    public function checkModelAccess($opName, ActiveRecord $model = null)
    {
        $this->ensureModelExists($model);
        $params = ['model' => $model];
        $this->checkAccess($opName, $params);
    }

    public function blockDemo()
    {
        if ($this->getWebUserModel()->getIsDemo()) {
            throw new CHttpException(403, Yii::t('controllers.AuthorizedController', 'access_error'));
        }
    }

    /**
     * @psalm-assert ActiveRecord $model
     */
    public function ensureModelExists(ActiveRecord $model = null, $message = null)
    {
        if ($model === null) {
            if (Yii::app()->request->getIsAjaxRequest()) {
                $this->renderJson(self::FAIL, ['message' => Yii::t('controllers.AuthorizedController', 'data_not_found')]);
            } else {
                throw new CHttpException(404, $message ?: Yii::t('controllers.AuthorizedController', 'data_not_found'));
            }
        }
    }
}
