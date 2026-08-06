<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\MentionedInChatNotification;
use App\Support\MessageEncryption;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MentionService
{
    public function __construct(
        private readonly ChatParticipantService $participantService,
    ) {
    }

    public function syncForMessage(
        Message $message,
        Group|Duo|MergeSession $chatable,
        string $chatType,
        ?array $selectedUserIds = null,
    ): array {
        $audience = $this->participantService->participants($chatable);
        $authorId = $message->user_id;

        if ($message->is_encrypted || MessageEncryption::isEncrypted($message->content)) {
            $ids = collect($selectedUserIds ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id !== (int) $authorId)
                ->filter(fn ($id) => $audience->contains('id', $id))
                ->unique()
                ->values();

            foreach ($ids as $userId) {
                $mention = MessageMention::firstOrCreate(
                    [
                        'message_id' => $message->id,
                        'user_id' => $userId,
                    ],
                    ['mention_type' => 'user'],
                );

                if ($mention->wasRecentlyCreated) {
                    $user = $audience->firstWhere('id', $userId) ?? User::find($userId);
                    if ($user) {
                        $user->notify(new MentionedInChatNotification($message, $chatType, 'user'));
                    }
                }
            }

            return $ids->all();
        }

        if (blank($message->content)) {
            return [];
        }

        $resolved = $this->resolveMentions($message->content, $chatable, $chatType, $audience, $selectedUserIds);

        $mentionedUserIds = [];

        foreach ($resolved as $entry) {
            foreach ($entry['user_ids'] as $userId) {
                if ((int) $userId === (int) $authorId) {
                    continue;
                }

                $mentionedUserIds[] = $userId;

                $mention = MessageMention::firstOrCreate(
                    [
                        'message_id' => $message->id,
                        'user_id' => $userId,
                    ],
                    ['mention_type' => $entry['type']],
                );

                if (!$mention->wasRecentlyCreated) {
                    continue;
                }

                $user = $audience->firstWhere('id', $userId) ?? User::find($userId);

                if ($user) {
                    $user->notify(new MentionedInChatNotification($message, $chatType, $entry['type']));
                }
            }
        }

        return array_values(array_unique($mentionedUserIds));
    }

    /**
     * @return list<array{type: string, user_ids: list<int>}>
     */
    private function resolveMentions(
        string $content,
        Group|Duo|MergeSession $chatable,
        string $chatType,
        Collection $audience,
        ?array $selectedUserIds,
    ): array {
        $entries = [];

        if (preg_match('/@all\b/i', $content)) {
            $entries[] = [
                'type' => 'all',
                'user_ids' => $audience->pluck('id')->all(),
            ];
        }

        if (preg_match('/@selected\b/i', $content) && $selectedUserIds) {
            $ids = $audience->whereIn('id', $selectedUserIds)->pluck('id')->all();
            $entries[] = ['type' => 'selected', 'user_ids' => $ids];
        }

        if ($chatType === 'merge') {
            if (preg_match_all('/@group[:.]([A-Za-z0-9_-]+)/i', $content, $groupMatches)) {
                $groups = $this->participantService->groupsInChat($chatable);
                $userIds = [];

                foreach ($groupMatches[1] as $token) {
                    $group = $groups->first(fn (Group $g) => $this->tokenMatches($g->name, trim($token)));

                    if ($group) {
                        $userIds = array_merge(
                            $userIds,
                            $this->participantService->participants($group)->pluck('id')->all(),
                        );
                    }
                }

                $entries[] = ['type' => 'group', 'user_ids' => array_values(array_unique($userIds))];
            }

            if (preg_match_all('/@([A-Za-z0-9_-]+)\.([A-Za-z0-9_]+)/', $content, $mergeUserMatches, PREG_SET_ORDER)) {
                $groups = $this->participantService->groupsInChat($chatable);
                $userIds = [];

                foreach ($mergeUserMatches as $match) {
                    $groupToken = $match[1];
                    $username = $match[2];

                    if (in_array(strtolower($groupToken), ['group', 'role'], true)) {
                        continue;
                    }

                    $group = $groups->first(fn (Group $g) => $this->tokenMatches($g->name, $groupToken));

                    if (!$group) {
                        continue;
                    }

                    $user = $this->participantService->participants($group)
                        ->first(fn (User $u) => strcasecmp($u->username, $username) === 0);

                    if ($user) {
                        $userIds[] = $user->id;
                    }
                }

                if ($userIds) {
                    $entries[] = ['type' => 'user', 'user_ids' => array_values(array_unique($userIds))];
                }
            }
        }

        if (preg_match_all('/@role[:.]([A-Za-z0-9_-]+)/i', $content, $roleMatches)) {
            $userIds = [];

            foreach ($roleMatches[1] as $token) {
                $userIds = array_merge(
                    $userIds,
                    $audience->filter(function (User $user) use ($token) {
                        $roleName = $user->tenantRole?->name;

                        return $roleName && $this->tokenMatches($roleName, trim($token));
                    })->pluck('id')->all(),
                );
            }

            $entries[] = ['type' => 'role', 'user_ids' => array_values(array_unique($userIds))];
        }

        if (preg_match_all('/@([A-Za-z0-9_]+)/', $content, $userMatches)) {
            $reserved = ['all', 'selected', 'role', 'group'];
            $userIds = [];

            foreach ($userMatches[1] as $token) {
                if (in_array(strtolower($token), $reserved, true)) {
                    continue;
                }

                $user = $audience->first(
                    fn (User $u) => strcasecmp($u->username, $token) === 0
                        || strcasecmp($u->display_name ?? '', $token) === 0,
                );

                if ($user) {
                    $userIds[] = $user->id;
                }
            }

            if ($userIds) {
                $entries[] = ['type' => 'user', 'user_ids' => array_values(array_unique($userIds))];
            }
        }

        return $entries;
    }

    private function tokenMatches(string $name, string $token): bool
    {
        return strcasecmp(Str::slug($name, ''), Str::slug($token, '')) === 0
            || strcasecmp(str_replace(' ', '', $name), str_replace(' ', '', $token)) === 0
            || strcasecmp($name, $token) === 0;
    }

    public function unreadMessageIdsForUser(
        User $user,
        Group|Duo|MergeSession $chatable,
    ): array {
        return MessageMention::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereHas('message', function ($query) use ($chatable) {
                $query->where('chatable_id', $chatable->id)
                    ->where('chatable_type', $chatable->getMorphClass());
            })
            ->orderBy('message_id')
            ->pluck('message_id')
            ->unique()
            ->values()
            ->all();
    }

    public function markMessageReadForUser(int $messageId, User $user): void
    {
        MessageMention::query()
            ->where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @param  array<string, string|null>  $roleColors
     * @param  array<string, string>  $usernameLabels
     * @param  array<string, string>  $mergeUserLabels
     */
    public function renderContentHtml(
        string $content,
        array $roleColors = [],
        array $usernameLabels = [],
        array $mergeUserLabels = [],
    ): string {
        $escaped = e($content);
        $placeholders = [];

        $protect = function (string $html) use (&$placeholders): string {
            $key = '@@MENTION'.count($placeholders).'@@';
            $placeholders[$key] = $html;

            return $key;
        };

        $escaped = preg_replace_callback(
            '/@(all|selected)\b/i',
            fn (array $m) => $protect('<span class="mention-pill">@'.strtolower($m[1]).'</span>'),
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/@role[:.]([A-Za-z0-9_-]+)/i',
            function (array $matches) use ($roleColors, $protect) {
                $roleName = $matches[1];
                $color = $roleColors[$roleName] ?? null;
                $style = $color ? ' style="color: '.e($color).'"' : '';

                return $protect(
                    '<span class="mention-pill"'.$style.'>@role:'.e($roleName).'</span>'
                );
            },
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/@group[:.]([A-Za-z0-9_-]+)/i',
            function (array $matches) use ($protect) {
                $groupName = $matches[1];

                return $protect(
                    '<span class="mention-pill">@group:'.e($groupName).'</span>'
                );
            },
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/@([A-Za-z0-9_-]+)\.([A-Za-z0-9_]+)/',
            function (array $matches) use ($mergeUserLabels, $protect) {
                $key = strtolower($matches[1].'.'.$matches[2]);
                $label = $mergeUserLabels[$key] ?? null;
                if (!$label) {
                    return $matches[0];
                }

                return $protect('<span class="mention-pill">@'.e($label).'</span>');
            },
            $escaped,
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/@([A-Za-z0-9_]+)/',
            function (array $matches) use ($usernameLabels, $roleColors, $protect) {
                $token = $matches[1];
                $label = $usernameLabels[strtolower($token)] ?? null;
                if (!$label) {
                    return $matches[0];
                }

                $color = $roleColors['_user_'.strtolower($token)] ?? null;
                $style = $color ? ' style="color: '.e($color).'"' : '';

                return $protect(
                    '<span class="mention-pill"'.$style.'>@'.e($label).'</span>'
                );
            },
            $escaped,
        ) ?? $escaped;

        return strtr($escaped, $placeholders);
    }

    /**
     * @return array{roleColors: array<string, string|null>, usernameLabels: array<string, string>, mergeUserLabels: array<string, string>}
     */
    public function renderContextForChatable(
        Group|Duo|MergeSession $chatable,
        string $chatType,
        ?int $tenantId = null,
    ): array {
        $audience = $this->participantService->participants($chatable);

        $roleColors = TenantRole::query()
            ->when($tenantId, fn ($q) => $q->forTenant($tenantId))
            ->pluck('color', 'name')
            ->all();

        $usernameLabels = [];
        $mergeUserLabels = [];

        foreach ($audience as $user) {
            $usernameLabels[strtolower($user->username)] = $user->display_name ?? $user->username;

            if ($user->tenantRole?->color) {
                $roleColors['_user_'.strtolower($user->username)] = $user->tenantRole->color;
            }
        }

        if ($chatType === 'merge') {
            foreach ($this->participantService->groupsInChat($chatable) as $group) {
                foreach ($this->participantService->participants($group) as $user) {
                    $key = strtolower($group->name.'.'.$user->username);
                    $mergeUserLabels[$key] = $group->name.'/'.($user->display_name ?? $user->username);
                }
            }
        }

        return [
            'roleColors' => $roleColors,
            'usernameLabels' => $usernameLabels,
            'mergeUserLabels' => $mergeUserLabels,
        ];
    }
}
