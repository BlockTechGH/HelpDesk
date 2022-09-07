<div id="setting_container" class="row">
    <div class="col">
        <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_interface', '?' => ['DOMAIN' => $domain]]) ?>" class="mt-4"
              enctype="multipart/form-data">
            <div class="header">
                <h3><?= $formTitle ?></h3>
            </div>
            <div class="form-group">
                <label for="phoneNumber"><?= __('Phone number') ?></label>
                <select id="phoneNumber" name="phoneNumber" class="form-control <?= (isset($errors['phone_number'])) ? 'border border-danger' : '' ?>">
                    <?php foreach ($phoneNumbers as $phoneNumber => $options): ?>
                    <option value="<?= $phoneNumber ?>" <?= isset($options['selected']) && $options['selected'] ? 'selected' : '' ?>>
                        <?= $options['title'] ?? $phoneNumber ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if (count($phoneNumbers) == 0): ?>
                        <option disabled><?= __('Phone numbers is has not now') ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group" id="templateEdit">
                <label for="template"><?= __('Template') ?></label>
                <div class="input-group">
                    <select id="template" name="template" class="form-control" v-model="current.name">
                        <option v-for="templ in templates" :selected="templ.selected" :key="templ.id" :value="templ.id">
                            {{ templ.title }} ({{ languages[`${templ.id_lang}`].code }})
                        </option>
                    </select>
                    <button type="button" class="input-group-btn btn btn-primary ml-4" data-toggle="modal" data-target="#templateModalTable">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list-columns-reverse" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M0 .5A.5.5 0 0 1 .5 0h2a.5.5 0 0 1 0 1h-2A.5.5 0 0 1 0 .5Zm4 0a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10A.5.5 0 0 1 4 .5Zm-4 2A.5.5 0 0 1 .5 2h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5Zm4 0a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5Zm-4 2A.5.5 0 0 1 .5 4h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5Zm4 0a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5Zm-4 2A.5.5 0 0 1 .5 6h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5Zm4 0a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 0 1h-8a.5.5 0 0 1-.5-.5Zm-4 2A.5.5 0 0 1 .5 8h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5Zm4 0a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 0 1h-8a.5.5 0 0 1-.5-.5Zm-4 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5Zm4 0a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10a.5.5 0 0 1-.5-.5Zm-4 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5Zm4 0a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5Zm-4 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5Zm4 0a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5Z"/>
                        </svg>
                    </button>
                </div>

                <div id="templateModalTable" class="modal fade" aria-hidden="true" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div id="modalContent" class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLabel">{{ i18n.Templates }}</h5>
                                <button type="button" class="close" data-dismiss="modal" :aria-label="i18n.Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-hover">
                                    <thead><tr>
                                        <th>{{ i18n.Name }}</th>
                                        <th>{{ i18n.Header }}</th>
                                        <th>{{ i18n.Placeholders }}</th>
                                        <th>{{ i18n.Language }}</th>
                                        <th>{{ i18n.Actions }}</th>
                                    </tr></thead>
                                    <tbody>
                                        <tr v-for="(templ, index) in templates" :key="index" v-bind:class="highlight(index)">
                                            <td>{{ templ.title }}</td>
                                            <td>{{ templ.header }}</td>
                                            <td>{{ templ.placeholders }}</td>
                                            <td>{{ languages[`${templ.id_lang}`].title }}</td>
                                            <td class="btn-group">
                                                <button type="button" class="btn btn-primary mr-2" v-on:click="uploadToForm(index)">{{ i18n.Edit }}</button>
                                                <button type="button" class="btn btn-secondary" v-on:click="removeTemplateByIndex(index)">{{ i18n.Delete }}</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <div class="col">
                                    <div class="form-group">
                                        <label for="title">{{ i18n.Name }}</label>
                                        <input id="title" class="form-control"
                                               v-model="current.title"
                                               v-bind:class="{border: current.error, 'border-danger': current.error}">
                                    </div>
                                    <div class="form-group">
                                        <label for="placeholders">{{ i18n.Placeholders }}</label>
                                        <input id="placeholders" class="form-control" v-model="current.placeholders">
                                    </div>
                                    <div class="form-group">
                                        <label for="idLang">{{ i18n.Language }}</label>
                                        <select id="idLang" class="form-control" name="idLang" v-model="current.id_lang">
                                            <option v-for="lang in languages" :value="lang.id" :selected="lang.id == current.id_lang">{{ lang.title }}</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="header">{{ i18n.Header }}</label>
                                        <input id="header" class="form-control" v-model="current.header">
                                    </div>
                                    <div class="form-group">
                                        <button type="button" class="btn btn-primary" v-on:click="saveTemplate">{{ i18n.Save }}</button>
                                        <button type="button" class="btn btn-secondary ml-4" data-dismiss="modal">{{ i18n.Close }}</button>
                                    </div>
                                    <p class="small">
                                        {{ i18n['Placeholders can be like'] }}<br/>
                                        {{ i18n['Placeholders full list'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="companyPhoneNumber"><?= __('Channel phone number') ?></label>
                <select id="companyPhoneNumber" name="companyPhoneNumber" class="form-control <?= (isset($errors['phone_number'])) ? 'border border-danger' : '' ?>">
                    <?php foreach ($companyPhoneNumbers as $phoneNumber => $options): ?>
                        <option value="<?= $phoneNumber ?>" <?= isset($options['selected']) && $options['selected'] ? 'selected' : '' ?>>
                            <?= "{$options['title']} ($phoneNumber)" ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (count($companyPhoneNumbers) == 0): ?>
                        <option disabled><?= __('Open lines is has not now') ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fileUpload"><?= __('You can upload a file') ?></label>
                <input id="fileUpload" name="fileUpload" type="file">
            </div>
            <div class="form-group">
                <label for="mediaUrl"><?= __('... or send link') ?></label>
                <input id="mediaUrl" name="mediaUrl" value="<?=$link?>">
            </div>

            <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
            <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
            <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
            <input type="hidden" name="member_id" value="<?=$memberId?>" />
            <input type="hidden" name="PLACEMENT_OPTIONS" value='<?=$placementOptions?>' />
            <input type="hidden" name="PLACEMENT" value="<?=$placement?>" />
            <input type="hidden" name="userID" value='<?=$userID?>' />

            <div class="form-group">
                <button type="submit" name="sendMessage" value="true" class="btn btn-primary"><?= __('Send') ?></button>
                <button class="btn btn-secondary ml-4" onclick="BX24.closeApplication();"><?= __('Cancel') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    window.ajaxReceiverUrl = '<?= $this->Url->build(['_name' => 'crm_interface', '?' => ['DOMAIN' => $domain]]) ?>';
    window.templates = <?= json_encode(array_values($templates)); ?>;
    window.languages = <?= json_encode($langs); ?>;
    window.i18n = <?= json_encode([
        'Name' => __('Name'),
        'Header' => __('Header (for media messages only)'),
        'Placeholders' => __('Placeholders'),
        'Placeholders can be like' => __('You can use placeholders as template parameters. Placeholders can be like {name}, {lastName}, {UfCrm_102938463} only, separated by comma.'),
        'Placeholders full list' => __('You can see full list of placeholders in Document > Add new template > Gear > Fields. Use placeholders of entity fields only, not linked entities.'),
        'Language' => __('Language'),
        'Actions' => __('Actions'),
        'Edit' => __('Edit'),
        'Save' => __('Save'),
        'New' => __('Flush'),
        'Delete' => __('Delete'),
        'Close' => __('Close'),
        'Templates' => __('Message templates'),
    ]) ?>;
    window.hiddens = <?= json_encode([
        'AUTH_ID' => $authId,
        'AUTH_EXPIRES' => $authExpires,
        'REFRESH_ID' => $refreshId,
        'member_id' => $memberId,
        'PLACEMENT' => $placement,
        'PLACEMENT_OPTIONS' => $placementOptions
    ]) ?>;

    const storage = {
        templates: window.templates,
        current: {
            id: 0,
            title: '',
            header: '',
            id_lang: 1,
            placeholders: '',
            error: false
        },
        languages: window.languages,
        i18n: window.i18n,
        hiddens: window.hiddens,
        ajax: window.ajaxReceiverUrl,
        showEdit: true
    };

    const modal = new Vue({
        el: "#modalContent",
        data: storage,
        methods: {
            highlight: function (index) {
               const highlightRow = this.templates[index].id === this.current.id;
               return {
                   border: highlightRow,
                   'border-primary': highlightRow
               };
            },
            removeTemplateByIndex: function (index) {
                const parameters = this.constructRequestParametersArray(this.templates[index], 'delete');
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                });
                this.templates.splice(index, 1);
            },
            saveTemplate: function () {
                if (this.current.id === 0 &&
                    this.templates.filter(t => t.title === this.current.title && t.id_lang == this.current.id_lang).length > 0)
                {
                    this.current.error = true;
                    return;
                }
                const parameters = this.constructRequestParametersArray(this.current, 'save');
                console.log("Send request", this.ajax, parameters);
                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    const stored = await result.json();
                    if (this.current.id !== stored.id) {
                        console.log(`Set id of template '${this.current.title}'`);
                    }

                    // Sync ID
                    if (this.current.id === 0) {
                        this.current.id = stored.id;
                        this.templates.push(this.current);
                    } else {
                        this.current.id = stored.id;
                        this.current.title = stored.name;
                        this.current.id_lang = stored.id_lang;
                        this.current.header = stored.header;
                        this.current.placeholders = stored.placeholders;
                    }
                    this.create();
                });
            },
            uploadToForm: function(index) {
                this.current = this.templates[index];
            },
            create: function () {
                this.current = {
                    id: 0,
                    title: '',
                    header: '',
                    id_lang: this.current.id_lang,
                    placeholders: '',
                    error: false
                };
            },
            constructRequestParametersArray: function (template, action) {
                return Object.assign({}, template, this.hiddens, { 'action': action });
            }
        }
    });
    console.log(modal.languages);

    const app = new Vue({
        el: "#template",
        data: storage
    });
</script>

