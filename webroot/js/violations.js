$(document).ready(function()
{
    BX24.init(function()
    {
        const violationsByAgent = new Vue({
            el: "#violationsByAgent",
            data: {
                records: {},
                users: {},
                headers: window.violations.i18n.violatedUserTableHeaders
            },
            template: `
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-uppercase" v-for="header in headers" scope="col">{{header}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(record, userId) in records">
                            <td>
                                <img v-if="users[userId].PERSONAL_PHOTO" v-bind:alt="users[userId].FULL_NAME" v-bind:src="users[userId].PERSONAL_PHOTO" class="rounded-circle avatar-img mb-0">
                                <span v-else class="border rounded-circle p-2">{{users[userId].ABBREVIATION}}</span>
                                <strong class="ml-2">{{users[userId].FULL_NAME}}</strong>
                            </td>
                            <td>{{record.violated + record.achieved}}</td>
                            <td>{{record.violated}}</td>
                            <td>{{record.achieved}}</td>
                        </tr>
                    </tbody>
                </table>
            `
        });

        const violationsFilter = new Vue({
            el: "#filter-violations",
            data: window.violations,
            methods: {
                async fetchData()
                {
                    let fromValue = $('#startViolationsDate').val();
                    let toValue = $('#finalViolationsDate').val();

                    if(!fromValue)
                    {
                        return;
                    }

                    violationsFilter.awaiting = true;

                    const violationsParameters = 
                    {
                        period: this.modes[$('#violatios_filter option:selected').val()].title,
                        from: fromValue,
                        to: toValue,
                        auth: this.required
                    };

                    var violationsData = await fetch(
                            this.ajax, {
                            method: "POST",
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(violationsParameters)
                        }
                    ).then(response => response.json());

                    violationsFilter.awaiting = false;

                    // change array on object if no result
                    if(Array.isArray(violationsData) && violationsData.length === 0)
                    {
                        violationsData = {};
                    }

                    drawViolationResult(violationsData);
                },
                selectFilterEntity(event)
                {
                    this.picker = this.modes[event.target.value];
                    ['#startViolationsDate', '#finalViolationsDate'].forEach(_id => {
                        let $picker = $(_id).data('DateTimePicker');
                        $picker.viewMode(this.picker.mode);
                        $picker.format(this.picker.format);
                        if (!this.picker.diapazone) {
                            $picker.maxDate(false);
                            $picker.minDate(false);
                        }
                    });
                    this.fetchData();
                }
            }
        });

        $("#startViolationsDate").datetimepicker({
            format: 'MM/YYYY',
            viewMode: 'months',
            useCurrent: false
        }).on('dp.change', function(e) {
            if(e.oldDate === null)
            {
                let dpData = $("#startViolationsDate").data("DateTimePicker");
                let currentFormat = dpData.format();
                dpData.date(e.date.format(currentFormat));
            }
            else
            {
                $('#finalViolationsDate').data("DateTimePicker").minDate(e.date);
                violationsFilter.fetchData();
            }
        });

        $("#finalViolationsDate").datetimepicker({
            format: 'MM/YYYY',
            viewMode: 'months',
            useCurrent: false
        }).on('dp.change', function(e) {
            if(e.oldDate === null)
            {
                let dpData = $("#finalViolationsDate").data("DateTimePicker");
                let currentFormat = dpData.format();
                dpData.date(e.date.format(currentFormat));
            }
            else
            {
                $('#startViolationsDate').data("DateTimePicker").maxDate(e.date);
                violationsFilter.fetchData();
            }
        });

        var drawViolationResult = function(violationsData)
        {
            let violationReport = document.getElementById('violationReport');
            let violationReportEmptyResult = document.getElementById('violationReportEmptyResult');

            if(violationsData.count === 0)
            {
                // display empty message
                $(violationReport).fadeOut('slow');
                $(violationReportEmptyResult).fadeIn('slow');
            } else {
                // display report
                $(violationReport).fadeIn('slow');
                $(violationReportEmptyResult).fadeOut('slow');

                // PUT logick here
                console.log(violationsData);
                violationsByAgent.records = violationsData.violations_by_agent;
                violationsByAgent.users = violationsData.users;

                // update chart for SLA Violated Tickets
                let newslaViolatedDataset = {
                    data: violationsData.sla_violated_tickets.values,
                    backgroundColor: new Array(violationsData.sla_violated_tickets.values.length).fill('#e95564')
                };
                let newslaViolatedLabels = violationsData.sla_violated_tickets.labels;

                slaViolatedChart.data.datasets = [newslaViolatedDataset];
                slaViolatedChart.data.labels = newslaViolatedLabels;
                slaViolatedChart.update();

                // update chart for Violations By Status
                let newData = [];
                for(statusId in statusIds)
                {
                    if(violationsData.violations_by_status.hasOwnProperty(statusId))
                    {
                        newData.push(violationsData.violations_by_status[statusId]);
                    } else {
                        newData.push(0);
                    }
                }
                let newDataset = {
                    data: newData,
                    backgroundColor: statusColors
                };

                violationsByStatusChart.data.datasets = [newDataset];
                violationsByStatusChart.update();

                // update chart for Achieved vs. Violated Count
                let newAchivedVsViolatedDataset = {
                    data: [
                        violationsData.achieved_vs_violated_tickets['achieved'],
                        violationsData.achieved_vs_violated_tickets['violated']
                    ],
                    backgroundColor: achivedVsViolatedColors
                };

                achivedVsViolatedChart.data.datasets = [newAchivedVsViolatedDataset];
                achivedVsViolatedChart.options.plugins.title.text = violationsData.achieved_vs_violated_tickets['percentage'] + '% ' + window.violations.i18n.violated;
                achivedVsViolatedChart.update();
            }
        };

        // chart for SLA Violated Tickets
        const slaViolatedData = {
            labels: [],
            datasets: [
                {
                    data: [],
                    backgroundColor: []
                }
            ]
        };

        const slaViolatedChart = new Chart(
            document.getElementById('slaViolatedTicket'),
            {
                type: 'bar',
                data: slaViolatedData,
                plugins: [ChartDataLabels],
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        datalabels: {
                            anchor: 'center',
                            align: 'center',
                            color: 'black',
                            clamp: false,
                            font: {
                                weight: 'bold',
                                size: 12
                            }
                        }
                    }
                }
            }
        );


        // chart for Violations By Status
        const statuses = window.violations.statuses;
        const statusLabels = [];
        const statusColors = [];
        const statusIds = {};
        var statusValues = [];

        for(let statusId in statuses)
        {
            statusLabels.push(statuses[statusId].name);
            statusColors.push(statuses[statusId].color);
            statusIds[statusId] = statusId;
            // fill default values
            statusValues.push(0);
        }

        const statusData = {
            labels: statusLabels,
            datasets: [
                {
                    data: statusValues,
                    backgroundColor: statusColors
                }
            ]
        };

        const violationsByStatusChart = new Chart(
            document.getElementById('violationsByStatus'),
            {
                type: 'bar',
                data: statusData,
                plugins: [ChartDataLabels],
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        datalabels: {
                            anchor: 'center',
                            align: 'top',
                            color: 'black',
                            font: {
                                weight: 'bold',
                                size: 16
                            }
                        }
                    }
                }
            }
        );

        // chart for Achieved vs. Violated Count
        const achivedVsViolatedColors = ['#619cee', '#e95564'];
        const achivedVsViolatedData = {
            labels: [window.violations.i18n.achieved, window.violations.i18n.violated],
            datasets: [
                {
                    data: [0, 0],
                    backgroundColor: achivedVsViolatedColors
                }
            ]
        };

        const achivedVsViolatedChart = new Chart(
            document.getElementById('achievedVsViolatedCount'),
            {
                type: 'doughnut',
                data: achivedVsViolatedData,
                plugins: [ChartDataLabels],
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        datalabels: {
                            anchor: 'center',
                            align: 'top',
                            color: 'white',
                            font: {
                                weight: 'bold',
                                size: 16
                            }
                        },
                        title: {
                            display: true,
                            text: '',
                            align: 'center',
                            position: 'top',
                            color: '#e95564'
                        }
                    }
                }
            }
        );
    });
});


