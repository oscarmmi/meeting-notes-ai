<?php

namespace App\Services\Llm;

interface LlmClient
{
    public function generate(string $transcript): string;
}
