<?php

class SameHostStrategy implements CsrfValidationStrategy
{
    public function validate(CHttpRequest $request): void
    {
        if (!$request->isPostRequest) {
            return;
        }

        $referer = $request->getUrlReferrer();
        $currentHost = $this->parseHost($request->getHostInfo());
        $refererHost = $this->parseHost($referer);

        $this->validateHosts($currentHost, $refererHost);
    }

    private function validateHosts(?string $current, ?string $referer): void
    {
        if (!$referer || !$current) {
            throw new CsrfValidationException('Invalid host of referer');
        }

        if (strcasecmp($referer, $current) !== 0) {
            throw new CsrfValidationException('Host mismatch');
        }
    }
    protected function parseHost(?string $url): ?string
    {
        return $url ? parse_url($url, PHP_URL_HOST) : null;
    }
}
