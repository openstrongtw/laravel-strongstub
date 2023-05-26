@extends('strongadmin::layouts.app')

@push('styles')
<style></style>
@endpush

@push('scripts')
<script></script>
@endpush

@section('content')
<div class="st-h15"></div>
<table class="layui-table" lay-size="sm">
<!--    <colgroup>
        <col width="150"><col>
        <col><col>
        <col width="150"><col>
        <col><col>
    </colgroup>-->
    <thead>
        <tr>
            <th colspan="4" style="text-align: center;background: #eee;">基本資訊</th>
        </tr> 
    </thead>
    <tbody>DummyShowParams
    </tbody>
</table>
@endsection

@push('scripts_bottom')
<script>
    !function () {
        //...
    }();
</script>
@endpush
