<?php

class CsrfStrategyFactory
{
        /**
     * Разрешить принимать запросы только от того же домена.
     */
    const CSRF_ALLOW_SAME = 0;
    /**
     * Разрешить принимать запросы с домена на уровень выше (если приложение на поддомене).
     */
    const CSRF_ALLOW_HOST = 1;
    /**
     * Разрешить принимать любые запросы.
     */
    const CSRF_ALLOW_ANY = 10;
    public static function create(int $rule): CsrfValidationStrategy
    {
        return match($rule) 
        {
            self::CSRF_ALLOW_SAME => new SameHostStrategy(),
            self::CSRF_ALLOW_HOST => new ParentHostStrategy(),
            self::CSRF_ALLOW_ANY => new AllowAnyStrategy(),
            default => throw new InvalidArgumentException('Unknow CSRF rule'),
        };
    }
}
