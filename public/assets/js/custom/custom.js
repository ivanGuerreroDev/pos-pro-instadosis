////////////////////////////////////////////////////////////////////////////////////////////////////

$('.view-btn').each(function () {
    let container = $(this);
    let id = container.data('id');

    // User View Modal
    $('#user_view_' + id).on('click', function () {
        $('#user_view_business_category').text($('#user_view_' + id).data('business_category'));
        $('#user_view_business_name').text($('#user_view_' + id).data('business_name'));

        let imageSrc = $('#user_view_' + id).data('image');
        $('#user_view_image').attr('src', imageSrc);
        $('#user_view_name').text($('#user_view_' + id).data('name'));
        $('#user_view_role').text($('#user_view_' + id).data('role'));
        $('#user_view_email').text($('#user_view_' + id).data('email'));
        $('#user_view_phone').text($('#user_view_' + id).data('phone'));
        $('#user_view_address').text($('#user_view_' + id).data('address'));
        $('#user_view_country_id').text($('#user_view_' + id).data('country_id'));
        $('#user_view_statfeatures-listus').text($('#user_view_' + id).data('status') == 1 ? 'Active' : 'Deactive');
    });

    // Plan View Modal
    $('#plan_view_' + id).on('click', function () {
        let features = $('#plan_view_' + id).data('features');
        let featuresList = $('#features-list');

        featuresList.empty();

        features.forEach(feature => {
            let featureHtml = `
                <div class="row align-items-center mt-3 feature-entry">
                    <div class="col-md-1">
                        <p id="plan_view_features_yes">
                            ${feature.value == 1 ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'}
                        </p>
                    </div>
                    <div class="col-1">
                        <p>:</p>
                    </div>
                    <div class="col-md-7">
                        <p id="plan_view_features_name">${feature.name}</p>
                    </div>
                </div>
            `;

            featuresList.append(featureHtml);
        });
    });

    // Category View
    $('#category_view_' + id).on('click', function () {
        $('#category_view_name').text($('#category_view_' + id).data('name'));
        $('#category_view_description').text($('#category_view_' + id).data('description'));
        $('#category_view_status').text($('#category_view_' + id).data('status') == 1 ? 'Active' : 'Deactive');
    });
    // Faqs view
    $('#faqs_view_' + id).on('click', function () {
        $('#faqs_view_question').text($('#faqs_view_' + id).data('question'));
        $('#faqs_view_answer').text($('#faqs_view_' + id).data('answer'));
        $('#faqs_view_status').text($('#faqs_view_' + id).data('status') == 1 ? 'Active' : 'Deactive');
    });

});

//Business view modal
$('.business-view').on('click', function () {
    $('.business_name').text($(this).data('name'));
    $('#image').attr('src', $(this).data('image'));
    $('#name').text($(this).data('name'));
    $('#address').text($(this).data('address'));
    $('#category').text($(this).data('category'));
    $('#phone').text($(this).data('phone'));
    $('#package').text($(this).data('package'));
    $('#last_enroll').text($(this).data('last_enroll'));
    $('#expired_date').text($(this).data('expired_date'));
    $('#created_date').text($(this).data('created_date'));
});

$('#plan_id').on('change', function () {
    $('.plan-price').val($(this).find(':selected').data('price'))
});

$(document).on('change', '.file-input-change', function () {
    let prevId = $(this).data('id');
    newPreviewImage(this, prevId);
});

