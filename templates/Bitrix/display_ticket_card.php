<?= $this->Html->css('ticket_card', ['block' => true]); ?>

<div id="ticket">
    <form
        method="POST"
        action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
    >
        <div class="row h-100 content-block" role="tabpanel" aria-labelledby="ticket-tab">
            <div class="col-3 border border-right-0">
                <div class="form-group p-2 mt-2">
                    <div class="text-muted"><?= __('Customer') ?></div>
                    <p class="form-input customer-block">
                        <span class="border rounded-circle p-2 customer-block-abr">{{ ticketAttributes.customer.abr }}</span>
                        <span class="ml-2"><a href="#" v-on:click.prevent="openEntityCard">{{ ticketAttributes.customer.title }}</a></span>
                    </p>
                    <p class="customer-communications">{{ ticketAttributes.customer.phone }}</p>
                    <p class="customer-communications">{{ ticketAttributes.customer.email }}</p>
                    <div v-if="dealName">
                        <span class="text-muted"><?= __('Deal') ?></span>
                        <a href="#" class="change-responsible float-right" @click="displaySelectDealDialog">
                            <?= __('Change') ?>
                        </a>
                        <div>
                            <a href="#" @click.prevent="openDealCard" v-html="dealName"></a>
                        </div>
                    </div>
                </div>

                <div class="form-group p-2">
                    <label class="text-muted" for="assigned_to">{{ i18n.Assigned }}</label>
                    <div id="assigned_to">
                        <img v-if="ticketAttributes.responsible.photo" class="rounded-circle avatar-img" v-bind:alt="ticketAttributes.responsible.title" v-bind:src="ticketAttributes.responsible.photo" />
                        <span v-else class="border rounded-circle p-2">{{ ticketAttributes.responsible.abr }}</span>
                        {{ ticketAttributes.responsible.title }}
                        <a href="#" class="change-responsible float-right" @click="displaySelectUserDialog">{{ i18n.Change }}</a>
                    </div>
                </div>

                <div class="form-group p-2">
                    <label for="ticket_status" class="text-muted">{{ i18n.Status }}</label>
                    <div class="input-group">
                        <select id="ticket_status" name="status" class="form-control" v-on:change="setStatus">
                            <option
                                v-for="(status, index) in statuses"
                                :selected="status.id == ticket.status_id"
                                :value="status.id"
                            >
                                {{ status.name }}
                            </option>
                        </select>
                        <span class="input-group-addon" v-if="awaiting">
                            <div class="d-flex align-items-center h-100">
                                <span role="status" aria-hidden="true" class="spinner-border status-spiner text-primary ml-2"></span>
                            </div>
                        </span>
                    </div>
                </div>
                <div id="bitrix_users" class="form-group p-2">
                    <label class="text-muted" for="assigned_to">{{ i18n.Users }}</label>
                    <div class="bitrix-users-block">
                        <bitrix-users
                            v-for="(bitrixUser, index) in bitrixUsers"
                            v-bind:key="'bitrixUser' + index"
                            v-bind:index="index"
                            v-bind:user="bitrixUser"
                            v-on:delete-bitrix-user="deleteBitrixUser"
                        >
                        </bitrix-users>
                        <div v-on:click.prevent="addBitrixUsers" class="btn btn-link create-even-add-entity">{{ i18n.Add }}</div>
                    </div>
                </div>
            </div>
            <div class="col-9 border">
                <div class="row">
                    <div class="input-group ml-4">
                        <h3 class="m-1">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="16" height="16"
                                fill="currentColor"
                                class="bi bi-envelope"
                                viewBox="0 0 16 16"
                                v-if="ticket.source_type_id == 'CRM_EMAIL'"
                            >
                                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="16" height="16"
                                fill="currentColor"
                                class="bi bi-chat"
                                viewBox="0 0 16 16"
                                v-else-if="ticket.source_type_id == 'IMOPENLINES_SESSION'"
                            >
                                <path d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z"/>
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                width="16" height="16"
                                fill="currentColor"
                                class="bi bi-telephone"
                                viewBox="0 0 16 16"
                                v-else-if="ticket.source_type_id == 'CRM_SMS' || ticket.source_type_id == 'VOXIMPLANT_CALL'"
                            >
                                <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
                            </svg>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16" height="16" fill="currentColor"
                                class="bi bi-pencil-square"
                                viewBox="0 0 16 16"
                                v-else-if="ticket.source_type_id == ticketActivityType">
                                <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                            </svg>
                            {{ i18n.Ticket }}<br/>
                            <sup>{{ ticket.created }}</sup>
                        </h3>
                        <div class="btn-group ml-auto">
                            <button
                                type="button"
                                v-on:click="feedback"
                                class="btn
                                    <?=(($ticket['source_type_id'] == $ticketActivityType) || ($ticket['source_type_id'] == 'VOXIMPLANT_CALL'))
                                        && (!$ticketAttributes['customer']['email'])
                                        ? 'btn-secondary'
                                        : 'btn-primary';?>
                                    rounded-circle m-3"
                                <?=(($ticket['source_type_id'] == $ticketActivityType) || ($ticket['source_type_id'] == 'VOXIMPLANT_CALL'))
                                    && (!$ticketAttributes['customer']['email'])
                                    ? 'disabled'
                                    : ''?>
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply" viewBox="0 0 16 16">
                                    <path d="M6.598 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.74 8.74 0 0 0-1.921-.306 7.404 7.404 0 0 0-.798.008h-.013l-.005.001h-.001L7.3 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L2.614 8.254a.503.503 0 0 0-.042-.028.147.147 0 0 1 0-.252.499.499 0 0 0 .042-.028l3.984-2.933zM7.8 10.386c.068 0 .143.003.223.006.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96v-.667z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Start menu -->
                <nav class="nav" role="tablist">
                    <a class="nav-link active" href="#" id="conversation-tab" data-toggle="tab" data-target="#conversation" role="tab" aria-controls="conversation" aria-selected="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat-left-text" viewBox="0 0 16 16">
                            <path d="M14 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H4.414A2 2 0 0 0 3 11.586l-2 2V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12.793a.5.5 0 0 0 .854.353l2.853-2.853A1 1 0 0 1 4.414 12H14a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                            <path d="M3 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM3 6a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9A.5.5 0 0 1 3 6zm0 2.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                        <?= __('Conversation') ?>
                    </a>
                    <a class="nav-link" href="#" id="resolution-tab" data-toggle="tab" data-target="#resolution" role="tab" aria-controls="resolution" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                        </svg>
                        <?= __('Resolution') ?>
                    </a>
                </nav>
                <!-- End menu -->

                <!-- Start content -->
                <div class="tab-content" id="ticketcard">
                    <div class="tab-pane fade show active" id="conversation" role="tabpanel" aria-labelledby="conversation-tab">
                        <!-- Start conversation tab -->
                        <div class="container-fluid pt-4">
                            <div class="border rounded p-3">
                                <?php if(!!$source): ?>
                                    <p class="ticket-customer-date"><?="{$source['customer']['title']} {$source['date']}";?></p>
                                    <div class=""><?=$source['text'];?></div>
                                    <!-- display view history button for Open channels -->
                                <?php else: ?>
                                    <p class="">
                                        <?=__("Source is not found");?>
                                    </p>
                                    <div class="">
                                        <?=__('Sorry, but source activity is closed, not exists, not associated with ticket or it is a wrong ID of activity');?>
                                    </div>
                                <?php endif;?>
                            </div>
                        </div>
                        <hr>
                        <?php if($ticket['source_type_id'] === 'IMOPENLINES_SESSION'): ?>
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-primary" onclick="BX24.im.openHistory('<?= $dialogId?>')"><?= __('Open history') ?></button>
                        </div>
                        <?php endif; ?>
                        <div class="row mt-2 mb-2" v-bind:class="{'justify-content-end': activity.DIRECTION == 2}" v-for="(activity, index) in arHistoryActivities" v-if="activity.ID != ticketAttributes.id">
                            <div class="col-10 border rounded ml-3 mr-3">
                                <div class="">{{ activity.SUBJECT }} {{ activity.CREATED }}</div>
                                <div v-html="activity.DESCRIPTION"></div>
                                <div v-if="activity.FILES" class="attachments mb-2">
                                    <div v-for="file in activity.FILES"><a v-bind:href="file.url">{{file.fileName}}</a></div>
                                </div>
                            </div>
                        </div>
                        <!-- End conversation tab -->
                    </div>
                    <div class="tab-pane fade" id="resolution" role="tabpanel" aria-labelledby="resolution-tab">
                        <div class="container-fluid pt-4">
                            <?= $this->element('resolutions', []); ?>
                        </div>
                    </div>
                </div>
                <!-- End content -->

            </div>
        </div>

        <footer class="d-flex justify-content-end pt-4">
            <div class="form-button">
                <button
                    type="button"
                    @click="completeToggle"
                    class="btn btn-secondary"
                    :disabled="awaiting"
                >
                    {{ ticketAttributes.active ? i18n.Close : i18n.Reopen }}
                    <span role="status" aria-hidden="true" class="spinner-border spinner-border-sm ml-2" v-if="awaiting"></span>
                </button>
            </div>
        </footer>
    </form>
