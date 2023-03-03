<?= $this->Html->script('violations', ['block' => false]); ?>

<script type="text/javascript">
    window.violations = Object.assign(
        {
            required: <?=json_encode($required)?>,
            ajax: '<?= $get_violations_url ?>',
            statuses: <?=json_encode($statuses)?>,
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
                'achieved' => __('Achieved'),
                'violated' => __('Violated'),
                'SLA Violated Tickets' => __('SLA Violated Tickets'),
                'Violations by Agent' => __('Violations by Agent'),
                'Violations by Status' => __('Violations by Status'),
                'Achieved vs. Violated Count' => __('Achieved vs. Violated Count'),
                'violatedUserTableHeaders' => [
                    __('Agent'), __('Applied'), __('Violated'), __('Achieved')
                ]
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
<span class="glyphicon glyphicon-time" aria-hidden="true"></span>
<span id="violationReport" class="hidden">
    <!-- SLA Violated Tickets -->
    <div class="row ml-1 mt-3">
        Chart
    </div>

    <!-- Violations by Agent -->
    <div class="row ml-1 mt-3">
        <h3><?= __('Violations by Agent') ?></h3>
        <div id="violationsByAgent"></div>
    </div>

    <div class="row ml-1 mt-3">
        <!-- Violations by Status -->
        <div class="col-8 text-center">
            <h3><?= __('Violations by Status') ?></h3>
            <canvas id="violationsByStatus"></canvas>
        </div>

        <!-- Achieved vs. Violated Count -->
        <div class="col-4 text-center">
            <h3><?= __('Achieved vs. Violated Count') ?></h3>
            <canvas id="achievedVsViolatedCount"></canvas>
        </div>
    </div>
</span>

<span id="violationReportEmptyResult">
    <div class="row ml-1 mt-3">
        <strong><?= __('No data for the selected period') ?></strong>
    </div>
</span>
