<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\SchoolEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Satu sumber kebenaran untuk agenda sekolah yang membebaskan KBM Diniyyah.
 * Agenda bersifat virtual: service ini tidak pernah membuat record jurnal.
 */
class DiniyyahNoKbmAgendaService
{
    /**
     * @param  Collection<int, ClassroomTerm>  $classroomTerms
     * @return Collection<int, SchoolEvent>
     */
    public function eventsForRange(Collection $classroomTerms, CarbonInterface $startsOn, CarbonInterface $endsOn): Collection
    {
        $termIds = $classroomTerms
            ->pluck('academic_term_id')
            ->filter()
            ->unique()
            ->values();

        if ($termIds->isEmpty()) {
            return collect();
        }

        return SchoolEvent::query()
            ->with('targetClassroomTerms.classroom')
            ->noKbm()
            ->whereIn('academic_term_id', $termIds)
            ->overlapping($startsOn, $endsOn)
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();
    }

    /**
     * @param  Collection<int, SchoolEvent>  $events
     * @return array{ids:list<int>, titles:list<string>, title:string, reason:string, events:Collection<int,SchoolEvent>}|null
     */
    public function forClassroomTerm(Collection $events, ClassroomTerm $classroomTerm, CarbonInterface|string $date): ?array
    {
        return $this->forClassroomTerms($events, collect([$classroomTerm]), $date);
    }

    /**
     * Tafsir dapat memiliki beberapa ClassroomTerm dalam satu sesi. Semua kelas
     * dalam sesi harus tercakup agar sesi serentak boleh dibebaskan.
     *
     * @param  Collection<int, SchoolEvent>  $events
     * @param  Collection<int, ClassroomTerm>  $classroomTerms
     * @return array{ids:list<int>, titles:list<string>, title:string, reason:string, events:Collection<int,SchoolEvent>}|null
     */
    public function forClassroomTerms(Collection $events, Collection $classroomTerms, CarbonInterface|string $date): ?array
    {
        $dateValue = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date, 'Asia/Jakarta')->toDateString();
        $terms = $classroomTerms->filter()->unique('id')->values();

        if ($terms->isEmpty()) {
            return null;
        }

        $matchingEvents = collect();
        foreach ($terms as $term) {
            $termEvents = $events->filter(fn (SchoolEvent $event): bool =>
                (int) $event->academic_term_id === (int) $term->academic_term_id
                && $event->starts_on?->toDateString() <= $dateValue
                && $event->ends_on?->toDateString() >= $dateValue
                && $this->appliesToClassroomTerm($event, $term)
            );

            if ($termEvents->isEmpty()) {
                return null;
            }

            $matchingEvents = $matchingEvents->merge($termEvents);
        }

        $matchingEvents = $matchingEvents->unique('id')->sortBy(fn (SchoolEvent $event) => [
            $event->starts_on?->toDateString() ?? '',
            $event->title,
        ])->values();
        $titles = $matchingEvents->pluck('title')->filter()->unique()->values()->all();
        $title = implode('; ', $titles);

        return [
            'ids' => $matchingEvents->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'titles' => $titles,
            'title' => $title,
            'reason' => 'Libur Mengajar - Agenda '.$title,
            'events' => $matchingEvents,
        ];
    }

    public function appliesToClassroomTerm(SchoolEvent $event, ClassroomTerm $classroomTerm): bool
    {
        return match ($event->target_scope) {
            'classes' => $event->targetClassroomTerms->contains('id', $classroomTerm->id),
            'gender' => (string) $event->target_gender_group === (string) $classroomTerm->classroom?->gender_group,
            default => $event->target_scope === 'all',
        };
    }
}