</div>

<script>
    window.data = {
        ajax: "<?=$ajax;?>",
        onChangeResponsibleUrl: "<?= $onChangeResponsibleUrl ?>",
        required: <?=json_encode($required)?>,
        ticketActivityType: "<?=$ticketActivityType;?>",
        ticket: <?=json_encode($ticket)?>,
        dialogId: "<?= $dialogId ?>",
        memberId: "<?=$memberId?>",
        bitrixUsers: <?=json_encode($bitrixUsers)?>,
        // Sub-issue #1 - 'customer' (ID in Bitrix, title (full name), e-mail address and phone number)
        // Sub-issue #2 - 'responsible' person (ID in Bitrix, full name (title), e-mail address and phone number)
        // Sub issues #5-7: Ticket auto-genered ID and subject of original message
        ticketAttributes: <?=json_encode($ticketAttributes);?>,
        // Sub issue #3 - statuses
        statuses: <?=json_encode($statuses);?>,
        arHistoryActivities: <?=json_encode($arHistoryActivities);?>,
        resolutions: <?= json_encode($resolutions) ?>,
        resolutionAwaiting: false,
        isResoltionInvalid: false,
        resolutionText: '',
        dealName: "<?= htmlspecialchars($dealName, ENT_QUOTES); ?>",
        dealId: "<?= $dealId ?>",
        i18n: <?=json_encode([
            'Assigned' => __('Responsible'),
            'Name' => __('Title'),
            'Ticket' => $ticketAttributes['subject'],
            'Status' => __('Status'),
            'Save' => __('Save'),
            'Add' => __('Add'),
            'Reply' => __('Answer'),
            'Source' => __('Source of ticket'),
            'IMOPENLINES_SESSION' => __('Open Channel (chat)'),
            'CRM_EMAIL' => __('E-mail'),
            'VOXIMPLANT_CALL' => __('Phone call'),
            'Close' => __('Close ticket'),
            'Reopen' => __('Reopen ticket'),
            'Wait' => __('Please wait'),
            'Change' => __('Change'),
            'Users' => __('Users'),
        ]);?>,
        awaiting: false,
        awaitingBitrixUser: false
    };
    console.log('Ticket attributes', window.data.ticketAttributes);
