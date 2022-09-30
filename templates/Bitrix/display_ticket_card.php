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
                </div>
            </form>
        </div>
        <div class="col-12 pt-4">
            <div class="border pt-2"
                v-for="(message, index) in messages">
                <p class="">{{ message.from }} {{ message.created }}</p>
                <div class="">{{ message.text }}</div>
            </div>
            <div class="border pt-2">
                <form 
                    method="POST" 
                    action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
                    enctype="multipart/form-data"
                >
                    <div class="forn-group">{{ answer.from }}</div>
                    <div class="form-group pt-1">
                        <label for="message">{{ i18n.Reply }}</label>
                        <textarea id="message" name="message" v-model="answer.message"></textarea>
                    </div>
                    <div class="form-group pt-1">
                        <label for="attachment">{{ i18n.Attachment }}</label>
                        <input type="file" name="attachment" id="attachment" @change="upload($event)">
                    </div>
                    <div class="btn-group pt-1">
                        <button
                            type="button"
                            name="answer"
                            class="btn btn-primary"
                            @click="send"
                        >
                            {{ i18n.Send }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div> 
</div>

<script>
    window.data = {
        ajax: "<?= $this->Url->build([
            '_name' => 'crm_settings_interface', 
            '?' => ['DOMAIN' => $domain]
        ]); ?>",
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
        i18n: <?=json_encode([
            'Name' => 'Name',
            'Ticket' => __('Ticket #'),
            'Status' => __('Status'),
            'Save' => __('Save'),
            'Add' => __('Add'),
            'Response' => __('Response'),
            'Reply' => __('Your answer'),
            'EnterReplicaText' => __('Enter your answer here'),
            'Send' => __('Send'),
            'Attachment' => __('Attache file'),

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
            send: function() {
                const parameters = Object.assign(
                    {
                        answer: this.answer,
                        ticket: this.ticket,
                    },
                    this.required
                );

                const headers = { 'Content-Type': 'application/json' };
                fetch(this.ajax, {
                    method: "POST",
                    headers,
                    body: JSON.stringify(parameters),
                }).then(async result => {
                    const all = await result.json();
                    this.messages = all;
                });
            },
            upload: function() {
            //    this.answer.attachment = this.$refs.attachment.files[0];
            }
        }
    });
</script>