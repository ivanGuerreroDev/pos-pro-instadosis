@extends('layouts.master')

@section('title')
    {{ __('Subscription Plan')  }}
@endsection

@section('main_content')
<div class="erp-table-section">
    <div class="container-fluid">
        <div class="card">
            <div class="card-bodys ">
                <div class="table-header p-16">
                    <h4>{{__('Plan List')}}</h4>
                    @can('plans-create')
                        <a type="button" href="{{route('admin.plans.create')}}" class="add-order-btn rounded-2 {{ Route::is('admin.plans.create') ? 'active' : '' }}" class="btn btn-primary" ><i class="fas fa-plus-circle me-1"></i>{{ __('Create Plans') }}</a>
                    @endcan
                </div>
                <div class="table-top-form p-16-0">
                    <form action="{{ route('admin.plans.filter') }}" method="post" class="filter-form" table="#plans-data">
                        @csrf

                        <div class="table-top-left d-flex gap-3 margin-l-16">
                            <div class="gpt-up-down-arrow position-relative">
                                <select name="per_page" class="form-control">
                                    <option value="10">{{__('Show- 10')}}</option>
                                    <option value="25">{{__('Show- 25')}}</option>
                                    <option value="50">{{__('Show- 50')}}</option>
                                    <option value="100">{{__('Show- 100')}}</option>
                                </select>
                                <span></span>
                            </div>

                            <div class="table-search position-relative">
                                <input class="form-control searchInput" type="text" name="search" placeholder="{{ __('Search...') }}" value="{{ request('search') }}">
                                <span class="position-absolute">
                                    <img src="{{ asset('assets/images/search.svg') }}" alt="">
                                </span>
                            </div>
                        </div>
                    </form>
                </div>

            </div>

            <div class="responsive-table m-0">
                <table class="table" id="datatable">
                    <thead>
                    <tr>
                        @can('plans-delete')
                            <th>
                                <div class="d-flex align-items-center gap-3">
                                    <label class="table-custom-checkbox">
                                        <input type="checkbox" class="table-hidden-checkbox selectAllCheckbox">
                                        <span class="table-custom-checkmark custom-checkmark"></span>
                                    </label>
                                    <i class="fal fa-trash-alt delete-selected"></i>
                                </div>
                            </th>
                        @endcan
                        <th>{{ __('SL') }}.</th>
                        <th class="text-start">{{ __('Subscription Name') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th>{{ __('Offer Price') }}</th>
                        <th>{{ __('Subscription Price') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody id="plans-data" class="searchResults">
                        @include('admin.plans.datas')
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $plans->links('vendor.pagination.bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
@push('modal')
    @include('admin.components.multi-delete-modal')
@endpush

@push('js')
    <script src="{{ asset('assets/js/custom/custom.js') }}"></script>
@endpush