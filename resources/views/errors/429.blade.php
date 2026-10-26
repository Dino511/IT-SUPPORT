{{-- Shown when a rate limit is hit, e.g. 10 sign-in attempts in a minute
     (routes/web.php: throttle:10,1). Retry-After says how long to wait. --}}
@php($seconds = (int) ($exception?->getHeaders()['Retry-After'] ?? 60))
@extends('errors.layout')
@section('code', '429')
@section('heading', 'Too many attempts')
@section('message')
    For your security, signing in is paused after too many tries in a short time.
    @php($wait = $seconds > 60 ? (int) ceil($seconds / 60) : $seconds)
    Please wait <b>{{ $wait }} {{ Str::plural($seconds > 60 ? 'minute' : 'second', $wait) }}</b>, then try again.
    If you forgot your password, ask the Super Admin to reset it.
@endsection
