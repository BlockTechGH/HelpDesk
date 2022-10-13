<?= $this->Html->script('fit_window'); ?>
<div id="setting_container" class="row mt-3">
    <div class="col-2">
        <div class="nav flex-column nav-pills" id="myTab" role="tablist" aria-orientation="vertical">
            <button class="nav-link active" data-toggle="tab" type="button" role="tab"
                aria-selected="true"
                id="tickets-tab"
                data-target="#tickets"
                aria-controls="tickets"
            >
                <?=__('Tickets');?>
            </button>
            <button class="nav-link" data-toggle="tab" type="button" role="tab" 
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
        </div>
    </div>
    <div class="col-10">
        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="tickets" role="tabpanel" aria-labelledby="tickets-tab">
                <form method="POST" action="<?= $ajax; ?>">
                    <div class="input-group">
                        <input type="date" id="fromDate" name="from" value="" placeholder="m/d/Y">
                        <span class="ml-1"> - </span>
                        <input class="ml-1" type="date" id="toDate" name="to" value="" placeholder="m/d/Y">
                    </div>
                </form>
                <table id="ticketsGrid" 
                    class="table table-condensed table-hover table-striped"
                >
                    <thead>
                        <th data-column-id="name"><?=__('Name');?></th>
                        <th data-column-id="id" 
                            data-identifier="true"
                            data-sortable="true"
                        >
                            <?=__('ID');?>
                        </th>
                        <th data-column-id="responsible" data-formatter="person"><?=__('Responsible person');?></th>
                        <th data-column-id="status_id" data-sortable="true" data-formatter="status"><?=__('Status');?></th>
                        <th data-column-id="client" data-formatter="person"><?=__('Client');?></th>
                        <th data-column-id="created" data-order="desc" data-sortable="true"><?=__('Created');?></th>
                    </thead>
                </table>
                <div class="btn-group mt-4">
                    <button type="button" class="btn btn-secondary" id="refresh">
                        <?=__('Refresh table');?>
                    </button>
                </div>
            </div>
            <div class="tab-pane fade show" id="sources" role="tabpanel" aria-labelledby="sources-tab">
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

                    <input type="hidden" name="AUTH_ID" value="<?=$required['AUTH_ID']?>" />
                    <input type="hidden" name="AUTH_EXPIRES" value="<?=$required['AUTH_EXPIRES']?>" />
                    <input type="hidden" name="REFRESH_ID" value="<?=$required['REFRESH_ID']?>" />
                    <input type="hidden" name="member_id" value="<?=$required['member_id']?>" />

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
                            <th>{{ i18n.StartStatus }}</th>
                            <th>{{ i18n.FinalStatus }}</th>
                            <th>{{ i18n.Action }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(status, index) in statuses" :key="index">
                                <td>{{ status.name }}</td>
                                <td>{{ status.active > 0 ? i18n.Yes : i18n.No }}</td>
                                <td>{{ status.mark == 1 ? i18n.Yes : i18n.No }}</td>
                                <td>{{ status.mark == 2 ? i18n.Yes : i18n.No }}</td>
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
                        
                        <label for="started" class="ml-1">{{ i18n.StartStatus }}</label>
                        <input id="started" type="checkbox" :checked="currentStatus.mark==1" @change="markStatus(0, 1)" class="btn btn-primary">

                        <label for="final" class="ml-1">{{ i18n.FinalStatus }}</label>
                        <input type="checkbox" :checked="currentStatus.mark==2" @change="markStatus(0, 2)" class="btn btn-primary">

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
                aria-labelledby="categories-tab"
                v-show="false">
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

<script>
    window.data = {
        ajax: "<?= $this->Url->build([
            '_name' => 'crm_settings_interface', 
            '?' => ['DOMAIN' => $domain]
        ]); ?>",
        required: <?=json_encode($required)?>,
        currentStatus: {
            id: 0,
            name: '',
            active: 1,
            member_id: "<?=$required['member_id'];?>",
            mark: 0
        },
        currentCategory: {
            id: 0,
            name: '',
            active: 1,
            member_id: "<?=$required['member_id'];?>",
        },
        memberId: "<?=$required['member_id']?>",
        categories: <?=json_encode($categories);?>,
        statuses: <?=json_encode($statuses);?>,
        i18n: <?=json_encode([
            'Name' => 'Name',
            'Save' => __('Save'),
            'Active' => __('Active'),
            'Add' => __('Add'),
            'Edit' => __('Edit'),
            'Action' => __('Action'),
            'Yes' => __('Yes'),
            'No' => __('No'),
            'StartStatus' => __('Start'),
            'FinalStatus' => __('Final'),
        ]);?>,
    };
