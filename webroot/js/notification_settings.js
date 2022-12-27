$(document).ready(function()
{
    const saveNotificationButton = $('#saveNotificationsSettings');
    const notificationSpinner = document.getElementById('notificationSpinner');

    const notificationSelects = {
        // contact
        notificationCreateTicketContact: document.getElementById('notificationCreateTicketContact'),
        notificationChangeTicketStatusContact: document.getElementById('notificationChangeTicketStatusContact'),
        notificationReceivingCustomerResponseContact: document.getElementById('notificationReceivingCustomerResponseContact'),
        notificationChangeResponsibleContact: document.getElementById('notificationChangeResponsibleContact'),
        // company
        notificationCreateTicketCompany: document.getElementById('notificationCreateTicketCompany'),
        notificationChangeTicketStatusCompany: document.getElementById('notificationChangeTicketStatusCompany'),
        notificationReceivingCustomerResponseCompany: document.getElementById('notificationReceivingCustomerResponseCompany'),
        notificationChangeResponsibleCompany: document.getElementById('notificationChangeResponsibleCompany'),
    };

    saveNotificationButton.on('click', function()
    {
        $(notificationSpinner).removeClass('hidden');
        saveNotificationButton.attr('disabled', true);
        let isValid = true;
        const selectValues = {};

        for(let i in notificationSelects)
        {
            $(notificationSelects[i]).removeClass('is-invalid');

            if(notificationSelects[i].value == 0)
            {
                isValid = false;
                $(notificationSelects[i]).addClass('is-invalid');
            } else {
                selectValues[i] = notificationSelects[i].value;
            }
        }

        if(isValid)
        {
            BX24.refreshAuth(function(auth)
            {
                $.ajax(window.data.ajax,
                {
                    data:
                    {
                        saveNotificationSettings: true,
                        AUTH_ID: auth.access_token,
                        REFRESH_ID: auth.refresh_token,
                        AUTH_EXPIRES: auth.expires_in,
                        member_id: auth.member_id,
                        selectValues: selectValues
                    },
                    dataType: 'json',
                    method: 'POST',
                    success: function(data)
                    {
                        if(data.error)
                        {
                            displayNotification(window.data.i18n.errorSaveNotificationSettings, 'error');
                        } else {
                            displayNotification(window.data.i18n.successSaveNotificationSettings, 'success');
                        }
                    },
                    error: function()
                    {
                        displayNotification(window.data.i18n.errorSaveNotificationSettings, 'error');
                    },
                    beforeSend: function()
                    {
                        $('.notification-message-alert').remove();
                    }
                }).always(function()
                {
                    $(notificationSpinner).addClass('hidden');
                    saveNotificationButton.removeAttr("disabled");
                });
            });
        } else {
            $(notificationSpinner).addClass('hidden');
            saveNotificationButton.removeAttr("disabled");
            return false;
        }
    });
});

function displayNotification(message, type)
{
    let flashMessageWrapper = document.getElementById('flashMessageWrapper');
    let hideButton = $('<button>',
    {
        type: 'button',
        class: "close",
        'data-dismiss': 'alert',
        'aria-label': 'Close'
    });
    hideButton.html('<span aria-hidden="true">&times;</span>');

    let messageAlert = $('<div>',
    {
        class: "alert alert-dismissible fade show col-10 notification-message-alert",
        role: "alert"
    });

    if(type === 'error')
    {
        messageAlert.addClass('alert-danger');
    } else {
        messageAlert.addClass('alert-success');
    }

    messageAlert.text(message);
    hideButton.appendTo(messageAlert);
    messageAlert.appendTo($(flashMessageWrapper));
    flashMessageWrapper.scrollIntoView({behavior: "smooth", block: "end", inline: "nearest"});
}