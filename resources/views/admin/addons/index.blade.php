@extends('layouts.master')

@section('title')
    {{ __('Addons List') }}
@endsection

@section('main_content')
    <div class="erp-table-section">
        <div class="container-fluid">
            <div class="card">
                <div class="card-bodys">
                    <div class="table-header p-16">
                        <h4>{{ __('Addons List') }}</h4>
                        @can('addons-create')
                            <a type="button" href="#addon-modal" data-bs-toggle="modal" class="add-order-btn rounded-2 active" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i> {{ __('Add New Addon') }}
                            </a>
                        @endcan
                    </div>

                    <div class="table-top-form p-16-0">
                        <form action="{{ route('admin.addons.index') }}" method="post" class="filter-form mb-0" table="#addon-data">
                            @csrf

                            <div class="table-top-left d-flex gap-3 margin-l-16">
                                <div class="gpt-up-down-arrow position-relative">
                                    <select name="per_page" class="form-control">
                                        <option value="1">{{ __('Show- 10') }}</option>
                                        <option value="2">{{ __('Show- 25') }}</option>
                                        <option value="3">{{ __('Show- 50') }}</option>
                                        <option value="100">{{ __('Show- 100') }}</option>
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
                                <th>{{ __('SL') }}</th>
                                <th>{{ __('Image') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="addon-data" class="searchResults">
                            {{-- @include('admin.banners.search') --}}
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{-- {{ $banners->links('vendor.pagination.bootstrap-5') }} --}}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('modal')
    @include('admin.components.multi-delete-modal')
@endpush

{{-- Create Modal --}}
<div class="modal modal-md fade" id="addon-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{ __('Install Addon') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.addons.store') }}" method="post" enctype="multipart/form-data" class="ajaxform_instant_reload">
                    @csrf

                    <div>
                        <label>{{ __('Upload addons zip file') }}</label>
                        <input type="file" name="file" class="form-control" accept="file/*">
                    </div>

                    <div class="col-lg-12">
                        <div class="button-group text-center mt-5">
                            <button type="reset" class="theme-btn border-btn m-2">{{ __('Cancel') }}</button>
                            <button class="theme-btn m-2 submit-btn">{{ __('Install') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
