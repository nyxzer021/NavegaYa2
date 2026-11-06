@extends('layouts.admin')
@section('title', __('admin_portal.menu.'.$pageKey))
@section('content')
    <x-ui.card>
        <x-ui.empty :title="__('admin_portal.preparation_label')" :text="__('admin_portal.preparation.'.$pageKey)" />
    </x-ui.card>
@endsection
