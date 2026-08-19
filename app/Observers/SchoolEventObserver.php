<?php

namespace App\Observers;

use App\Models\ClassroomTerm;
use App\Models\SchoolEvent;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Collection;

class SchoolEventObserver
{
    public function created(SchoolEvent $event): void
    {
        $this->dispatch($event, 'Agenda sekolah baru', 'diinput', 'school_event_created', 'info');
    }

    public function updated(SchoolEvent $event): void
    {
        // Hanya bila field penting berubah.
        if (! $event->wasChanged(['title', 'event_type', 'is_no_kbm', 'starts_on', 'ends_on', 'target_scope', 'target_level_name', 'target_gender_group', 'location', 'show_to_teachers', 'show_to_guardians'])) {
            return;
        }
        $this->dispatch($event, 'Agenda sekolah diperbarui', 'diperbarui', 'school_event_updated', 'info');
    }

    public function deleted(SchoolEvent $event): void
    {
        $this->dispatch($event, 'Agenda sekolah dibatalkan', 'dibatalkan', 'school_event_deleted', 'warning');
    }

    private function dispatch(SchoolEvent $event, string $titlePrefix, string $verb, string $type, string $severity): void
    {
        $dateLabel = $event->starts_on?->locale('id')->translatedFormat('d M Y')
            .($event->ends_on && $event->ends_on->notEqualTo($event->starts_on)
                ? ' s.d. '.$event->ends_on->locale('id')->translatedFormat('d M Y')
                : '');
        $target = $event->targetSummary();
        $body = "Agenda \"{$event->title}\" {$verb} — {$dateLabel}. Target: {$target}.";

        $dispatcher = app(NotificationDispatcher::class);

        // Tentukan classroom_terms yang relevan untuk resolusi audiens.
        $classroomTermIds = $this->resolveClassroomTermIds($event);

        // Ke guru (jika show_to_teachers).
        if ($event->show_to_teachers) {
            if (empty($classroomTermIds)) {
                // scope=all → broadcast ke semua guru via role.
                $dispatcher->dispatchToRole('guru', $titlePrefix, $body, $type, route('guru.calendar'), $severity);
            } else {
                $dispatcher->dispatchToTeachersOfClassrooms($classroomTermIds, $titlePrefix, $body, $type, route('guru.calendar'), $severity);
            }
        }

        // Ke wali santri (jika show_to_guardians).
        if ($event->show_to_guardians) {
            if (empty($classroomTermIds)) {
                $dispatcher->dispatchToRole('wali_santri', $titlePrefix, $body, $type, route('wali.calendar'), $severity);
            } else {
                $dispatcher->dispatchToGuardiansOfClassrooms($classroomTermIds, $titlePrefix, $body, $type, route('wali.calendar'), $severity);
            }
        }
    }

    /**
     * Resolve classroom_term IDs yang jadi target event (untuk dispatch ke
     * guru/wali santri yang relevan). Bila scope=all → return [] (broadcast).
     */
    private function resolveClassroomTermIds(SchoolEvent $event): array
    {
        if ($event->target_scope === 'all') {
            return [];
        }

        if ($event->target_scope === 'classes') {
            return $event->targetClassroomTerms()->pluck('classroom_terms.id')->all();
        }

        // scope=level/gender/level_gender → resolve classroom_terms yang match.
        $query = ClassroomTerm::query()
            ->where('academic_term_id', $event->academic_term_id)
            ->where('status', 'active')
            ->whereHas('classroom', function ($q) use ($event) {
                if ($event->target_scope === 'level') {
                    $q->where('level_name', $event->target_level_name);
                } elseif ($event->target_scope === 'gender') {
                    $q->where('gender_group', $event->target_gender_group);
                } elseif ($event->target_scope === 'level_gender') {
                    $q->where('level_name', $event->target_level_name)
                        ->where('gender_group', $event->target_gender_group);
                }
            });

        return $query->pluck('id')->all();
    }
}
