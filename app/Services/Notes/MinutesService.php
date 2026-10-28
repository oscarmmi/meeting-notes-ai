<?php

namespace App\Services\Notes;

use App\Models\Meeting;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Facades\DB;
use JsonException;

class MinutesService
{
    public function __construct(private readonly LlmClient $llm) {}

    /** @return array{summary: string, decisions: array<int, string>, actions: array<int, array{owner: string, task: string, due_hint: ?string}>} */
    public function generate(string $transcript): array
    {
        try {
            $data = json_decode($this->llm->generate($transcript), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidMinutesResponse('The notes service returned unreadable JSON.', previous: $exception);
        }

        if (! is_array($data)
            || ! is_string($data['summary'] ?? null)
            || ! is_array($data['decisions'] ?? null)
            || ! is_array($data['actions'] ?? null)
        ) {
            throw new InvalidMinutesResponse('The notes service returned an incomplete response.');
        }

        $decisions = [];
        foreach ($data['decisions'] as $decision) {
            if (! is_string($decision) || trim($decision) === '') {
                throw new InvalidMinutesResponse('The notes service returned an invalid decision.');
            }
            $decisions[] = trim($decision);
        }

        $actions = [];
        foreach ($data['actions'] as $action) {
            if (! is_array($action)
                || ! is_string($action['owner'] ?? null)
                || trim($action['owner']) === ''
                || ! is_string($action['task'] ?? null)
                || trim($action['task']) === ''
                || (! is_string($action['due_hint'] ?? null) && ($action['due_hint'] ?? null) !== null)
            ) {
                throw new InvalidMinutesResponse('The notes service returned an invalid action item.');
            }

            $actions[] = [
                'owner' => trim($action['owner']),
                'task' => trim($action['task']),
                'due_hint' => isset($action['due_hint']) ? trim($action['due_hint']) : null,
            ];
        }

        return [
            'summary' => trim($data['summary']),
            'decisions' => $decisions,
            'actions' => $actions,
        ];
    }

    /** @param array{title: string, raw_transcript: string, attendees: array<int, string>} $input */
    public function createMeeting(array $input): Meeting
    {
        $minutes = $this->generate($input['raw_transcript']);

        return DB::transaction(function () use ($input, $minutes): Meeting {
            $meeting = Meeting::query()->create([
                'title' => $input['title'],
                'raw_transcript' => $input['raw_transcript'],
                'summary' => $minutes['summary'],
                'attendees_json' => $input['attendees'],
                'decisions_json' => $minutes['decisions'],
            ]);

            $meeting->actionItems()->createMany($minutes['actions']);

            return $meeting->load('actionItems');
        });
    }
}
