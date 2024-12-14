<div class="modal fade" id="signup-modal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5">Create a <span id="subscription_name"> Free</span> account</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="personal-info">
                    <form action="{{ route('business.store') }}" method="post" enctype="multipart/form-data" class="add-brand-form pt-0 ajaxform_instant_reload">
                        @csrf

                        <div class="row">
                            <input type="hidden" name="plan_id" id="plan_id">
                            <div class="mt-3 col-lg-12">
                                <label class="custom-top-label">{{ __('Business/Store Name') }}</label>
                                <input type="text" name="store_name" placeholder="{{ __('Enter business/store Name') }}" class="form-control" />
                            </div>
                            <div class="mt-3 col-lg-12">
                                <label class="custom-top-label">{{ __('Business Category') }}</label>
                                <div class="gpt-up-down-arrow position-relative">
                                <select name="business_category_id" id="business_category"  class="form-control form-selected" required>
                                    <option value="">{{ __('Select Business Category') }}</option>
                                </select>
                                <span ></span>
                                </div>
                            </div>
                            <div class="mt-3 col-lg-12">
                                <label class="custom-top-label">{{ __('Company Address') }}</label>
                                <input type="text" name="companyName" placeholder="{{ __('Enter Company Address') }}" class="form-control" />
                            </div>
                            <div class="mt-3 col-lg-12">
                                <label class="custom-top-label">{{ __('Email Address') }}</label>
                                <input type="email" name="email" placeholder="{{ __('Enter Email Address') }}" class="form-control" required/>
                            </div>
                            <div class="mt-3 col-lg-12">
                                <label class="custom-top-label">{{ __('Password') }}</label>
                                <input type="password" name="password" placeholder="{{ __('Enter Password') }}" class="form-control" required/>
                            </div>
                        </div>

                        <div class="offcanvas-footer mt-3 d-flex justify-content-center">
                            <button type="button" data-bs-dismiss="modal" class="cancel-btn btn btn-outline-danger" data-bs-dismiss="offcanvas" aria-label="Close">
                                {{ __('Close') }}
                            </button>
                            <button class="submit-btn btn btn-primary text-white ms-2 btn-outline-danger" type="submit">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!--Verify Modal Start -->
<div class="modal fade" id="verifymodal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content verify-content">
            <div class="modal-header border-bottom-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body verify-modal-body  text-center">
                <h4>Email Verification</h4>
                <p class="des p-8-0">we sent an OTP in your email address <br>
                <span>shaidulislma@gmail.com</span></p>
                <form action="{{ route('business.verify-code') }}" method="post" class="verify_form">
                    <div class="code-input">
                        <input  class="form-control" type="text">
                        <input  class="form-control" type="text">
                        <input  class="form-control" type="text">
                        <input  class="form-control" type="text">
                        <input  class="form-control" type="text">
                        <input  class="form-control" type="text">
                    </div>
                    <p class="des p-24-0">Code send in 03:00 <span class="reset">Resend code</span></p>

                    <button class="cancel-btn btn btn-outline-danger submit-btn">Verify</button>
                </form>
            </div>
        </div>
    </div>
</div>
<!--Verify Modal end -->

<!-- success Modal Start -->
<div class="modal fade" id="successmodal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content success-content">
            <div class="modal-header border-bottom-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body success-modal-body text-center">
                <img src="{{ asset('assets/img/icon/1.svg') }}" alt="">
                <h4>Successfully!</h4>
                <p>Congratulations, Your account has been <br> successfully created</p>
                <button class="cancel-btn btn btn-outline-danger">Download App From Play store !</button>
            </div>
        </div>
    </div>
</div>
<!--success Modal end -->
