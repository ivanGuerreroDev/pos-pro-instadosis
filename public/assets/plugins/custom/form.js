"use strict";

    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr("content");
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": CSRF_TOKEN
        }
    });
    let $savingLoader = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>',

    $ajaxform = $(".ajaxform");
    $ajaxform.initFormValidation(),
    $(document).on("submit", ".ajaxform", function (e) {
        e.preventDefault();
        let t = $(this).find(".submit-btn"),
            a = t.html();
        $ajaxform.valid() &&
        $.ajax({
            type: "POST",
            url: this.action,
            data: new FormData(this),
            dataType: "json",
            contentType: !1,
            cache: !1,
            processData: !1,
            beforeSend: function () {
                t.html($savingLoader).attr("disabled", !0);
            },
            success: function (e) {
                t.html(a).attr("disabled", false);
                Notify("success", null, e)
            },
            error: function (e) {
                t.html(a).attr("disabled", !1), Notify("error", e);
            },
        });
    });

    let $ajaxform_instant_reload = $(".ajaxform_instant_reload");
    $ajaxform_instant_reload.initFormValidation(),
    $(document).on("submit", ".ajaxform_instant_reload", function (e) {
        e.preventDefault();
        let t = $(this).find(".submit-btn"),
            a = t.html();
        $ajaxform_instant_reload.valid() &&
            $.ajax({
                type: "POST",
                url: this.action,
                data: new FormData(this),
                dataType: "json",
                contentType: !1,
                cache: !1,
                processData: !1,
                // showDuration: 30000000000000,
                // hideDuration: 30000000000000,
                // timeOut: 30000000000000,
                // extendedTimeOut: 30000000000000,
                positionClass: "toast-top-left",
                beforeSend: function () {
                    t.html($savingLoader).addClass("disabled").attr("disabled", !0);
                },
                success: function (e) {
                    t.html(a).removeClass("disabled").attr("disabled", !1), (window.sessionStorage.hasPreviousMessage = !0), (window.sessionStorage.previousMessage = e.message ?? null), e.redirect && (location.href = e.redirect);
                },
                error: function (e) {
                    t.html(a).removeClass("disabled").attr("disabled", !1), showInputErrors(e.responseJSON), Notify("error", e);
                },
            });
    });

function ajaxSuccess(e, t) {
    e.redirect ? (e.message && ((window.sessionStorage.hasPreviousMessage = !0), (window.sessionStorage.previousMessage = e.message ?? null)), (location.href = e.redirect)) : e.message && Notify("success", e);
}

//PREVIEW IMAGE
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function (e) {
            var inputId = $(input).attr('id');

            // Select the image element based on the input's ID
            var imageElement = $('img.product-img').filter(function () {
                return $(this).closest('label').attr('for') === inputId;
            });
            imageElement.attr('src', e.target.result);
            imageElement.hide().fadeIn(650);
        };

        reader.readAsDataURL(input.files[0]);
    }
}

// Status button Change
$(".change-text").on("change", function () {
    var $dynamicText = $(this).closest('.form-control').find('.dynamic-text');

    if (this.checked) {
        $dynamicText.text("Active");
    } else {
        $dynamicText.text("Deactive");
    }
});

// Status button Change
$(".cnge-text").on("change", function () {
    var $test = $(this).closest('.form-control').find('.is-live-text');

    if (this.checked) {
        $test.text("Yes");
    } else {
        $test.text("No");
    }
});

/** STATUS CHANGE */
$('.status').on('change', function () {
    var checkbox = $(this);
    var status = checkbox.prop('checked') ? 1 : 0;
    var url = checkbox.data('url');

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            status: status
        },
        success: function (response) {
            if (status === 1) {
                toastr.success(response.message + ' status published');
            } else {
                toastr.success(response.message + ' status unpublished');
            }
        },
        error: function (xhr, status, error) {
            console.log(error)
            toastr.error('Something Went Wrong');
        }
    });
});

$(document).ready(function () {
    /** SEARCH */
    $(".searchInput").on("input", function (e) {
        e.preventDefault();
        const searchText = $(this).val();
        const url = $(this).attr("action");
        $.ajax({
            url: url,
            type: "GET",
            data: {
                search: searchText
            },
            success: function (res) {
                $(".searchResults").html(res.data);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            },
        });
    });

    // Handle the "x" icon click event
    $(".clearSearchInput").on("click", function () {
        $(".searchInput").val(""); // Clear the search input
        $(".clearSearchInput").addClass('d-none');
        $(this).closest('.searchForm').submit();
    });

    // Show/hide "delete" button based on input value
    $(".searchInput").on("input", function () {
        if ($(this).val().trim() !== "") {
            $(".clearSearchInput").removeClass('d-none');
        } else {
            $(".clearSearchInput").addClass('d-none');
        }
    });


});

/** CHECKBOX FOR DELETE ALL */
$(document).ready(function () {
    // Select all checkboxes when the checkbox in the header is clicked
    $(".selectAllCheckbox").on("click", function () {
        $(".checkbox-item").prop("checked", this.checked);
        if (this.checked) {
            $('.delete-selected').addClass('text-danger');
        } else {
            $('.delete-selected').removeClass('text-danger');
        }
    });

    // Perform the delete action for selected elements when the delete icon is clicked
    $(".delete-selected").on("click", function (e) {
        var checkedCheckboxes = $(".checkbox-item:checked");
        if (checkedCheckboxes.length === 0) {
            toastr.error('No items selected. Please select at least one item to delete.');
        } else {
            $('#multi-delete-modal').modal('show');
        }
    });


    $('.multi-delete-btn').on('click', function () {
        var ids = $(".checkbox-item:checked").map(function () {
            return $(this).val();
        }).get();

        let submitButton = $(this);
        let originalButtonText = submitButton.html();
        let del_url = $('.checkbox-item').data('url');

        $.ajax({
            type: "POST",
            url: del_url,
            data: {
                ids
            },
            dataType: "json",
            beforeSend: function () {
                submitButton.html($savingLoader).attr('disabled', true);
            },
            success: function (res) {
                submitButton.html(originalButtonText).attr('disabled', false);
                window.sessionStorage.hasPreviousMessage = true;
                window.sessionStorage.previousMessage = res.message ?? null;
                res.redirect && (location.href = res.redirect);
            },
            error: function (xhr) {
                submitButton.html(originalButtonText).attr('disabled', false);
                Notify('error', xhr);
            }
        });
    });
});

/** system setting start */
// Initial label text
var initialLabelText = $("#mail-driver-type-select option:selected").val();

$("#mail-driver-type-select").on("change", function () {
    var selectedOptionValue = $(this).val();
    $("#mail-driver-label").text(selectedOptionValue);
});

$("#mail-driver-label").text(initialLabelText);

/** system setting end */


/**  filter all from start */
$('.filter-form').on('input', function (e) {
    e.preventDefault();

    var table = $(this).attr('table');
    $.ajax({
        type: "POST",
        url: $(this).attr('action'),
        data: new FormData(this),
        dataType: "json",
        contentType: false,
        cache: false,
        processData: false,
        success: function (res) {
            $(table).html(res.data);
        }
    });
});

/**  filter all from  end */
