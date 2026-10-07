<?php

declare(strict_types=1);

namespace OneMax\Http;

interface Transport
{
    public function send(Request $request): Response;
}
