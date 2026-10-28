<?php

namespace Database\Seeders;

use App\Models\Meeting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $sprintReview = Meeting::query()->firstOrCreate(
            ['title' => 'Harbor Desk — Sprint review'],
            [
                'raw_transcript' => "The Harbor Desk team reviewed onboarding progress and the release plan.\nDECISION: Ship the improved workspace onboarding in the next release.\nACTION: Product lead | Finalize the release checklist | Friday\nACTION: Design lead | Review the onboarding empty state | Thursday",
                'summary' => 'The team reviewed onboarding progress and agreed on the next release scope.',
                'attendees_json' => ['Product lead', 'Design lead', 'Engineering lead'],
                'decisions_json' => ['Ship the improved workspace onboarding in the next release.'],
            ],
        );

        if ($sprintReview->actionItems()->doesntExist()) {
            $sprintReview->actionItems()->createMany([
                ['owner' => 'Product lead', 'task' => 'Finalize the release checklist', 'due_hint' => 'Friday'],
                ['owner' => 'Design lead', 'task' => 'Review the onboarding empty state', 'due_hint' => 'Thursday'],
            ]);
        }

        $incidentRetro = Meeting::query()->firstOrCreate(
            ['title' => 'Harbor Desk — Incident retro'],
            [
                'raw_transcript' => "The team reviewed a short staging outage caused by an expired service token.\nDECISION: Add an expiry alert and rotate staging tokens before each release.\nACTION: Platform lead | Add a token expiry alert | Next sprint\nACTION: Release lead | Document the staging recovery steps | Wednesday",
                'summary' => 'A staging outage was traced to an expired service token. The team agreed to add proactive expiry monitoring and clearer recovery steps.',
                'attendees_json' => ['Platform lead', 'Release lead', 'Engineering lead'],
                'decisions_json' => ['Add an expiry alert and rotate staging tokens before each release.'],
            ],
        );

        if ($incidentRetro->actionItems()->doesntExist()) {
            $incidentRetro->actionItems()->createMany([
                ['owner' => 'Platform lead', 'task' => 'Add a token expiry alert', 'due_hint' => 'Next sprint'],
                ['owner' => 'Release lead', 'task' => 'Document the staging recovery steps', 'due_hint' => 'Wednesday'],
            ]);
        }
    }
}
