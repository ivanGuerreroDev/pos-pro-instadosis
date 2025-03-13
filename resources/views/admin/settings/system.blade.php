@extends('layouts.master')

@section('title')
    {{__('System Settings') }}
@endsection

@section('main_content')

<div class="erp-table-section system-settings">
    <div class="container-fluid">
        <div class="card ">
            <div class="card-bodys">

        <div class="table-header ">
            <div class="card-bodys">
                <div class="table-header mb-0 border-0 p-16">
                    <h4>{{ __('Note :') }} <span class="custom-warning">{{ __("Don't Use Any Kind Of Space In The Input Fields") }}</span></h4>
                </div>
            </div>
        </div>

        <div class="order-form-section mt-4 p-16">
            <div class="tab-content">
                <div class="tab-pane fade active show" id="add-new-petty" role="tabpanel">
                    <div class="row">
                        <div class="col-12 col-sm-12 col-md-4 mb-3">
                            <div class="cards-header shadow">
                                <div class="card-body">
                                    <ul class="nav nav-pills flex-column">
                                        <li class="nav-item">
                                            <a href="#app" id="home-tab4" class="add-report-btn active nav-link" data-bs-toggle="tab">{{ __('App') }}</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#Drivers" class="add-report-btn nav-link" data-bs-toggle="tab">{{ __('Drivers') }}</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#storage" class="add-report-btn nav-link" data-bs-toggle="tab">{{ __('Storage Settings') }}</a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="#mail-configuration" class="add-report-btn nav-link" data-bs-toggle="tab">{{ __('Mail Configuration') }}</a>
                                        </li>

                                        <li class="nav-item">
                                            <a href="#other" class="add-report-btn nav-link" data-bs-toggle="tab">{{ __('Others') }}</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12 col-md-8">
                            <div class="cards-header shadow">
                                <div class="card-body">
                                    <form action="{{ route('admin.system-settings.store') }}" method="post" class="ajaxform">
                                        @csrf
                                        <div class="tab-content no-padding">
                                            <div class="tab-pane fade show active" id="app">
                                                <div class="form-group">
                                                    <label>{{ __('APP_NAME') }}</label>
                                                    <input type="text" name="APP_NAME" value="{{ env('APP_NAME') ?? '' }}"  required class="form-control">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('APP_KEY') }}</label>
                                                    <input type="text" name="APP_KEY" value="{{ env('APP_KEY') ?? '' }}" required  class="form-control" readonly>
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('APP_DEBUG') }}</label>
                                                    <div class="gpt-up-down-arrow position-relative">
                                                    <select class="form-control" required name="APP_DEBUG">
                                                        <option value="true" @selected(env('APP_DEBUG') == true)>{{ __('true (Developers Only)') }}</option>
                                                        <option value="false" @selected(env('APP_DEBUG') == false)>{{ __('false') }}</option>
                                                    </select>
                                                    <span></span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('APP_URL') }}</label>
                                                    <input type="text" name="APP_URL" value="{{ env('APP_URL') ?? '' }}" required class="form-control">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('EMAGIC_API_KEY') }}</label>
                                                    <input type="text" name="EMAGIC_API_KEY" class="form-control" value="{{ env('EMAGIC_API_KEY') ?? '' }}">
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="button-group text-center mt-4">
                                                            <button class="theme-btn m-2 submit-btn">{{ __('Update') }}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="mail-configuration">
                                                <div class="form-group">
                                                    <label for="QUEUE_MAIL" class="required">{{ __('QUEUE_MAIL') }}</label>
                                                    <div class="gpt-up-down-arrow position-relative">
                                                    <select name="QUEUE_MAIL" id="QUEUE_MAIL" class="form-control">
                                                        <option @selected(env('QUEUE_MAIL') == true) value="true">{{ __('true') }}</option>
                                                        <option @selected(env('QUEUE_MAIL') == false) value="false">{{ __('false') }}</option>
                                                    </select>
                                                    <span></span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('MAIL_DRIVER_TYPE') }}</label>
                                                    <div class="gpt-up-down-arrow position-relative">
                                                    <select name="MAIL_DRIVER_TYPE" class="form-control" id="mail-driver-type-select">
                                                        <option value="MAIL_MAILER" @selected(env('MAIL_MAILER') ? 'selected' : '')>{{ __('MAIL MAILER') }}</option>
                                                        <option value="MAIL_DRIVER" @selected(env('MAIL_DRIVER') ? 'selected' : '')>{{ __('MAIL DRIVER') }}</option>
                                                    </select>
                                                    <span></span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label id="mail-driver-label">{{ __('MAIL DRIVER') }}</label>
                                                    <div class="gpt-up-down-arrow position-relative">
                                                    <select name="MAIL_DRIVER" class="form-control">
                                                        <option value="sendmail">{{ __('sendmail') }}</option>
                                                        <option value="smtp" selected>{{ __('smtp') }}</option>
                                                    </select>
                                                    <span></span>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>{{ __('MAIL_HOST') }}</label>
                                                    <input type="text"  name="MAIL_HOST" value="{{ env('MAIL_HOST') ?? '' }}" class="form-control" >
                                                </div>

                                                <div class="form-group">
                                                    <label>{{ __('MAIL_PORT') }}</label>
                                                    <input type="text"  name="MAIL_PORT" value="{{ env('MAIL_PORT') ?? '' }}" class="form-control" >
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('MAIL_USERNAME') }}</label>
                                                    <input type="text"   name="MAIL_USERNAME" value="{{ env('MAIL_USERNAME') ?? '' }}" class="form-control" >
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('MAIL_PASSWORD') }}</label>
                                                    <input type="text"   name="MAIL_PASSWORD" value="{{ env('MAIL_PASSWORD') ?? '' }}" class="form-control" >
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('MAIL_ENCRYPTION') }}</label>
                                                    <input type="text"   name="MAIL_ENCRYPTION" value="{{ env('MAIL_ENCRYPTION') ?? '' }}" class="form-control" >
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('MAIL_FROM_ADDRESS') }}</label>
                                                    <input type="text"   name="MAIL_FROM_ADDRESS" value="{{ env('MAIL_FROM_ADDRESS') ?? '' }}" class="form-control" >
                                                </div>

                                                <div class="form-group">
                                                    <label>{{ __('MAIL_FROM_NAME') }}</label>
                                                    <input type="text"   name="MAIL_FROM_NAME" value="{{ env('MAIL_FROM_NAME') ?? '' }}" class="form-control" >
                                                </div>

                                                <span>{{ __('Note :') }} <span class="text-danger">{{ __('If you are using MAIL QUEUE after Changing The Mail Settings You Need To Restart Your Supervisor From Your Server') }}</span></span><br>

                                                <span>{{ __('QUEUE COMMAND Path :') }} <span class="text-danger">{{ __('/home/u186958312/domains/maanai.acnoo.com/public_html/maanai') }}</span></span><br>
                                                <span>{{ __('QUEUE COMMAND :') }} <span class="text-danger">{{ __('php artisan queue:work') }}</span></span>
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="button-group text-center mt-4">
                                                            <button class="theme-btn m-2 submit-btn">{{ __('Update') }}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="Drivers">
                                                <div class="form-group">
                                                    <label for="CACHE_DRIVER">{{ __('CACHE_DRIVER') }}</label>
                                                    <div class="gpt-up-down-arrow position-relative">
                                                    <select class="form-control" name="CACHE_DRIVER" required>
                                                        <option value="array" {{ old('CACHE_DRIVER', 'file') == 'array' ? 'selected' : '' }}>{{ __('Array (Low Performance)') }}</option>
                                                        <option value="file" {{ old('CACHE_DRIVER', 'file') == 'file' ? 'selected' : '' }}>{{ __('File (Good Performance)') }}</option>
                                                        <option value="memcached" {{ old('CACHE_DRIVER', 'file') == 'memcached' ? 'selected' : '' }}>{{ __("Memcached (Don't Enable If You Don't Have Memcached Extension)") }}</option>
                                                        <option value="redis" {{ old('CACHE_DRIVER', 'file') == 'redis' ? 'selected' : '' }}>{{ __("Redis (Don't Enable If You Don't Have phpredis Extension)") }}</option>
                                                    </select>
                                                    <span></span>
                                                    </div>

                                                    <small class="text-danger">{{ __('Recommended') }} <strong>{{ __('Memcached or Redis') }}</strong>{{ __('Cache Driver For Height Performance Application And Optimize Call Database Query') }} </small>
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('QUEUE_CONNECTION') }}</label>
                                                    <input type="text" required="" name="QUEUE_CONNECTION" class="form-control" value="{{ env('QUEUE_CONNECTION') ?? 'database' }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('SESSION_DRIVER') }}</label>
                                                    <input type="text" required="" name="SESSION_DRIVER" class="form-control" value="{{ env('SESSION_DRIVER') ?? 'file' }}">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('SESSION_LIFETIME') }}</label>
                                                    <input type="number" required="" name="SESSION_LIFETIME" class="form-control" value="{{ env('SESSION_LIFETIME') ?? 200 }}">
                                                </div>

                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="button-group text-center mt-4">
                                                            <button class="theme-btn m-2 submit-btn">{{ __('Update') }}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>


                                            

                                            <div class="tab-pane fade" id="redis_method">
                                                <div class="form-group">
                                                    <label>{{ __('REDIS_PORT') }}</label>
                                                    <input type="text"  name="REDIS_PORT" class="form-control" value="6379">
                                                </div>

                                                <div class="form-group">
                                                    <label>{{ __('REDIS_URL') }}</label>
                                                    <input type="text"  name="REDIS_URL" class="form-control" value="">
                                                </div>

                                                <div class="form-group">
                                                    <label>{{ __('REDIS_PASSWORD') }}</label>
                                                    <input type="text"  name="REDIS_PASSWORD" class="form-control" value="">
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="storage">
                                                <h6>{{ __('Storage Settings') }}</h6>
                                                <div class="form-group">
                                                    <label>{{ __('Storage Method') }}</label>
                                                    <div class="gpt-up-down-arrow position-relative">
                                                    <select class="form-control" name="FILESYSTEM_DISK">
                                                        <option @selected(env('FILESYSTEM_DISK') == 'public') value="public">{{ __('public (uploads folder)') }}</option>
                                                        <option @selected(env('FILESYSTEM_DISK') == 's3') value="s3">{{ __('AWS S3 Storage Bucket') }}</option>
                                                        <option @selected(env('FILESYSTEM_DISK') == 'wasabi') value="wasabi">{{ __('Wasabi Storage Bucket') }}</option>
                                                    </select>
                                                    <span></span>
                                                    </div>
                                                </div>

                                                <hr>
                                                <p class="custom-warning">{{ __('Fill up this credentials if you want to use AWS S3 Storage Bucket') }}</p>
                                                <div class="form-group">
                                                    <label>{{ __('AWS_ACCESS_KEY_ID') }}</label>
                                                    <input type="text"  name="AWS_ACCESS_KEY_ID" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('AWS_SECRET_ACCESS_KEY') }}</label>
                                                    <input type="text"  name="AWS_SECRET_ACCESS_KEY" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('AWS_DEFAULT_REGION') }}</label>
                                                    <input type="text"  name="AWS_DEFAULT_REGION" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('AWS_BUCKET') }}</label>
                                                    <input type="text"  name="AWS_BUCKET" class="form-control" value="">
                                                </div>
                                                <hr>
                                                <p class="custom-warning">{{ __('Fill up this credentials if you want to use Wasabi Storage Bucket') }}</p>
                                                <div class="form-group">
                                                    <label>{{ __('WAS_ACCESS_KEY_ID') }}</label>
                                                    <input type="text"  name="WAS_ACCESS_KEY_ID" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('WAS_SECRET_ACCESS_KEY') }}</label>
                                                    <input type="text"  name="WAS_SECRET_ACCESS_KEY" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('WAS_DEFAULT_REGION') }}</label>
                                                    <input type="text"  name="WAS_DEFAULT_REGION" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('WAS_BUCKET') }}</label>
                                                    <input type="text"  name="WAS_BUCKET" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label>{{ __('WAS_ENDPOINT') }}</label>
                                                    <input type="text"  name="WAS_ENDPOINT" class="form-control" value="">
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="button-group text-center mt-4">
                                                            <button class="theme-btn m-2 submit-btn">{{ __('Update') }}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="tab-pane fade" id="other">
                                                <div class="">
                                                    <div class="form-group">
                                                        <label>{{ __('CACHE_LIFETIME') }}</label>
                                                        <input type="text"  name="CACHE_LIFETIME" class="form-control" value="{{ env('CACHE_LIFETIME') ?? '' }}">
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="TIMEZONE">{{ __('TIMEZONE') }}</label>
                                                        <div class="gpt-up-down-arrow position-relative">
                                                        <select class="form-control" name="TIMEZONE" id="TIMEZONE" >
                                                            <option value='Africa/Abidjan'>{{ __('Africa/Abidjan') }}</option>
                                                            <option value='Africa/Accra'>{{ __('Africa/Accra') }}</option>
                                                            <option value='Africa/Addis_Ababa'>{{ __('Africa/Addis_Ababa') }}</option>
                                                            <option value='Africa/Algiers'>{{ __('Africa/Algiers') }}</option>
                                                            <option value='Africa/Asmara'>{{ __('Africa/Asmara') }}</option>
                                                            <option value='Africa/Bamako'>{{ __('Africa/Bamako') }}</option>
                                                            <option value='Africa/Bangui'>{{ __('Africa/Bangui') }}</option>
                                                            <option value='Africa/Banjul'>{{ __('Africa/Banjul') }}</option>
                                                            <option value='Africa/Bissau'>{{ __('Africa/Bissau') }}</option>
                                                            <option value='Africa/Blantyre'>{{ __('Africa/Blantyre') }}</option>
                                                            <option value='Africa/Brazzaville'>{{ __('Africa/Brazzaville') }}</option>
                                                            <option value='Africa/Bujumbura'>{{ __('Africa/Bujumbura') }}</option>
                                                            <option value='Africa/Cairo'>{{ __('Africa/Cairo') }}</option>
                                                            <option value='Africa/Casablanca'>{{ __('Africa/Casablanca') }}</option>
                                                            <option value='Africa/Ceuta'>{{ __('Africa/Ceuta') }}</option>
                                                            <option value='Africa/Conakry'>{{ __('Africa/Conakry') }}</option>
                                                            <option value='Africa/Dakar'>{{ __('Africa/Dakar') }}</option>
                                                            <option value='Africa/Dar_es_Salaam'>{{ __('Africa/Dar_es_Salaam') }}</option>
                                                            <option value='Africa/Djibouti'>{{ __('Africa/Djibouti') }}</option>
                                                            <option value='Africa/Douala'>{{ __('Africa/Douala') }}</option>
                                                            <option value='Africa/El_Aaiun'>{{ __('Africa/El_Aaiun') }}</option>
                                                            <option value='Africa/Freetown'>{{ __('Africa/Freetown') }}</option>
                                                            <option value='Africa/Gaborone'>{{ __('Africa/Gaborone') }}</option>
                                                            <option value='Africa/Harare'>{{ __('Africa/Harare') }}</option>
                                                            <option value='Africa/Johannesburg'>{{ __('Africa/Johannesburg') }}</option>
                                                            <option value='Africa/Juba'>{{ __('Africa/Juba') }}</option>
                                                            <option value='Africa/Kampala'>{{ __('Africa/Kampala') }}</option>
                                                            <option value='Africa/Khartoum'>{{ __('Africa/Khartoum') }}</option>
                                                            <option value='Africa/Kigali'>{{ __('Africa/Kigali') }}</option>
                                                            <option value='Africa/Kinshasa'>{{ __('Africa/Kinshasa') }}</option>
                                                            <option value='Africa/Lagos'>{{ __('Africa/Lagos') }}</option>
                                                            <option value='Africa/Libreville'>{{ __('Africa/Libreville') }}</option>
                                                            <option value='Africa/Lome'>{{ __('Africa/Lome') }}</option>
                                                            <option value='Africa/Luanda'>{{ __('Africa/Luanda') }}</option>
                                                            <option value='Africa/Lubumbashi'>{{ __('Africa/Lubumbashi') }}</option>
                                                            <option value='Africa/Lusaka'>{{ __('Africa/Lusaka') }}</option>
                                                            <option value='Africa/Malabo'>{{ __('Africa/Malabo') }}</option>
                                                            <option value='Africa/Maputo'>{{ __('Africa/Maputo') }}</option>
                                                            <option value='Africa/Maseru'>{{ __('Africa/Maseru') }}</option>
                                                            <option value='Africa/Mbabane'>{{ __('Africa/Mbabane') }}</option>
                                                            <option value='Africa/Mogadishu'>{{ __('Africa/Mogadishu') }}</option>
                                                            <option value='Africa/Monrovia'>{{ __('Africa/Monrovia') }}</option>
                                                            <option value='Africa/Nairobi'>{{ __('Africa/Nairobi') }}</option>
                                                            <option value='Africa/Ndjamena'>{{ __('Africa/Ndjamena') }}</option>
                                                            <option value='Africa/Niamey'>{{ __('Africa/Niamey') }}</option>
                                                            <option value='Africa/Nouakchott'>{{ __('Africa/Nouakchott') }}</option>
                                                            <option value='Africa/Ouagadougou'>{{ __('Africa/Ouagadougou') }}</option>
                                                            <option value='Africa/Porto-Novo'>{{ __('Africa/Porto-Novo') }}</option>
                                                            <option value='Africa/Sao_Tome'>{{ __('Africa/Sao_Tome') }}</option>
                                                            <option value='Africa/Tripoli'>{{ __('Africa/Tripoli') }}</option>
                                                            <option value='Africa/Tunis'>{{ __('Africa/Tunis') }}</option>
                                                            <option value='Africa/Windhoek'>{{ __('Africa/Windhoek') }}</option>
                                                            <option value='America/Adak'>{{ __('America/Adak') }}</option>
                                                            <option value='America/Anchorage'>{{ __('America/Anchorage') }}</option>
                                                            <option value='America/Anguilla'>{{ __('America/Anguilla') }}</option>
                                                            <option value='America/Antigua'>{{ __('America/Antigua') }}</option>
                                                            <option value='America/Araguaina'>{{ __('America/Araguaina') }}</option>
                                                            <option value='America/Argentina/Buenos_Aires'>{{ __('America/Argentina/Buenos_Aires') }}</option>
                                                            <option value='America/Argentina/Catamarca'>{{ __('America/Argentina/Catamarca') }}</option>
                                                            <option value='America/Argentina/Cordoba'>{{ __('America/Argentina/Cordoba') }}</option>
                                                            <option value='America/Argentina/Jujuy'>{{ __('America/Argentina/Jujuy') }}</option>
                                                            <option value='America/Argentina/La_Rioja'>{{ __('America/Argentina/La_Rioja') }}</option>
                                                            <option value='America/Argentina/Mendoza'>{{ __('America/Argentina/Mendoza') }}</option>
                                                            <option value='America/Argentina/Rio_Gallegos'>{{ __('America/Argentina/Rio_Gallegos') }}</option>
                                                            <option value='America/Argentina/Salta'>{{ __('America/Argentina/Salta') }}</option>
                                                            <option value='America/Argentina/San_Juan'>{{ __('America/Argentina/San_Juan') }}</option>
                                                            <option value='America/Argentina/San_Luis'>{{ __('America/Argentina/San_Luis') }}</option>
                                                            <option value='America/Argentina/Tucuman'>{{ __('America/Argentina/Tucuman') }}</option>
                                                            <option value='America/Argentina/Ushuaia'>{{ __('America/Argentina/Ushuaia') }}</option>
                                                            <option value='America/Aruba'>{{ __('America/Aruba') }}</option>
                                                            <option value='America/Asuncion'>{{ __('America/Asuncion') }}</option>
                                                            <option value='America/Atikokan'>{{ __('America/Atikokan') }}</option>
                                                            <option value='America/Bahia'>{{ __('America/Bahia') }}</option>
                                                            <option value='America/Bahia_Banderas'>{{ __('America/Bahia_Banderas') }}</option>
                                                            <option value='America/Barbados'>{{ __('America/Barbados') }}</option>
                                                            <option value='America/Belem'>{{ __('America/Belem') }}</option>
                                                            <option value='America/Belize'>{{ __('America/Belize') }}</option>
                                                            <option value='America/Blanc-Sablon'>{{ __('America/Blanc-Sablon') }}</option>
                                                            <option value='America/Boa_Vista'>{{ __('America/Boa_Vista') }}</option>
                                                            <option value='America/Bogota'>{{ __('America/Bogota') }}</option>
                                                            <option value='America/Boise'>{{ __('America/Boise') }}</option>
                                                            <option value='America/Cambridge_Bay'>{{ __('America/Cambridge_Bay') }}</option>
                                                            <option value='America/Campo_Grande'>{{ __('America/Campo_Grande') }}</option>
                                                            <option value='America/Cancun'>{{ __('America/Cancun') }}</option>
                                                            <option value='America/Caracas'>{{ __('America/Caracas') }}</option>
                                                            <option value='America/Cayenne'>{{ __('America/Cayenne') }}</option>
                                                            <option value='America/Cayman'>{{ __('America/Cayman') }}</option>
                                                            <option value='America/Chicago'>{{ __('America/Chicago') }}</option>
                                                            <option value='America/Chihuahua'>{{ __('America/Chihuahua') }}</option>
                                                            <option value='America/Costa_Rica'>{{ __('America/Costa_Rica') }}</option>
                                                            <option value='America/Creston'>{{ __('America/Creston') }}</option>
                                                            <option value='America/Cuiaba'>{{ __('America/Cuiaba') }}</option>
                                                            <option value='America/Curacao'>{{ __('America/Curacao') }}</option>
                                                            <option value='America/Danmarkshavn'>{{ __('America/Danmarkshavn') }}</option>
                                                            <option value='America/Dawson'>{{ __('America/Dawson') }}</option>
                                                            <option value='America/Dawson_Creek'>{{ __('America/Dawson_Creek') }}</option>
                                                            <option value='America/Denver'>{{ __('America/Denver') }}</option>
                                                            <option value='America/Detroit'>{{ __('America/Detroit') }}</option>
                                                            <option value='America/Dominica'>{{ __('America/Dominica') }}</option>
                                                            <option value='America/Edmonton'>{{ __('America/Edmonton') }}</option>
                                                            <option value='America/Eirunepe'>{{ __('America/Eirunepe') }}</option>
                                                            <option value='America/El_Salvador'>{{ __('America/El_Salvador') }}</option>
                                                            <option value='America/Fort_Nelson'>{{ __('America/Fort_Nelson') }}</option>
                                                            <option value='America/Fortaleza'>{{ __('America/Fortaleza') }}</option>
                                                            <option value='America/Glace_Bay'>{{ __('America/Glace_Bay') }}</option>
                                                            <option value='America/Godthab'>{{ __('America/Godthab') }}</option>
                                                            <option value='America/Goose_Bay'>{{ __('America/Goose_Bay') }}</option>
                                                            <option value='America/Grand_Turk'>{{ __('America/Grand_Turk') }}</option>
                                                            <option value='America/Grenada'>{{ __('America/Grenada') }}</option>
                                                            <option value='America/Guadeloupe'>{{ __('America/Guadeloupe') }}</option>
                                                            <option value='America/Guatemala'>{{ __('America/Guatemala') }}</option>
                                                            <option value='America/Guayaquil'>{{ __('America/Guayaquil') }}</option>
                                                            <option value='America/Guyana'>{{ __('America/Guyana') }}</option>
                                                            <option value='America/Halifax'>{{ __('America/Halifax') }}</option>
                                                            <option value='America/Havana'>{{ __('America/Havana') }}</option>
                                                            <option value='America/Hermosillo'>{{ __('America/Hermosillo') }}</option>
                                                            <option value='America/Indiana/Indianapolis'>{{ __('America/Indiana/Indianapolis') }}</option>
                                                            <option value='America/Indiana/Knox'>{{ __('America/Indiana/Knox') }}</option>
                                                            <option value='America/Indiana/Marengo'>{{ __('America/Indiana/Marengo') }}</option>
                                                            <option value='America/Indiana/Petersburg'>{{ __('America/Indiana/Petersburg') }}</option>
                                                            <option value='America/Indiana/Tell_City'>{{ __('America/Indiana/Tell_City') }}</option>
                                                            <option value='America/Indiana/Vevay'>{{ __('America/Indiana/Vevay') }}</option>
                                                            <option value='America/Indiana/Vincennes'>{{ __('America/Indiana/Vincennes') }}</option>
                                                            <option value='America/Indiana/Winamac'>{{ __('America/Indiana/Winamac') }}</option>
                                                            <option value='America/Inuvik'>{{ __('America/Inuvik') }}</option>
                                                            <option value='America/Iqaluit'>{{ __('America/Iqaluit') }}</option>
                                                            <option value='America/Jamaica'>{{ __('America/Jamaica') }}</option>
                                                            <option value='America/Juneau'>{{ __('America/Juneau') }}</option>
                                                            <option value='America/Kentucky/Louisville'>{{ __('America/Kentucky/Louisville') }}</option>
                                                            <option value='America/Kentucky/Monticello'>{{ __('America/Kentucky/Monticello') }}</option>
                                                            <option value='America/Kralendijk'>{{ __('America/Kralendijk') }}</option>
                                                            <option value='America/La_Paz'>{{ __('America/La_Paz') }}</option>
                                                            <option value='America/Lima'>{{ __('America/Lima') }}</option>
                                                            <option value='America/Los_Angeles'>{{ __('America/Los_Angeles') }}</option>
                                                            <option value='America/Lower_Princes'>{{ __('America/Lower_Princes') }}</option>
                                                            <option value='America/Maceio'>{{ __('America/Maceio') }}</option>
                                                            <option value='America/Managua'>{{ __('America/Managua') }}</option>
                                                            <option value='America/Manaus'>{{ __('America/Manaus') }}</option>
                                                            <option value='America/Marigot'>{{ __('America/Marigot') }}</option>
                                                            <option value='America/Martinique'>{{ __('America/Martinique') }}</option>
                                                            <option value='America/Matamoros'>{{ __('America/Matamoros') }}</option>
                                                            <option value='America/Mazatlan'>{{ __('America/Mazatlan') }}</option>
                                                            <option value='America/Menominee'>{{ __('America/Menominee') }}</option>
                                                            <option value='America/Merida'>{{ __('America/Merida') }}</option>
                                                            <option value='America/Metlakatla'>{{ __('America/Metlakatla') }}</option>
                                                            <option value='America/Mexico_City'>{{ __('America/Mexico_City') }}</option>
                                                            <option value='America/Miquelon'>{{ __('America/Miquelon') }}</option>
                                                            <option value='America/Moncton'>{{ __('America/Moncton') }}</option>
                                                            <option value='America/Monterrey'>{{ __('America/Monterrey') }}</option>
                                                            <option value='America/Montevideo'>{{ __('America/Montevideo') }}</option>
                                                            <option value='America/Montserrat'>{{ __('America/Montserrat') }}</option>
                                                            <option value='America/Nassau'>{{ __('America/Nassau') }}</option>
                                                            <option value='America/New_York'>{{ __('America/New_York') }}</option>
                                                            <option value='America/Nipigon'>{{ __('America/Nipigon') }}</option>
                                                            <option value='America/Nome'>{{ __('America/Nome') }}</option>
                                                            <option value='America/Noronha'>{{ __('America/Noronha') }}</option>
                                                            <option value='America/North_Dakota/Beulah'>{{ __('America/North_Dakota/Beulah') }}</option>
                                                            <option value='America/North_Dakota/Center'>{{ __('America/North_Dakota/Center') }}</option>
                                                            <option value='America/North_Dakota/New_Salem'>{{ __('America/North_Dakota/New_Salem') }}</option>
                                                            <option value='America/Ojinaga'>{{ __('America/Ojinaga') }}</option>
                                                            <option value='America/Panama'>{{ __('America/Panama') }}</option>
                                                            <option value='America/Pangnirtung'>{{ __('America/Pangnirtung') }}</option>
                                                            <option value='America/Paramaribo'>{{ __('America/Paramaribo') }}</option>
                                                            <option value='America/Phoenix'>{{ __('America/Phoenix') }}</option>
                                                            <option value='America/Port-au-Prince'>{{ __('America/Port-au-Prince') }}</option>
                                                            <option value='America/Port_of_Spain'>{{ __('America/Port_of_Spain') }}</option>
                                                            <option value='America/Porto_Velho'>{{ __('America/Porto_Velho') }}</option>
                                                            <option value='America/Puerto_Rico'>{{ __('America/Puerto_Rico') }}</option>
                                                            <option value='America/Punta_Arenas'>{{ __('America/Punta_Arenas') }}</option>
                                                            <option value='America/Rainy_River'>{{ __('America/Rainy_River') }}</option>
                                                            <option value='America/Rankin_Inlet'>{{ __('America/Rankin_Inlet') }}</option>
                                                            <option value='America/Recife'>{{ __('America/Recife') }}</option>
                                                            <option value='America/Regina'>{{ __('America/Regina') }}</option>
                                                            <option value='America/Resolute'>{{ __('America/Resolute') }}</option>
                                                            <option value='America/Rio_Branco'>{{ __('America/Rio_Branco') }}</option>
                                                            <option value='America/Santarem'>{{ __('America/Santarem') }}</option>
                                                            <option value='America/Santiago'>{{ __('America/Santiago') }}</option>
                                                            <option value='America/Santo_Domingo'>{{ __('America/Santo_Domingo') }}</option>
                                                            <option value='America/Sao_Paulo'>{{ __('America/Sao_Paulo') }}</option>
                                                            <option value='America/Scoresbysund'>{{ __('America/Scoresbysund') }}</option>
                                                            <option value='America/Sitka'>{{ __('America/Sitka') }}</option>
                                                            <option value='America/St_Barthelemy'>{{ __('America/St_Barthelemy') }}</option>
                                                            <option value='America/St_Johns'>{{ __('America/St_Johns') }}</option>
                                                            <option value='America/St_Kitts'>{{ __('America/St_Kitts') }}</option>
                                                            <option value='America/St_Lucia'>{{ __('America/St_Lucia') }}</option>
                                                            <option value='America/St_Thomas'>{{ __('America/St_Thomas') }}</option>
                                                            <option value='America/St_Vincent'>{{ __('America/St_Vincent') }}</option>
                                                            <option value='America/Swift_Current'>{{ __('America/Swift_Current') }}</option>
                                                            <option value='America/Tegucigalpa'>{{ __('America/Tegucigalpa') }}</option>
                                                            <option value='America/Thule'>{{ __('America/Thule') }}</option>
                                                            <option value='America/Thunder_Bay'>{{ __('America/Thunder_Bay') }}</option>
                                                            <option value='America/Tijuana'>{{ __('America/Tijuana') }}</option>
                                                            <option value='America/Toronto'>{{ __('America/Toronto') }}</option>
                                                            <option value='America/Tortola'>{{ __('America/Tortola') }}</option>
                                                            <option value='America/Vancouver'>{{ __('America/Vancouver') }}</option>
                                                            <option value='America/Whitehorse'>{{ __('America/Whitehorse') }}</option>
                                                            <option value='America/Winnipeg'>{{ __('America/Winnipeg') }}</option>
                                                            <option value='America/Yakutat'>{{ __('America/Yakutat') }}</option>
                                                            <option value='America/Yellowknife'>{{ __('America/Yellowknife') }}</option>
                                                            <option value='Antarctica/Casey'>{{ __('Antarctica/Casey') }}</option>
                                                            <option value='Antarctica/Davis'>{{ __('Antarctica/Davis') }}</option>
                                                            <option value='Antarctica/DumontDUrville'>{{ __('Antarctica/DumontDUrville') }}</option>
                                                            <option value='Antarctica/Macquarie'>{{ __('Antarctica/Macquarie') }}</option>
                                                            <option value='Antarctica/Mawson'>{{ __('Antarctica/Mawson') }}</option>
                                                            <option value='Antarctica/McMurdo'>{{ __('Antarctica/McMurdo') }}</option>
                                                            <option value='Antarctica/Palmer'>{{ __('Antarctica/Palmer') }}</option>
                                                            <option value='Antarctica/Rothera'>{{ __('Antarctica/Rothera') }}</option>
                                                            <option value='Antarctica/Syowa'>{{ __('Antarctica/Syowa') }}</option>
                                                            <option value='Antarctica/Troll'>{{ __('Antarctica/Troll') }}</option>
                                                            <option value='Antarctica/Vostok'>{{ __('Antarctica/Vostok') }}</option>
                                                            <option value='Arctic/Longyearbyen'>{{ __('Arctic/Longyearbyen') }}</option>
                                                            <option value='Asia/Aden'>{{ __('Asia/Aden') }}</option>
                                                            <option value='Asia/Almaty'>{{ __('Asia/Almaty') }}</option>
                                                            <option value='Asia/Amman'>{{ __('Asia/Amman') }}</option>
                                                            <option value='Asia/Anadyr'>{{ __('Asia/Anadyr') }}</option>
                                                            <option value='Asia/Aqtau'>{{ __('Asia/Aqtau') }}</option>
                                                            <option value='Asia/Aqtobe'>{{ __('Asia/Aqtobe') }}</option>
                                                            <option value='Asia/Ashgabat'>{{ __('Asia/Ashgabat') }}</option>
                                                            <option value='Asia/Atyrau'>{{ __('Asia/Atyrau') }}</option>
                                                            <option value='Asia/Baghdad'>{{ __('Asia/Baghdad') }}</option>
                                                            <option value='Asia/Bahrain'>{{ __('Asia/Bahrain') }}</option>
                                                            <option value='Asia/Baku'>{{ __('Asia/Baku') }}</option>
                                                            <option value='Asia/Bangkok'>{{ __('Asia/Bangkok') }}</option>
                                                            <option value='Asia/Barnaul'>{{ __('Asia/Barnaul') }}</option>
                                                            <option value='Asia/Beirut'>{{ __('Asia/Beirut') }}</option>
                                                            <option value='Asia/Bishkek'>{{ __('Asia/Bishkek') }}</option>
                                                            <option value='Asia/Brunei'>{{ __('Asia/Brunei') }}</option>
                                                            <option value='Asia/Chita'>{{ __('Asia/Chita') }}</option>
                                                            <option value='Asia/Choibalsan'>{{ __('Asia/Choibalsan') }}</option>
                                                            <option value='Asia/Colombo'>{{ __('Asia/Colombo') }}</option>
                                                            <option value='Asia/Damascus'>{{ __('Asia/Damascus') }}</option>
                                                            <option value='Asia/Dhaka'>{{ __('Asia/Dhaka') }}</option>
                                                            <option value='Asia/Dili'>{{ __('Asia/Dili') }}</option>
                                                            <option value='Asia/Dubai'>{{ __('Asia/Dubai') }}</option>
                                                            <option value='Asia/Dushanbe'>{{ __('Asia/Dushanbe') }}</option>
                                                            <option value='Asia/Famagusta'>{{ __('Asia/Famagusta') }}</option>
                                                            <option value='Asia/Gaza'>{{ __('Asia/Gaza') }}</option>
                                                            <option value='Asia/Hebron'>{{ __('Asia/Hebron') }}</option>
                                                            <option value='Asia/Ho_Chi_Minh'>{{ __('Asia/Ho_Chi_Minh') }}</option>
                                                            <option value='Asia/Hong_Kong'>{{ __('Asia/Hong_Kong') }}</option>
                                                            <option value='Asia/Hovd'>{{ __('Asia/Hovd') }}</option>
                                                            <option value='Asia/Irkutsk'>{{ __('Asia/Irkutsk') }}</option>
                                                            <option value='Asia/Jakarta'>{{ __('Asia/Jakarta') }}</option>
                                                            <option value='Asia/Jayapura'>{{ __('Asia/Jayapura') }}</option>
                                                            <option value='Asia/Jerusalem'>{{ __('Asia/Jerusalem') }}</option>
                                                            <option value='Asia/Kabul'>{{ __('Asia/Kabul') }}</option>
                                                            <option value='Asia/Kamchatka'>{{ __('Asia/Kamchatka') }}</option>
                                                            <option value='Asia/Karachi'>{{ __('Asia/Karachi') }}</option>
                                                            <option value='Asia/Kathmandu'>{{ __('Asia/Kathmandu') }}</option>
                                                            <option value='Asia/Khandyga'>{{ __('Asia/Khandyga') }}</option>
                                                            <option value='Asia/Kolkata'>{{ __('Asia/Kolkata') }}</option>
                                                            <option value='Asia/Krasnoyarsk'>{{ __('Asia/Krasnoyarsk') }}</option>
                                                            <option value='Asia/Kuala_Lumpur'>{{ __('Asia/Kuala_Lumpur') }}</option>
                                                            <option value='Asia/Kuching'>{{ __('Asia/Kuching') }}</option>
                                                            <option value='Asia/Kuwait'>{{ __('Asia/Kuwait') }}</option>
                                                            <option value='Asia/Macau'>{{ __('Asia/Macau') }}</option>
                                                            <option value='Asia/Magadan'>{{ __('Asia/Magadan') }}</option>
                                                            <option value='Asia/Makassar'>{{ __('Asia/Makassar') }}</option>
                                                            <option value='Asia/Manila'>{{ __('Asia/Manila') }}</option>
                                                            <option value='Asia/Muscat'>{{ __('Asia/Muscat') }}</option>
                                                            <option value='Asia/Nicosia'>{{ __('Asia/Nicosia') }}</option>
                                                            <option value='Asia/Novokuznetsk'>{{ __('Asia/Novokuznetsk') }}</option>
                                                            <option value='Asia/Novosibirsk'>{{ __('Asia/Novosibirsk') }}</option>
                                                            <option value='Asia/Omsk'>{{ __('Asia/Omsk') }}</option>
                                                            <option value='Asia/Oral'>{{ __('Asia/Oral') }}</option>
                                                            <option value='Asia/Phnom_Penh'>{{ __('Asia/Phnom_Penh') }}</option>
                                                            <option value='Asia/Pontianak'>{{ __('Asia/Pontianak') }}</option>
                                                            <option value='Asia/Pyongyang'>{{ __('Asia/Pyongyang') }}</option>
                                                            <option value='Asia/Qatar'>{{ __('Asia/Qatar') }}</option>
                                                            <option value='Asia/Qostanay'>{{ __('Asia/Qostanay') }}</option>
                                                            <option value='Asia/Qyzylorda'>{{ __('Asia/Qyzylorda') }}</option>
                                                            <option value='Asia/Riyadh'>{{ __('Asia/Riyadh') }}</option>
                                                            <option value='Asia/Sakhalin'>{{ __('Asia/Sakhalin') }}</option>
                                                            <option value='Asia/Samarkand'>{{ __('Asia/Samarkand') }}</option>
                                                            <option value='Asia/Seoul'>{{ __('Asia/Seoul') }}</option>
                                                            <option value='Asia/Shanghai'>{{ __('Asia/Shanghai') }}</option>
                                                            <option value='Asia/Singapore'>{{ __('Asia/Singapore') }}</option>
                                                            <option value='Asia/Srednekolymsk'>{{ __('Asia/Srednekolymsk') }}</option>
                                                            <option value='Asia/Taipei'>{{ __('Asia/Taipei') }}</option>
                                                            <option value='Asia/Tashkent'>{{ __('Asia/Tashkent') }}</option>
                                                            <option value='Asia/Tbilisi'>{{ __('Asia/Tbilisi') }}</option>
                                                            <option value='Asia/Tehran'>{{ __('Asia/Tehran') }}</option>
                                                            <option value='Asia/Thimphu'>{{ __('Asia/Thimphu') }}</option>
                                                            <option value='Asia/Tokyo'>{{ __('Asia/Tokyo') }}</option>
                                                            <option value='Asia/Tomsk'>{{ __('Asia/Tomsk') }}</option>
                                                            <option value='Asia/Ulaanbaatar'>{{ __('Asia/Ulaanbaatar') }}</option>
                                                            <option value='Asia/Urumqi'>{{ __('Asia/Urumqi') }}</option>
                                                            <option value='Asia/Ust-Nera'>{{ __('Asia/Ust-Nera') }}</option>
                                                            <option value='Asia/Vientiane'>{{ __('Asia/Vientiane') }}</option>
                                                            <option value='Asia/Vladivostok'>{{ __('Asia/Vladivostok') }}</option>
                                                            <option value='Asia/Yakutsk'>{{ __('Asia/Yakutsk') }}</option>
                                                            <option value='Asia/Yangon'>{{ __('Asia/Yangon') }}</option>
                                                            <option value='Asia/Yekaterinburg'>{{ __('Asia/Yekaterinburg') }}</option>
                                                            <option value='Asia/Yerevan'>{{ __('Asia/Yerevan') }}</option>
                                                            <option value='Atlantic/Azores'>{{ __('Atlantic/Azores') }}</option>
                                                            <option value='Atlantic/Bermuda'>{{ __('Atlantic/Bermuda') }}</option>
                                                            <option value='Atlantic/Canary'>{{ __('Atlantic/Canary') }}</option>
                                                            <option value='Atlantic/Cape_Verde'>{{ __('Atlantic/Cape_Verde') }}</option>
                                                            <option value='Atlantic/Faroe'>{{ __('Atlantic/Faroe') }}</option>
                                                            <option value='Atlantic/Madeira'>{{ __('Atlantic/Madeira') }}</option>
                                                            <option value='Atlantic/Reykjavik'>{{ __('Atlantic/Reykjavik') }}</option>
                                                            <option value='Atlantic/South_Georgia'>{{ __('Atlantic/South_Georgia') }}</option>
                                                            <option value='Atlantic/St_Helena'>{{ __('Atlantic/St_Helena') }}</option>
                                                            <option value='Atlantic/Stanley'>{{ __('Atlantic/Stanley') }}</option>
                                                            <option value='Australia/Adelaide'>{{ __('Australia/Adelaide') }}</option>
                                                            <option value='Australia/Brisbane'>{{ __('Australia/Brisbane') }}</option>
                                                            <option value='Australia/Broken_Hill'>{{ __('Australia/Broken_Hill') }}</option>
                                                            <option value='Australia/Currie'>{{ __('Australia/Currie') }}</option>
                                                            <option value='Australia/Darwin'>{{ __('Australia/Darwin') }}</option>
                                                            <option value='Australia/Eucla'>{{ __('Australia/Eucla') }}</option>
                                                            <option value='Australia/Hobart'>{{ __('Australia/Hobart') }}</option>
                                                            <option value='Australia/Lindeman'>{{ __('Australia/Lindeman') }}</option>
                                                            <option value='Australia/Lord_Howe'>{{ __('Australia/Lord_Howe') }}</option>
                                                            <option value='Australia/Melbourne'>{{ __('Australia/Melbourne') }}</option>
                                                            <option value='Australia/Perth'>{{ __('Australia/Perth') }}</option>
                                                            <option value='Australia/Sydney'>{{ __('Australia/Sydney') }}</option>
                                                            <option value='Europe/Amsterdam'>{{ __('Europe/Amsterdam') }}</option>
                                                            <option value='Europe/Andorra'>{{ __('Europe/Andorra') }}</option>
                                                            <option value='Europe/Astrakhan'>{{ __('Europe/Astrakhan') }}</option>
                                                            <option value='Europe/Athens'>{{ __('Europe/Athens') }}</option>
                                                            <option value='Europe/Belgrade'>{{ __('Europe/Belgrade') }}</option>
                                                            <option value='Europe/Berlin'>{{ __('Europe/Berlin') }}</option>
                                                            <option value='Europe/Bratislava'>{{ __('Europe/Bratislava') }}</option>
                                                            <option value='Europe/Brussels'>{{ __('Europe/Brussels') }}</option>
                                                            <option value='Europe/Bucharest'>{{ __('Europe/Bucharest') }}</option>
                                                            <option value='Europe/Budapest'>{{ __('Europe/Budapest') }}</option>
                                                            <option value='Europe/Busingen'>{{ __('Europe/Busingen') }}</option>
                                                            <option value='Europe/Chisinau'>{{ __('Europe/Chisinau') }}</option>
                                                            <option value='Europe/Copenhagen'>{{ __('Europe/Copenhagen') }}</option>
                                                            <option value='Europe/Dublin'>{{ __('Europe/Dublin') }}</option>
                                                            <option value='Europe/Gibraltar'>{{ __('Europe/Gibraltar') }}</option>
                                                            <option value='Europe/Guernsey'>{{ __('Europe/Guernsey') }}</option>
                                                            <option value='Europe/Helsinki'>{{ __('Europe/Helsinki') }}</option>
                                                            <option value='Europe/Isle_of_Man'>{{ __('Europe/Isle_of_Man') }}</option>
                                                            <option value='Europe/Istanbul'>{{ __('Europe/Istanbul') }}</option>
                                                            <option value='Europe/Jersey'>{{ __('Europe/Jersey') }}</option>
                                                            <option value='Europe/Kaliningrad'>{{ __('Europe/Kaliningrad') }}</option>
                                                            <option value='Europe/Kiev'>{{ __('Europe/Kiev') }}</option>
                                                            <option value='Europe/Kirov'>{{ __('Europe/Kirov') }}</option>
                                                            <option value='Europe/Lisbon'>{{ __('Europe/Lisbon') }}</option>
                                                            <option value='Europe/Ljubljana'>{{ __('Europe/Ljubljana') }}</option>
                                                            <option value='Europe/London'>{{ __('Europe/London') }}</option>
                                                            <option value='Europe/Luxembourg'>{{ __('Europe/Luxembourg') }}</option>
                                                            <option value='Europe/Madrid'>{{ __('Europe/Madrid') }}</option>
                                                            <option value='Europe/Malta'>{{ __('Europe/Malta') }}</option>
                                                            <option value='Europe/Mariehamn'>{{ __('Europe/Mariehamn') }}</option>
                                                            <option value='Europe/Minsk'>{{ __('Europe/Minsk') }}</option>
                                                            <option value='Europe/Monaco'>{{ __('Europe/Monaco') }}</option>
                                                            <option value='Europe/Moscow'>{{ __('Europe/Moscow') }}</option>
                                                            <option value='Europe/Oslo'>{{ __('Europe/Oslo') }}</option>
                                                            <option value='Europe/Paris'>{{ __('Europe/Paris') }}</option>
                                                            <option value='Europe/Podgorica'>{{ __('Europe/Podgorica') }}</option>
                                                            <option value='Europe/Prague'>{{ __('Europe/Prague') }}</option>
                                                            <option value='Europe/Riga'>{{ __('Europe/Riga') }}</option>
                                                            <option value='Europe/Rome'>{{ __('Europe/Rome') }}</option>
                                                            <option value='Europe/Samara'>{{ __('Europe/Samara') }}</option>
                                                            <option value='Europe/San_Marino'>{{ __('Europe/San_Marino') }}</option>
                                                            <option value='Europe/Sarajevo'>{{ __('Europe/Sarajevo') }}</option>
                                                            <option value='Europe/Saratov'>{{ __('Europe/Saratov') }}</option>
                                                            <option value='Europe/Simferopol'>{{ __('Europe/Simferopol') }}</option>
                                                            <option value='Europe/Skopje'>{{ __('Europe/Skopje') }}</option>
                                                            <option value='Europe/Sofia'>{{ __('Europe/Sofia') }}</option>
                                                            <option value='Europe/Stockholm'>{{ __('Europe/Stockholm') }}</option>
                                                            <option value='Europe/Tallinn'>{{ __('Europe/Tallinn') }}</option>
                                                            <option value='Europe/Tirane'>{{ __('Europe/Tirane') }}</option>
                                                            <option value='Europe/Ulyanovsk'>{{ __('Europe/Ulyanovsk') }}</option>
                                                            <option value='Europe/Uzhgorod'>{{ __('Europe/Uzhgorod') }}</option>
                                                            <option value='Europe/Vaduz'>{{ __('Europe/Vaduz') }}</option>
                                                            <option value='Europe/Vatican'>{{ __('Europe/Vatican') }}</option>
                                                            <option value='Europe/Vienna'>{{ __('Europe/Vienna') }}</option>
                                                            <option value='Europe/Vilnius'>{{ __('Europe/Vilnius') }}</option>
                                                            <option value='Europe/Volgograd'>{{ __('Europe/Volgograd') }}</option>
                                                            <option value='Europe/Warsaw'>{{ __('Europe/Warsaw') }}</option>
                                                            <option value='Europe/Zagreb'>{{ __('Europe/Zagreb') }}</option>
                                                            <option value='Europe/Zaporozhye'>{{ __('Europe/Zaporozhye') }}</option>
                                                            <option value='Europe/Zurich'>{{ __('Europe/Zurich') }}</option>
                                                            <option value='Indian/Antananarivo'>{{ __('Indian/Antananarivo') }}</option>
                                                            <option value='Indian/Chagos'>{{ __('Indian/Chagos') }}</option>
                                                            <option value='Indian/Christmas'>{{ __('Indian/Christmas') }}</option>
                                                            <option value='Indian/Cocos'>{{ __('Indian/Cocos') }}</option>
                                                            <option value='Indian/Comoro'>{{ __('Indian/Comoro') }}</option>
                                                            <option value='Indian/Kerguelen'>{{ __('Indian/Kerguelen') }}</option>
                                                            <option value='Indian/Mahe'>{{ __('Indian/Mahe') }}</option>
                                                            <option value='Indian/Maldives'>{{ __('Indian/Maldives') }}</option>
                                                            <option value='Indian/Mauritius'>{{ __('Indian/Mauritius') }}</option>
                                                            <option value='Indian/Mayotte'>{{ __('Indian/Mayotte') }}</option>
                                                            <option value='Indian/Reunion'>{{ __('Indian/Reunion') }}</option>
                                                            <option value='Pacific/Apia'>{{ __('Pacific/Apia') }}</option>
                                                            <option value='Pacific/Auckland'>{{ __('Pacific/Auckland') }}</option>
                                                            <option value='Pacific/Bougainville'>{{ __('Pacific/Bougainville') }}</option>
                                                            <option value='Pacific/Chatham'>{{ __('Pacific/Chatham') }}</option>
                                                            <option value='Pacific/Chuuk'>{{ __('Pacific/Chuuk') }}</option>
                                                            <option value='Pacific/Easter'>{{ __('Pacific/Easter') }}</option>
                                                            <option value='Pacific/Efate'>{{ __('Pacific/Efate') }}</option>
                                                            <option value='Pacific/Enderbury'>{{ __('Pacific/Enderbury') }}</option>
                                                            <option value='Pacific/Fakaofo'>{{ __('Pacific/Fakaofo') }}</option>
                                                            <option value='Pacific/Fiji'>{{ __('Pacific/Fiji') }}</option>
                                                            <option value='Pacific/Funafuti'>{{ __('Pacific/Funafuti') }}</option>
                                                            <option value='Pacific/Galapagos'>{{ __('Pacific/Galapagos') }}</option>
                                                            <option value='Pacific/Gambier'>{{ __('Pacific/Gambier') }}</option>
                                                            <option value='Pacific/Guadalcanal'>{{ __('Pacific/Guadalcanal') }}</option>
                                                            <option value='Pacific/Guam'>{{ __('Pacific/Guam') }}</option>
                                                            <option value='Pacific/Honolulu'>{{ __('Pacific/Honolulu') }}</option>
                                                            <option value='Pacific/Kiritimati'>{{ __('Pacific/Kiritimati') }}</option>
                                                            <option value='Pacific/Kosrae'>{{ __('Pacific/Kosrae') }}</option>
                                                            <option value='Pacific/Kwajalein'>{{ __('Pacific/Kwajalein') }}</option>
                                                            <option value='Pacific/Majuro'>{{ __('Pacific/Majuro') }}</option>
                                                            <option value='Pacific/Marquesas'>{{ __('Pacific/Marquesas') }}</option>
                                                            <option value='Pacific/Midway'>{{ __('Pacific/Midway') }}</option>
                                                            <option value='Pacific/Nauru'>{{ __('Pacific/Nauru') }}</option>
                                                            <option value='Pacific/Niue'>{{ __('Pacific/Niue') }}</option>
                                                            <option value='Pacific/Norfolk'>{{ __('Pacific/Norfolk') }}</option>
                                                            <option value='Pacific/Noumea'>{{ __('Pacific/Noumea') }}</option>
                                                            <option value='Pacific/Pago_Pago'>{{ __('Pacific/Pago_Pago') }}</option>
                                                            <option value='Pacific/Palau'>{{ __('Pacific/Palau') }}</option>
                                                            <option value='Pacific/Pitcairn'>{{ __('Pacific/Pitcairn') }}</option>
                                                            <option value='Pacific/Pohnpei'>{{ __('Pacific/Pohnpei') }}</option>
                                                            <option value='Pacific/Port_Moresby'>{{ __('Pacific/Port_Moresby') }}</option>
                                                            <option value='Pacific/Rarotonga'>{{ __('Pacific/Rarotonga') }}</option>
                                                            <option value='Pacific/Saipan'>{{ __('Pacific/Saipan') }}</option>
                                                            <option value='Pacific/Tahiti'>{{ __('Pacific/Tahiti') }}</option>
                                                            <option value='Pacific/Tarawa'>{{ __('Pacific/Tarawa') }}</option>
                                                            <option value='Pacific/Tongatapu'>{{ __('Pacific/Tongatapu') }}</option>
                                                            <option value='Pacific/Wake'>{{ __('Pacific/Wake') }}</option>
                                                            <option value='Pacific/Wallis'>{{ __('Pacific/Wallis') }}</option>
                                                            <option value='UTC'>{{ __('UTC') }}</option>
                                                        </select>
                                                        <span></span>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-lg-12">
                                                            <div class="button-group text-center mt-4">
                                                                <button class="theme-btn m-2 submit-btn">{{ __('Update') }}</button>
                                                            </div>
                                                        </div>
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
            </div>
        </div>

            </div>
            </div>
        </div>
    </div>
</div>
@endsection
