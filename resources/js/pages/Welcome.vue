<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type ActionItem = {
    id: number;
    owner: string;
    task: string;
    due_hint: string | null;
    status: string;
};

type Meeting = {
    id: number;
    title: string;
    raw_transcript: string;
    summary: string;
    attendees_json: string[];
    decisions_json: string[];
    action_items: ActionItem[];
    created_at: string;
};

const exampleTranscript = `Harbor Desk daily standup — Tuesday, 10:00 AM
Morgan: The new workspace navigation is merged and ready for review.
Riley: I tested the invite flow. The happy path works, but expired links need clearer copy.
Casey: Search performance improved after the index change; the dashboard now loads in under a second.
Morgan: Great progress. Let's keep the release focused on onboarding and reliability.
Riley: I'll update the expired invite message and add coverage for the resend flow.
DECISION: Keep the release scope to onboarding and reliability improvements.
ACTION: Riley | Update expired invite copy and add resend-flow tests | Thursday
Casey: I can monitor the new search index in staging during the rollout.
ACTION: Casey | Monitor search index latency in staging | Before release
Morgan: We still need final screenshots for the onboarding guide.
ACTION: Morgan | Capture updated onboarding screenshots | Wednesday
Riley: The API rate limit alert is too sensitive for normal traffic.
DECISION: Raise the staging rate-limit alert threshold and review it after one week.
Casey: I'll update the alert and share the dashboard with the team.
ACTION: Casey | Adjust the staging rate-limit alert threshold | Today
Morgan: Let's review the rollout checklist in our next standup.
Riley: I'll add the checklist link to the release notes.
ACTION: Riley | Add the rollout checklist link to release notes | Friday
Morgan: Thanks, everyone. We are on track for the planned release.`;

const props = defineProps<{ meetings: Meeting[]; llmMode: string }>();
const meetings = ref<Meeting[]>(props.meetings);
const selectedMeetingId = ref<number | null>(meetings.value[0]?.id ?? null);
const selectedMeeting = computed(
    () =>
        meetings.value.find(
            (meeting) => meeting.id === selectedMeetingId.value,
        ) ?? null,
);
const title = ref(selectedMeeting.value?.title ?? '');
const attendees = ref(selectedMeeting.value?.attendees_json.join(', ') ?? '');
const transcript = ref(selectedMeeting.value?.raw_transcript ?? '');
const isGenerating = ref(false);
const errorMessage = ref('');
const noticeMessage = ref('');
const openActionCount = computed(() =>
    meetings.value.reduce(
        (total, meeting) =>
            total +
            meeting.action_items.filter((item) => item.status === 'open')
                .length,
        0,
    ),
);

function selectMeeting(meeting: Meeting) {
    selectedMeetingId.value = meeting.id;
    title.value = meeting.title;
    attendees.value = meeting.attendees_json.join(', ');
    transcript.value = meeting.raw_transcript;
    errorMessage.value = '';
    noticeMessage.value = '';
}

function startNewMeeting() {
    selectedMeetingId.value = null;
    title.value = '';
    attendees.value = '';
    transcript.value = '';
    errorMessage.value = '';
    noticeMessage.value = '';
}

async function generateMinutes() {
    errorMessage.value = '';
    noticeMessage.value = '';

    if (!transcript.value.trim()) {
        errorMessage.value = 'Paste a transcript before generating minutes.';
        return;
    }

    isGenerating.value = true;

    try {
        const response = await fetch('/meetings', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({
                title: title.value,
                attendees: attendees.value,
                raw_transcript: transcript.value,
            }),
        });
        const result = await response.json();

        if (!response.ok) {
            errorMessage.value =
                result.message ??
                Object.values(result.errors ?? {}).flat()[0] ??
                'Could not generate minutes. Please try again.';
            return;
        }

        const meeting = result.meeting as Meeting;
        meetings.value = [
            meeting,
            ...meetings.value.filter((item) => item.id !== meeting.id),
        ];
        selectMeeting(meeting);
        noticeMessage.value = 'Minutes generated and saved.';
    } catch {
        errorMessage.value =
            'We could not reach the notes desk. Check your connection and try again.';
    } finally {
        isGenerating.value = false;
    }
}

