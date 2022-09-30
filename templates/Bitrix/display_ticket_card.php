<div id="ticket" class="col">
    <div class="row" role="tabpanel" aria-labelledby="ticket-tab">
        <div class="col-12 border">
            <form 
                method="POST" 
                action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
            >
                <div class="input-group">
                    <p>{{ ticket.id > 0 ? i18n.Ticket + ticket.id : "" }}</p>
                </div>
                <div class="input-group pt-2">
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
                <div class="form-group pt-2">
                    {{ i18n.Source }}{{ i18n[ticket.source_type_id] }}
                </div>
                <div class="btn-group pt-2">
                    <button
                        type="button"
                        v-on:click="save"
                        class="btn btn-primary"
                    >
                        {{ i18n.Save }}
                    </button>
                    <button
                        type="button"
                        v-on:click="feedback"
                        class="btn btn-primary ml-1"
                    >
                        {{ i18n.Reply }}
                    </button>
                </div>
            </form>
        </div>
        <div class="col-12 pt-4">
            <div class="border pt-2"
                v-for="(message, index) in messages">
                <p class="">{{ message.from }} {{ message.created }}</p>
                <div class="" v-html="message.text"></div>
            </div>
        </div>
    </div> 
</div>

<script>
    window.data = {
        ajax: "<?=$ajax;?>",
        required: <?=json_encode($required)?>,
        answer: {
            from: "<?=$from;?>",
            message: "",
            attachment: [],
        },
        ticket: <?=json_encode($ticket)?>,
        memberId: "<?=$memberId?>",
        statuses: <?=json_encode($statuses);?>,
        messages: <?=json_encode($messages);?>,
        subject: '<?=$subject;?>',
        i18n: <?=json_encode([
            'Name' => 'Name',
            'Ticket' => __('Ticket #'),
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
                            'text': this.subject,
                            'color': '#07ff0e',
                        },
                        answer: true,
                        subject: this.subject,
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