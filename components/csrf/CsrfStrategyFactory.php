<?php

class CsrfStrategyFactory
{
    public static function create(int $rule): CsrfValidationStrategy
    {
        return match($rule) 
        {
            BaseController::CSRF_ALLOW_SAME => new SameHostStrategy(),
            BaseController::CSRF_ALLOW_HOST => new ParentHostStrategy(),
            BaseController::CSRF_ALLOW_ANY => new AllowAnyStrategy(),
            default => throw new InvalidArgumentException('Unknow CSRF rule'),
        };
    }
}