function loadExample() {
    if (!title.value.trim()) title.value = 'Harbor Desk — Daily standup';
    if (!attendees.value.trim()) attendees.value = 'Morgan, Riley, Casey';
    transcript.value = exampleTranscript;
    selectedMeetingId.value = null;
    errorMessage.value = '';
    noticeMessage.value =
        'Example transcript loaded — 20 lines, ready to generate.';
}

function formatDate(date: string) {
    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: 'numeric',
    }).format(new Date(date));
}
</script>

<template>
    <Head title="Meeting Notes Desk" />
    <div class="desk-shell min-h-screen text-slate-900">
        <aside class="desk-sidebar flex flex-col">
            <a
                href="/"
                class="brand-lockup flex items-center gap-3"
                aria-label="Meeting Notes AI home"
            >
                <span
                    class="brand-mark grid h-10 w-10 place-items-center rounded-xl"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        class="h-5 w-5"
                        aria-hidden="true"
                    >
                        <path
                            d="M6.75 3.75h7.5l4.5 4.5v12h-12v-16.5Z"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M14.25 3.75v4.5h4.5M9.75 12h6M9.75 15.75h6"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                        />
                    </svg>
                </span>
                <span>
                    <span
                        class="block text-sm font-semibold tracking-tight text-white"
                        >Minutes</span
                    >
                    <span class="block text-[11px] text-slate-400"
                        >MEETING NOTES AI</span
                    >
                </span>
            </a>

            <div class="mt-11 flex items-center justify-between px-3">
                <p class="section-kicker">WORKSPACE</p>
                <span class="workspace-dot" aria-hidden="true"></span>
            </div>
            <button
                class="workspace-switch mt-3 flex w-full items-center justify-between rounded-lg px-3 py-3 text-left"
            >
                <span class="flex items-center gap-3">
                    <span
                        class="workspace-avatar grid h-8 w-8 place-items-center rounded-lg text-xs font-semibold"
                        >HD</span
                    >
                    <span
                        ><span class="block text-sm font-medium text-slate-100"
                            >Harbor Desk</span
                        ><span class="block text-xs text-slate-400"
                            >Product team</span
                        ></span
                    >
                </span>
                <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    class="h-4 w-4 text-slate-500"
                    aria-hidden="true"
                >
                    <path
                        d="m6 8 4 4 4-4"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>

            <div class="mt-10 flex items-center justify-between px-3">
                <p class="section-kicker">YOUR MEETINGS</p>
                <span class="text-xs text-slate-500">{{
                    meetings.length
                }}</span>
            </div>
            <nav class="mt-3 space-y-1" aria-label="Meetings">
                <button
                    v-for="meeting in meetings"
                    :key="meeting.id"
                    class="meeting-nav-item flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition"
                    :class="
                        selectedMeetingId === meeting.id
                            ? 'meeting-nav-active'
                            : ''
                    "
                    @click="selectMeeting(meeting)"
                >
                    <svg
                        viewBox="0 0 20 20"
                        fill="none"
                        class="mt-0.5 h-4 w-4 shrink-0"
                        aria-hidden="true"
                    >
                        <rect
                            x="3.25"
                            y="4.25"
                            width="13.5"
                            height="12.5"
                            rx="2"
                            stroke="currentColor"
                            stroke-width="1.4"
                        />
                        <path
                            d="M6.5 2.75v3M13.5 2.75v3M3.5 8h13"
                            stroke="currentColor"
                            stroke-width="1.4"
                            stroke-linecap="round"
                        />
                    </svg>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-medium">{{
                            meeting.title
                        }}</span>
                        <span class="mt-1 block text-[11px] text-slate-500">{{
                            formatDate(meeting.created_at)
                        }}</span>
                    </span>
                    <span
                        v-if="selectedMeetingId === meeting.id"
                        class="mt-1 h-1.5 w-1.5 rounded-full bg-teal-300"
                    ></span>
                </button>
            </nav>
            <button
                class="new-meeting-button mt-3 flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium"
                @click="startNewMeeting"
            >
                <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    class="h-4 w-4"
                    aria-hidden="true"
                >
                    <path
                        d="M10 4v12M4 10h12"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                    />
                </svg>
                New meeting
            </button>

            <div
                class="mt-auto rounded-xl border border-white/10 bg-white/[0.035] p-4"
            >
                <div class="flex items-center gap-2">
                    <span class="status-indicator"></span>
                    <span class="text-xs font-medium text-slate-200">{{
                        llmMode
                    }}</span>
                </div>
                <p class="mt-2 text-[11px] leading-relaxed text-slate-500">
                    {{
                        llmMode === 'Offline mode'
                            ? 'Offline extraction runs locally. Gemini mode sends transcripts to Google for generation.'
                            : 'Transcripts are sent to Google Gemini to generate your meeting notes.'
                    }}
                </p>
            </div>
            <div class="mt-5 flex items-center gap-3 px-2 pb-1">
                <span
                    class="profile-avatar grid h-8 w-8 place-items-center rounded-full text-xs font-semibold"
                    >OD</span
                >
                <span
                    ><span class="block text-xs font-medium text-slate-200"
                        >Workspace owner</span
                    ><span class="block text-[11px] text-slate-500"
                        >Local demo</span
                    ></span
                >
            </div>
        </aside>

        <main class="desk-main min-w-0">
            <header class="topbar flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span>Harbor Desk</span><span class="text-slate-300">/</span
                    ><span class="font-medium text-slate-700"
                        >Meeting notes</span
                    >
                </div>
                <div class="flex items-center gap-4">
                    <span class="hidden text-xs text-slate-500 sm:inline"
                        >{{ openActionCount }} open action items</span
                    >
                    <span
                        class="topbar-avatar grid h-8 w-8 place-items-center rounded-full text-[11px] font-semibold"
                        >OD</span
                    >
                </div>
            </header>

            <div
                class="content-wrap mx-auto max-w-[1220px] px-6 py-9 sm:px-9 lg:px-12"
            >
                <section
                    class="page-heading flex flex-col justify-between gap-5 sm:flex-row sm:items-end"
                >
                    <div>
                        <div
                            class="mb-3 flex items-center gap-2 text-xs font-medium tracking-[0.14em] text-teal-700 uppercase"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-teal-600"
                            ></span
                            >Team workspace
                        </div>
                        <h1
                            class="text-[30px] font-semibold tracking-[-0.04em] text-slate-900 sm:text-[34px]"
                        >
                            Meeting notes desk
                        </h1>
                        <p
                            class="mt-2 max-w-xl text-sm leading-6 text-slate-500"
                        >
                            Turn a conversation into clear minutes, decisions,
                            and work the team can follow through on.
                        </p>
                    </div>
                    <button
                        class="secondary-button inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium"
                        @click="loadExample"
                    >
                        <svg
                            viewBox="0 0 20 20"
                            fill="none"
                            class="h-4 w-4"
                            aria-hidden="true"
                        >
                            <path
                                d="M10 3.5v8m0 0 3-3m-3 3-3-3M4.5 13v2.5h11V13"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        Load example
                    </button>
                </section>

                <div
                    class="stats-row mt-8 grid grid-cols-1 gap-3 sm:grid-cols-3"
                >
                    <div
                        class="stat-card flex items-center gap-4 rounded-xl px-5 py-4"
                    >
                        <span class="stat-icon bg-teal-50 text-teal-700"
                            ><svg
                                viewBox="0 0 24 24"
                                fill="none"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    d="M7 3.75h7.5L19 8.25v12H7v-16.5Z"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M14.5 3.75v4.5H19M10 12h5M10 15.5h5"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                /></svg
                        ></span>
                        <span
                            ><span
                                class="block text-xl font-semibold tracking-tight"
                                >{{ meetings.length }}</span
                            ><span class="mt-0.5 block text-xs text-slate-500"
                                >Meetings documented</span
                            ></span
                        >
                    </div>
                    <div
                        class="stat-card flex items-center gap-4 rounded-xl px-5 py-4"
                    >
                        <span class="stat-icon bg-amber-50 text-amber-700"
                            ><svg
                                viewBox="0 0 24 24"
                                fill="none"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    d="m8 12 2.5 2.5L16.5 8M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                /></svg
                        ></span>
                        <span
                            ><span
                                class="block text-xl font-semibold tracking-tight"
                                >{{
                                    selectedMeeting?.decisions_json.length ?? 0
                                }}</span
                            ><span class="mt-0.5 block text-xs text-slate-500"
                                >Decisions in this meeting</span
                            ></span
                        >
                    </div>
                    <div
                        class="stat-card flex items-center gap-4 rounded-xl px-5 py-4"
                    >
                        <span class="stat-icon bg-indigo-50 text-indigo-700"
                            ><svg
                                viewBox="0 0 24 24"
                                fill="none"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    d="M8 7h11M8 12h11M8 17h11M4.75 7h.5M4.75 12h.5M4.75 17h.5"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                /></svg
                        ></span>
                        <span
                            ><span
                                class="block text-xl font-semibold tracking-tight"
                                >{{
                                    selectedMeeting?.action_items.length ?? 0
                                }}</span
                            ><span class="mt-0.5 block text-xs text-slate-500"
                                >Action items captured</span
                            ></span
                        >
                    </div>
                </div>

                <div
                    v-if="errorMessage"
                    role="alert"
                    class="alert-error mt-6 rounded-lg px-4 py-3 text-sm"
                >
                    {{ errorMessage }}
                </div>
                <div
                    v-if="noticeMessage"
                    role="status"
                    class="alert-success mt-6 rounded-lg px-4 py-3 text-sm"
                >
                    {{ noticeMessage }}
                </div>

                <section class="editor-card mt-6 overflow-hidden rounded-xl">
                    <div
                        class="card-heading flex flex-col justify-between gap-2 border-b px-6 py-5 sm:flex-row sm:items-center"
                    >
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Transcript
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                Paste meeting notes below. We’ll structure the
                                key takeaways for you.
                            </p>
                        </div>
                        <span
                            class="privacy-pill inline-flex items-center gap-1.5 self-start rounded-full px-2.5 py-1 text-[11px] font-medium sm:self-auto"
                        >
                            <svg
                                viewBox="0 0 16 16"
                                fill="none"
                                class="h-3.5 w-3.5"
                                aria-hidden="true"
                            >
                                <rect
                                    x="3.25"
                                    y="7"
                                    width="9.5"
                                    height="6.5"
                                    rx="1.5"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                />
                                <path
                                    d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"
                                    stroke="currentColor"
                                    stroke-width="1.3"
                                    stroke-linecap="round"
                                />
                            </svg>
                            Workspace transcript
                        </span>
                    </div>
                    <div class="p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="field-label"
                                >Meeting title<input
                                    v-model="title"
                                    class="text-input mt-2 w-full rounded-lg px-3.5 py-2.5 text-sm"
                                    placeholder="e.g. Weekly product sync"
                                    maxlength="180"
                            /></label>
                            <label class="field-label"
                                >Attendees
                                <span class="font-normal text-slate-400"
                                    >(optional)</span
                                ><input
                                    v-model="attendees"
                                    class="text-input mt-2 w-full rounded-lg px-3.5 py-2.5 text-sm"
                                    placeholder="Names, separated by commas"
                            /></label>
                        </div>
                        <label class="field-label mt-5 block"
                            >Raw transcript
                            <textarea
                                v-model="transcript"
                                class="transcript-input mt-2 w-full rounded-lg p-4 text-[13px] leading-6"
                                placeholder="Paste your meeting transcript here…"
                                rows="9"
                                maxlength="50000"
                                aria-label="Meeting transcript"
                            ></textarea>
                        </label>
                        <div
                            class="mt-4 flex flex-col-reverse items-start justify-between gap-4 sm:flex-row sm:items-center"
                        >
                            <span class="text-xs text-slate-400"
                                >{{ transcript.length.toLocaleString() }} /
                                50,000 characters</span
                            >
                            <button
                                class="primary-button inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-70"
                                :disabled="isGenerating"
                                @click="generateMinutes"
                            >
                                <svg
                                    v-if="!isGenerating"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="m10 2.75 1.8 5.45 5.45 1.8-5.45 1.8L10 17.25l-1.8-5.45-5.45-1.8 5.45-1.8L10 2.75Z"
                                        stroke="currentColor"
                                        stroke-width="1.45"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="m15.5 2.75.7 2.05 2.05.7-2.05.7-.7 2.05-.7-2.05-2.05-.7 2.05-.7.7-2.05Z"
                                        fill="currentColor"
                                    />
                                </svg>
                                <span
                                    v-if="isGenerating"
                                    class="button-spinner"
                                ></span>
                                {{
                                    isGenerating
                                        ? 'Generating minutes…'
                                        : 'Generate minutes'
                                }}
                            </button>
                        </div>
                    </div>
                </section>

                <div
                    class="results-grid mt-6 grid gap-6 lg:grid-cols-[1fr_1.1fr]"
                >
                    <section class="result-card rounded-xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="result-kicker">THE RECAP</p>
                                <h2
                                    class="mt-1 text-base font-semibold tracking-tight"
                                >
                                    Meeting summary
                                </h2>
                            </div>
                            <span
                                class="summary-icon grid h-9 w-9 place-items-center rounded-lg"
                                ><svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4.5 4.25h11v11.5h-11zM7 7.25h6M7 10h6M7 12.75h3.5"
                                        stroke="currentColor"
                                        stroke-width="1.35"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    /></svg
                            ></span>
                        </div>
                        <p
                            v-if="selectedMeeting"
                            class="mt-5 text-[13px] leading-6 text-slate-600"
                        >
                            {{ selectedMeeting.summary }}
                        </p>
                        <p
                            v-else
                            class="mt-5 text-[13px] leading-6 text-slate-400"
                        >
                            Generate minutes to see a concise recap of your
                            discussion here.
                        </p>
                        <div class="mt-7 border-t border-slate-100 pt-5">
                            <div class="flex items-center gap-2">
                                <span
                                    class="decision-icon grid h-6 w-6 place-items-center rounded-md"
                                    ><svg
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        class="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="m4 8 2.5 2.5L12 5"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        /></svg
                                ></span>
                                <h3 class="text-[13px] font-semibold">
                                    Decisions made
                                </h3>
                            </div>
                            <ul
                                v-if="selectedMeeting?.decisions_json.length"
                                class="mt-3 space-y-3"
                            >
                                <li
                                    v-for="(
                                        decision, index
                                    ) in selectedMeeting.decisions_json"
                                    :key="`${decision}-${index}`"
                                    class="flex gap-3 text-[13px] leading-5 text-slate-600"
                                >
                                    <span
                                        class="decision-bullet mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                                    ></span
                                    >{{ decision }}
                                </li>
                            </ul>
                            <p v-else class="mt-3 text-xs text-slate-400">
                                No decisions captured yet.
                            </p>
                        </div>
                    </section>

                    <section class="result-card overflow-hidden rounded-xl">
                        <div
                            class="flex items-center justify-between px-6 py-5"
                        >
                            <div>
                                <p class="result-kicker">FOLLOW THROUGH</p>
                                <h2
                                    class="mt-1 text-base font-semibold tracking-tight"
                                >
                                    Action items
                                </h2>
                            </div>
                            <span
                                class="count-pill rounded-full px-2.5 py-1 text-xs font-semibold"
                                >{{
                                    selectedMeeting?.action_items.length ?? 0
                                }}
                                total</span
                            >
                        </div>
                        <div
                            v-if="selectedMeeting?.action_items.length"
                            class="overflow-x-auto"
                        >
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="table-head">
                                        <th class="px-6 py-2.5">TASK</th>
                                        <th class="px-4 py-2.5">OWNER</th>
                                        <th class="px-4 py-2.5">DUE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in selectedMeeting.action_items"
                                        :key="item.id"
                                        class="action-row"
                                    >
                                        <td
                                            class="px-6 py-4 text-[13px] leading-5 text-slate-700"
                                        >
                                            {{ item.task }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <span
                                                class="owner-chip inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                                                >{{ item.owner }}</span
                                            >
                                        </td>
                                        <td
                                            class="px-4 py-4 text-xs whitespace-nowrap text-slate-500"
                                        >
                                            {{ item.due_hint || '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else
                            class="mx-6 mb-6 rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center"
                        >
                            <p class="text-sm font-medium text-slate-600">
                                No action items yet
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Assign the next steps when you generate minutes.
                            </p>
                        </div>
                        <div
                            v-if="selectedMeeting?.action_items.length"
                            class="table-foot px-6 py-3 text-[11px] text-slate-500"
                        >
                            Action items are saved with this meeting.
                        </div>
                    </section>
                </div>
                <footer
                    class="mt-10 flex flex-col justify-between gap-2 border-t border-slate-200/70 py-5 text-[11px] text-slate-400 sm:flex-row"
                >
                    <span
                        >Meeting Notes AI <span class="px-1">·</span> A focused
                        space for better follow-through.</span
                    ><span>Built for teams that turn talk into action.</span>
                </footer>
            </div>
        </main>
    </div>
</template>
