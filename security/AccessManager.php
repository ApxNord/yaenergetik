<?php

class AccessManager
{
    public function createRules($userRole, array $defaultRoles, array $defaultUsers)
    {
        $allow = ['allow'];
        if ($userRole !== null) {
            $allow['roles'] = array_merge($defaultRoles, (array) $userRole);
        } else {
            $allow['users'] = $defaultUsers;
        }

        return [$allow, ['deny']];
    }

    public function verify($opName, ?ActiveRecord $model)
    {
        if (!Yii::app()->user->checkAccess($opName, ['model' => $model])) {
            $this->handleAccessError();
        }
    }

    private function handleAccessError()
    {
        if (Yii::app()->request->getIsAjaxRequest()) {
            $this->renderJson(self::FAIL, ['message' => Yii::t('controllers.AuthorizedController', 'access_error')]);
        } else {
            throw new CHttpException(403, Yii::t('controllers.AuthorizedController', 'access_error'));
        }
    }
}