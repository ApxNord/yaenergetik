<?php

/**
 * Класс для управления переключением между базами данных.
 * 
 * Автоматически перенаправляет на версию URL с нужной БД, 
 * если действие требует использования базы данных по умолчанию.
 * 
 * @package components.db
 */
class DatabaseResolver
{
    /**
     * Определяет необходимость переключения БД и выполняет редирект.
     * 
     * @param CAction $action Текущий экшен
     * @param CController $controller Контроллер
     * 
     * @throws DatabaseNotFoundException Если отсутствует дефолтная БД
     */
    public function resolve(CAction $action, CController $controller): void
    {
        $actions = $controller->getDefaultDbActions();

        // Проверка необходимости переключения
        if ($actions === null || (!empty($actions) && !in_array($action->getId(), $actions, true))) {
            return;
        }

        $currentDb = Yii::app()->db->getCurrentDatabase();
        $defaultDb = Database::model()->findByAttributes(['default' => 1]) ?: Database::model()->find();

        if ($defaultDb && $currentDb->id != $defaultDb->id) {
            $controller->redirect($this->buildUrl($defaultDb, $action, $controller));
        }
    }

    /**
     * Строит URL для редиректа с учетом БД.
     */
    private function buildUrl(Database $db, CAction $action, CController $controller): string
    {
        return Yii::app()->createAbsoluteUrl(
            "/{$db->title}/{$controller->getId()}/{$action->getId()}",
            Yii::app()->request->queryParams
        );
    }
}
