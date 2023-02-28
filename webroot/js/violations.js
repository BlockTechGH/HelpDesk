$(document).ready(function()
{
    var drawViolationResult = function(violationsData)
    {
        let violationReport = document.getElementById('violationReport');
        let violationReportEmptyResult = document.getElementById('violationReportEmptyResult');

        if(Object.keys(violationsData).length === 0)
        {
            // display empty message
            $(violationReport).fadeOut('slow');
            $(violationReportEmptyResult).fadeIn('slow');
        } else {
            // display report
            $(violationReport).fadeIn('slow');
            $(violationReportEmptyResult).fadeOut('slow');
            // PUT logick here
        }
    };

    BX24.init(function()
    {
        const violationsFilter = new Vue({
            el: "#filter-violations",
            data: window.violations,
            methods: {
                async fetchData()
                {
                    let fromValue = $('#startViolationsDate').val();
                    let toValue = $('#startViolationsDate').val();

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
    });
});


