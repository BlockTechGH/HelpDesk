Vue.component('department-config',
{
    template: `
        <div class="jumbotron p2" v-bind:id="'block' + config.departmentId">
            <h3 class="display-6">{{config.departmentName}}</h3>
            <div class="form-group">
                <label for="initialRTKPI">{{lang.initRespTimeKPI}}</label>
                <input type="number" class="form-control" id="initialRTKPI" v-model="config.initialRTKPI">
            </div>
            <div class="form-group">
                <label for="initialNotificationPerson">{{lang.sendNotificationTo}}</label>
                <div class="user-outer-container">
                    <div class="user-container">
                        <div class="user-items">
                            <!-- users blocks -->
                            <div class="user-item" v-for="(user, id) in config.initialNotificationUsers">
                                <div class="user-item-title">
                                    {{ user.name }}
                                </div>
                                <div class="user-item-remove" v-on:click.prevent="$emit('delete-user-from-config', config.departmentId, id, 'initial')">
                                </div>
                            </div>
                            <!-- Add button -->
                            <span class="user-add-button">
                                <span class="user-add-button-caption" v-on:click.prevent="displayUserSelectDialog('initial')">
                                    {{lang.add}}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="devide-kpi-block">
            <div class="form-group">
                <label for="subsequentRTKPI">{{lang.subRespTimeKPI}}</label>
                <input type="number" class="form-control" id="subsequentRTKPI" v-model="config.subsequentRTKPI">
            </div>
            <div class="form-group">
                <label for="subsequentNotificationPerson">{{lang.sendNotificationTo}}</label>
                <div class="user-outer-container">
                    <div class="user-container">
                        <div class="user-items">
                            <!-- users blocks -->
                            <div class="user-item" v-for="(user, id) in config.subsequentNotificationUsers">
                                <div class="user-item-title">
                                    {{ user.name }}
                                </div>
                                <div class="user-item-remove" v-on:click.prevent="$emit('delete-user-from-config', config.departmentId, id, 'subsequent')">
                                </div>
                            </div>
                            <!-- Add button -->
                            <span class="user-add-button">
                                <span class="user-add-button-caption" v-on:click.prevent="displayUserSelectDialog('subsequent')">
                                    {{lang.add}}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="devide-kpi-block">
            <div class="form-group">
                <label for="contactTemplate">{{lang.contactTemplate}}</label>
                <select id="contactTemplate" class="custom-select mr-sm-2" v-model="config.contactTemplate">
                    <option
                        v-for="(template, index) in contactTemplates"
                        :value="index"
                    >
                        {{ template }}
                    </option>
                </select>
            </div>
            <div class="form-group">
                <label for="companyTemplate">{{lang.companyTemplate}}</label>
                <select id="companyTemplate" class="custom-select mr-sm-2" v-model="config.companyTemplate">
                    <option
                        v-for="(template, index) in companyTemplates"
                        :value="index"
                    >
                        {{ template }}
                    </option>
                </select>
            </div>
            <div>
                <button type="button" name="deleteBlock" class="btn btn-danger float-right" v-on:click.prevent="$emit('delete-department-block', config.departmentId)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                    </svg>
                    {{lang.delete}}
                </button>
            </div>
        </div>
    `,
    props: ['config', 'lang', 'contactTemplates', 'companyTemplates'],
    methods:
    {
        displayUserSelectDialog: function(level)
        {
            if(level === 'initial')
            {
                BX24.selectUsers(this.addInitialUserCallBack);
            }
            if(level === 'subsequent')
            {
                BX24.selectUsers(this.addSubsequentUserCallBack);
            }
        },
        addInitialUserCallBack: function(users)
        {
            this.$emit('add-users-to-config', this.config.departmentId, users, 'initial');
        },
        addSubsequentUserCallBack: function(users)
        {
            this.$emit('add-users-to-config', this.config.departmentId, users, 'subsequent');
        }
    }
});


BX24.ready(function()
{
    var slaApp = new Vue(
    {
        el: '#slaApplication',
        data: window.slaData,
        computed:
        {
            isAddButtonDisabled()
            {
                if(parseInt(this.selectedDepartment) > 0 && (typeof this.departmentsConfigs[this.selectedDepartment] === "undefined"))
                {
                    return false;
                }

                return true;
            },
            isSaveButtonDisabled()
            {
                if(Object.keys(this.departmentsConfigs).length > 0)
                {
                    return false;
                }

                return true;
            }
        },
        methods:
        {
            addDepartment: function(event)
            {
                let depConfig = {
                    departmentId: this.selectedDepartment,
                    departmentName: this.arDepartments[this.selectedDepartment],
                    initialRTKPI: 10,
                    initialNotificationUsers: {},
                    subsequentRTKPI: 60,
                    subsequentNotificationUsers: {},
                    contactTemplate: 0,
                    companyTemplate: 0
                };

                this.$set(this.departmentsConfigs, this.selectedDepartment, depConfig);

                this.$nextTick(function()
                {
                    let block = document.getElementById('block' + this.selectedDepartment);
                    if(block) block.scrollIntoView();
                });
            },
            deleteDepartmentBlock: function(departmentId)
            {
                this.$delete(this.departmentsConfigs, departmentId);
            },
            addUsersToConfig: function(departmentId, users, level)
            {
                users.forEach(function(item, index)
                {
                    if(level === 'initial')
                    {
                        this.$set(this.departmentsConfigs[departmentId].initialNotificationUsers, item.id, {name: item.name});
                    }
                    if(level === 'subsequent')
                    {
                        this.$set(this.departmentsConfigs[departmentId].subsequentNotificationUsers, item.id, {name: item.name});
                    }
                }, this);
            },
            deleteUserFromConfig: function(departmentId, userId, level)
            {
                if(level === 'initial')
                {
                    this.$delete(this.departmentsConfigs[departmentId].initialNotificationUsers, userId);
                }
                if(level === 'subsequent')
                {
                    this.$delete(this.departmentsConfigs[departmentId].subsequentNotificationUsers, userId);
                }
            },
            saveSlaSettings: function()
            {
                this.awaiting = true;

                const parameters = Object.assign(
                    {
                        saveSLASettings: true,
                        settings: this.departmentsConfigs
                    },
                    this.required
                );

                fetch(this.ajax, {
                    method: "POST",
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(parameters)
                }).then(async result => {
                    this.awaiting = false;
                    const arResult = await result.json();

                    if(!arResult.error)
                    {
                        this.displayNotification(this.langPhrases.saveSuccess, 'success');
                    } else {
                        this.displayNotification(this.langPhrases.saveError, 'error');
                    }
                });
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
                flashMessageWrapper.scrollIntoView({behavior: "smooth", block: "end", inline: "nearest"});
            }
        }
    });
});
