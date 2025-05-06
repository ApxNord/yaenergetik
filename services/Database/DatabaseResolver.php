<?php

class DatabaseResolver
{
    public function resolve(CAction $action, CController $controller): void
    {
        $actions = $controller->getDefaultDbActions();

        if ($actions === null || (!empty($actions) && !in_array($action->getId(), $actions, true))) {
            return;
        }

        $currentDb = Yii::app()->db->getCurrentDatabase();
        $defaultDb = Database::model()->findByAttributes(['default' => 1]) ?: Database::model()->find();

        if ($defaultDb && $currentDb->id != $defaultDb->id) {
            $controller->redirect($this->buildUrl($defaultDb, $action, $controller));
        }
    }

    private function buildUrl(Database $db, CAction $action, CController $controller): string
    {
        return Yii::app()->createAbsoluteUrl(
            "/{$db->title}/{$controller->getId()}/{$action->getId()}",
            Yii::app()->request->queryParams
        );
    }
}
