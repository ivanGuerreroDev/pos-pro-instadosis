@extends('layouts.master')

@section('title')
    {{ __('Edit Business') }}
@endsection

@section('main_content')
<div class="erp-table-section">
    <div class="container-fluid">
        <div class="card border-0">
            <div class="card-bodys">
                <div class="table-header p-16">
                    <h4>{{ __('Edit Business') }}</h4>
                    @can('plans-read')
                        <a href="{{ route('admin.business.index') }}" class="add-order-btn rounded-2 {{ Route::is('admin.users.create') ? 'active' : '' }}"><i class="far fa-list" aria-hidden="true"></i> {{ __('Business List') }}</a>
                    @endcan
                </div>
                <div class="order-form-section p-16">
                    <form action="{{ route('admin.business.update', $business->id) }}" method="POST" class="ajaxform_instant_reload">
                        @csrf
                        @method('PUT')
                        <div class="add-suplier-modal-wrapper d-block">
                            <div class="row">

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Business Name') }}</label>
                                    <input type="text" name="companyName" value="{{ $business->companyName }}" required class="form-control" placeholder="{{ __('Enter Company Name') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{__('Business Category')}}</label>
                                    <div class="gpt-up-down-arrow position-relative">
                                        <select name="business_category_id" required
                                                class="form-control table-select w-100 role">
                                            <option value=""> {{__('Select Business Category')}}</option>
                                            @foreach ($categories as $category)
                                                <option @selected($category->id == $business->business_category_id) value="{{ $category->id }}"> {{ ucfirst($category->name) }} </option>
                                            @endforeach
                                        </select>
                                        <span></span>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Subscription Plan') }}</label>
                                    <div class="gpt-up-down-arrow position-relative">
                                        <select name="plan_subscribe_id" class="form-control table-select w-100 role">
                                            <option value="">{{ __('Select One') }}</option>
                                            @foreach ($plans as $plan)
                                                <option @selected($plan->id == $business->plan_subscribe_id ) value="{{ $plan->id }}">
                                                    {{ ucfirst($plan->subscriptionName) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span></span>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Phone') }}</label>
                                    <input type="text" name="phoneNumber" value="{{ $business->phoneNumber }}" required class="form-control" placeholder="{{ __('Enter Phone Number') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Email') }}</label>
                                    <input type="email" name="email" value="{{ $user->email }}" class="form-control" placeholder="{{ __('Enter Email') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Shop Opening Balance') }}</label>
                                    <input type="number" name="shopOpeningBalance" value="{{ $business->shopOpeningBalance }}" required class="form-control" placeholder="{{ __('Enter Balance') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Address') }}</label>
                                    <input type="text" name="address" value="{{ $business->address }}" required class="form-control" placeholder="{{ __('Enter Address') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{__('Password')}}</label>
                                    <input type="password" name="password" class="form-control" placeholder="{{ __('Enter Password') }}">
                                </div>

                                <div class="col-lg-6">
                                    <div class="row">
                                        <div class="col-10">
                                            <label class="img-label">{{ __('Image') }}</label>
                                            <input type="file" accept="image/*" name="pictureUrl" class="form-control file-input-change" data-id="image">
                                        </div>
                                        <div class="col-2 align-self-center mt-3">
                                            <img src="{{ asset($business->pictureUrl ?? 'assets/images/icons/upload.png') }}" id="image" class="table-img">
                                        </div>
                                    </div>
                                </div>

                                
                            </div>
                        </div>
                        <h5 class="mt-5">{{ __('Invoice Information') }}</h5>
                        <div class="add-suplier-modal-wrapper d-block">
                            <div class="row">
                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Tipo de RUC') }}</label>
                                    <div class="gpt-up-down-arrow position-relative">
                                        <select name="dtipoRuc" required class="form-control">
                                            <option value="">{{ __('Select One') }}</option>
                                            <option value="Natural" @selected($business->invoice_data?->dtipoRuc == 'Natural')>Natural</option>
                                            <option value="Jurídico" @selected($business->invoice_data?->dtipoRuc == 'Jurídico')>Jurídico</option>
                                        </select>
                                        <span></span>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('RUC') }}</label>
                                    <input type="text" name="druc" required class="form-control" value="{{ $business->invoice_data?->druc }}" placeholder="{{ __('Enter RUC') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Dígito Verificador') }}</label>
                                    <input type="text" name="ddv" required class="form-control" value="{{ $business->invoice_data?->ddv }}" placeholder="{{ __('Enter DV') }}">
                                </div>

                                <div class="col-lg-6 mb-2 business-name-field" style="display: {{ $business->invoice_data?->dtipoRuc == 'Jurídico' ? 'block' : 'none' }};">
                                    <label>{{ __('Nombre de Empresa') }}</label>
                                    <input type="text" name="dnombEm" class="form-control" value="{{ $business->invoice_data?->dnombEm }}" placeholder="{{ __('Enter Business Name') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Dirección') }}</label>
                                    <input type="text" name="ddirecEm" class="form-control" value="{{ $business->invoice_data?->ddirecEm }}" placeholder="{{ __('Enter Address') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Provincia') }}</label>
                                    <select name="dprov" class="form-control" required>
                                        <option value="">{{ __('Select Province') }}</option>
                                        @foreach($provinces as $province)
                                            <option value="{{ $province->codigo }}" @selected($business->invoice_data?->dprov == $province->codigo)>
                                                {{ $province->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Distrito') }}</label>
                                    <select name="ddistr" class="form-control" required>
                                        <option value="">{{ __('Select District') }}</option>
                                        @foreach($districts as $district)
                                            <option value="{{ $district->codigo }}" @selected($business->invoice_data?->ddistr == $district->codigo)>
                                                {{ $district->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Corregimiento') }}</label>
                                    <select name="dcorreg" class="form-control" required>
                                        <option value="">{{ __('Select Township') }}</option>
                                        @foreach($townships as $township)
                                            <option value="{{ $township->codigo }}" @selected($business->invoice_data?->dcorreg == $township->codigo)>
                                                {{ $township->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Coordenadas') }}</label>
                                    <input type="text" name="dcoordEm" class="form-control" value="{{ $business->invoice_data?->dcoordEm }}" placeholder="{{ __('Enter Coordinates') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Teléfono') }}</label>
                                    <input type="text" name="dtfnEm" class="form-control" value="{{ $business->invoice_data?->dtfnEm }}" placeholder="{{ __('Enter Phone Number') }}">
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <label>{{ __('Correo Electrónico') }}</label>
                                    <input type="email" name="dcorElectEmi" class="form-control" value="{{ $business->invoice_data?->dcorElectEmi }}" placeholder="{{ __('Enter Email') }}">
                                </div>

                                <div class="col-lg-12">
                                    <div class="button-group text-center mt-5">
                                        <button type="reset" class="theme-btn border-btn m-2">{{ __('Cancel') }}</button>
                                        <button class="theme-btn m-2 submit-btn">{{ __('Save') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
    <script src="{{ asset('assets/js/custom/custom.js') }}"></script>
    <script>
    $(document).ready(function() {
        // Existing RUC type handler
        $('select[name="dtipoRuc"]').on('change', function() {
            if($(this).val() == 'Jurídico') {
                $('.business-name-field').show();
                $('input[name="dnombEm"]').prop('required', true);
            } else {
                $('.business-name-field').hide();
                $('input[name="dnombEm"]').prop('required', false);
            }
        });

        // Location selectors handlers
        $('select[name="dprov"]').on('change', function() {
            const province = $(this).val();
            $('select[name="ddistr"]').empty().append('<option value="">{{ __("Select District") }}</option>');
            $('select[name="dcorreg"]').empty().append('<option value="">{{ __("Select Township") }}</option>');
            
            if (province) {
                $.get(`{{ url('admin/dgi/districts') }}/${province}`, function(data) {
                    data.forEach(function(item) {
                        $('select[name="ddistr"]').append(
                            `<option value="${item.codigo}">${item.nombre}</option>`
                        );
                    });
                });
            }
        });

        $('select[name="ddistr"]').on('change', function() {
            const district = $(this).val();
            const province = $('select[name="dprov"]').val();
            $('select[name="dcorreg"]').empty().append('<option value="">{{ __("Select Township") }}</option>');
            
            if (district && province) {
                $.get(`{{ url('admin/dgi/townships') }}/${district}`, function(data) {
                    data.forEach(function(item) {
                        $('select[name="dcorreg"]').append(
                            `<option value="${item.codigo}">${item.nombre}</option>`
                        );
                    });
                });
            }
        });
    });
    </script>
@endpush
