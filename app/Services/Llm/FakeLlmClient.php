<?php

namespace App\Services\Llm;

class FakeLlmClient implements LlmClient
{
    public function generate(string $transcript): string
    {
        $decisions = [];
        $actions = [];

        foreach (preg_split('/\R/', $transcript) ?: [] as $line) {
            $line = trim($line);

            if (preg_match('/^DECISION:\s*(.+)$/i', $line, $matches) === 1) {
                $decisions[] = trim($matches[1]);
            }

            if (preg_match('/^ACTION:\s*(.+)$/i', $line, $matches) === 1) {
                $parts = array_map('trim', explode('|', $matches[1], 3));
                $action = [
                    'owner' => isset($parts[1]) ? $parts[0] : 'Unassigned',
                    'task' => $parts[1] ?? $parts[0],
                    'due_hint' => $parts[2] ?? null,
                ];

                if (! isset($parts[1])) {
                    $action['owner'] = 'Unassigned';
                }

                $actions[] = $action;
            }
        }

        $context = collect(preg_split('/\R/', $transcript) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '' && ! preg_match('/^(ACTION|DECISION):/i', $line));

        return json_encode([
            'summary' => $context
                ? "The team discussed: {$context}"
                : 'The team reviewed the meeting transcript.',
            'decisions' => $decisions,
            'actions' => $actions,
        ], JSON_THROW_ON_ERROR);
    }
}
