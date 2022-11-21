<?= $this->Html->css('ticket_card'); ?>

<div id="ticket">
    <form 
        method="POST" 
        action="<?= $ajax ?>"
    >
        <div class="row h-100 content-block" role="tabpanel" aria-labelledby="ticket-tab">
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
                        <?php if($responsible['photo']): ?>
                            <img class="rounded-circle avatar-img" alt="<?= $responsible['title'] ?>" src="<?= $responsible['photo'] ?>" />
                        <?php else: ?>
                            <span class="border rounded-circle p-2">{{ responsible.abr }}</span>
                        <?php endif; ?>
                        {{ responsible.title }}
                    </div>
                </div>

                <div class="form-group p-2">
                    <label for="ticket_status">{{ i18n.Status }}</label>
                    <select id="ticket_status" name="status" class="form-control" v-on:change="setStatus">
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
                    <div class="col-9">
                        <div class="form-group mt-4">
                            <label for="title">{{ i18n.Name }}</label>
                            <input id="title" v-model="subject" class="form-control">
                            <small id="titleHelp" class="form-text text-muted"><?= __('Specify only the ticket name. The ticket number will be filled in automatically.') ?></small>
                        </div>
                        <div class="form-group">
                            <label for="description">{{ i18n.Description }}</label>
                            <textarea id="description" v-model="description" class="form-control" rows="6"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="d-flex justify-content-center pt-4">
            <div class="form-button">
                <button type="button" id="saveButton" @click="save" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-plus-lg" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/>
                    </svg>
                    {{ i18n.Add }}
                    <span role="status" aria-hidden="true" class="spinner-border spinner-border-sm ml-2" v-if="awaiting"></span>
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
            'Assigned' => __('Responsible'),
            'Name' => __('Ticket name'),
            'Description' => __('Description'),
            'Status' => __('Status'),
            'Add' => __('Add'),
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
        el: '#ticket',
        data: window.data,
        methods: {
            setStatus: function (event)
            {
                this.status_id = event.target.value;
            },
            save: function ()
            {
                this.awaiting = true;
                const parameters = Object.assign(
                    {
                        subject: this.subject,
                        description: this.description,
                        responsible: this.responsible.id,
                        status: this.statusId,
                    }, 
                    this.required
                );
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    const stored = await result.json();
                    this.ticket = stored.ticket;
                    this.awaiting = false;

                    let addButton = document.querySelector('#saveButton');
                    addButton.disabled = true;

                    setTimeout(function()
                    {
                        BX24.closeApplication();
                    }, 2000);
                }).catch(err => {
                    console.error(err);
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