<?= $this->Html->script('fit_window'); ?>
<?php if(!isset($ticket)): ?>
<div id="setting_container" class="row">
    <div class="col-2">
        <div class="nav flex-column nav-pills" id="myTab" role="tablist" aria-orientation="vertical">
            <button class="nav-link active" data-toggle="tab" type="button" role="tab" 
                aria-selected="true"
                id="sources-tab" 
                data-target="#sources" 
                aria-controls="sources"
            >
                <?=__('Sources');?>
            </button>
            <button class="nav-link" data-toggle="tab" type="button" role="tab" 
                id="statuses-tab" 
                data-target="#statuses" 
                aria-controls="statuses"
            >
                <?=__('Statuses');?>
            </button>
            <button class="nav-link" data-toggle="tab" type="button" role="tab" 
                id="categories-tab" 
                data-target="#categories" 
                aria-controls="categories"
            >
                <?=__('Categories');?>
            </button>
            <?php if(isset($ticket)): ?>
            <button class="nav-link" data-toggle="tab" type="button" role="tab"
                id="ticket-tab"
                data-target="#ticket"
                aria-controls="ticket"
            >
                <?=__('Ticket card');?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-10">
        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="sources" role="tabpanel" aria-labelledby="sources-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <div class="form-group">
                        <label for="sources_on_email"><?=__('Create ticket by e-mail');?></label>
                        <input type="checkbox"
                            id="sources_on_email" 
                            name="sources_on_email"
                            <?=$options['sources_on_email'] ? 'checked' : '';?>/>
                    </div>
                    <div class="form-group">
                        <label for="sources_on_open_channel"><?=__('Create ticket by chat via Open Channel');?></label>
                        <input type="checkbox"
                            id="sources_on_open_channel" 
                            name="sources_on_open_channel"
                            <?=$options['sources_on_open_channel'] ? 'checked' : '';?>/>
                    </div>
                    <div class="form-group">
                        <label for="sources_on_phone_calls"><?=__('Create ticket by phone call');?></label>
                        <input type="checkbox"
                            id="sources_on_phone_calls" 
                            name="sources_on_phone_calls"
                            <?=$options['sources_on_phone_calls'] ? 'checked' : '';?>/>
                    </div>

                    <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
                    <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
                    <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
                    <input type="hidden" name="member_id" value="<?=$memberId?>" />

                    <button type="submit" name="saveSettings" class="btn btn-primary"><?= __('Save') ?></button>
                </form>
            </div>
            <div class="tab-pane fade show" 
                id="statuses" 
                role="tabpanel" 
                aria-labelledby="statuses-tab"
            >
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                <table class="table table-hover">
                        <thead><tr>
                            <th>{{ i18n.Name }}</th>
                            <th>{{ i18n.Active }}</th>
                            <th>{{ i18n.Action }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(status, index) in statuses" :key="index">
                                <td>{{ status.name }}</td>
                                <td>{{ status.active > 0 ? i18n.Yes : i18n.No }}</td>
                                <td>
                                    <button 
                                        type="button" 
                                        class="btn btn-primary btn-sm"
                                        v-on:click="edit(index)"
                                    >
                                        {{ i18n.Edit }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="form-group">
                        <label for="status_name">{{ i18n.Option }}</label>
                        <input class="mr-2" id="status_name" v-model="currentStatus.name">
                            
                        <label for="status_active">{{ i18n.Active }}</label>
                        <input type="checkbox" 
                            id="status_active"
                            v-bind:class="{btn: true, 'btn-primary': currentStatus.active}" 
                            v-model="currentStatus.active"/>

                        <button 
                            type="button" 
                            v-on:click="save" 
                            class="btn btn-primary ml-1">
                            {{ i18n.Save }}
                        </button>
                    </div>
                </form>
            </div>
            <div 
                class="tab-pane fade show" 
                id="categories" 
                role="tabpanel" 
                aria-labelledby="categories-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <table class="table table-hover">
                        <thead><tr>
                            <th>{{ i18n.Name }}</th>
                            <th>{{ i18n.Active }}</th>
                            <th>{{ i18n.Action }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(category, index) in categories" :key="index">
                                <td>{{ category.name }}</td>
                                <td>{{ category.active > 0 ? i18n.Yes : i18n.No }}</td>
                                <td>
                                    <button 
                                        type="button" 
                                        class="btn btn-primary btn-sm"
                                        v-on:click="edit(index)"
                                    >
                                        {{ i18n.Edit }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="form-group">
                        <label for="opt_name">{{ i18n.Option }}</label>
                        <input class="" id="opt_name" v-model="currentCategory.name">
                            
                        <label for="category_active">{{ i18n.Active }}</label>
                        <input type="checkbox" 
                            id="category_active"
                            v-bind:class="{btn: true, 'btn-primary': currentCategory.active}" 
                            v-model="currentCategory.active"
                            />
    
                            
                        <button type="button" v-on:click="save" class="btn btn-primary ml-1">
                            {{ i18n.Save }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    <div id="setting_container" class="col">
        <div class="row" id="ticket" role="tabpanel" aria-labelledby="ticket-tab">
            <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                <div class="form-group">
                    <p>{{ currentTicket.id > 0 ? i18n.Ticket + currentTicket.id : "" }}</p>
                </div>
                <div class="form-group">
                    <label for="ticket_status">{{ i18n.Status }}</label>
                    <select id="ticket_status" name="status" class="form-control" v-model="currentTicket.status_id">
                        <option 
                            v-for="(status, index) in statuses"
                            :selected="status.id == currentTicket.status_id"
                            :value="status.id"
                        >
                            {{ status.name }}
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ticket_category">{{ i18n.Category }}</label>
                    <select id="ticket_categoty" name="category" class="form-control" v-model="currentTicket.category_id">
                        <option 
                            v-for="(category, index) in categories"
                            :selected="category.id == currentTicket.category_id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                </div>
                <div class="btn-group">
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
        <div class="row" id="messages">
            <div class="col">
                <div class=""
                    v-for="(message, index) in messages">
                    <p class="">{{ message.from }} {{ message.created }}</p>
                    <textarea readonly>{{ message.text }}</textarea>
                </div>
            </div>
            <div class="form row">
                <form 
                    method="POST" 
                    action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
                    enctype="multipart/form-data"
                >

                    <div class="forn-group">{{ answer.from }}</div>
                    <div class="form-group">
                        <label for="message">{{ i18n.Reply }}</label>
                        <textarea id="message" name="message"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachment">{{ i18n.Attachment }}</label>
                        <input type="file" name="attachment" id="attachment" @change="upload($event)">
                    </div>
                    <div class="btn-group">
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
<?php endif; ?>

<script>
    window.data = {
        ajax: "<?= $this->Url->build([
            '_name' => 'crm_settings_interface', 
            '?' => ['DOMAIN' => $domain]
        ]); ?>",
        required: <?=json_encode([
            'AUTH_ID' => $authId,
            'AUTH_EXPIRES' => $authExpires,
            'REFRESH_ID' => $refreshId,
            'member_id' => $memberId,
            'PLACEMENT_OPTIONS' => json_encode($PLACEMENT_OPTIONS),
        ])?>,
        currentStatus: {
            id: 0,
            name: '',
            active: 1,
            member_id: "<?=$memberId;?>",
        },
        currentCategory: {
            id: 0,
            name: '',
            active: 1,
            member_id: "<?=$memberId;?>",
        },
        answer: {
            from: "<?="";?>",
            message: "",
            attachment: [],
        },
        currentTicket: <?=json_encode($ticket)?>,
        memberId: "<?=$memberId?>",
        categories: <?=json_encode($categories);?>,
        statuses: <?=json_encode($statuses);?>,
        messages: <?=json_encode([]);?>,
        i18n: <?=json_encode([
            'Name' => 'Name',
            'Ticket' => __('Ticket #'),
            'Status' => __('Status'),
            'Category' => __('Category'),
            'Save' => __('Save'),
            'Active' => __('Active'),
            'Add' => __('Add'),
            'Edit' => __('Edit'),
            'Response' => __('Response'),
            'Action' => __('Action'),
            'Yes' => __('Yes'),
            'No' => __('No'),
            'Reply' => __('Your answer'),
            'EnterReplicaText' => __('Enter your answer here'),
            'Send' => __('Send'),
            'Attachment' => __('Attache file'),
        ]);?>,
    };
</script>
<?php if(!isset($ticket)): ?>
<script>
const categories = new Vue({
    'el': '#categories',
    'data': window.data,
    'methods': {
        save: function ()
        {
            const parameters = Object.assign(
                {
                    category: this.currentCategory,
                }, 
                this.required
            );
            fetch(this.ajax, {
                method: "POST",
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(parameters)
            }).then(async result => {
                const stored = await result.json();
                this.categories.push(stored);
                this.create();
            });
        },
        edit: function (index)
        {
            this.currentCategory = this.categories[index];
        },
        create: function()
        {
            this.currentCategory = {
                id: 0,
                name: "",
                active: 1,
                member_id: this.memberId,
            };
        }
    }
});
</script>
<script>
const statuses = new Vue({
    'el': '#statuses',
    'data': window.data,
    'methods': {
        save: function ()
        {
            const parameters = Object.assign(
                {
                    ticket_status: this.currentStatus,
                }, 
                this.required
            );
            if (this.currentStatus.id > 0)
            {
               parameters.do = "edit"; 
            }
            fetch(this.ajax, {
                method: "POST",
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(parameters)
            }).then(async result => {
                const stored = await result.json();
                if (this.currentStatus.id < 1)
                {
                    this.statuses.push(stored);
                }
                this.create();
            });
        },
        edit: function (index)
        {
            this.currentStatus = this.statuses[index];
        },
        create: function()
        {
            this.currentStatus = {
                id: 0,
                name: "",
                active: 1,
                member_id: this.memberId,
            };
        }
    }
});
</script>
<?php else: ?>
<script>
    const ticket = new Vue({
        'el': '#ticket',
        'data': window.data,
        'methods': {
            save: function ()
            {
                const parameters = Object.assign(
                    {
                        ticket: this.currentTicket,
                    }, 
                    this.required
                );
                if (this.currentTicket.id > 0)
                {
                    parameters.do = "edit"; 
                }
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    const stored = await result.json();
                    this.currentTicket = stored;
                });
            },
        }
    });
    const messages = new Vue({
        'el': '#messages',
        'data': window.data,
        'methods': {
            send: function() {
                const formData = new FormData();
                formData.append('from', this.answer.from);
                formData.append('message', this.answer.message);
                formData.append('attachment', this.answer.attachment);
                formData.append('AUTH_ID', this.required.AUTH_ID);
                formData.append('AUTH_EXPIES', this.required.AUTH_EXPIRES);
                formData.append('REFRESH_ID', this.required.REFRESH_ID);
                formData.append('member_id', this.required.member_id);
                formData.append('PLACEMENT_OPTIONS', JSON.stringify(this.required.PLACEMENT_OPTIONS));
                formData.append('ticket', <?=json_encode($ticket)?>);

                const headers = { 'Content-Type': 'multipart/form-data' };
                fetch(this.ajax, {
                    method: "POST",
                    headers,
                    body: formData
                }).then(async result => {
                    const all = await result.json();
                    this.messages = all;
                });
            },
            upload: function() {
                this.attachment = this.$refs.attachment.files[0];
            }
        }
    });
</script>
<?php endif;?>
