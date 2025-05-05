<?php

class DatabaseResolver
{
    public function resolveForAction(CAction $action): void
    {
        $currentDb = Yii::app()->db->getCurrentDatabase();
        $actions = $this->getDefaultDbActions();
        if ($actions === null || (!empty($actions) && !in_array($action->id, $actions))) {
            return;
        }
       
        if ($this->needsDefaultDb($action, $actions)) {
            $this->switchToDefaultDb();
        }
    }

        /**
     * Возвращает экшены, которые необходимо выполнять в контекте бд по умолчанию.
     *
     * @return null|array null - никакие, [] - все
     */
    public function getDefaultDbActions()
    {
        return null;
    }

    private function needsDefaultDb(CAction $action, ?array $actions): bool
    {
        return $actions === null || (!empty($actions) && !in_array($action->id, $actions));
    }

    private function switchToDefaultDb(): void
    {
        $defaultDb = Database::model()->findByAttribures(['default' => 1]) 
            ?? Database::model()->find();
            
        if ($defaultDb && $currentDb->id != $defaultDb->id) {
            $this->redirect('/'.$defaultDb->title.'/'.Yii::app()->controller->id.'/'.$action->id
                .(Yii::app()->request->queryString ? '?'.Yii::app()->request->queryString : ''));
        }
    }
}