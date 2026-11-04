@extends('layouts.company')
@php($menuKey = $pageKey === 'payments' ? 'online_payments' : $pageKey)
@section('title', __('company_portal.menu.'.$menuKey))
@section('page-title', __('company_portal.menu.'.$menuKey))
@section('content')
    <x-ui.card>
        <x-ui.empty :title="__('company_portal.menu.'.$menuKey)" :text="__('company_portal.preparation.'.$pageKey)" />
    </x-ui.card>
@endsection
