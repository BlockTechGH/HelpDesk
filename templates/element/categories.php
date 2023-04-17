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

<script>
const categories = new Vue({
    el: '#categories',
    data: window.data,
    methods: {
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
                    this.categories[stored.id] = stored;
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

