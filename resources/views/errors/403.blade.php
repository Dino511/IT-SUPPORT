@extends('errors.layout')
@section('code', '403')
@section('heading', 'Access restricted')
@section('message', $exception->getMessage() ?: 'Your role does not have access to this page.')
