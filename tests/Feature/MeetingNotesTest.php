<?php

use App\Models\Meeting;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(LlmClient::class, new FakeLlmClient);
});

function standupTranscript(): string
{
    return <<<'TRANSCRIPT'
Harbor Desk morning standup
Morgan: The updated workspace flow is ready for review.
Riley: I found one confusing message in the invite flow.
Casey: Search is stable after yesterday's index change.
DECISION: Keep this release focused on onboarding improvements.
ACTION: Riley | Rewrite the expired invite message | Thursday
ACTION: Casey | Monitor search latency in staging | Friday
Morgan: The onboarding guide needs current screenshots.
ACTION: Morgan | Capture new onboarding screenshots | Wednesday
TRANSCRIPT;
}

it('uses the fake LLM and saves generated minutes and owned action items', function () {
    expect(app(LlmClient::class))->toBeInstanceOf(FakeLlmClient::class);

    $response = $this->postJson(route('meetings.store'), [
        'title' => 'Harbor Desk — Daily standup',
        'attendees' => 'Morgan, Riley, Casey',
        'raw_transcript' => standupTranscript(),
    ]);

    $response->assertOk()
        ->assertJsonPath('meeting.title', 'Harbor Desk — Daily standup')
        ->assertJsonPath('meeting.decisions_json.0', 'Keep this release focused on onboarding improvements.')
        ->assertJsonCount(3, 'meeting.action_items');

    $this->assertDatabaseCount('meetings', 1);
    $this->assertDatabaseCount('action_items', 3);
    $this->assertDatabaseHas('action_items', [
        'owner' => 'Riley',
        'task' => 'Rewrite the expired invite message',
        'due_hint' => 'Thursday',
        'status' => 'open',
    ]);
});

it('returns validation errors for an empty transcript', function () {
    $this->postJson(route('meetings.store'), [
        'title' => 'Empty meeting',
        'raw_transcript' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors('raw_transcript');

    $this->assertDatabaseCount('meetings', 0);
});

it('handles malformed LLM JSON gracefully without persisting a meeting', function () {
    app()->instance(LlmClient::class, new class implements LlmClient
    {
        public function generate(string $transcript): string
        {
            return '{not valid json';
        }
    });

    $this->postJson(route('meetings.store'), [
        'title' => 'Malformed response',
        'raw_transcript' => standupTranscript(),
    ])->assertUnprocessable()->assertJsonPath('message', 'We could not read the generated minutes. Please try again or check the transcript.');

    $this->assertDatabaseCount('meetings', 0);
});

it('returns a friendly unavailable response when the LLM fails', function () {
    app()->instance(LlmClient::class, new class implements LlmClient
    {
        public function generate(string $transcript): string
        {
            throw new RuntimeException('provider timeout');
        }
    });

    $this->postJson(route('meetings.store'), [
        'title' => 'Provider failure',
        'raw_transcript' => standupTranscript(),
    ])->assertStatus(503)->assertJsonPath('message', 'Meeting notes are temporarily unavailable. Your transcript was not saved; please try again.');

    $this->assertDatabaseCount('meetings', 0);
});

it('shows the notes desk with its saved meeting history', function () {
    $meeting = Meeting::query()->create([
        'title' => 'Harbor Desk — Sprint review',
        'raw_transcript' => 'Release plan reviewed.',
        'summary' => 'The team aligned on the release plan.',
        'attendees_json' => ['Product lead'],
        'decisions_json' => [],
    ]);
    $meeting->actionItems()->create([
        'owner' => 'Product lead',
        'task' => 'Share release checklist',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('meetings', 1)
            ->where('meetings.0.title', 'Harbor Desk — Sprint review')
            ->where('llmMode', 'Offline mode'));
});

it('seeds the two fictional Harbor Desk meetings with action items', function () {
    $this->seed();

    expect(Meeting::query()->count())->toBe(2)
        ->and(Meeting::query()->where('title', 'like', 'Harbor Desk%')->count())->toBe(2)
        ->and(Meeting::query()->withCount('actionItems')->get()->sum('action_items_count'))->toBe(4);
});
