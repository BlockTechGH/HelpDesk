<?php $this->start('css');?>
    <?=$this->Html->css('home');?>
    <link rel="stylesheet" href="https://unpkg.com/@pluginjs/collapse/dist/collapse.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.css" rel="stylesheet">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.1/jquery.bootgrid.min.css" integrity="sha512-WBBdLBZSQGm9JN1Yut45Y9ijfFANbcOX3G+/A5+oO8W2ZWASp3NkPrG8mgr8QvGviyLoAz8y09l7SJ1dt0as7g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<?php $this->end();?>

<?php $this->start('script');?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min.js" integrity="sha512-42PE0rd+wZ2hNXftlM78BSehIGzezNeQuzihiBCvUEB3CVxHvsShF86wBWwQORNxNINlBPuq7rG4WWhNiTVHFg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://unpkg.com/@pluginjs/collapse/dist/collapse.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.0/jquery.bootgrid.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-bootgrid/1.3.1/jquery.bootgrid.fa.min.js" integrity="sha512-9n0UG6HszJFRxzkSCxUItSZeu48ecVvY95pRVu0GDhRspSavKvKcm04U96VYeNLPSb2lCDOZ5wXCDbowg1gHhg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" integrity="sha256-+8RZJua0aEWg+QVVKg4LEzEEm/8RFez5Tb4JBNiV5xA=" crossorigin="anonymous"></script>
<?php $this->end();?>

<?= $this->Html->script('fit_window'); ?>
<div id="setting_container" class="row mb-3">
    <div class="col-12">
    <div class="mb-5"><ul class="nav nav-pills" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-toggle="tab" type="button" role="tab"
                aria-selected="true"
                id="tickets-tab"
                data-target="#tickets"
                aria-controls="tickets"
            >
                <?=__('Tickets');?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-toggle="tab" type="button" role="tab"
                aria-selected="true"
                id="summary-tab"
                data-target="#summary"
                aria-controls="summary"
            >
                <?=__('Summary');?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-toggle="tab" type="button" role="tab" 
                id="sources-tab" 
                data-target="#sources" 
                aria-controls="sources"
                aria-selected="false"
            >
                <?=__('Sources');?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-toggle="tab" type="button" role="tab" 
                id="statuses-tab" 
                data-target="#statuses" 
                aria-controls="statuses"
                aria-selected="false"
            >
                <?=__('Statuses');?>
            </button>
        </li>
    </ul>
    </div>
    <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="tickets" role="tabpanel" aria-labelledby="tickets-tab">
                <?= $this->element('filter', [
                    'rootId' => 'filter-form',
                    'ajax' => $ajax,
                    'filterId' => 'period',
                    'fromDatePickerId' => 'fromDate',
                    'toDatePickerId' =>   'toDate',
                    'onDateChange' => null,
                ]);?>
                <table id="ticketsGrid" 
                    class="table table-condensed table-hover table-striped"
                >
                    <thead>
                        <th data-column-id="id" 
                            data-identifier="true"
                            data-sortable="true"
                        >
                            <?=__('ID');?>
                        </th>
                        <th data-column-id="name"><?=__('Name');?></th>
                        <th data-column-id="responsible" data-formatter="person"><?=__('Responsible person');?></th>
                        <th data-column-id="status_id" data-sortable="true" data-formatter="status"><?=__('Status');?></th>
                        <th data-column-id="client" data-formatter="person"><?=__('Client');?></th>
                        <th data-column-id="created" data-order="desc" data-sortable="true"><?=__('Created');?></th>
                    </thead>
                </table>
            </div>
            <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                <div class="row">
                    <?= $this->element('filter', [
                        'rootId' => 'filter-summary',
                        'ajax' => $ajax,
                        'filterId' => 'between',
                        'fromDatePickerId' => 'startDate',
                        'toDatePickerId' =>   'finalDate',
                        'onDateChange' => 'fetchData',
                    ]);?>
                </div>
                <div class="row">
                    <div class="col-4"></div>
                    <div class="col-4"><canvas id="summaryChart" class="ml-6"></canvas></div>
                </div> 
            </div>
            <div class="tab-pane fade" id="sources" role="tabpanel" aria-labelledby="sources-tab">
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
            <div class="tab-pane fade" 
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
            <div class="tab-pane fade" id="categories" role="tabpanel" aria-labelledby="categories-tab"
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
    window.tickets = {
        picker: {
            title: 'month',
            format: "MM/YYYY",
            mode: "months",
            diapazone: false,
        },
        modes: [
            {
                title: 'month',
                mode: "months",
                format: "MM/YYYY",
                diapazone: false,
            },
            {
                title: 'date',
                mode: "days",
                format: "MM/DD/YYYY",
                diapazone: false,
            },
            {
                title: 'between',
                mode: "days",
                format: "MM/DD/YYYY",
                diapazone: true,
            }
        ],
        i18n: <?= json_encode([
            'year' => __("Year"),
            'month' => __('Month'),
            'date' => __('Day'),
            'between' => __('Between'),
            'total' => __('Total'),
            'open' => __('Open'),
            'closed' => __('Closed'),
            'escalated' => __('Escalated')
        ]);?>,
    };
    window.summary = Object.assign(
        {
            required: window.data.required
        }, window.tickets
    );
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

