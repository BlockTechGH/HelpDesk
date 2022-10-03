<div id="ticket">
    <form 
        method="POST" 
        action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
    >
        <div class="row h-100" role="tabpanel" aria-labelledby="ticket-tab">
            <div class="col-3 border">
                <div class="form-group border p-2">
                    <p class="form-input">
                        <span class="">{{ ticketAttributes.customer.abr }}</span>
                        {{ ticketAttributes.customer.title }}
                    </p>
                    <p class="form-input">{{ ticket.source_type_id == 'CRM_EMAIL' ? ticketAttributes.customer.email : ticketAttributes.customer.phone }}</p>
                </div>

                <div class="form-group border p-2">
                    <label class="" for="assigned_to">{{ i18n.Assigned }}</label>
                    <div id="assigned_to">
                        <span class="">{{ ticketAttributes.responsible.abr }}</span>
                        {{ ticketAttributes.responsible.title }}
                    </div>
                </div>

                <div class="form-group border p-2">
                    <label for="ticket_status">{{ i18n.Status }}</label>
                    <select id="ticket_status" name="status" class="form-control ml-2" v-model="ticket.status_id">
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
                        <h1 class="m-1">
                            <span :title="i18n.Source">{{ i18n[ticket.source_type_id] }}</span>
                            {{ i18n.Ticket }}<br/>
                            <sub>{{ ticket.created }}</sub>
                        </h1>
                        <div class="btn-group ml-auto">
                            <button
                                type="button"
                                v-on:click="feedback"
                                class="btn btn-primary m-3"
                            >
                                {{ i18n.Reply }}
                            </button>
                        </div>
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
            'Source' => __('Source of ticket: '),
            'IMOPENLINES_SESSION' => __('Open Channel (chat)'),
            'CRM_EMAIL' => __('E-mail'),
            'VOXIMPLANT_CALL' => __('Phone call'),
        ]);?>,
    };
    console.log('Ticket attributes', window.data.ticketAttributes);
</script>

<script>
    new Vue({
        'el': '#ticket',
        'data': window.data,
        'methods': {
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
                console.log("feedback - arguments", parameters);
                BX24.openApplication(
                    parameters, 
                    function () {
                        console.log(this.data);
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
            }
        }
    });
</script>