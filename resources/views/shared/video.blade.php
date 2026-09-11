@extends('layouts.shared')

@section('title')
    {{ $video->product_name }} · Mega Stationery Katalog
@endsection

@section('content')
    <div class="page">
        <a class="back" href="{{ route('shared-catalog.show', $token) }}" aria-label="Kembali">‹</a>
        <div class="media">
            <video src="{{ route('shared-catalog.media', [$token, $video]) }}" controls autoplay muted playsinline loop></video>
        </div>
        <section class="detail">
            <div class="row">
                <span class="code">{{ $video->product_code }}</span>
                <span class="muted">{{ number_format($video->video_size_bytes / 1048576, 1) }} MB</span>
            </div>
            <h1 class="name">{{ $video->product_name }}</h1>
            <div class="price">
                <span>Harga</span>
                <strong>{{ $price }}</strong>
            </div>
            <div class="desc">
                <h2>Deskripsi</h2>
                <p>{{ $description }}</p>
            </div>
        </section>
    </div>
@endsection
