@extends('errors::minimal')

@section('title', __('Forbidden'))
@section('code', '403')
@section('message', __('Forbidden'))
@section('description', __('Mohon maaf, Anda tidak memiliki izin untuk mengakses halaman ini. (' . $exception->getMessage() . ')'))