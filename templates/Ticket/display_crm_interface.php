<div id="ticket">
    <form 
        method="POST" 
        action="<?= $ajax ?>"
    >
        <div class="row h-100" role="tabpanel" aria-labelledby="ticket-tab">
            <div class="col-3 border border-right-0">
                <div class="form-group p-2 mt-2">
                    <p class="form-input">
                        <span class="border rounded-circle p-2">{{ customer.abr }}</span>
                        {{ customer.title }}
                    </p>
                    <p>{{ customer.phone }}</p>
                    <p>{{ customer.email }}</p>
                </div>

                <div class="form-group p-2">
                    <label for="assigned_to">{{ i18n.Assigned }}</label>
                    <div id="assigned_to">
                        <span class="border rounded-circle p-2">{{ responsible.abr }}</span>
                        {{ responsible.title }}
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
                    <div class="input-group pl-2">
                        <h3 class="m-1">
                            <label for="title">{{ i18n.Name }}</label>
                            <input id="title" v-model="subject" class="form-control">
                        </h3>
                    </div>
                </div>

                <div class="container-fluid pt-4">
                    <label for="description">{{ i18n.Description }}</label>
                    <textarea id="description" v-model="description" class="form-control"></textarea>
                </div>
            </div>
        </div>

        <footer class="d-flex justify-content-end pt-4">
            <div class="form-button">
                <button type="button" @click="save" class="btn btn-primary">
                    {{ i18n.Save }}
                </button>
                <button type="button" @click="cancel" class="btn btn-secondary">
                    {{i18n.Cancel}}
                </button>
            </div>
        </footer>
    </form>
</div>

<script>
    window.data = {
        ajax: "<?=$ajax;?>",
        required: <?=json_encode($required)?>,
        customer: <?=json_encode($customer);?>,
        responsible: <?=json_encode($responsible);?>,
        statuses: <?=json_encode($statuses);?>,
        
        i18n: <?=json_encode([
            'Assigned' => __('Assigned to'),
            'Name' => __('Name'),
            'Description' => __('Description'),
            'Status' => __('Status'),
            'Save' => __('Save'),
            'Cancel' => __('Cancel'),
            'Wait' => __('Please wait'),
        ]);?>,
        awaiting: false,

        subject: "",
        description: "",
        statusId: <?=$statusId?>,
    };
</script>

<script>
    new Vue({
        'el': '#ticket',
        'data': window.data,
        'methods': {
            setStatus: function (event)
            {
                this.status_id = event.target.value;
                this.save();
            },
            save: function ()
            {
                this.awaiting = true;
                const parameters = Object.assign(
                    {
                        subject: this.subject,
                        description: this.description,
                        responsible: this.responsible.id,
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
            cancel: function()
            {
                BX24.closeApplication();
            }
        }
    });
</script>