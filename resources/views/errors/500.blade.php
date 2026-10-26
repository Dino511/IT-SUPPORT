{{-- Shown for unexpected errors when APP_DEBUG=false. The details go to
     storage/logs/laravel-YYYY-MM-DD.log; the time below helps find the entry. --}}
@extends('errors.layout')
@section('code', '500')
@section('heading', 'Something went wrong')
@section('message')
    The error was recorded. Go back and try again. If it keeps happening, tell the system administrator
    it happened at <b>{{ now()->format('M j, Y g:i:s A') }}</b>.
@endsection
