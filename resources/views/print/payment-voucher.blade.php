@extends('print.layout')

@section('title', 'Phiếu thu nội bộ '.$voucher->voucher_number)

@section('content')
    <h2>PHIẾU THU NỘI BỘ</h2>
    <p class="meta"><strong>Số phiếu:</strong> {{ $voucher->voucher_number }}</p>
    <p class="meta"><strong>Ngày:</strong> {{ $voucher->issued_at?->format('d/m/Y H:i') }}</p>
    <p class="meta"><strong>Sinh viên:</strong> {{ $studentName }} ({{ $studentCode }})</p>
    <p class="meta"><strong>Số tiền thu:</strong> {{ number_format((float) $payment->amount, 0, ',', '.') }} ₫</p>
    <p class="meta"><strong>Phương thức:</strong> {{ $payment->method }}</p>
    <p class="meta"><strong>Người lập:</strong> {{ $issuerName }}</p>

    <h3>Khoản được cấn trừ</h3>
    <table>
        <thead>
            <tr>
                <th>Dòng hóa đơn</th>
                <th class="amount">Số tiền</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($voucher->allocations_snapshot ?? [] as $row)
                <tr>
                    <td>#{{ $row['invoice_line_id'] ?? '' }}</td>
                    <td class="amount">{{ number_format((float) ($row['amount'] ?? 0), 0, ',', '.') }} ₫</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Không có khoản được cấn trừ.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h3>Khoản bỏ qua</h3>
    <table>
        <thead>
            <tr>
                <th>Khoản thu</th>
                <th>Lý do</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($voucher->skipped_snapshot ?? [] as $row)
                <tr>
                    <td>#{{ $row['charge_id'] ?? '' }}</td>
                    <td>{{ $skipLabels[$row['reason'] ?? ''] ?? ($row['reason'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Không có khoản bị bỏ qua.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ((float) $voucher->unapplied_amount > 0)
        <p class="meta"><strong>Còn dư chưa quyết (SV đang học):</strong> {{ number_format((float) $voucher->unapplied_amount, 0, ',', '.') }} ₫</p>
    @endif

    <p class="legal">Phiếu thu nội bộ — không phải hoá đơn GTGT. Chứng từ này không dùng để kê khai thuế.</p>
@endsection
