@extends('errors::minimal')

@section('title', __('Too Many Requests'))
@section('code', '429')
@section('message', __('Too Many Requests'))
@section('description', __('Mohon maaf, Anda telah melakukan permintaan terlalu banyak. Silahkan coba lagi beberapa saat lagi.'))
