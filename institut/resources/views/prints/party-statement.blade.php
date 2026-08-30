@extends('prints.layout')

@section('title', __('general.party_statement'))

@section('content')
    @php
        $isStaffComprehensive = ($statement['party_type'] ?? '') === 'staff' && ($statement['staff_mode'] ?? 'advances') === 'comprehensive';
        $title = ($statement['party_type'] ?? '') === 'staff'
            ? ($isStaffComprehensive ? __('general.staff_comprehensive_statement') : __('general.staff_advances_statement'))
            : __('general.party_statement');
    @endphp

    <h2 class="title">{{ $title }} — {{ $statement['party']?->name ?? '' }}</h2>

    @php
        $typeLabel = match ($statement['party_type'] ?? '') {
            'student' => __('general.student'),
            'staff' => $isStaffComprehensive ? __('general.staff_comprehensive_statement') : __('general.staff_advances_statement'),
            'supplier' => __('general.supplier'),
            'other' => __('general.other_people'),
            default => __('general.party'),
        };
    @endphp

    <div class="info-grid">
        <div class="item">
            <span class="label">{{ $typeLabel }}</span>
            <span class="value">{{ $statement['party']?->name ?? '—' }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.opening_balance') }}</span>
            <span class="value">{{ number_format((float) $statement['opening']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.total_debit') }}</span>
            <span class="value">{{ number_format((float) $statement['totalDebit']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.total_credit') }}</span>
            <span class="value">{{ number_format((float) $statement['totalCredit']) }} {{ __('general.currency') }}</span>
        </div>
        <div class="item">
            <span class="label">{{ __('general.closing_balance') }}</span>
            @php
                $partyType = $statement['party_type'] ?? '';
                $closingFormatted = match ($partyType) {
                    'student' => \App\Helpers\MoneyFormatter::formatStudentBalance((float) $statement['closing'], true),
                    'staff' => \App\Helpers\MoneyFormatter::formatStaffAdvanceBalance((float) $statement['closing'], true),
                    'supplier' => \App\Helpers\MoneyFormatter::formatSupplierBalance((float) $statement['closing'], true),
                    'other' => \App\Helpers\MoneyFormatter::formatOtherPersonBalance((float) $statement['closing'], true),
                    default => number_format((float) $statement['closing']).' '.__('general.currency'),
                };
            @endphp
            <span class="value {{ (float) $statement['closing'] > 0 ? 'amount-danger' : ((float) $statement['closing'] < 0 ? 'amount-success' : '') }}">
                {{ $closingFormatted }}
            </span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('general.date') }}</th>
                <th>{{ __('general.description') }}</th>
                <th>{{ __('general.reference') }}</th>
                <th>{{ __('general.counterparty') }}</th>
                <th>{{ __('general.debit') }}</th>
                <th>{{ __('general.credit') }}</th>
                <th>{{ __('general.balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['rows'] as $row)
                @php
                    $formattedRowBalance = match ($partyType) {
                        'student' => \App\Helpers\MoneyFormatter::formatStudentBalance((float) $row['balance'], true),
                        'staff' => \App\Helpers\MoneyFormatter::formatStaffAdvanceBalance((float) $row['balance'], true),
                        'supplier' => \App\Helpers\MoneyFormatter::formatSupplierBalance((float) $row['balance'], true),
                        'other' => \App\Helpers\MoneyFormatter::formatOtherPersonBalance((float) $row['balance'], true),
                        default => number_format((float) $row['balance']).' '.__('general.currency'),
                    };
                @endphp
                <tr>
                    <td>{{ $row['date']->format('d/m/Y') }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['reference'] ?? '—' }}</td>
                    <td>{{ $row['counterparty'] }}</td>
                    <td class="{{ $row['debit'] > 0 ? 'amount-danger' : '' }}">{{ number_format((float) $row['debit']) }}</td>
                    <td class="{{ $row['credit'] > 0 ? 'amount-success' : '' }}">{{ number_format((float) $row['credit']) }}</td>
                    <td>{{ $formattedRowBalance }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">{{ __('general.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection