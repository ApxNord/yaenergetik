<?php

interface CsrfValidationStrategy
{
    public function validate(CHttpRequest $request): void;
}
