{{-- Shown while the app is in maintenance mode (php artisan down / up). --}}
@extends('errors.layout')
@section('code', '503')
@section('heading', 'Under maintenance')
@section('message', 'IT Inventory is being updated and will be back shortly. Please try again in a few minutes.')
