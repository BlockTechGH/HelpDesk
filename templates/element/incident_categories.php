<form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
    <table class="table table-hover">
        <thead><tr>
            <th>{{ i18n.Name }}</th>
            <th>{{ i18n.Active }}</th>
            <th>{{ i18n.Action }}</th>
        </tr></thead>
        <tbody>
            <tr v-for="(category, index) in incidentCategories" :key="index">
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
        <input class="" id="opt_name" v-model="currentIncidentCategory.name">

        <label for="category_active">{{ i18n.Active }}</label>
        <input type="checkbox" 
            id="category_active"
            v-bind:class="{btn: true, 'btn-primary': currentIncidentCategory.active}" 
            v-model="currentIncidentCategory.active"
            />


        <button type="button" v-on:click="save" class="btn btn-primary ml-1">
            {{ i18n.Save }}
        </button>
    </div>
</form>

<script>
const incidentCategoriesApp = new Vue({
    el: '#incident_categories',
    data: window.data,
    methods: {
        save: function ()
        {
            const parameters = Object.assign(
                {
                    incidentCategory: this.currentIncidentCategory
                }, 
                this.required
            );
            fetch(this.ajax, {
                method: "POST",
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(parameters)
            }).then(async result => {
                const stored = await result.json();
                if (this.currentIncidentCategory.id == 0) {
                    this.incidentCategories[stored.id] = stored;
                } else {
                    this.incidentCategories[this.currentIncidentCategory.index] = stored;
                }
                this.create();
            });
        },
        edit: function (index)
        {
            selected = this.incidentCategories[index];
            this.currentIncidentCategory.id = selected.id;
            this.currentIncidentCategory.name = selected.name;
            this.currentIncidentCategory.active = selected.active;
            this.currentIncidentCategory.index = index;
        },
        create: function()
        {
            this.currentIncidentCategory = {
                id: 0,
                name: "",
                active: 1,
                member_id: this.memberId
            };
        }
    }
});
</script>

