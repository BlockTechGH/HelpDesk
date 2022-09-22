<?= $this->Html->script('fit_window'); ?>
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
            <div class="tab-pane fade show" id="statuses" role="tabpanel" aria-labelledby="statuses-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
                    <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
                    <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
                    <input type="hidden" name="member_id" value="<?=$memberId?>" />

                    <button type="submit" name="saveStatuses" class="btn btn-primary"><?= __('Save') ?></button>
                </form>
            </div>
            <div class="tab-pane fade show" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <table class="table table-hover">
                        <thead><tr>
                            <th>{{ i18n.Name }}</th>
                            <th>{{ i18n.Active }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(category, index) in categories" :key="index">
                                <td>{{ category.name }}</td>
                                <td class="btn-group">
                                    <input type="checkbox" 
                                        v-bind:name="category.name"
                                        v-bind:class="{btn: true, 'btn-primary': category.active, 'btn-second': !category.active}" 
                                        v-model="category.active"/>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="form-group">
                        <label for="opt_name">{{ i18n.Option }}</label>
                        <input class="" id="opt_name" v-model="optName">
                        <button type="button" v-on:click="append">
                            {{ i18n.Add }}
                        </button>
                    </div>

                    <button 
                        type="button" 
                        name="saveCategories"
                        v-on:click="update"
                        class="btn btn-primary">
                        {{ i18n.Save }}
                    </button>
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
        required: <?=json_encode([
            'AUTH_ID' => $authId,
            'AUTH_EXPIRES' => $authExpires,
            'REFRESH_ID' => $refreshId,
            'member_id' => $memberId,
        ])?>,
        optName: "",
        memberId: "<?=$memberId?>",
        categories: <?=json_encode($categories);?>,
        statuses: <?=json_encode($statuses);?>,
        i18n: <?=json_encode([
            'Name' => 'Name',
            'Status' => __('Name of new status'),
            'Category' => __('Name of new category'),
            'Save' => __('Save'),
            'Active' => __('Active'),
            'Add' => __('Add')
        ]);?>,
    };
</script>
<script>
const categories = new Vue({
    'el': '#categories',
    'data': window.data,
    'methods': {
        append: function ()
        {
            const parameters = Object.assign(
                {
                    optName: this.optName, 
                    do: "add",
                    entity: 'category'
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
                this.optName = "";
            });
        },
        update: function()
        {
            const parameters = Object.assign(
                {
                    categories: this.categories,
                    do: "update",
                    entity: 'category'
                }, 
                this.required
            );
            console.log("Send request to update a categories", this.categories);
            fetch(this.ajax, {
                method: "POST",
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(parameters)
            }).then(async result => {
                this.categories = await result.json();
                this.optName = "";
            });
        }
    }
});
</script>