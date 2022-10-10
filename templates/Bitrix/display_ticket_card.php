<div id="ticket">
    <form 
        method="POST" 
        action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
    >
        <div class="row h-100" role="tabpanel" aria-labelledby="ticket-tab">
            <div class="col-3">
                <div class="form-group p-2">
                    <p class="form-input">
                        <span class="border rounded-circle p-2">{{ ticketAttributes.customer.abr }}</span>
                        {{ ticketAttributes.customer.title }}
                    </p>
                    <p class="form-input">{{ ticket.source_type_id == 'CRM_EMAIL' ? ticketAttributes.customer.email : ticketAttributes.customer.phone }}</p>
                </div>

                <div class="form-group p-2">
                    <label class="" for="assigned_to">{{ i18n.Assigned }}</label>
                    <div id="assigned_to">
                        <span class="border rounded-circle p-2">{{ ticketAttributes.responsible.abr }}</span>
                        {{ ticketAttributes.responsible.title }}
                    </div>
                </div>

                <div class="form-group p-2">
                    <label for="ticket_status">{{ i18n.Status }}</label>
                    <select id="ticket_status" name="status" class="form-control ml-2" v-on:change="setStatus">
                        <option 
                            v-for="(status, index) in statuses"
                            :selected="status.id == ticket.status_id"
                            :value="status.id"
                        >
                            {{ status.name }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="col-9 border">
                <div class="row">
                    <div class="input-group">
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
                            <span v-else>{{ i18n[ticket.source_type_id] ?? ticket.source_type_id }}</span>
                            {{ i18n.Ticket }}<br/>
                            <sup>{{ ticket.created }}</sup>
                        </h3>
                        <div class="btn-group ml-auto">
                            <button
                                type="button"
                                v-on:click="feedback"
                                class="btn btn-primary rounded-circle m-3"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply" viewBox="0 0 16 16">
                                    <path d="M6.598 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.74 8.74 0 0 0-1.921-.306 7.404 7.404 0 0 0-.798.008h-.013l-.005.001h-.001L7.3 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L2.614 8.254a.503.503 0 0 0-.042-.028.147.147 0 0 1 0-.252.499.499 0 0 0 .042-.028l3.984-2.933zM7.8 10.386c.068 0 .143.003.223.006.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96v-.667z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row pt-4">
                    <div class="border m-4">
                        <?php if(!!$source): ?>
                            <p class=""><?="{$source['customer']['title']} {$source['date']}";?></p>
                            <div class=""><?=$source['text'];?></div>
                        <?php else: ?>
                            <p class="">
                                <?=__("Activity #{0} not found", $ticket->source_id);?>
                            </p>
                            <div class="">
                                <?=__('Sorry, but source activity is closed, not exists, not associated with ticket or it is a wrong ID of activity');?>
                            </div>
                        <?php endif;?>
                    </div>
                </div>
            
                <div class="row pt-4">
                    <div class="border pt-2"
                        v-for="(message, index) in messages"
                    >
                        <p class="">{{ message.from }} {{ message.created }}</p>
                        <div class="" v-html="message.text"></div>
                    </div>
                </div>
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
        required: <?=json_encode($required)?>,
        ticket: <?=json_encode($ticket)?>,
        memberId: "<?=$memberId?>",
        // Sub-issue #1 - 'customer' (ID in Bitrix, title (full name), e-mail address and phone number)
        // Sub-issue #2 - 'responsible' person (ID in Bitrix, full name (title), e-mail address and phone number)
        // Sub issues #5-7: Ticket auto-genered ID and subject of original message
        ticketAttributes: <?=json_encode($ticketAttributes);?>,
        // Sub issue #3 - statuses
        statuses: <?=json_encode($statuses);?>,
        messages: <?=json_encode([]);?>,
        i18n: <?=json_encode([
            'Assigned' => __('Assigned to'),
            'Name' => __('Name'),
            'Ticket' => $ticketAttributes['subject'],
            'Status' => __('Status'),
            'Save' => __('Save'),
            'Add' => __('Add'),
            'Reply' => __('Answer'),
            'Source' => __('Source of ticket'),
            'IMOPENLINES_SESSION' => __('Open Channel (chat)'),
            'CRM_EMAIL' => __('E-mail'),
            'VOXIMPLANT_CALL' => __('Phone call'),
            'Close' => __('Close'),
            'Reopen' => __('Reopen'),
            'Wait' => __('Please wait'),
        ]);?>,
        awaiting: false,
    };
    console.log('Ticket attributes', window.data.ticketAttributes);
</script>

<script>
    new Vue({
        'el': '#ticket',
        'data': window.data,
        'methods': {
            setStatus: function (event)
            {
                this.ticket.status_id = event.target.value;
                this.save();
            },
            save: function ()
            {
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
                    this.ticket = stored;
                });
            },
            feedback: function() {
                const parameters = Object.assign(
                    {
                        'bx24_label': {
                            'bgColor':'pink', // aqua/green/orange/brown/pink/blue/grey/violet
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
                BX24.openApplication(
                    parameters, 
                    function () {
                        const parameters = Object.assign(
                            {
                                fetch_messages: true,
                                ticket_id: this.data.ticket.id
                            },
                            this.data.required
                        );
                        const headers = { 'Content-Type': 'application/json' };
                        fetch('<?=$ajax;?>', {
                            method: "POST",
                            headers,
                            body: JSON.stringify(parameters),
                        }).then(async result => {
                            const all = await result.json();
                            this.data.messages = all;
                        });
                    }
                );
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
                        await result.json();
                        this.ticketAttributes.active = !this.ticketAttributes.active;
                    } catch (e) {
                        content = await result.text();
                        console.log("Error occuried: " + content);
                    }
                });
            }
        }
    });
</script>