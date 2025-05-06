<?php

class RedirectHandler
{
    public function getDashboardUrl()
    {
        return Yii::app()->user->getDashboardUrl();
    }

    public function redirectEx($url, $terminate = true)
    {
        $request = Yii::app()->request;

        if ($request->getIsAjaxRequest()) {
            Yii::app()->controller->redirect($url, $terminate, 400);
        } else {
            Yii::app()->controller->redirect($url, $terminate);
        }
    }
}
