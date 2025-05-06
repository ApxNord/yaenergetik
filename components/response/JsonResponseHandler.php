<?php

class JsonResponseHandler
{
    public function send($success, $data = [])
    {
        Yii::app()->json->send(array_merge(
            ['success' => $success],
            $data
        ));
    }

    /**
     * Отправить страницу в виде JSON.
     *
     * @param string $view
     * @param array  $data
     * @param bool   $processOutput
     */
    public function sendView($view, $data = [], $processOutput = false)
    {
        $html = Yii::app()->controller->renderPartial($view, $data, true, $processOutput);
        $this->send(CHtml::value($data, 'success', BaseController::SUCCESS), ['html' => $html]);
    }
}