</script>

<script>
    Vue.component('bitrix-users', {
        template: `
                <div class="mt-1">
                    <input type="hidden" :name="nameIdField" v-model="user.ID">
                    <img v-if="user.PHOTO" class="rounded-circle avatar-img" v-bind:alt="user.NAME" v-bind:src="user.PHOTO" />
                    <span v-else class="border rounded-circle p-2 bitrix-user-block-abr">{{ user.ABR }}</span>
                    {{user.NAME}}
                    <a href="#" v-on:click.prevent="$emit('delete-bitrix-user', index)" class="change-responsible float-right pt-1"><?=__('x');?>
                </div>
        `,
        props: ['user', 'index'],
        computed: {
            nameIdField: function() {
                return 'BITRIX_USERS[' + this.index + ']';
            }
        },
    });

    new Vue({
        el: '#ticket',
        data: window.data,
        methods:
        {
            displaySelectDealDialog: function()
            {
                BX24.selectCRM(
                {
                    entityType: ['deal'],
                    multiple: false
                }, BX24.proxy(this.handleSelectDeal, this));
            },
            handleSelectDeal: function(select)
            {
                let newDeal = select.deal[0];
                let newDealId = newDeal.id.replace('D_', '');
                let oldDealId = this.dealId;
                console.log('oldDealId: ' + oldDealId);
                console.log('newDealId: ' + newDealId);
                if(newDealId === oldDealId)
                {
                    return;
                }

                const parameters = Object.assign(
                    {
                        activityId: this.ticketAttributes.id,
                        newDealId: newDealId,
                        oldDealId: oldDealId
                    },
                    this.required
                );
                if(this.ticket.id > 0)
                {
                    parameters.do = "assignNewDeal";
                }

                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    const newDealData = await result.json();
                    console.log('newDealData');
                    console.log(newDealData);
                    if(newDealData.success)
                    {
                        this.dealName = newDealData.data.title;
                        this.dealId = newDealData.data.id;
                    }
                });
            },
            openEntityCard: function()
            {
                let entityPath = '';

                if(this.ticketAttributes.ENTITY_TYPE_ID == 3 && this.ticketAttributes.customer.id)
                {
                    entityPath = '/crm/contact/details/' + this.ticketAttributes.customer.id + '/';
                }

                if(this.ticketAttributes.ENTITY_TYPE_ID == 4 && this.ticketAttributes.customer.id)
                {
                    entityPath = '/crm/company/details/' + this.ticketAttributes.customer.id + '/';
                }

                if(entityPath)
                {
                    BX24.openPath(entityPath);
                } else {
                    return false;
                }
            },
            openDealCard: function()
            {
                if(this.dealId)
                {
                    let dealPath = '/crm/deal/details/' + this.dealId + '/';
                    BX24.openPath(dealPath);
                } else {
                    return false;
                }
            },
            addResolutions: function()
            {
                this.resolutionAwaiting = true;

                if(this.resolutionText.length === 0)
                {
                    this.isResoltionInvalid = true;
                    this.resolutionAwaiting = false;
                    return;
                }

                // all ok - processing
                this.isResoltionInvalid = false;

                const parameters = Object.assign(
                    {
                        resolutionText: this.resolutionText,
                        ticketId: this.ticket.id,
                        do: 'addResolution'
                    },
                    this.required
                );
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    const resultData = await result.json();
                    this.resolutionAwaiting = false;

                    console.log(resultData);
                    if(resultData.success)
                    {
                        this.resolutions.unshift(resultData.record);
                        $('#collapseResolution').collapse('hide');
                        this.resolutionText = '';
                    } else {
                        this.displayResolutionError(resultData.message);
                    }
                });
            },
            displayResolutionError: function(message)
            {
                let flashMessageWrapper = document.getElementById('resolutionFlashMessageWrapper');
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
                    class: "alert alert-danger alert-dismissible fade show notification-message-alert",
                    role: "alert"
                });

                messageAlert.text(message);
                hideButton.appendTo(messageAlert);
                messageAlert.appendTo($(flashMessageWrapper));
            },
            setStatus: function (event)
            {
                this.ticket.status_id = event.target.value;
                this.save();
            },
            save: function ()
            {
                this.awaiting = true;
                this.ticket.bitrixUsers = this.bitrixUsers;
                const parameters = Object.assign(
                    {
                        ticket: this.ticket,
                    },
                    this.required
                );
                if (this.ticket.id > 0)
                {
                    parameters.do = "edit";
                }
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    const stored = await result.json();
                    this.ticket = stored.ticket;
                    this.ticketAttributes.active = stored.active;
                    this.awaiting = false;
                });
            },
            feedback: function() {
                const parameters = Object.assign(
                    {
                        'bx24_label': {
                            'bgColor':'blue', // aqua/green/orange/brown/pink/blue/grey/violet
                            'text': this.ticketAttributes.subject,
                            'color': '#07ff0e',
                        },
                        answer: true,
                        subject: this.ticketAttributes.subject,
                    },
                    JSON.parse(this.required.PLACEMENT_OPTIONS),
                    this.required
                );
                delete parameters.PLACEMENT_OPTIONS;

                if(this.ticket.source_type_id === 'IMOPENLINES_SESSION')
                {
                    BX24.im.openMessenger(this.dialogId);
                } else {
                    BX24.openApplication(
                        parameters,
                        function()
                        {
                            const parameters = Object.assign(
                                {
                                    fetch_messages: true,
                                    ticketId: this.data.ticket.id,
                                    sourceTypeId: this.data.ticket.source_type_id
                                },
                                this.data.required
                            );
                            const headers = { 'Content-Type': 'application/json' };
                            fetch('<?=$ajax;?>', {
                                method: "POST",
                                headers,
                                body: JSON.stringify(parameters)
                            }).then(async result => {
                                try {
                                    const all = await result.json();
                                    this.data.arHistoryActivities = all;
                                } catch (e) {
                                    console.log("Error occuried");
                                    console.log(e);
                                }
                            });
                        }
                    );
                }
            },
            upload: function() {
            //    this.answer.attachment = this.$refs.attachment.files[0];
            },
            completeToggle: function()
            {
                this.awaiting = true;
                const parameters = Object.assign(
                    {
                        activity_id: this.ticket.action_id,
                        set: !this.ticketAttributes.active
                    },
                    this.required
                );
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    this.awaiting = false;
                    try {
                        const response = await result.json();
                        this.ticket.status_id = response.status;
                        this.ticketAttributes.active = !this.ticketAttributes.active;
                    } catch (e) {
                        content = await result.text();
                        console.log("Error occuried: " + content);
                    }
                });
            },
            displaySelectUserDialog: function()
            {
                BX24.selectUser(this.handleSelectResponsible);
            },
            handleSelectResponsible: function(select)
            {
                if(select.id != this.ticketAttributes.responsible.id)
                {
                    BX24.callMethod('crm.activity.update',
                        {
                            id: this.ticketAttributes.id,
                            fields: {
                                'RESPONSIBLE_ID': select.id
                            }
                        },
                        this.changeresponsibleInView(select)
                    );
                } else {
                    return false;
                }
            },
            getAbbreviation: function(name)
            {
                let abr = '';

                let arPartial = name.split(" ", 2);

                for(let i in arPartial)
                {
                    abr = abr + arPartial[i].substr(0, 1);
                }

                return abr;
            },
            changeresponsibleInView: function(data)
            {
                this.ticketAttributes.responsible.id = data.id;
                this.ticketAttributes.responsible.title = data.name;
                this.ticketAttributes.responsible.photo = data.photo;
                this.ticketAttributes.responsible.abr = this.getAbbreviation(data.name);

                this.runOnChangeResponsible(data);
            },
            runOnChangeResponsible: function(data)
            {
                console.log('onChangeResponsible');
                console.log(data);
                console.log(this.onChangeResponsibleUrl);
                const parameters = Object.assign(
                    {
                        activityId: this.ticketAttributes.id,
                        newResponsible: data
                    },
                    this.required
                );

                fetch(this.onChangeResponsibleUrl,
                {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    try
                    {
                        const response = await result.json();
                        console.log(response);
                    } catch (e) {
                        content = await result.text();
                        console.error("Error occuried: " + content);
                    }
                });

            },
            deleteBitrixUser: function(index)
            {
                this.bitrixUsers.splice(index, 1);
                this.save('awaitingBitrixUser');
            },
            addBitrixUsers: function()
            {
                BX24.selectUsers(this.addBitrixUsersCallback);
            },
            addBitrixUsersCallback(result)
            {
                if (result)
                {
                    result.forEach(function(item, index)
                    {
                        let row = {
                            ID: result[index].id,
                            NAME: result[index].name,
                            PHOTO: result[index].photo,
                            ABR: this.getAbbreviation(result[index].name)
                        };
                        let needToPush = true;
                        if (this.bitrixUsers.length > 0)
                        {
                            this.bitrixUsers.forEach(function (bitrixUser)
                            {
                                if (bitrixUser.ID === result[index].id)
                                {
                                    needToPush = false;
                                    return false;
                                }
                            });
                            if (needToPush)
                            {
                                this.bitrixUsers.push(row);
                            }
                        }
                        else
                        {
                            this.bitrixUsers.push(row);
                        }
                    }, this);
                    this.save('awaitingBitrixUser');
                }
            },
        }
    });
</script>
