@php
    // Partial grid presensi santri untuk form jurnal (create & edit).
    // $students         : Collection ClassEnrollment (active) with('student')
    // $dailyAbsences    : map [class_enrollment_id => status] dari StudentAttendance (sick/permission/absent) — terkunci.
    // $existingAbsences : map [class_enrollment_id => status] dari jurnal yang sudah ada (untuk prefill edit); kosong di create.
    $existingAbsences = $existingAbsences ?? [];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[60vh] sm:max-h-60 overflow-y-auto pr-2">
    @foreach($students as $enrollment)
        @php
            $dailyStatus = $dailyAbsences[$enrollment->id] ?? null;
            $isDailyAbsent = $dailyStatus !== null;
            $existingStatus = $existingAbsences[$enrollment->id] ?? null;
        @endphp
        <div class="flex items-center p-3 border {{ $isDailyAbsent ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white hover:border-slate-300' }} rounded-xl transition-colors cursor-pointer" onclick="var el=document.getElementById('student_{{ $enrollment->id }}'); if(el) el.click();">
            <div class="flex items-center h-5">
                @if($isDailyAbsent)
                    <input type="hidden" name="absences[{{ $enrollment->id }}]" value="{{ $dailyStatus }}">
                    <input type="checkbox" checked disabled class="h-5 w-5 text-amber-600 rounded border-slate-300 pointer-events-none">
                @else
                    <input id="student_{{ $enrollment->id }}" type="checkbox" name="absences[{{ $enrollment->id }}]" value="{{ $existingStatus ?? 'skipped' }}" {{ $existingStatus ? 'checked' : '' }} class="h-5 w-5 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer" onclick="event.stopPropagation()">
                @endif
            </div>
            <div class="ml-3 flex-1 flex justify-between items-center text-sm">
                <label for="student_{{ $enrollment->id }}" title="{{ $enrollment->student->name }}" class="font-bold text-slate-700 truncate cursor-pointer select-none w-full" onclick="event.stopPropagation()">{{ $enrollment->student->name }}</label>
                @if($isDailyAbsent)
                    <span class="text-[10px] font-bold text-amber-800 uppercase bg-amber-200 px-2 py-0.5 rounded ml-2">{{ $dailyStatus }}</span>
                @endif
            </div>
        </div>
    @endforeach
</div>