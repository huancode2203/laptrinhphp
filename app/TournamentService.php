<?php

namespace App;

use App\Models\GameEvent;
use Illuminate\Support\Str;

class TournamentService
{
    public function format(GameEvent $event): string
    {
        $s = Str::lower(Str::ascii($event->format, 'vi'));

        return str_contains($s, 'swiss') ? 'swiss' : ((str_contains($s, 'round_robin') || str_contains($s, 'vong tron')) ? 'round_robin' : 'single_elimination');
    }

    public function standings(GameEvent $event): array
    {
        $rows = [];
        foreach ($event->registrations()->where('status', 'confirmed')->with('user:id,name')->get() as $reg) {
            $rows[$reg->user_id] = ['id' => $reg->user_id, 'name' => $reg->user->name, 'played' => 0, 'wins' => 0, 'losses' => 0, 'points' => 0, 'score_for' => 0, 'score_against' => 0, 'buchholz' => 0, 'opponents' => [], 'bye' => false];
        }
        foreach ($event->matches()->whereNotNull('winner_id')->get() as $m) {
            if (! $m->player_a || ! $m->player_b) {
                if ($this->format($event) === 'swiss' && isset($rows[$m->winner_id])) {
                    $rows[$m->winner_id]['points'] += 3;
                    $rows[$m->winner_id]['bye'] = true;
                }

                continue;
            }
            if (! isset($rows[$m->player_a],$rows[$m->player_b])) {
                continue;
            }
            foreach ([[$m->player_a, $m->player_b, $m->score_a, $m->score_b], [$m->player_b, $m->player_a, $m->score_b, $m->score_a]] as [$id,$other,$a,$b]) {
                $rows[$id]['played']++;
                $rows[$id]['opponents'][] = $other;
                $rows[$id]['score_for'] += (int) $a;
                $rows[$id]['score_against'] += (int) $b;
                if ($m->winner_id === $id) {
                    $rows[$id]['wins']++;
                    $rows[$id]['points'] += 3;
                } else {
                    $rows[$id]['losses']++;
                }
            }
        }
        foreach ($rows as &$row) {
            $row['buchholz'] = array_sum(array_map(fn ($id) => $rows[$id]['points'], $row['opponents']));
        }unset($row);
        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [$b['points'], $b['buchholz'], $b['score_for'] - $b['score_against'], -$b['id']] <=> [$a['points'], $a['buchholz'], $a['score_for'] - $a['score_against'], -$a['id']]);

        return $rows;
    }

    public function draw(GameEvent $event, array $players): void
    {
        if ($this->format($event) === 'swiss') {
            $this->nextSwiss($event);

            return;
        }
        if (count($players) % 2) {
            $players[] = null;
        }
        $count = count($players);
        for ($round = 1; $round < $count; $round++) {
            for ($i = 0; $i < $count / 2; $i++) {
                $a = $players[$i];
                $b = $players[$count - 1 - $i];
                $event->matches()->create(['round' => $round, 'position' => $i, 'player_a' => $a, 'player_b' => $b, 'winner_id' => ! $a || ! $b ? $a ?? $b : null]);
            }
            $fixed = array_shift($players);
            $last = array_pop($players);
            array_unshift($players, $fixed, $last);
        }
    }

    private function pair(array $ids, array $rows, int &$attempts): ?array
    {
        if (! $ids) {
            return [];
        }
        if (++$attempts > 15000) {
            return null;
        }
        $a = array_shift($ids);
        foreach ($ids as $index => $b) {
            if (in_array($b, $rows[$a]['opponents'], true)) {
                continue;
            }
            $rest = $ids;
            array_splice($rest, $index, 1);
            $next = $this->pair($rest, $rows, $attempts);
            if ($next !== null) {
                return [[$a, $b], ...$next];
            }
        }

        return null;
    }

    public function nextSwiss(GameEvent $event): void
    {
        $round = (int) $event->matches()->max('round') + 1;
        if ($event->matches()->whereNull('winner_id')->exists()) {
            return;
        }
        $standings = $this->standings($event);
        $max = max(1, (int) ceil(log(max(2, count($standings)), 2)));
        if (preg_match('/swiss[: ](\d+)/i', $event->format, $m)) {
            $max = min(count($standings) - 1, max(1, (int) $m[1]));
        }
        if ($round > $max) {
            return;
        }
        $rows = collect($standings)->keyBy('id')->all();
        $ids = array_column($standings, 'id');
        $bye = null;
        if (count($ids) % 2) {
            for ($i = count($ids) - 1; $i >= 0; $i--) {
                if (! $rows[$ids[$i]]['bye']) {
                    $bye = $ids[$i];
                    array_splice($ids, $i, 1);
                    break;
                }
            }
            abort_unless($bye, 422, 'Không thể xếp suất miễn đấu mà không lặp.');
        }
        $attempts = 0;
        $pairs = $this->pair($ids, $rows, $attempts);
        abort_unless($pairs !== null, 422, 'Chưa xếp được cặp Swiss không lặp đối thủ. Hãy kiểm tra số vòng và người tham gia.');
        foreach ($pairs as $position => [$a,$b]) {
            $event->matches()->create(['round' => $round, 'position' => $position, 'player_a' => $a, 'player_b' => $b]);
        }
        if ($bye) {
            $event->matches()->create(['round' => $round, 'position' => count($pairs), 'player_a' => $bye, 'player_b' => null, 'winner_id' => $bye]);
        }
    }
}
