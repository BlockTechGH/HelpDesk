<?= $this->Html->css('ticket_card', ['block' => true]); ?>

<div id="ticket">
    <form
        method="POST"
        action="<?= $ajax ?>"
    >
        <div class="row h-100 content-block" role="tabpanel" aria-labelledby="ticket-tab">
            <div class="col-3 border border-right-0">
                <div class="form-group p-2 mt-2">
                    <p class="form-input customer-block">
                        <span class="border rounded-circle p-2 customer-block-abr">{{ customer.abr }}</span>
                        <span class="ml-2">{{ customer.title }}</span>
                    </p>
                    <p class="customer-communications">{{ customer.phone }}</p>
                    <p class="customer-communications">{{ customer.email }}</p>
                </div>

                <div class="form-group p-2">
                    <label for="assigned_to">{{ i18n.Assigned }}</label>
                    <div id="assigned_to">
                        <img v-if="responsible.photo" class="rounded-circle avatar-img" v-bind:alt="responsible.title" v-bind:src="responsible.photo" />
                        <span v-else class="border rounded-circle p-2">{{ responsible.abr }}</span>
                        {{ responsible.title }}
                        <a href="#" class="change-responsible float-right" @click="displaySelectUserDialog">{{ i18n.Change }}</a>
                    </div>
                </div>

                <div class="form-group p-2 mb-0">
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

                <div class="form-group p-2 mb-0">
                    <label for="ticket_category">{{ i18n.Ticket_Category }}</label>
                    <select id="ticket_category" name="ticket_category" class="form-control" v-on:change="setTicketCategory">
                        <option
                            v-for="(category, index) in categories"
                            :selected="category.id == ticket.category_id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                </div>

                <div class="form-group p-2 mb-0">
                    <label for="incident_category">{{ i18n.Incident_Category }}</label>
                    <select id="incident_category" name="incident_category" class="form-control" v-on:change="setIncidentCategory">
                        <option
                            v-for="(category, index) in incidentCategories"
                            :selected="category.id == ticket.incident_category_id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                </div>

                <div id="bitrix_users" class="form-group p-2">
                    <label for="bitrix_users">{{ i18n.Users }}</label>
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

<script type="text/javascript">
    window.data = {
        ajax: "<?=$ajax;?>",
        required: <?=json_encode($required)?>,
        customer: <?=json_encode($customer);?>,
        responsible: <?=json_encode($responsible);?>,
        statuses: <?=json_encode($statuses);?>,
        bitrixUsers: <?=json_encode($bitrixUsers);?>,
        categories: <?=json_encode($categories);?>,
        incidentCategories: <?=json_encode($incidentCategories);?>,

        i18n: <?=json_encode([
            'Assigned' => __('Responsible'),
            'Name' => __('Ticket name'),
            'Description' => __('Description'),
            'Status' => __('Status'),
            'Ticket_Category' => __('Ticket Category'),
            'Incident_Category' => __('Incident Category'),
            'Add' => __('Add'),
            'Cancel' => __('Cancel'),
            'Wait' => __('Please wait'),
            'Change' => __('Change'),
            'Users' => __('Users'),
            'Remove' => __('x')
        ]);?>,
        awaiting: false,

        subject: "",
        description: "",
        statusId: <?=$statusId?>,
    };
</script>

<script type="text/javascript">

    Vue.component('bitrix-users', {
        template: `
                <div class="mt-1">
                    <input type="hidden" :name="nameIdField" v-model="user.ID">
                    <img v-if="user.PHOTO" class="rounded-circle avatar-img" v-bind:alt="user.NAME" v-bind:src="user.PHOTO" />
                    <span v-else class="border rounded-circle p-2 bitrix-user-block-abr">{{ user.ABR }}</span>
                    {{user.NAME}}
                    <a href="#" v-on:click.prevent="$emit('delete-bitrix-user', index)" class="change-responsible float-right pt-1"><?=__('x');?></div>
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
                        bitrixUsers: this.bitrixUsers,
                        categoryId: this.categoryId,
                        incidentCategoryId: this.incidentCategoryId
                    },
                    this.required
                );
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    const stored = await result.json();

                    let addButton = document.querySelector('#saveButton');
                    addButton.disabled = true;

                    if(stored.error)
                    {
                        this.displayNotification(stored.status, 'error');
                        this.awaiting = false;
                    } else {
                        this.displayNotification(stored.status);
                        this.ticket = stored.ticket;
                        this.awaiting = false;

                        setTimeout(function()
                        {
                            BX24.closeApplication();
                        }, 3000);
                    }

                }).catch(err => {
                    console.error(err);
                    this.awaiting = false;
                });
            },
            cancel: function()
            {
                BX24.closeApplication();
            },
            displayNotification: function(message, type)
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
            },
            displaySelectUserDialog: function()
            {
                BX24.selectUser(this.handleSelectResponsible);
            },
            handleSelectResponsible: function(select)
            {
                if(select.id != this.responsible.id)
                {
                    this.responsible.id = select.id;
                    this.responsible.title = select.name;
                    this.responsible.photo = select.photo;
                    this.responsible.abr = this.getAbbreviation(select.name);
                } else {
                    return false;
                }
            },
            deleteBitrixUser: function(index)
            {
                this.bitrixUsers.splice(index, 1);
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
            setTicketCategory: function(event)
            {
                this.categoryId = event.target.value;
            },
            setIncidentCategory: function()
            {
                this.incidentCategoryId = event.target.value;
            }
        }
    });
</script>
