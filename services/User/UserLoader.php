<?php

class UserLoader
{
    public function load(?int $id): User
    {
        $user = Yii::app()->user->getIsAdmin() && $id
            ? User::model()->findByPk($id)
            : Yii::app()->user->getModel();

        if (!$user) {
            throw new NotFoundException("Error Processing Request", 1);
            
        }

        return $user;
    }
}