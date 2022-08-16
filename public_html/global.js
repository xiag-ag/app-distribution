function showSpinner() { $('#loadingDiv').show(); }
function hideSpinner() { $('#loadingDiv').hide(); }

function sendAJAX(url, data, onSuccess = function() {}, method = 'POST')
{
    showSpinner();
    $.ajax({
        url: url,
        type: method,
        enctype: 'multipart/form-data',
        processData: false,
        contentType: false,
        cache: false,
        data: data,
        success: (response) => {
            hideSpinner();
            onSuccess(response);
        },
        error: hideSpinner
    });
}
