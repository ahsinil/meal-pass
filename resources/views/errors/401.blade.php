@extends('errors::minimal')

@section('title', __('Unauthorized'))
@section('code', '401')
@section('message', __('Unauthorized'))
@section('description', __('Mohon maaf, Anda tidak memiliki izin untuk mengakses halaman ini.'))