<script>
$(document).ready(function () {
    BX24.init(function(){
        // Chart
        const chartData = {
            labels: ['open', 'assigned', 'process', 'closed', 'escalated'],
            datasets: [
                {
                    data: [1, 1, 1, 1, 1],
                    backgroundColor: [
                        "#FF6384",
                        "#63FF84",
                        "#6384FF",
                        "#84FF63",
                        "#8463FF",
                        "#F59420",
                    ]
                }
            ]
        };
        const chart = new Chart($("#summaryChart"),{
            'type': 'pie',
            'data': chartData,
            options: {
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 20
                            },
                            // Documentation: https://github.com/chartjs/Chart.js/blob/master/src/controllers/controller.doughnut.js#L42-L69
                            // Solution like as: https://stackoverflow.com/questions/39454586/pie-chart-legend-chart-js
                            generateLabels: (chart) => {
                                const { data } = chart;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const meta = chart.getDatasetMeta(0);
                                        const style = meta.controller.getStyle(i);
                                        const value = chart.config.data.datasets[0].data[i];

                                        return {
                                            text: `${label}: ${value}`,
                                            fillStyle: style.backgroundColor,
                                            strokeStyle: style.borderColor,
                                            lineWidth: style.borderWidth,
                                            hidden: !chart.getDataVisibility(i),
                                            index: i,
                                        };
                                    });
                                }
                                return [];
                            },
                        },
                    },
                    title: {
                        display: true,
                        text: '<?=__('Tickets per status summary');?>',
                        font: {
                            size: 20
                        }
                    },
                },
            }
        });
        // Solution from: https://stackoverflow.com/questions/33363373/how-to-display-pie-chart-data-values-of-each-slice-in-chart-js
        function drawSegmentValues() {
            const segments = chart.data;
            const meta = chart._cachedMeta;
            for(var i=0; i<segments.length; i++) {
                // Default properties for text (size is scaled)
                ctx.fillStyle="white";
                var textSize = canvas.width/10;
                ctx.font= textSize+"px Verdana";

                // Get needed variables
                var value = segments[i].value;
                var startAngle = segments[i].startAngle;
                var endAngle = segments[i].endAngle;
                var middleAngle = startAngle + ((endAngle - startAngle)/2);

                // Compute text location
                var posX = (radius/2) * Math.cos(middleAngle) + midX;
                var posY = (radius/2) * Math.sin(middleAngle) + midY;

                // Text offside to middle of text
                var w_offset = ctx.measureText(value).width/2;
                var h_offset = textSize/4;

                ctx.fillText(value, posX - w_offset, posY + h_offset);
            }
        };

        // Filters
        //   -- On Tickets tab
        const filter = new Vue({
            el: "#filter-form",
            data: window.tickets,
            methods: {
                selectFilterEntity(event) {
                    this.picker = this.modes[event.target.value];
                    ['#fromDate', '#toDate'].forEach(_id => {
                        let $picker = $(_id).data('DateTimePicker');
                        $picker.viewMode(this.picker.mode);
                        $picker.format(this.picker.format);
                        if (!this.picker.diapazone) {
                            $picker.maxDate(false);
                            $picker.minDate(false);
                        }
                    });
                },
            }
        });
        $("#fromDate").datetimepicker({
            format: 'MM/YYYY',
            viewMode: 'months',
        }).on('dp.change', function(e) {
            $('#toDate').data("DateTimePicker").minDate(e.date);
            $('#ticketsGrid').bootgrid('reload');
        });
        $("#toDate").datetimepicker({
            format: 'MM/YYYY',
            viewMode: 'months',
            useCurrent: false,
        }).on('dp.change', function(e) {
            $('#fromDate').data("DateTimePicker").maxDate(e.date);
            $('#ticketsGrid').bootgrid('reload');
        });
        //   -- On Summary tab
        const period = new Vue({
            el: "#filter-summary",
            data: window.summary,
            methods: {
                async fetchData() {
                    var auth = BX24.getAuth();
                    const parameters = {
                        memberId: auth.member_id,
                        period: this.modes[$('#between option:selected').val()].title,
                        from: $('#startDate').val(),
                        to: $('#finalDate').val(),
                        auth: this.required
                    };
                    const tickets = await fetch(
                        "<?=$this->Url->build(['_name' => 'fetch_tickets', '?' => ['DOMAIN' => $domain]]);?>", {
                            method: "POST",
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(parameters)
                        }
                    ).then(response => response.json());
                    console.log('Fetch response', tickets);
                    const dataset = {
                        data: [],
                        backgroundColor: chartData.datasets[0].backgroundColor,
                    };
                
                    chart.data.labels = [];
                    for (const label in tickets.summary) {
                        if (Object.hasOwnProperty.call(tickets.summary, label)) {
                            const value = tickets.summary[label];
                            chart.data.labels.push(label);
                            dataset.data.push(value);
                        }
                    }
                    chart.data.datasets = [dataset];
                    chart.options.plugins.title.text = '<?=__('Tickets per status summary. Total: ');?>' + tickets.total;
                    chart.update();
                    drawSegmentValues();
                },
                selectFilterEntity(event) {
                    this.picker = this.modes[event.target.value];
                    ['#startDate', '#finalDate'].forEach(_id => {
                        let $picker = $(_id).data('DateTimePicker');
                        $picker.viewMode(this.picker.mode);
                        $picker.format(this.picker.format);
                        if (!this.picker.diapazone) {
                            $picker.maxDate(false);
                            $picker.minDate(false);
                        }
                    });
                    this.fetchData();
                },
            }
        });
        period.fetchData();
        $("#startDate").datetimepicker({
            format: 'MM/YYYY',
            viewMode: 'months',
        }).on('dp.change', function(e) {
            $('#finalDate').data("DateTimePicker").minDate(e.date);
            period.fetchData();
        });
        $("#finalDate").datetimepicker({
            format: 'MM/YYYY',
            viewMode: 'months',
            useCurrent: false,
        }).on('dp.change', function(e) {
            $('#startDate').data("DateTimePicker").maxDate(e.date);
            period.fetchData();
        });

        // Grid
        var grid = $('#ticketsGrid').bootgrid({
            rowCount: [10, 25, 50],
            formatters: {
                'person': (column, row) => row[column.id]?.title,
                'status': (column, row) => window.data.statuses[row.status_id].name,
            },
            templates: {
                header: "<div id=\"{{ctx.id}}\" class=\"{{css.header}}\"><div class=\"row\"><div class=\"actionBar\"><p class=\"{{css.search}}\"></p><p class=\"{{css.actions}}\"></p></div></div></div>",
            },
            css: {
                paginationButton: "btn border",
                selected: "active text-primary"
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
                request['period'] = window.tickets.modes[$('#period option:selected').val()].title;
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
        }).on("loaded.rs.jquery.bootgrid", function(e)
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
        });
        $('.fa-search').addClass('btn');
    });
});
</script>