// image preview
function newPreviewImage(input, prevId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            $('#' + prevId).attr('src', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

//Upgrade plan
$('.business-upgrade-plan').on('click', function () {
    var url = $(this).data('url');

    $('#business_name').val($(this).data('name'));
    $('#business_id').val($(this).data('id'));
    $('.upgradePlan').attr('action', url);
});

$('.modal-reject').on('click', function () {
    var url = $(this).data('url');
    $('.modalRejectForm').attr('action', url);
});

$('.modal-approve').on('click', function () {
    var url = $(this).data('url');
    $('.modalApproveForm').attr('action', url);
});


//edit banner
$('.edit-btn').each(function () {
    let container = $(this);
    let service = container.data('id');
    let id = service;
    $('#edit-banner-' + service).on('click', function () {

        $('#checkbox').prop('checked', $('#edit-banner-' + service).data('status') == 1);
        $('.dynamic-text').text($('#edit-banner-' + service).data('status') == 1 ? 'Active' : 'Deactive');

        let edit_action_route = $(this).data('url');
        $('#editForm').attr('action', edit_action_route + '/' + id);
    });

});

$('.edit-banner-btn').on('click', function () {
    let status = $(this).data('status');
    $('.edit-status').prop('checked', status);
    $('.edit-imageUrl-form').attr('action', $(this).data('url'));
    $('#edit-imageUrl').attr('src', $(this).data('image'));

    if (status == 1) {
        $('.dynamic-text').text('Active');
    } else {
        $('.dynamic-text').text('Deactive');
    }
});

$(function () {
    $("body").on("click", ".remove-one", function () {
        $(this).closest(".remove-list").remove();
    });
});
/** Subscriptions Plan end */

//Dynamic Tags Setting Start

$(document).off('click', '.add-new-tag').on('click', '.add-new-tag', function () {
    let html = `
    <div class="col-md-6">
        <div class="row row-items">
            <div class="col-sm-10">
                <label for="">Tags</label>
                <input type="text" name="tags[]" class="form-control" required
                    placeholder="Enter tags name">
            </div>
            <div class="col-sm-2 align-self-center mt-3">
                <button type="button" class="btn text-danger trash remove-btn-features"
                    onclick="removeDynamicField(this)"><i
                        class="fas fa-trash"></i></button>
            </div>
        </div>
    </div>
    `;
    $(".manual-rows .single-tags").append(html);
});
//Dynamic tag ends

$(document).on('click', ".add-new-item", function () {
    let html = `
    <div class="row row-items">
        <div class="col-sm-5">
            <label for="">Label</label>
            <input type="text" name="manual_data[label][]" value="" class="form-control" placeholder="Enter label name">
        </div>
        <div class="col-sm-5">
            <label for="">Select Required/Optionl</label>
            <select class="form-control" required name="manual_data[is_required][]">
                <option value="1">Required</option>
                <option value="0">Optional</option>
            </select>
        </div>
        <div class="col-sm-2 align-self-center mt-3">
            <button type="button" class="btn text-danger trash remove-btn-features"><i class="fas fa-trash"></i></button>
        </div>
    </div>
    `
    $(".manual-rows").append(html);
});

$(document).on('click', ".remove-btn-features", function () {
    var $row = $(this).closest(".row-items");
    $row.remove();
});


// Staff view Start
$('.staff-view-btn').on('click', function () {
    var staffName = $(this).data('staff-view-name');
    var staffPhone = $(this).data('staff-view-phone-number');
    var staffemail = $(this).data('staff-view-email-number');
    var staffRole = $(this).data('staff-view-role');

    $('#staff_view_name').text(staffName);
    $('#staff_view_phone_number').text(staffPhone);
    $('#staff_view_email_number').text(staffemail);
    $('#staff_view_role').text(staffRole);
});
// Staff view End


var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
})



    // subscription-plan-edit-custom-input size
    const inputs = document.querySelectorAll('.subscription-plan-edit-custom-input');

    function resizeInput() {
        const tempSpan = document.createElement('span');
        tempSpan.style.visibility = 'hidden';
        tempSpan.style.position = 'absolute';
        tempSpan.style.whiteSpace = 'pre';
        tempSpan.style.font = window.getComputedStyle(this).font;
        tempSpan.textContent = this.value || this.placeholder;

        document.body.appendChild(tempSpan);

        this.style.width = (tempSpan.offsetWidth + 20) + 'px'; // 20 mean by, left + right = 20px. please check css

        document.body.removeChild(tempSpan);
    }

    inputs.forEach(function(input) {
        input.addEventListener('input', resizeInput);
        resizeInput.call(input);
    });

