@extends('print.layout')

@section('title', 'Thông báo học phí')

@section('content')
    <h2>THÔNG BÁO HỌC PHÍ</h2>
    <p class="meta">Số liệu tại thời điểm phát hành. Không phản ánh thay đổi sau đó.</p>
    <div class="notice-body">
        {!! $renderedHtml !!}
    </div>
@endsection
