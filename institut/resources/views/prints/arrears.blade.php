@extends('prints.layout')

@section('title', __('general.arrears_report'))

@section('content')
    <h2 class="title">{{ __('general.arrears_report') }}</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('general.student_code') }}</th>
                <th>{{ __('general.student') }}</th>
                <th>{{ __('general.phone') }}</th>
                <th>{{ __('general.guardian_phone') }}</th>
                <th>{{ __('general.course') }}</th>
                <th class="amount-danger" style="text-align:right">{{ __('general.remaining_on_you') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $student)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $student->student_code ?? '—' }}</td>
                    <td>{{ $student->name }}</td>
                    <td>{{ $student->phone ?? '—' }}</td>
                    <td>{{ $student->guardian_phone ?? '—' }}</td>
                    <td>
                        {{ $student->registrations->map(fn ($r) => $r->course->name)->implode('، ') ?: '—' }}
                    </td>
                    <td class="amount-danger" style="text-align:right">{{ number_format($student->balance) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="total-line grand">
            <span>{{ __('general.remaining_on_you') }}</span>
            <span class="amount-danger">{{ number_format($total) }} {{ __('general.currency') }}</span>
        </div>
    </div>
@endsection
