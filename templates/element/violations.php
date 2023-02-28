<?= $this->Html->script('violations', ['block' => false]); ?>

<script type="text/javascript">
    window.violations = Object.assign(
        {
            required: <?=json_encode($required)?>,
            ajax: '<?= $get_violations_url ?>',
            picker:
            {
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
                    diapazone: false
                },
                {
                    title: 'date',
                    mode: "days",
                    format: "MM/DD/YYYY",
                    diapazone: false
                },
                {
                    title: 'between',
                    mode: "days",
                    format: "MM/DD/YYYY",
                    diapazone: true
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
                'escalated' => __('Escalated'),
                'SLA Violated Tickets' => __('SLA Violated Tickets'),
                'Violations by Agent' => __('Violations by Agent'),
                'Violations by Status' => __('Violations by Status'),
                'Achieved vs. Violated Count' => __('Achieved vs. Violated Count')
            ]);?>,
            awaiting: false,
            violationsData: {}
        }
    );
</script>

<div class="row">
    <?= $this->element('filter', [
        'rootId' => 'filter-violations',
        'ajax' => $get_violations_url,
        'filterId' => 'violatios_filter',
        'fromDatePickerId' => 'startViolationsDate',
        'toDatePickerId' =>   'finalViolationsDate',
        'onDateChange' => 'fetchData',
        'value' => ''
    ]);?>
</div>

<span id="violationReport">
    <!-- SLA Violated Tickets -->
    <div class="row ml-1 mt-3">
        Chart
    </div>

    <!-- Violations by Agent -->
    <div class="row ml-1 mt-3">
        Vue app
    </div>

    <div class="row ml-1 mt-3">
        <!-- Violations by Status -->
        <div class="col-6">
            Chart
        </div>

        <!-- Achieved vs. Violated Count -->
        <div class="col-6">
            Chart
        </div>
    </div>
</span>

<span id="violationReportEmptyResult" class="hidden">
    <div class="row ml-1 mt-3">
        <?= __('No data for the selected period') ?>
    </div>
</span>