</script>

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
                if (this.currentCategory.id == 0) {
                    this.categories.push(stored);
                } else {
                    this.categories[this.currentCategory.index] = stored;
                }
                this.create();
            });
        },
        edit: function (index)
        {
            selected = this.categories[index];
            this.currentCategory.id = selected.id;
            this.currentCategory.name = selected.name;
            this.currentCategory.active = selected.active;
            this.currentCategory.index = index;
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
                this.create();
                const stored = await result.json();
                console.log(this.statuses, stored);
                for (const key in stored) {
                    if (Object.hasOwnProperty.call(stored, key)) {
                        const status = stored[key];
                        if (Object.hasOwnProperty(this.statuses, key))
                        {
                            this.statuses[key].id = status.id;
                            this.statuses[key].name = status.name;
                            this.statuses[key].active = status.active;
                            this.statuses[key].mark = status.mark;
                        } else {
                            this.statuses[key] = status;
                        }
                    }
                }
            });
        },
        markStatus: function (index, mark){
            if (status == 0) {
                this.currentStatus.mark = this.currentStatus.mark == mark ? 0 : mark;
            } else {
                this.statuses[index].mark = mark == this.statuses[index].mark ? 0 : mark;
            }
            console.debug("Status #", index, " toogle mark", mark);
        },
        edit: function (index)
        {
            selected = this.statuses[index];
            this.currentStatus.id = selected.id;
            this.currentStatus.name = selected.name;
            this.currentStatus.active = selected.active;
            this.currentStatus.index = index;
            this.currentStatus.mark = selected.mark;
        },
        create: function()
        {
            this.currentStatus = {
                id: 0,
                name: "",
                active: 1,
                member_id: this.memberId,
                mark: 0,
                index: 0
            };
        }
    }
});
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.1/jquery.bootgrid.css" integrity="sha512-CF1ovh2vRt2kC4JJ/Hl7VC7a+tu/NTO8iW+iltdfvIjvsb45t/6NkRNSrOe6HBxCTVplYLHW5GdlbtRLlCUp2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.0/jquery.bootgrid.min.js"></script>
<script>
$(document).ready(function () {
    BX24.init(function(){
        var grid = $('#ticketsGrid').bootgrid({
            rowCount: [10, 25, 50],
            formatters: {
                'person': (column, row) => {
                    console.log(row.id, column.id, row[column.id]);
                    return row[column.id]?.title;
                },
                'status': (column, row) => window.data.statuses[row.status_id].name,
            //    'ticketId': (column, row) => row.id,
            },
            ajax: true,
            url: '<?=$this->Url->build(['_name' => 'fetch_tickets', '?' => ['DOMAIN' => $domain]]);?>',
            'post': function (request)
            {
                var auth = BX24.getAuth();
                if (typeof(request) == "undefined")
                {
                    request = {};
                }
                request['memberId'] = auth.member_id;
                request['entityFilter'] = $('.entity-filter option:selected').val();
                request['from'] = $('#fromDate').val();
                request['to'] = $('#toDate').val();
                request['auth'] = window.data.required;
                return request;
            },
        });
        grid.on("load.rs.jquery.bootgrid", function (e)
        {
            if (typeof(Storage) !== "undefined") {
                sessionStorage.setItem("ticket_entity_filter", $('.entity-filter option:selected').val());
                if (
                    sessionStorage.getItem("ticket_list_navigated_back") != "yes"
                    && sessionStorage.getItem("ticket_change_page_after_search") != "yes"
                    && sessionStorage.getItem("ticket_change_page_after_row_count") != "yes"
                ) {
                    sessionStorage.setItem("ticket_current_page", grid.bootgrid("getCurrentPage"));
                    sessionStorage.setItem("ticket_search_phrase", grid.bootgrid("getSearchPhrase"));
                    sessionStorage.setItem("ticket_row_count", grid.bootgrid("getRowCount"));
                }
            }
        }).on("loaded.rs.jquery.bootgrid", function()
        {
            if (typeof(Storage) !== "undefined") {
                if (
                    sessionStorage.getItem("ticket_change_page_after_search") == "yes"
                    || sessionStorage.getItem("ticket_change_page_after_row_count") == "yes"
                ) {
                    if (sessionStorage.getItem("ticket_row_count") && !(sessionStorage.getItem("ticket_change_page_after_row_count") == "yes")) {
                        sessionStorage.setItem("tticket_change_page_after_row_count", "yes");
                        if (sessionStorage.getItem("ticket_row_count") != grid.bootgrid("getRowCount")) {
                            $("a[data-action='" + sessionStorage.getItem("ticket_row_count") + "'].dropdown-item-button").click();
                        } else {
                            grid.bootgrid("reload");
                        }

                    } else if (sessionStorage.getItem("ticket_current_page")) {
                        $("a[data-page='" + sessionStorage.getItem("ticket_current_page") + "']").click();
                        sessionStorage.setItem("ticket_change_page_after_row_count", "");
                    }
                    sessionStorage.setItem("ticket_change_page_after_search", "");
                } else if (sessionStorage.getItem("ticket_change_page_after_row_count") == "yes") {
                    if (sessionStorage.getItem("ticket_current_page")) {
                        $("a[data-page='" + sessionStorage.getItem("ticket_current_page") + "']").click();
                    }
                    sessionStorage.setItem("ticket_change_page_after_row_count", "");
                } else if (sessionStorage.getItem("ticket_list_navigated_back") == "yes") {
                    $('.entity-filter option[value="' + sessionStorage.getItem("ticket_entity_filter") + '"]').prop('selected', true);
                    if (sessionStorage.getItem("ticket_search_phrase")) {
                        grid.bootgrid("search", sessionStorage.getItem("ticket_search_phrase"));
                        sessionStorage.setItem("ticket_change_page_after_search", "yes");
                    } else if (sessionStorage.getItem("ticket_row_count")) {
                        if (sessionStorage.getItem("ticket_row_count") != grid.bootgrid("getRowCount")) {
                            $("a[data-action='" + sessionStorage.getItem("ticket_row_count") + "'].dropdown-item-button").click();
                        } else {
                            grid.bootgrid("reload");
                        }
                        sessionStorage.setItem("ticket_change_page_after_row_count", "yes");
                    } else if (sessionStorage.getItem("ticket_current_page")) {
                        $("a[data-page='" + sessionStorage.getItem("ticket_current_page") + "']").click();
                    }
                    sessionStorage.setItem("ticket_list_navigated_back", "");
                }
            }

            grid.find(".command-edit").on("click", function(e, columns, row)
            {

            }).end().find(".command-delete").on("click", function(e)
            {
            });
        });
        $("#refresh").on('click', async function(){
            console.log("Clear cache started");
            await fetch('<?= $this->Url->build(['_name' => 'clear_cache', '?' => ['DOMAIN' => $domain]]);?>', {
                'method': 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(parameters)
            }).then(() => console.log('Cache is cleared'));

            console.log("Table start refresh");
            grid.bootgrid("reload");
        });
    });
});
</script>
